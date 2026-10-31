<?php
declare(strict_types=1);
// Signing ledger (KEY_PATH.signed): a Node never signs a checkpoint generation or descriptor revision
// it already signed, for example after storage:restore of an older backup; --adopt-signed-chain continues
// the published chain, so a peer that holds the pre-restore head sees no equivocation. Both backends.
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/health-report.inc.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\SigningLedger;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageTransaction;

$project = dirname(__DIR__, 2); $cli = $project . '/node-php/bin/onion-service-discovery'; $verifier = new RecordVerifier($project . '/protocol/schemas');
$root = sys_get_temp_dir() . '/osd-signing-ledger-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
/** @return array<string,mixed> */
function cli(array $arguments, int $expected): array
{
    global $cli;
    $process = proc_open([PHP_BINARY, $cli, ...$arguments, '--json', '--no-interaction'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $arguments[0] . ' exit ' . $exit . ' ' . $out . $err); }
    return json_decode($out, true, 64, JSON_THROW_ON_ERROR);
}
function exactBytes($socket, int $length): string { $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS'); } $bytes .= $part; } return $bytes; }
/** One bounded peer-sync step against $api through a local SOCKS stand-in (the peer-sync.php harness). */
function peerStep(DataApi $api, string $path, string $onion, int $now): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('fixture unavailable'); }
    $port = substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/peer-sync-worker.php', $path, $onion, $port, (string) $now], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes); fclose($pipes[0]);
    $requests = 0; $exit = null; $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process); if (!$status['running']) { $exit = $status['exitcode']; break; }
        $socket = @stream_socket_accept($server, 0.1); if ($socket === false) { continue; } stream_set_timeout($socket, 3);
        if (++$requests > 6) { throw new RuntimeException('request budget exceeded'); }
        $greeting = exactBytes($socket, 2); exactBytes($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
        $auth = exactBytes($socket, 2); exactBytes($socket, ord($auth[1])); exactBytes($socket, ord(exactBytes($socket, 1))); fwrite($socket, "\x01\x00");
        if (exactBytes($socket, 4) !== "\x05\x01\x00\x03" || exactBytes($socket, ord(exactBytes($socket, 1))) !== $onion || exactBytes($socket, 2) !== "\x00\x50") { throw new RuntimeException('Tor destination mismatch'); }
        fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50"); $request = '';
        while (!str_contains($request, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('HTTP request missing'); } $request .= $line; }
        if (preg_match('~\AGET ([^ ]+) HTTP/1.1\r\n~', $request, $match) !== 1) { throw new RuntimeException('HTTP method mismatch'); }
        $response = $api->handle('GET', $match[1]); $body = $response['body'];
        fwrite($socket, 'HTTP/1.1 ' . $response['status'] . " Response\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body); fclose($socket);
    }
    fclose($server); if ($exit === null) { proc_terminate($process); throw new RuntimeException('worker deadline'); }
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    if ($exit !== 0) { throw new RuntimeException('peer worker failed: ' . $output . $stderr); }
    return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
}
/** Steps until the peer is CURRENT or fails. */
function peerSync(DataApi $api, string $path, string $onion, int $now): array
{
    for ($step = 0; $step < 12; ++$step) { $result = peerStep($api, $path, $onion, $now + $step); if (($result['status'] ?? null) !== 'PROGRESS') { return $result; } }
    throw new RuntimeException('peer sync did not finish');
}
$ledgerOf = static fn (string $path): array => json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
$exportBlobs = static function (string $exports): array { $names = []; foreach (new DirectoryIterator($exports) as $file) { if ($file->isFile()) { $names[] = $file->getFilename(); } } sort($names); return $names; };

try {
    $fixtures = json_decode((string) file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
    foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $records = array_map($verifier->verify(...), $case->records); } }
    foreach (['filesystem', 'sqlite'] as $backend) {
        $dir = $root . '/' . $backend; mkdir($dir, 0700); $suffix = $backend === 'sqlite' ? '.sqlite' : '';
        $signer = ProtectedFileSigner::create($dir . '/node.key', 'node', 'TEST ONLY signing ledger passphrase', $verifier); $key = $signer->publicKey();
        file_put_contents($dir . '/passphrase', 'TEST ONLY signing ledger passphrase'); chmod($dir . '/passphrase', 0600); $pass = ['--passphrase-file', $dir . '/passphrase'];
        $v = json_decode((string) file_get_contents($project . '/node-php/config/node.example.json'), true, 32, JSON_THROW_ON_ERROR); $onion = $v['node']['onion'];
        $writeConfig = static function (string $name, string $storage, bool $mirror) use ($v, $backend, $dir): string {
            $v['storage'] = ['backend' => $backend, 'path' => $storage]; $v['export_path'] = $dir . '/exports'; $v['node']['key_path'] = $dir . '/node.key';
            $v['features']['mirror_directory'] = $mirror; $v['features']['server_search'] = !$mirror;
            file_put_contents($dir . '/' . $name, json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)); chmod($dir . '/' . $name, 0600);
            return $dir . '/' . $name;
        };
        $config = $writeConfig('node.json', $dir . '/data' . $suffix, true); $ledgerPath = $dir . '/node.key.signed';

        // node:init writes the ledger after the descriptor committed: owner-only, exact shape.
        $assert(cli(['node:init', $config, ...$pass], 0)['status'] === 'INITIALIZED', $backend . ': node:init');
        $ledger = $ledgerOf($ledgerPath); $firstDescriptor = $ledger['descriptor']['hash'];
        $assert((fileperms($ledgerPath) & 0777) === 0600 && array_keys($ledger) === ['checkpoint', 'descriptor', 'ledger_version', 'node_identity_public_key'] && $ledger['ledger_version'] === 1
            && $ledger['node_identity_public_key'] === $key && $ledger['checkpoint'] === null && $ledger['descriptor']['revision'] === 1, $backend . ': ledger after node:init ' . json_encode($ledger));
        $store = NodeConfig::load($config)->store();
        $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
        $heads = [];
        foreach ([[], ['--rebuild-derived']] as $index => $flags) {
            $published = cli(['node:snapshot', $config, ...$pass, ...$flags], 0); $heads[$index + 1] = $published['checkpoint_hash'];
            $assert($published['status'] === 'PUBLISHED' && $ledgerOf($ledgerPath)['checkpoint'] === ['generation' => $index + 1, 'hash' => $published['checkpoint_hash']], $backend . ': ledger follows generation ' . ($index + 1));
        }
        $assert(cli(['storage:backup', $backend, $dir . '/data' . $suffix, $dir . '/old.backup'], 0)['status'] === 'PASS', $backend . ': backup at generation 2');
        // Two descriptor revisions and two generations after the backup.
        $assert(cli(['node:init', $writeConfig('mirror-off.json', $dir . '/data' . $suffix, false), ...$pass], 0)['status'] === 'INITIALIZED', $backend . ': descriptor revision 2');
        $assert(cli(['node:init', $config, ...$pass], 0)['status'] === 'INITIALIZED', $backend . ': descriptor revision 3');
        foreach ([3, 4] as $generation) { $heads[$generation] = cli(['node:snapshot', $config, ...$pass, '--rebuild-derived'], 0)['checkpoint_hash']; }
        $ledger = $ledgerOf($ledgerPath); $lastDescriptor = $ledger['descriptor']['hash'];
        $assert($ledger['checkpoint'] === ['generation' => 4, 'hash' => $heads[4]] && $ledger['descriptor']['revision'] === 3, $backend . ': ledger at generation 4, revision 3');

        // A peer syncs the pre-restore head.
        $peer = $dir . '/peer.sqlite'; PeerRegistry::add(new SQLiteStore($peer, create: true), OnionAddress::parse($onion), $key);
        $now = time(); $blobs = new PublicBlobStore($dir . '/exports');
        $synced = peerSync(new DataApi($store, $blobs, $verifier, true), $peer, $onion, $now);
        $assert(($synced['status'] ?? null) === 'CURRENT' && $synced['generation'] === 4, $backend . ': peer holds generation 4 ' . json_encode($synced));
        unset($store);

        // Restore the generation-2 backup into a new store and point a configuration at it.
        $assert(cli(['storage:restore', $backend, $dir . '/restored' . $suffix, $dir . '/old.backup', '--trusted-private-backup'], 0)['status'] === 'PASS', $backend . ': restore');
        $restoredConfig = $writeConfig('restored.json', $dir . '/restored' . $suffix, true); $restored = NodeConfig::load($restoredConfig)->store();
        $before = $restored->snapshot(); $ledgerBytes = file_get_contents($ledgerPath); $blobsBefore = $exportBlobs($dir . '/exports');
        $assert($before['tables']['node']['key:published-snapshot']['generation'] === 2, $backend . ': restored store is at generation 2');
        $refused = cli(['node:snapshot', $restoredConfig, ...$pass], 4);
        $assert($refused['error'] === 'SIGNED_CHAIN_AHEAD_OF_STORE' && isset($refused['meaning']), $backend . ': node:snapshot refuses ' . json_encode($refused));
        $refused = cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 4);
        $assert($refused['error'] === 'SIGNED_CHAIN_AHEAD_OF_STORE', $backend . ': forced rebuild refuses too');
        $assert(cli(['node:init', $restoredConfig, ...$pass], 4)['error'] === 'SIGNED_CHAIN_AHEAD_OF_STORE', $backend . ': node:init refuses');
        $assert($restored->snapshot() === $before && file_get_contents($ledgerPath) === $ledgerBytes && $exportBlobs($dir . '/exports') === $blobsBefore, $backend . ': refusal signed, stored and exported nothing');
        $doctor = cli(['doctor', $restoredConfig], 4); assertHealthReport($doctor);
        $assert($doctor['signing_ledger'] === 'AHEAD_OF_STORE' && in_array('SIGNING_LEDGER_AHEAD_OF_STORE', $doctor['failures'], true) && str_contains((string) $doctor['next_action'], '--adopt-signed-chain'), $backend . ': doctor names the ledger state');
        $status = cli(['node:status', $restoredConfig], 6);
        $assert($status['signing_ledger'] === 'AHEAD_OF_STORE' && str_contains($status['next_action'], '--adopt-signed-chain'), $backend . ': node:status names the ledger state');
        // Monitoring sees it too: the card may say HEALTHY, but signing has stopped (exit 4 like doctor's FAIL).
        $check = cli(['status', $restoredConfig, '--check'], 4);
        $assert($check['signing_ledger'] === 'AHEAD_OF_STORE' && str_contains((string) ($check['node']['next_action'] ?? ''), '--adopt-signed-chain'), $backend . ': status --check names the ledger state ' . json_encode($check['node'] ?? null));
        // Export pruning keeps the checkpoint the ledger names although the restored store does not reference it.
        cli(['exports:prune', $restoredConfig, '--workers-stopped'], 0);
        $assert($blobs->get($heads[4]) !== null, $backend . ': ledger checkpoint survives exports:prune');

        // --adopt-signed-chain continues at the ledger's generation plus one.
        $adopted = cli(['node:snapshot', $restoredConfig, ...$pass, '--adopt-signed-chain'], 0);
        $assert($adopted['status'] === 'PUBLISHED' && $adopted['adopted_signed_chain'] === ['checkpoint' => 'ADOPTED', 'descriptor' => 'ADOPTED'], $backend . ': adopted ' . json_encode($adopted));
        $head = $verifier->verifyWire((string) $blobs->get($adopted['checkpoint_hash']))->document(); $state = $restored->snapshot();
        $assert($head->generation === 5 && $head->previous_checkpoint_hash === $heads[4] && $state['tables']['node']['key:published-snapshot']['service_index_version'] === 2
            && $state['tables']['node']['key:descriptor']['hash'] === $lastDescriptor && $restored->readRecord($heads[4]) !== null && $restored->readRecord($heads[3]) !== null, $backend . ': chain continues from the adopted head, generation 3 admitted between');
        $assert($ledgerOf($ledgerPath)['checkpoint'] === ['generation' => 5, 'hash' => $adopted['checkpoint_hash']] && $ledgerOf($ledgerPath)['descriptor']['hash'] === $lastDescriptor, $backend . ': ledger at generation 5');
        $doctor = cli(['doctor', $restoredConfig], 0); assertHealthReport($doctor);
        $assert($doctor['signing_ledger'] === 'CONSISTENT', $backend . ': doctor CONSISTENT after adoption');
        $again = cli(['node:snapshot', $restoredConfig, ...$pass, '--adopt-signed-chain'], 0);
        $assert($again['status'] === 'UNCHANGED' && $again['adopted_signed_chain'] === ['checkpoint' => 'CURRENT', 'descriptor' => 'CURRENT'], $backend . ': adoption is idempotent');
        // The peer that held generation 4 accepts generation 5 without equivocation.
        $synced = peerSync(new DataApi($restored, $blobs, $verifier, true), $peer, $onion, $now + 200000);
        $peerState = (new SQLiteStore($peer))->snapshot();
        $assert(($synced['status'] ?? null) === 'CURRENT' && $synced['generation'] === 5 && $peerState['tables']['peers']['key:' . $onion]['state'] !== 'QUARANTINED', $backend . ': peer continues ' . json_encode($synced));

        // Descriptor guard: a store whose descriptor is older than the ledger's refuses; adoption restores it.
        $restored->transaction(static function (StorageTransaction $tx) use ($firstDescriptor): void { $tx->set('node', 'descriptor', ['hash' => $firstDescriptor]); });
        $assert(cli(['node:init', $restoredConfig, ...$pass], 4)['error'] === 'SIGNED_DESCRIPTOR_AHEAD_OF_STORE', $backend . ': node:init refuses an older descriptor');
        $assert(cli(['node:snapshot', $restoredConfig, ...$pass], 4)['error'] === 'SIGNED_DESCRIPTOR_AHEAD_OF_STORE', $backend . ': node:snapshot refuses an older descriptor');
        $adopted = cli(['node:snapshot', $restoredConfig, ...$pass, '--adopt-signed-chain'], 0);
        $assert($adopted['adopted_signed_chain'] === ['checkpoint' => 'CURRENT', 'descriptor' => 'ADOPTED'] && $restored->snapshot()['tables']['node']['key:descriptor']['hash'] === $lastDescriptor, $backend . ': descriptor adopted');
        $assert(cli(['node:init', $restoredConfig, ...$pass], 0)['status'] === 'UNCHANGED', $backend . ': node:init after descriptor adoption');

        // A damaged or foreign ledger stops signing and is never replaced.
        $good = (string) file_get_contents($ledgerPath);
        foreach (['{"ledger_version":1', str_replace('"ledger_version":1', '"ledger_version":2', $good), str_replace($key, 'ed25519:' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), $good)] as $index => $bad) {
            file_put_contents($ledgerPath, $bad);
            $assert(cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 4)['error'] === 'SIGNING_LEDGER_INVALID' && file_get_contents($ledgerPath) === $bad, $backend . ': invalid ledger ' . $index . ' refused and kept');
            $doctor = cli(['doctor', $restoredConfig], 4); assertHealthReport($doctor);
            $assert($doctor['signing_ledger'] === 'INVALID' && in_array('SIGNING_LEDGER_INVALID', $doctor['failures'], true), $backend . ': doctor reports invalid ledger ' . $index);
        }
        file_put_contents($ledgerPath, $good); chmod($ledgerPath, 0644);
        $assert(cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 4)['error'] === 'SIGNING_LEDGER_INVALID', $backend . ': ledger readable by others refused');
        chmod($ledgerPath, 0600); rename($ledgerPath, $dir . '/copy.signed'); symlink($dir . '/copy.signed', $ledgerPath);
        $assert(cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 4)['error'] === 'SIGNING_LEDGER_INVALID' && is_link($ledgerPath), $backend . ': symlinked ledger refused');
        unlink($ledgerPath); rename($dir . '/copy.signed', $ledgerPath);

        // A missing ledger (lost, or first run after an upgrade) is raised to the store, then signing continues.
        unlink($ledgerPath);
        $doctor = cli(['doctor', $restoredConfig], 0); assertHealthReport($doctor);
        $assert($doctor['signing_ledger'] === 'NOT_PRESENT', $backend . ': doctor NOT_PRESENT');
        $published = cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 0); $ledger = $ledgerOf($ledgerPath);
        $assert($published['status'] === 'PUBLISHED' && $ledger['checkpoint']['generation'] === 6 && $ledger['descriptor']['hash'] === $lastDescriptor, $backend . ': missing ledger self-heals');
        // A ledger behind the store (a crash between the commit and the ledger write) is raised too.
        $behind = (string) file_get_contents($ledgerPath);
        cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 0); file_put_contents($ledgerPath, $behind);
        $assert(cli(['node:status', $restoredConfig], 0)['signing_ledger'] === 'BEHIND_STORE', $backend . ': node:status BEHIND_STORE');
        $published = cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 0);
        $assert($ledgerOf($ledgerPath)['checkpoint'] === ['generation' => 8, 'hash' => $published['checkpoint_hash']], $backend . ': behind ledger raised and advanced');

        // A failing commit leaves the store unchanged and the checkpoint pending in the ledger (its blob written first).
        $failing = new class ($restored) implements StorageBackend {
            public function __construct(private StorageBackend $inner) {}
            public function backendName(): string { return $this->inner->backendName(); }
            public function supportsPruning(): bool { return $this->inner->supportsPruning(); }
            public function transaction(callable $mutation): mixed { throw new StorageException('STORAGE_UNAVAILABLE'); }
            public function snapshot(): array { return $this->inner->snapshot(); }
            public function readRecord(string $contentHash): ?string { return $this->inner->readRecord($contentHash); }
            public function readRecords(array $contentHashes): array { return $this->inner->readRecords($contentHashes); }
            public function integrityCheck(): array { return $this->inner->integrityCheck(); }
        };
        $ledgerBytes = file_get_contents($ledgerPath); $before = $restored->snapshot();
        try { (new SnapshotProducer($failing, $blobs, $verifier, $signer, false, true, SigningLedger::forKey($dir . '/node.key')))->publish(time()); throw new RuntimeException('failing commit published'); }
        catch (StorageException $error) { $assert($error->getMessage() === 'STORAGE_UNAVAILABLE', $backend . ': commit failure surfaces'); }
        $pendingEntry = $ledgerOf($ledgerPath)['pending_checkpoint'] ?? null;
        $assert($restored->snapshot() === $before && $ledgerOf($ledgerPath)['checkpoint'] === json_decode($ledgerBytes, true)['checkpoint'] && ($pendingEntry['generation'] ?? null) === 9 && $blobs->get($pendingEntry['hash']) !== null, $backend . ': uncommitted checkpoint held as pending');
        $assert(cli(['node:status', $restoredConfig], 6)['signing_ledger'] === 'AHEAD_OF_STORE', $backend . ': node:status reports the pending checkpoint');
        // The next run adopts the pending generation 9 (it directly follows the store's head) instead of signing generation 9 again.
        $published = cli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 0); $ledger = $ledgerOf($ledgerPath);
        $assert($ledger['checkpoint']['generation'] === 10 && !isset($ledger['pending_checkpoint']) && $verifier->verifyWire((string) $blobs->get($published['checkpoint_hash']))->document()->previous_checkpoint_hash === $pendingEntry['hash'], $backend . ': pending checkpoint adopted, chain continues from it');

        // Nothing to adopt from: the ledger names a checkpoint whose blob is gone.
        $assert(cli(['storage:restore', $backend, $dir . '/lost' . $suffix, $dir . '/old.backup', '--trusted-private-backup'], 0)['status'] === 'PASS', $backend . ': second restore');
        $lostConfig = $writeConfig('lost.json', $dir . '/lost' . $suffix, true); $head = $ledgerOf($ledgerPath)['checkpoint']['hash'];
        unlink($dir . '/exports/' . substr($head, 7) . '.json');
        $assert(cli(['node:snapshot', $lostConfig, ...$pass, '--adopt-signed-chain'], 4)['error'] === 'SIGNED_CHAIN_UNRECOVERABLE', $backend . ': missing checkpoint blob is unrecoverable');
        unset($restored, $failing, $signer);
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: signing ledger refuses a restored older store, adopts the signed chain without equivocation, self-heals and fails hard on damage {$checks} checks\n";
