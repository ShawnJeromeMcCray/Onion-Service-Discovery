<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\SQLiteStore;

// PCP-004 through the real Node worker: first contact with a revised Service succeeds only
// with a connecting history bundle; a non-connecting or malformed bundle changes nothing.
function exactBytes($socket, int $length): string {
    $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS fixture'); } $bytes .= $part; } return $bytes;
}
/** Serves the given bodies, one SOCKS connection each, and returns the worker result and request paths. */
function runWorker(string $root, string $onion, array $bodies): array {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('fixture unavailable'); }
    $port = substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/direct-verifier-worker.php', $root, $port], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes); fclose($pipes[0]);
    $paths = [];
    foreach ($bodies as $body) {
        $socket = stream_socket_accept($server, 5); if ($socket === false) { break; } stream_set_timeout($socket, 5);
        $greeting = exactBytes($socket, 2); exactBytes($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
        $auth = exactBytes($socket, 2); exactBytes($socket, ord($auth[1])); exactBytes($socket, ord(exactBytes($socket, 1))); fwrite($socket, "\x01\x00");
        if (exactBytes($socket, 4) !== "\x05\x01\x00\x03" || exactBytes($socket, ord(exactBytes($socket, 1))) !== $onion || exactBytes($socket, 2) !== "\x00\x50") { throw new RuntimeException('wrong target'); }
        fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50");
        $request = ''; while (!str_contains($request, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('request truncated'); } $request .= $line; }
        $paths[] = explode(' ', $request)[1];
        $status = $body === null ? '404 Not Found' : '200 OK'; $body ??= '{"error":"NOT_FOUND"}';
        fwrite($socket, "HTTP/1.1 " . $status . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body); fclose($socket);
    }
    fclose($server);
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('worker failed: ' . $stderr . $output); }
    return [json_decode($output, true, 32, JSON_THROW_ON_ERROR), $paths];
}
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'valid-successor') { [$r1, $r2] = array_map($verifier->verify(...), $case->records); } }
$onion = $r2->document()->onion;
$bundle = static fn (array $records): string => json_encode(['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'bundle_type' => 'service_history', 'records' => array_map(static fn ($record) => $record->document(), $records)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$scenarios = [
    'connecting-bundle' => [[$r2->canonicalWire, $bundle([$r1, $r2])], 'VERIFIED', null, true],
    'no-bundle' => [[$r2->canonicalWire, null], 'BACKOFF', 'HISTORY_INCOMPLETE', false],
    'non-connecting-bundle' => [[$r2->canonicalWire, $bundle([$r2])], 'BACKOFF', 'HISTORY_INCOMPLETE', false],
    'malformed-bundle' => [[$r2->canonicalWire, '{"protocol":"Onion Service Discovery"}'], 'BACKOFF', 'HISTORY_BUNDLE_INVALID', false],
];
$checks = 0;
foreach ($scenarios as $name => [$bodies, $expectedStatus, $expectedError, $admitsGenesis]) {
    $root = sys_get_temp_dir() . '/osd-direct-history-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
    try {
        ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY direct verifier passphrase', $verifier);
        $store = new SQLiteStore($root . '/data.sqlite', create: true); (new CandidateQueue($store))->enqueue(OnionAddress::parse($onion)); unset($store);
        [$result, $paths] = runWorker($root, $onion, $bodies);
        if ($paths !== ['/.well-known/onion-service-discovery.json', '/.well-known/onion-service-discovery-history.json']) { throw new RuntimeException($name . ': unexpected requests ' . json_encode($paths)); }
        if (($expectedStatus !== null && $result['status'] !== $expectedStatus) || ($result['error'] ?? null) !== $expectedError) { throw new RuntimeException($name . ': ' . json_encode($result)); }
        $store = new SQLiteStore($root . '/data.sqlite', readOnly: true); $state = $store->snapshot();
        if (isset($state['records'][$r1->contentHash]) !== $admitsGenesis || (count($state['tables']['local_observations']) === 1) !== $admitsGenesis) { throw new RuntimeException($name . ': admitted evidence boundary'); }
        unset($store); ++$checks;
    } finally {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
    }
}
echo "PASS: Node direct verification fetches the PCP-004 history bundle only when needed and admits only proven records ({$checks} scenarios)\n";
