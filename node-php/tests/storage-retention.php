<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\LocalMaintenance;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\PeerSync;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\PrivateBackup;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageJson;
use OnionServiceDiscovery\Storage\StorageLimits;
use OnionServiceDiscovery\Storage\StorageTransaction;

/*
 * Long unattended run (Blocker A): a Node that re-signs its snapshot every period and follows a peer
 * that does the same used to keep every checkpoint as a permanent record until the record budget
 * stopped it with STORAGE_BUDGET_EXHAUSTED. With node:maintain the record count and state size stay
 * bounded on both backends, the newest own checkpoints stay served for ancestry walks, and pruned
 * stores survive backup and restore with their sequences.
 */
function exactRetention($socket, int $length): string { $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS'); } $bytes .= $part; } return $bytes; }
/** One PeerSync step in a worker process against $api over a loopback SOCKS fixture (at most six requests). */
function stepRetention(DataApi $api, string $path, string $onion, int $now, int $maxRecords): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('fixture unavailable'); }
    $port = substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/peer-sync-worker.php', $path, $onion, $port, (string) $now, (string) $maxRecords], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes); fclose($pipes[0]);
    $requests = 0; $exit = null; $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process); if (!$status['running']) { $exit = $status['exitcode']; break; }
        $socket = @stream_socket_accept($server, 0.1); if ($socket === false) { continue; } stream_set_timeout($socket, 3);
        if (++$requests > 6) { throw new RuntimeException('request budget exceeded'); }
        $greeting = exactRetention($socket, 2); exactRetention($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
        $auth = exactRetention($socket, 2); exactRetention($socket, ord($auth[1])); exactRetention($socket, ord(exactRetention($socket, 1))); fwrite($socket, "\x01\x00");
        if (exactRetention($socket, 4) !== "\x05\x01\x00\x03" || exactRetention($socket, ord(exactRetention($socket, 1))) !== $onion || exactRetention($socket, 2) !== "\x00\x50") { throw new RuntimeException('Tor destination mismatch'); }
        fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50"); $request = ''; while (!str_contains($request, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('HTTP request missing'); } $request .= $line; }
        if (preg_match('~\AGET ([^ ]+) HTTP/1.1\r\n~', $request, $match) !== 1) { throw new RuntimeException('HTTP method mismatch'); }
        $response = $api->handle('GET', $match[1]); $body = $response['body'];
        fwrite($socket, 'HTTP/1.1 ' . $response['status'] . " Response\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body); fclose($socket);
    }
    fclose($server); if ($exit === null) { proc_terminate($process); throw new RuntimeException('worker deadline'); }
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    if ($exit !== 0) { throw new RuntimeException('peer worker failed: ' . $output . $stderr); }
    return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
}
function openRetention(string $backend, string $path, StorageLimits $limits, bool $create = false): StorageBackend
{ return $backend === 'filesystem' ? new FilesystemStore($path, $limits, $create) : new SQLiteStore($path, $limits, $create); }

$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $records = array_map($verifier->verify(...), $case->records); break; } }
$root = sys_get_temp_dir() . '/osd-retention-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$checks = 0;
try {
    // Peer B: a real Node with a descriptor, three service records and a snapshot re-signed every period.
    $onion = $records[0]->document()->onion;
    $peerSigner = ProtectedFileSigner::create($root . '/peer.key', 'node', 'TEST ONLY retention peer passphrase', $verifier);
    $v = json_decode(file_get_contents($project . '/node-php/config/example.json'), true); $v['role'] = 'node'; $v['node'] = ['onion' => $onion, 'key_path' => $root . '/peer.key']; $v['storage']['path'] = $root . '/peer-store'; $v['export_path'] = $root . '/peer-exports'; $v['features']['mirror_directory'] = true;
    file_put_contents($root . '/peer.json', json_encode($v, JSON_THROW_ON_ERROR)); $peerConfig = NodeConfig::load($root . '/peer.json'); NodeInitializer::initialize($peerConfig, $peerSigner, $verifier);
    $peerStore = $peerConfig->store(); $peerStore->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
    $peerProducer = new SnapshotProducer($peerStore, new PublicBlobStore($root . '/peer-exports'), $verifier, $peerSigner);
    $peerApi = new DataApi($peerStore, new PublicBlobStore($root . '/peer-exports'), $verifier, true);
    $signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY retention node passphrase', $verifier);
    $paths = []; $now = 1700000000;
    foreach (['filesystem', 'sqlite'] as $backend) {
        // 80 records: without pruning, own and peer checkpoints alone exceed it after 40 periods.
        $limits = new StorageLimits(maxRecords: 80); $path = $paths[$backend] = $root . '/node-' . $backend . ($backend === 'sqlite' ? '.sqlite' : '');
        $store = openRetention($backend, $path, $limits, true); PeerRegistry::add($store, OnionAddress::parse($onion), $peerSigner->publicKey());
        mkdir($root . '/exports-' . $backend, 0700); $blobs = new PublicBlobStore($root . '/exports-' . $backend);
        $producer = new SnapshotProducer($store, $blobs, $verifier, $signer); $maintenance = new LocalMaintenance($store, 64);
        $bytes = []; $highest = 0;
        for ($period = 1; $period <= 110; ++$period) {
            // A day per period forces a new signed generation on both Nodes even without new evidence.
            $now += 86400;
            if ($peerProducer->publish($now)['status'] !== 'PUBLISHED') { throw new RuntimeException('peer did not re-sign'); }
            $synced = stepRetention($peerApi, $path, $onion, $now + 60, 80);
            if ($synced['status'] !== 'CURRENT') { throw new RuntimeException($backend . ': peer sync stopped in period ' . $period . ': ' . json_encode($synced)); }
            if ($producer->publish($now + 120)['status'] !== 'PUBLISHED') { throw new RuntimeException('node did not re-sign'); }
            $maintained = $maintenance->run($now + 180);
            if ($maintained['record_pruning'] !== 'ENABLED') { throw new RuntimeException('pruning not enabled on a new store'); }
            (new LocalMaintenance($peerStore))->run($now + 180);
            $state = $store->snapshot(); $highest = max($highest, count($state['records'])); $bytes[$period] = strlen(StorageJson::encode($state));
        }
        $state = $store->snapshot(); $usage = StorageTransaction::usage($state, $limits);
        if ($highest > 64 + 12 || $state['pruned_records'] < 100 || count($state['records']) + $state['pruned_records'] !== $state['last_sequence'] || $usage['state'] === 'FAIL') { throw new RuntimeException($backend . ': records not bounded: ' . json_encode($usage)); }
        // Once retention is reached the state stops growing (a few bytes for larger sequence numbers aside).
        if ($bytes[110] > $bytes[80] + 512) { throw new RuntimeException($backend . ': state size still grows: ' . $bytes[80] . ' -> ' . $bytes[110]); }
        $witnesses = array_filter(array_keys($state['tables']['checkpoint_evidence']), static fn (string $key): bool => str_starts_with($key, 'key:checkpoint:'));
        if (count($witnesses) > 1 + PeerSync::WITNESSES) { throw new RuntimeException($backend . ': checkpoint witnesses not bounded'); }
        foreach ($records as $record) { if (!isset($state['records'][$record->contentHash])) { throw new RuntimeException($backend . ': service evidence was pruned'); } }
        $checks += 4;
        // D-052: publishers and peers walk the newest own checkpoints back; all 64 kept ones are served.
        $api = new DataApi($store, $blobs, $verifier, true); $hash = $state['tables']['node']['key:published-snapshot']['hash'];
        for ($depth = 0; $depth < 64; ++$depth) {
            $response = $api->handle('GET', '/onion-service-discovery/v1/snapshots/' . substr($hash, 7));
            if ($response['status'] !== 200) { throw new RuntimeException($backend . ': own checkpoint ' . $depth . ' generations back not served'); }
            $hash = $verifier->verifyWire($response['body'])->document()->previous_checkpoint_hash;
        }
        if ($api->handle('GET', '/onion-service-discovery/v1/snapshots/' . substr((string) $hash, 7))['status'] !== 404) { throw new RuntimeException($backend . ': retention kept more than asked'); }
        // The public index names own retained checkpoints only, never a peer's.
        foreach ($state['tables']['node'] as $key => $row) {
            if (!str_starts_with($key, 'key:public-index-')) { continue; }
            // Checkpoints pruned since the last publication drop out of the index then; /changes skips them meanwhile.
            foreach ($row['hashes'] as $public) { $wire = $store->readRecord($public); if ($wire === null) { continue; } $record = $verifier->verifyWire($wire); if ($record->type === 'snapshot_descriptor' && $record->document()->node_identity_public_key !== $signer->publicKey()) { throw new RuntimeException('peer checkpoint in the public index'); } }
        }
        $store->integrityCheck(); $checks += 2;
        unset($store, $producer, $maintenance, $api);
    }
    // A pruned store moves to the other backend with its sequences and retired sequences intact.
    foreach (['filesystem' => 'sqlite', 'sqlite' => 'filesystem'] as $from => $to) {
        $source = openRetention($from, $paths[$from], new StorageLimits(maxRecords: 80));
        PrivateBackup::export($source, $root . '/' . $from . '.backup');
        $target = openRetention($to, $root . '/restored-' . $from . ($to === 'sqlite' ? '.sqlite' : ''), new StorageLimits(maxRecords: 80), true);
        PrivateBackup::restore($target, $root . '/' . $from . '.backup', $verifier);
        $a = $source->snapshot(); $b = $target->snapshot();
        if ($a['records'] !== $b['records'] || $a['last_sequence'] !== $b['last_sequence'] || $a['pruned_records'] !== $b['pruned_records'] || StorageJson::encode($a['tables']) !== StorageJson::encode($b['tables'])) { throw new RuntimeException('pruned backup restore changed sequences'); }
        $target->integrityCheck(); ++$checks; unset($source, $target);
    }
    // Stores of the previous format keep every signed record until storage:migrate, then prune.
    foreach (['filesystem', 'sqlite'] as $backend) {
        $path = $root . '/legacy-' . $backend . ($backend === 'sqlite' ? '.sqlite' : ''); $store = openRetention($backend, $path, new StorageLimits(), true);
        $producer = new SnapshotProducer($store, new PublicBlobStore($root . '/legacy-exports-' . $backend, true), $verifier, $signer);
        for ($day = 1; $day <= 70; ++$day) { $producer->publish($now + $day * 86400); }
        if ($backend === 'filesystem') { file_put_contents($path . '/storage-version', '2'); }
        else { $pdo = new PDO('sqlite:' . $path); $pdo->exec("DELETE FROM metadata WHERE name='pruned_records'"); $pdo->exec('PRAGMA user_version=1'); unset($pdo); }
        $store = openRetention($backend, $path, new StorageLimits());
        $before = count($store->snapshot()['records']); $result = (new LocalMaintenance($store, 64))->run($now + 80 * 86400);
        if ($result['record_pruning'] !== 'MIGRATION_REQUIRED' || $result['signed_records_removed'] !== 0 || count($store->snapshot()['records']) !== $before || $store->supportsPruning()) { throw new RuntimeException($backend . ': old format pruned without migration'); }
        $migrated = $backend === 'filesystem' ? FilesystemStore::migrateFormat($path) : SQLiteStore::migrateSchema($path);
        $store = openRetention($backend, $path, new StorageLimits()); $result = (new LocalMaintenance($store, 64))->run($now + 80 * 86400);
        if ($migrated['status'] !== 'MIGRATED' || $result['record_pruning'] !== 'ENABLED' || $result['signed_records_removed'] !== 6 || count($store->snapshot()['records']) !== 64) { throw new RuntimeException($backend . ': migration did not enable pruning: ' . json_encode($result)); }
        $store->integrityCheck(); $checks += 2;
        // A format this release does not know is refused, not misread.
        if ($backend === 'filesystem') { file_put_contents($path . '/storage-version', '4'); }
        else { $pdo = new PDO('sqlite:' . $path); $pdo->exec('PRAGMA user_version=3'); unset($pdo); }
        unset($store);
        try { openRetention($backend, $path, new StorageLimits()); throw new RuntimeException('unknown format opened'); }
        catch (StorageException $error) { if ($error->getMessage() !== 'STORAGE_VERSION_UNSUPPORTED') { throw $error; } ++$checks; }
    }
} finally {
    unset($peerStore, $peerProducer, $peerApi);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
}
echo 'PASS: bounded checkpoint retention over 110 re-signed periods on both backends, served own ancestry, pruned backup restore and explicit migration (' . $checks . " checks)\n";
