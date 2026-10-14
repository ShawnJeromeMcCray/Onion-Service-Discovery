<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
function exact($socket, int $length): string {
    $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS fixture'); } $bytes .= $part; } return $bytes;
}
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $manifest = $verifier->verify($case->records[0]); break; } }
$onion = $manifest->document()->onion;
foreach (['current', 'incomplete-history'] as $scenario) {
    $root = sys_get_temp_dir() . '/osd-direct-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
    $signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY direct verifier passphrase', $verifier);
    $store = new SQLiteStore($root . '/data.sqlite', create: true);
    $queue = new CandidateQueue($store); $queue->enqueue(OnionAddress::parse($onion));
    // Importing even a locally signed observation cannot create a local-verification row.
    $imported = $verifier->verify($signer->signRecord((object) ['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'direct_observation', 'node_identity_public_key' => $signer->publicKey(), 'onion' => $onion, 'service_identity_public_key' => $manifest->document()->service_identity_public_key, 'revision' => 1, 'manifest_hash' => $manifest->contentHash, 'result' => 'SUCCESS_CURRENT', 'observed_at' => '2023-01-01T00:00:00Z']));
    $store->transaction(static fn (StorageTransaction $tx) => $tx->putRecord($imported));
    if ($store->snapshot()['tables']['local_observations'] !== []) { throw new RuntimeException('import elevated to local observation'); }
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('fixture unavailable'); }
    $port = substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/direct-verifier-worker.php', $root, $port], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes); fclose($pipes[0]);
    $socket = stream_socket_accept($server, 5); if ($socket === false) { throw new RuntimeException('request missing'); } stream_set_timeout($socket, 5);
    $greeting = exact($socket, 2); exact($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
    $auth = exact($socket, 2); exact($socket, ord($auth[1])); exact($socket, ord(exact($socket, 1))); fwrite($socket, "\x01\x00");
    if (exact($socket, 4) !== "\x05\x01\x00\x03") { throw new RuntimeException('remote DNS missing'); }
    if (exact($socket, ord(exact($socket, 1))) !== $onion || exact($socket, 2) !== "\x00\x50") { throw new RuntimeException('wrong target'); }
    fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50");
    $request = ''; while (!str_contains($request, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('request truncated'); } $request .= $line; }
    $wire = $scenario === 'current' ? $manifest->canonicalWire : $verifier->verify($case->records[1])->canonicalWire;
    fwrite($socket, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($wire) . "\r\n\r\n" . $wire); fclose($socket); fclose($server);
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('worker failed: ' . $stderr . $output); }
    $result = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    $expected = $scenario === 'current' ? 'VERIFIED' : 'BACKOFF';
    if ($result['status'] !== $expected) { throw new RuntimeException('unexpected result: ' . $output); }
    $local = $store->snapshot()['tables']['local_observations'];
    if (($scenario === 'current') !== (count($local) === 1)) { throw new RuntimeException('local evidence boundary failed'); }
    $store->integrityCheck(); unset($store, $queue, $signer);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
}
echo "PASS: controlled direct verification, imported/local separation and incomplete-history backoff\n";
