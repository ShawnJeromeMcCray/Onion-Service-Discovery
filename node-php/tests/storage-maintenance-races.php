<?php
declare(strict_types=1);
// Storage behaviour around bounded retention, on both backends where it applies: a backup or archive export
// that maintenance pruning races is retried, never reported as STORAGE_CORRUPT; at the byte budget a
// removal-only (pruning) transaction still commits and storage usage reports the store's bytes; storage:check
// reclaims object files a crash during pruning left behind; and next attempts or leases written while the
// clock ran ahead do not idle peers and candidates until the clock catches up.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/node-recovery.inc.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\PeerSync;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\PrivateBackup;
use OnionServiceDiscovery\Storage\RecordArchive;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageLimits;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Transport\TorTransport;

$verifier = new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas');
$root = sys_get_temp_dir() . '/osd-maintenance-races-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
$records = recoveryRecords($verifier);
$open = static fn (string $backend, string $path, ?StorageLimits $limits = null, bool $create = false): StorageBackend => $backend === 'sqlite' ? new SQLiteStore($path, $limits, create: $create) : new FilesystemStore($path, $limits, create: $create);
/** A store whose snapshot() is followed by a maintenance prune of one record, on the calls $prunes selects (as node:maintain racing an export). */
$racing = static fn (StorageBackend $inner, \Closure $prunes): StorageBackend => new class ($inner, $prunes) implements StorageBackend {
    private int $calls = 0;
    public function __construct(private StorageBackend $inner, private \Closure $prunes) {}
    public function backendName(): string { return $this->inner->backendName(); }
    public function supportsPruning(): bool { return $this->inner->supportsPruning(); }
    public function transaction(callable $mutation): mixed { return $this->inner->transaction($mutation); }
    public function snapshot(): array
    {
        $state = $this->inner->snapshot();
        if (($this->prunes)(++$this->calls) && $state['records'] !== []) { $victim = (string) array_key_last($state['records']); $this->inner->transaction(static fn (StorageTransaction $tx): bool => $tx->removeRecord($victim)); }
        return $state;
    }
    public function readRecord(string $contentHash): ?string { return $this->inner->readRecord($contentHash); }
    public function readRecords(array $contentHashes): array { return $this->inner->readRecords($contentHashes); }
    public function integrityCheck(): array { return $this->inner->integrityCheck(); }
};

try {
    foreach (['sqlite', 'filesystem'] as $backend) {
        $path = $root . '/' . $backend . ($backend === 'sqlite' ? '.sqlite' : '');
        $fill = static function (StorageBackend $store) use ($records): void { $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } }); };

        // 1. Backup and archive exports retry when pruning removes a record of their snapshot (before: STORAGE_CORRUPT).
        $store = $open($backend, $path, null, true); $fill($store);
        $backup = PrivateBackup::export($racing($store, static fn (int $call): bool => $call === 1), $root . '/' . $backend . '.backup');
        $assert($backup['record_count'] === count($records) - 1 && PrivateBackup::verify($root . '/' . $backend . '.backup', $verifier)['record_count'] === count($records) - 1, $backend . ': backup retried after a concurrent prune');
        $fill($store);
        $archive = RecordArchive::export($racing($store, static fn (int $call): bool => $call === 1), $root . '/' . $backend . '.archive', $verifier);
        $assert($archive['record_count'] >= 1 && is_file($root . '/' . $backend . '.archive'), $backend . ': archive retried after a concurrent prune');
        // Pruning that never stops ends with EVIDENCE_CHANGED_RETRY after the bounded attempts, without a partial file.
        $fill($store);
        try { PrivateBackup::export($racing($store, static fn (int $call): bool => $call % 2 === 1), $root . '/' . $backend . '-busy.backup'); throw new LogicException('export finished'); }
        catch (StorageException $error) { $assert($error->getMessage() === 'EVIDENCE_CHANGED_RETRY' && !file_exists($root . '/' . $backend . '-busy.backup'), $backend . ': continued pruning ends in EVIDENCE_CHANGED_RETRY ' . $error->getMessage()); }
        $fill($store); $assert($store->integrityCheck()['integrity'] === 'PASS', $backend . ': store intact');
        unset($store);

        // 2. At the byte budget a pruning transaction commits (it frees space); any write that adds is still refused.
        clearstatcache(); $size = 0;
        foreach ($backend === 'sqlite' ? [$path, $path . '-wal', $path . '-shm'] : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            $name = is_string($file) ? $file : $file->getPathname(); if (is_file($name)) { $size += (int) filesize($name); }
        }
        $full = $open($backend, $path, new StorageLimits(maxBytes: $size - 1));
        $victim = (string) array_key_first($full->snapshot()['records']);
        try { $full->transaction(static function (StorageTransaction $tx): void { $tx->set('maintenance', 'probe', ['a' => 1]); }); throw new LogicException('write at budget committed'); }
        catch (StorageException $error) { $assert($error->getMessage() === 'STORAGE_BUDGET_EXHAUSTED', $backend . ': adding write refused at the budget'); }
        $full->transaction(static fn (StorageTransaction $tx): bool => $tx->removeRecord($victim));
        $assert(!isset($full->snapshot()['records'][$victim]), $backend . ': pruning commits at the budget (before: STORAGE_BUDGET_EXHAUSTED)');
        $state = $full->snapshot(); $bytes = $full instanceof SQLiteStore || $full instanceof FilesystemStore ? $full->diskBytes() : 0;
        $usage = StorageTransaction::usage($state, new StorageLimits(maxBytes: (int) ceil($bytes / 0.85)), $full);
        $assert($usage['store_bytes'] === $bytes && $usage['state'] === 'WARN' && $usage['usage_percent'] >= 80, $backend . ': usage warns from 80 % of the byte budget ' . json_encode($usage));
        $assert(StorageTransaction::usage($state, new StorageLimits())['store_bytes'] === null, $backend . ': usage without a store reports no bytes');
        unset($full);
    }

    // 3. storage:check reclaims object files a crash between HEAD and the unlinks of a pruning commit left behind.
    $path = $root . '/orphans'; $store = new FilesystemStore($path, create: true);
    $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
    $victim = (string) array_key_first($store->snapshot()['records']); $object = $store->snapshot()['records'][$victim]['object_hash'];
    $objectPath = $path . '/objects/' . substr($object, 0, 2) . '/' . $object . '.json';
    $crash = new FilesystemStore($path, faultHook: static function (string $stage): void { if ($stage === 'after_head') { throw new RuntimeException('crash'); } });
    try { $crash->transaction(static fn (StorageTransaction $tx): bool => $tx->removeRecord($victim)); throw new LogicException('no crash'); } catch (RuntimeException) {}
    $store = new FilesystemStore($path);
    $assert(!isset($store->snapshot()['records'][$victim]) && is_file($objectPath), 'crash left the object file');
    // The previous checkpoint (readers of the last generation) still names it: kept until a later commit replaces that checkpoint.
    $assert($store->integrityCheck()['orphaned_objects_removed'] === 0 && is_file($objectPath), 'orphan kept while the previous checkpoint names it');
    $store->transaction(static function (StorageTransaction $tx): void { $tx->set('maintenance', 'probe', ['a' => 1]); });
    $assert(!array_key_exists('orphaned_objects_removed', (new FilesystemStore($path, readOnly: true))->integrityCheck()) && is_file($objectPath), 'read-only check removes nothing');
    $checked = recoveryCli(['storage:check', 'filesystem', $path], 0); clearstatcache();
    $assert($checked['integrity']['orphaned_objects_removed'] === 1 && !is_file($objectPath) && $store->integrityCheck()['integrity'] === 'PASS', 'storage:check reclaims the orphan ' . json_encode($checked));
    unset($store, $crash);

    // 4. A next attempt or lease written while the clock ran ahead is due now, not when the clock catches up.
    $store = new SQLiteStore($root . '/schedule.sqlite', create: true); $now = time();
    $onion = OnionAddress::parse(json_decode((string) file_get_contents(dirname(__DIR__) . '/config/node.example.json'), true, 32, JSON_THROW_ON_ERROR)['node']['onion']);
    PeerRegistry::add($store, $onion, \OnionServiceDiscovery\Crypto\Encoding::encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()), 'ed25519:'));
    $listener = stream_socket_server('tcp://127.0.0.1:0'); $closedPort = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1); fclose($listener);
    $sync = new PeerSync($store, $verifier, new TorTransport('127.0.0.1', $closedPort, 1000));
    $schedule = static function (string $table, string $key, array $fields) use ($store): void { $store->transaction(static function (StorageTransaction $tx) use ($table, $key, $fields): void { $tx->set($table, $key, $fields + $tx->get($table, $key)); }); };
    $schedule('peers', $onion->hostname, ['state' => 'BACKOFF', 'next_attempt' => $now + 3600]);
    $assert($sync->step($onion, $now)['status'] === 'IDLE', 'peer waits for an ordinary backoff');
    $schedule('peers', $onion->hostname, ['next_attempt' => $now + 30 * 86400]);
    $assert($sync->step($onion, $now)['status'] !== 'IDLE', 'peer scheduled beyond the horizon (clock ran ahead) is due');
    $schedule('peers', $onion->hostname, ['state' => 'CANDIDATE', 'next_attempt' => 0, 'lease_until' => $now + 30 * 86400, 'claim' => 'stale']);
    $assert($sync->step($onion, $now)['status'] !== 'IDLE', 'peer lease beyond the horizon is expired');
    $queue = new CandidateQueue($store); $queue->enqueue($onion, 'manual', $now);
    $schedule('candidates', $onion->hostname, ['state' => 'BACKOFF', 'next_attempt' => $now + 3600]);
    $assert($queue->claim($now) === null, 'candidate waits for an ordinary backoff');
    $schedule('candidates', $onion->hostname, ['next_attempt' => $now + 60 * 86400]);
    $assert(($queue->claim($now)['onion'] ?? null) === $onion->hostname, 'candidate scheduled beyond the horizon is due');
    $schedule('candidates', $onion->hostname, ['lease_until' => $now + 60 * 86400]);
    $assert($queue->claim($now) === null && $store->snapshot()['tables']['candidates']['key:' . $onion->hostname]['state'] === 'BACKOFF', 'candidate lease beyond the horizon is released');
    unset($store, $sync, $queue);
} finally { recoveryRemove($root); }
echo "PASS: exports retry concurrent pruning, pruning commits at the byte budget, orphaned objects are reclaimed and future-dated schedules are due {$checks} checks\n";
