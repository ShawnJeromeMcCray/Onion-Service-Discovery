<?php
declare(strict_types=1);
// Production front controller: visitor-chosen relay targets draw on their own budget,
// so exhausting it cannot starve operator-configured sources. No Tor is reachable here.
$project = dirname(__DIR__, 2); $root = sys_get_temp_dir() . '/osd-visitor-relay-' . bin2hex(random_bytes(12)); mkdir($root, 0700); mkdir($root . '/exports', 0700);
$fixture = json_decode(file_get_contents($project . '/protocol/test-vectors/browser-dataset.json'), true, 64, JSON_THROW_ON_ERROR);
$configured = $fixture['sources'][0]['onion']; $stranger = $fixture['sources'][1]['onion'];
$closed = stream_socket_server('tcp://127.0.0.1:0'); $socksPort = (int) substr(strrchr(stream_socket_get_name($closed, false), ':'), 1); fclose($closed);
$config = json_decode(file_get_contents($project . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
$config['sources'] = [$configured]; $config['export_path'] = $root . '/exports'; $config['storage']['path'] = $root . '/unused';
$config['features']['visitor_sources'] = true; $config['transport']['proxy_port'] = $socksPort; $config['transport']['timeout_ms'] = 500;
file_put_contents($root . '/config.json', json_encode($config, JSON_THROW_ON_ERROR)); chmod($root . '/config.json', 0600);
$listener = stream_socket_server('tcp://127.0.0.1:0'); $port = (int) substr(strrchr(stream_socket_get_name($listener, false), ':'), 1); fclose($listener);
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project . '/node-php/public', $project . '/node-php/public/index.php'], [['pipe', 'r'], ['file', $root . '/server.log', 'a'], ['file', $root . '/server.log', 'a']], $pipes, null, ['ONION_SERVICE_DISCOVERY_CONFIG' => $root . '/config.json']); fclose($pipes[0]);
$status = static function (string $path) use ($port): int {
    $socket = stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 2); stream_set_timeout($socket, 5);
    fwrite($socket, "GET " . $path . " HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
    $reply = (string) stream_get_contents($socket, 4096); fclose($socket);
    return preg_match('~\AHTTP/1\.1 ([0-9]{3}) ~', $reply, $match) === 1 ? (int) $match[1] : 0;
};
try {
    $deadline = microtime(true) + 5;
    do { $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, .1); if ($probe !== false) { fclose($probe); break; } usleep(100000); } while (microtime(true) < $deadline);
    // Each admitted visitor request reaches the (closed) Tor port and fails as unavailable, never as not found.
    $codes = [];
    for ($index = 0; $index < 70; ++$index) { $codes[] = $status('/onion-service-discovery/relay/' . $stranger . '/node'); }
    if ($codes[0] !== 503 || !in_array(429, $codes, true) || count(array_filter($codes, static fn (int $code): bool => $code === 503)) > 64 || count(array_filter($codes, static fn (int $code): bool => $code === 503)) < 60) { throw new RuntimeException('visitor budget not enforced: ' . json_encode(array_count_values($codes))); }
    if ($status('/onion-service-discovery/relay/' . $configured . '/node') !== 503) { throw new RuntimeException('exhausted visitor budget starved a configured source'); }
    if (!is_file($root . '/exports/.visitor-relay-budget') || (fileperms($root . '/exports/.visitor-relay-budget') & 0077) !== 0) { throw new RuntimeException('visitor budget file'); }
    if (str_contains((string) file_get_contents($root . '/exports/.visitor-relay-budget'), $stranger)) { throw new RuntimeException('budget stored a Node address'); }
} finally {
    proc_terminate($process); proc_close($process);
    foreach (['/exports/.visitor-relay-budget', '/exports/.relay-budget', '/config.json', '/server.log'] as $file) { if (is_file($root . $file)) { unlink($root . $file); } }
    rmdir($root . '/exports'); rmdir($root);
}
echo "PASS: visitor-chosen relay targets use a separate budget that cannot starve configured sources\n";
