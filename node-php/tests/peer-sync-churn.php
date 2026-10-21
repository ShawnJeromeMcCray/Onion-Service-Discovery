<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\LocalMaintenance;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotFormat;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;

/*
 * Blocker B: a peer with more chunks than one sync step fetches, whose checkpoint changes before
 * every step, used to be restarted at chunk 0 each time, so its anchor never advanced. The pending
 * checkpoint is now verified to the end while the peer still serves its chunks; when it no longer
 * does, sync restarts at the current one; and checkpoints the peer pruned leave a chain gap
 * instead of a fault.
 */
function exactChurn($socket, int $length): string { $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS'); } $bytes .= $part; } return $bytes; }
/** One PeerSync step in a worker process; $seen gets every requested path. */
function stepChurn(DataApi $api, string $path, string $onion, int $now, ?callable $seen = null): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('fixture unavailable'); }
    $port = substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/peer-sync-worker.php', $path, $onion, $port, (string) $now], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes); fclose($pipes[0]);
    $requests = 0; $exit = null; $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process); if (!$status['running']) { $exit = $status['exitcode']; break; }
        $socket = @stream_socket_accept($server, 0.1); if ($socket === false) { continue; } stream_set_timeout($socket, 3);
        if (++$requests > 6) { throw new RuntimeException('request budget exceeded'); }
        $greeting = exactChurn($socket, 2); exactChurn($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
        $auth = exactChurn($socket, 2); exactChurn($socket, ord($auth[1])); exactChurn($socket, ord(exactChurn($socket, 1))); fwrite($socket, "\x01\x00");
        if (exactChurn($socket, 4) !== "\x05\x01\x00\x03" || exactChurn($socket, ord(exactChurn($socket, 1))) !== $onion || exactChurn($socket, 2) !== "\x00\x50") { throw new RuntimeException('Tor destination mismatch'); }
        fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50"); $request = ''; while (!str_contains($request, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('HTTP request missing'); } $request .= $line; }
        if (preg_match('~\AGET ([^ ]+) HTTP/1.1\r\n~', $request, $match) !== 1) { throw new RuntimeException('HTTP method mismatch'); }
        if ($seen !== null) { $seen($match[1]); }
        $response = $api->handle('GET', $match[1]); $body = $response['body'];
        fwrite($socket, 'HTTP/1.1 ' . $response['status'] . " Response\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body); fclose($socket);
    }
    fclose($server); if ($exit === null) { proc_terminate($process); throw new RuntimeException('worker deadline'); }
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    if ($exit !== 0) { throw new RuntimeException('peer worker failed: ' . $output . $stderr); }
    return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
}

$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
$unique = [];
foreach ($fixtures->cases as $case) { foreach ($case->records as $document) { try { $record = $verifier->verify($document); } catch (InvalidArgumentException) { continue; } $unique[$record->contentHash] = $record; } }
ksort($unique, SORT_STRING); $ordered = array_values(array_slice($unique, 0, 12, true));
if (count($ordered) !== 12) { throw new RuntimeException('fixture records missing'); }
$onion = $ordered[0]->document()->onion ?? null;
foreach ($ordered as $record) { if (isset($record->document()->onion)) { $onion = $record->document()->onion; break; } }
$root = sys_get_temp_dir() . '/osd-churn-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$checks = 0;
try {
    $signer = ProtectedFileSigner::create($root . '/peer.key', 'node', 'TEST ONLY churn peer passphrase', $verifier);
    $v = json_decode(file_get_contents($project . '/node-php/config/example.json'), true); $v['role'] = 'node'; $v['node'] = ['onion' => $onion, 'key_path' => $root . '/peer.key']; $v['storage']['path'] = $root . '/peer-store'; $v['export_path'] = $root . '/peer-exports'; $v['features']['mirror_directory'] = true;
    file_put_contents($root . '/peer.json', json_encode($v, JSON_THROW_ON_ERROR)); $config = NodeConfig::load($root . '/peer.json'); NodeInitializer::initialize($config, $signer, $verifier);
    $peer = $config->store(); $blobs = new PublicBlobStore($root . '/peer-exports');
    $peer->transaction(static function (StorageTransaction $tx) use ($ordered): void { foreach ($ordered as $record) { $tx->putRecord($record); } });
    $base = $verifier->verifyWire((string) $peer->readRecord((new SnapshotProducer($peer, $blobs, $verifier, $signer))->publish(1700000000)['checkpoint_hash']))->document();
    $api = new DataApi($peer, $blobs, $verifier, true);
    // Twelve records as twelve one-record chunks (odd generations) or six two-record chunks (even ones):
    // consecutive checkpoints share no chunk, and one step fetches at most four.
    $publish = static function (int $generation, ?string $previous, int $size, int $created) use ($ordered, $blobs, $signer, $verifier, $peer, $base): ValidatedRecord {
        $chunks = [];
        foreach (array_chunk($ordered, $size) as $group) { $wire = CanonicalJson::encode(array_map(static fn (ValidatedRecord $record) => $record->document(), $group)); $hash = SnapshotFormat::chunkHash($wire); $blobs->put($hash, $wire); $chunks[] = (object) ['hash' => $hash, 'bytes' => strlen($wire), 'record_count' => count($group)]; }
        $document = clone $base; $document->generation = $generation; $document->previous_checkpoint_hash = $previous; $document->chunks = $chunks; $document->root_hash = SnapshotFormat::rootHash($chunks);
        $document->record_count = count($ordered); $document->cursor = 'churn-' . $generation; $document->created_at = gmdate('Y-m-d\TH:i:s\Z', $created);
        $checkpoint = $verifier->verify($signer->signRecord($document)); $blobs->put($checkpoint->contentHash, $checkpoint->canonicalWire);
        $peer->transaction(static function (StorageTransaction $tx) use ($checkpoint): void { $tx->putRecord($checkpoint); $tx->set('node', 'published-snapshot', ['hash' => $checkpoint->contentHash]); });
        return $checkpoint;
    };
    // 1. The peer publishes a new checkpoint before every step; anchors still advance.
    $node = new SQLiteStore($root . '/node.sqlite', create: true); PeerRegistry::add($node, OnionAddress::parse($onion), $signer->publicKey());
    $now = 1700001000; $previous = null; $generation = 0; $anchors = []; $superseded = 0; $current = null;
    for ($step = 1; $step <= 14; ++$step) {
        $current = $publish(++$generation, $previous?->contentHash, $generation % 2 === 1 ? 1 : 2, $now); $previous = $current;
        $live = array_column($current->document()->chunks, 'hash');
        $result = stepChurn($api, $root . '/node.sqlite', $onion, $now += 60, static function (string $path) use ($live, &$superseded): void {
            if (str_contains($path, '/chunks/') && !in_array('sha256:' . substr($path, -64), $live, true)) { ++$superseded; }
        });
        if (!in_array($result['status'], ['PROGRESS', 'CURRENT'], true)) { throw new RuntimeException('churn sync failed: ' . json_encode($result)); }
        if ($result['status'] === 'CURRENT') { $anchors[] = $result['generation']; if ($result['record_count'] !== 12) { throw new RuntimeException('record count not verified'); } }
    }
    $sync = $node->snapshot()['tables']['sync']['key:' . $onion];
    if (count($anchors) < 2 || $anchors[0] !== 1 || $superseded < 3 || $sync['generation'] < 5 || $sync['seen_generation'] !== $generation) { throw new RuntimeException('anchor never advanced under churn: ' . json_encode([$anchors, $superseded, $sync['generation']])); }
    foreach ($ordered as $record) { if ($node->readRecord($record->contentHash) === null) { throw new RuntimeException('a later chunk never arrived'); } }
    $checks += 3;
    // 2. Once the peer no longer serves the pending checkpoint's chunks, sync restarts at its current one.
    $fresh = new SQLiteStore($root . '/fresh.sqlite', create: true); PeerRegistry::add($fresh, OnionAddress::parse($onion), $signer->publicKey());
    $pinned = $publish(++$generation, $previous->contentHash, 1, $now += 60); $previous = $pinned;
    if (stepChurn($api, $root . '/fresh.sqlite', $onion, $now += 60)['next_chunk'] !== 3) { throw new RuntimeException('first contact did not start the snapshot'); }
    $current = $publish(++$generation, $previous->contentHash, 2, $now += 60); $previous = $current;
    foreach ($pinned->document()->chunks as $chunk) { unlink($root . '/peer-exports/' . substr($chunk->hash, 7) . '.json'); }
    $restarted = stepChurn($api, $root . '/fresh.sqlite', $onion, $now += 60);
    if ($restarted['status'] !== 'PROGRESS' || $restarted['phase'] !== 'CHECKPOINT_RESTARTED' || $restarted['generation'] !== $generation || $fresh->snapshot()['tables']['sync']['key:' . $onion]['pending_hash'] !== $current->contentHash) { throw new RuntimeException('withdrawn chunks did not restart: ' . json_encode($restarted)); }
    for ($step = 0; $step < 4 && ($result = stepChurn($api, $root . '/fresh.sqlite', $onion, $now += 60))['status'] !== 'CURRENT'; ++$step) {}
    if ($result['status'] !== 'CURRENT' || $result['generation'] !== $generation) { throw new RuntimeException('restart did not complete'); }
    $checks += 2;
    // 3. Checkpoints the peer pruned (more than 64 generations back) leave a chain gap, not a quarantine.
    $gap = $root . '/gap-store'; $gapStore = new FilesystemStore($gap, create: true); PeerRegistry::add($gapStore, OnionAddress::parse($onion), $signer->publicKey());
    for ($step = 0; $step < 6 && ($result = stepChurn($api, $gap, $onion, $now += 60))['status'] !== 'CURRENT'; ++$step) {}
    $anchor = $generation;
    for ($more = 0; $more < 70; ++$more) { $previous = $publish(++$generation, $previous->contentHash, 2, $now += 60); }
    (new LocalMaintenance($peer, 64))->run($now);
    for ($step = 0; $step < 40 && ($result = stepChurn($api, $gap, $onion, $now += 4000))['status'] !== 'CURRENT'; ++$step) {
        if ($result['status'] !== 'PROGRESS') { throw new RuntimeException('pruned ancestry treated as a fault: ' . json_encode($result)); }
    }
    $sync = $gapStore->snapshot()['tables']['sync']['key:' . $onion];
    if ($result['status'] !== 'CURRENT' || $result['generation'] !== $generation || $sync['chain_gap'] !== true || $sync['baseline_history_proven'] !== false || $anchor >= $generation - 64) { throw new RuntimeException('chain gap not accepted: ' . json_encode($result)); }
    $checks += 1;
    $node->integrityCheck(); $fresh->integrityCheck(); $gapStore->integrityCheck();
    unset($node, $fresh, $gapStore, $peer, $api, $blobs, $signer, $config);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
}
echo 'PASS: a large peer that changes before every step is still verified to the end, withdrawn chunks restart at the current checkpoint and pruned ancestry is a gap (' . $checks . " checks)\n";
