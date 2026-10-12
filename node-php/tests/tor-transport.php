<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TorTransport;
use OnionServiceDiscovery\Transport\TransportException;
function readExact($socket, int $length): string {
    $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS message'); } $bytes .= $part; } return $bytes;
}
$fixtures = json_decode(file_get_contents(dirname(__DIR__, 2) . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $onion = $case->records[0]->onion; break; } }
$cases = [
    'valid' => ["HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: 2\r\n\r\n{}", ['body' => '{}']],
    'redirect' => ["HTTP/1.1 302 Found\r\nLocation: http://example.org/\r\nContent-Length: 0\r\n\r\n", ['error' => 'HTTP_STATUS_REJECTED']],
    'encoding' => ["HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Encoding: gzip\r\nContent-Length: 2\r\n\r\n{}", ['error' => 'CONTENT_ENCODING_REJECTED']],
    'type' => ["HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: 2\r\n\r\n{}", ['error' => 'CONTENT_TYPE_REJECTED']],
    'oversize' => ["HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: 16385\r\n\r\n" . str_repeat('x', 16385), ['error' => 'RESPONSE_TOO_LARGE']],
    'duplicate' => ["HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Type: application/json\r\nContent-Length: 2\r\n\r\n{}", ['error' => 'HEADERS_INVALID']],
];
$checks = 0;
foreach ($cases as $name => [$response, $expected]) {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($server === false) { throw new RuntimeException('local fixture unavailable'); }
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $process = proc_open([PHP_BINARY, __DIR__ . '/tor-transport-worker.php', (string) $port, $onion], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes, null, ['NO_PROXY' => '*', 'HTTP_PROXY' => 'http://127.0.0.1:1']);
    fclose($pipes[0]);
    $socket = stream_socket_accept($server, 3);
    if ($socket === false) { throw new RuntimeException('proxy bypass or timeout'); }
    stream_set_timeout($socket, 3);
    $greeting = readExact($socket, 2); $methods = readExact($socket, ord($greeting[1]));
    if (ord($greeting[0]) !== 5 || !str_contains($methods, "\x02")) { throw new RuntimeException('SOCKS authentication unavailable'); }
    fwrite($socket, "\x05\x02");
    $auth = readExact($socket, 2); $username = readExact($socket, ord($auth[1])); $password = readExact($socket, ord(readExact($socket, 1)));
    if (strlen($username) !== 32 || strlen($password) !== 32) { throw new RuntimeException('isolation credentials missing'); }
    fwrite($socket, "\x01\x00");
    $request = readExact($socket, 4);
    if ($request !== "\x05\x01\x00\x03") { throw new RuntimeException('hostname was not resolved through SOCKS'); }
    $hostname = readExact($socket, ord(readExact($socket, 1))); $destinationPort = unpack('nport', readExact($socket, 2))['port'];
    if ($hostname !== $onion || $destinationPort !== 80) { throw new RuntimeException('destination mismatch'); }
    fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50");
    $requestLines = ''; while (!str_contains($requestLines, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('HTTP request missing'); } $requestLines .= $line; }
    if (!str_starts_with($requestLines, "GET /.well-known/onion-service-discovery.json HTTP/1.1\r\n") || str_contains(strtolower($requestLines), 'cookie:')) { throw new RuntimeException('unexpected HTTP request'); }
    fwrite($socket, $response); fclose($socket); fclose($server);
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || json_decode($output, true) !== $expected) { throw new RuntimeException('transport case ' . $name . ': ' . $output . $stderr); }
    ++$checks;
}
try { new TorTransport('example.org'); throw new RuntimeException('remote proxy accepted'); } catch (TransportException) { ++$checks; }
try { ProtocolEndpoint::changes(-1); throw new RuntimeException('negative cursor accepted'); } catch (TransportException) { ++$checks; }
try { ProtocolEndpoint::chunk('../../file'); throw new RuntimeException('arbitrary path accepted'); } catch (TransportException) { ++$checks; }
echo 'PASS: Tor transport ' . $checks . " controlled checks (not live Tor evidence)\n";
