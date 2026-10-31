<?php
declare(strict_types=1);
// Shared by signing-ledger-recovery.php and storage-maintenance-races.php: the CLI, a Node directory with key,
// passphrase and configuration, the fixture records, and bounded peer-sync steps through a local SOCKS stand-in.
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;

/** @return array<string,mixed> */
function recoveryCli(array $arguments, int $expected): array
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/onion-service-discovery', ...$arguments, '--json', '--no-interaction'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $arguments[0] . ' exit ' . $exit . ' ' . $out . $err); }
    return json_decode($out, true, 64, JSON_THROW_ON_ERROR);
}
function recoveryBytes($socket, int $length): string { $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS'); } $bytes .= $part; } return $bytes; }
/** One bounded peer-sync step against $api (the peer-sync.php harness). @return array<string,mixed> */
function recoveryPeerStep(DataApi $api, string $path, string $onion, int $now): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('fixture unavailable'); }
    $port = substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/peer-sync-worker.php', $path, $onion, $port, (string) $now], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes); fclose($pipes[0]);
    $exit = null; $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process); if (!$status['running']) { $exit = $status['exitcode']; break; }
        $socket = @stream_socket_accept($server, 0.1); if ($socket === false) { continue; } stream_set_timeout($socket, 3);
        $greeting = recoveryBytes($socket, 2); recoveryBytes($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
        $auth = recoveryBytes($socket, 2); recoveryBytes($socket, ord($auth[1])); recoveryBytes($socket, ord(recoveryBytes($socket, 1))); fwrite($socket, "\x01\x00");
        if (recoveryBytes($socket, 4) !== "\x05\x01\x00\x03" || recoveryBytes($socket, ord(recoveryBytes($socket, 1))) !== $onion || recoveryBytes($socket, 2) !== "\x00\x50") { throw new RuntimeException('Tor destination mismatch'); }
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
/** Steps until the peer is not in PROGRESS. @return array<string,mixed> */
function recoveryPeerSync(DataApi $api, string $path, string $onion, int $now): array
{
    for ($step = 0; $step < 12; ++$step) { $result = recoveryPeerStep($api, $path, $onion, $now + $step); if (($result['status'] ?? null) !== 'PROGRESS') { return $result; } }
    throw new RuntimeException('peer sync did not finish');
}
/**
 * A Node directory: key, passphrase file and a configuration writer (name, storage path) => config path.
 * @return array{0:\Closure,1:list<string>,2:ProtectedFileSigner,3:string,4:string}
 */
function recoveryNode(string $dir, string $backend, RecordVerifier $verifier): array
{
    mkdir($dir, 0700, true); $suffix = $backend === 'sqlite' ? '.sqlite' : '';
    $signer = ProtectedFileSigner::create($dir . '/node.key', 'node', 'TEST ONLY recovery passphrase', $verifier);
    file_put_contents($dir . '/passphrase', 'TEST ONLY recovery passphrase'); chmod($dir . '/passphrase', 0600);
    $v = json_decode((string) file_get_contents(dirname(__DIR__) . '/config/node.example.json'), true, 32, JSON_THROW_ON_ERROR);
    $writeConfig = static function (string $name, string $storage) use ($v, $backend, $dir): string {
        $v['storage'] = ['backend' => $backend, 'path' => $storage]; $v['export_path'] = $dir . '/exports'; $v['node']['key_path'] = $dir . '/node.key';
        $v['features']['mirror_directory'] = true; $v['features']['server_search'] = false;
        file_put_contents($dir . '/' . $name, json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)); chmod($dir . '/' . $name, 0600);
        return $dir . '/' . $name;
    };
    return [$writeConfig, ['--passphrase-file', $dir . '/passphrase'], $signer, $v['node']['onion'], $suffix];
}
/** @return list<\OnionServiceDiscovery\Protocol\ValidatedRecord> */
function recoveryRecords(RecordVerifier $verifier): array
{
    $fixtures = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
    foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { return array_map($verifier->verify(...), $case->records); } }
    throw new RuntimeException('fixture missing');
}
function recoveryRemove(string $root): void
{
    if (!is_dir($root)) { return; }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
