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
$manifest = $case->records[0];
$verified = (new \OnionServiceDiscovery\Protocol\RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas'))->verify($manifest);
$wire = json_encode($manifest, JSON_THROW_ON_ERROR);
$cases['cli-valid'] = ["HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($wire) . "\r\n\r\n" . $wire, ['status'=>'SIGNATURE_AND_BINDING_VALID','history_bundle'=>'NOT_SERVED_GENESIS_NEEDS_NONE','onion'=>$onion,'manifest_hash'=>$verified->contentHash,'service_identity_public_key'=>$manifest->service_identity_public_key,'revision'=>$manifest->revision,'publish_service'=>$manifest->discovery->publish_service,'identity_state'=>'NOT_DERIVED','state_mutated'=>false,'network_side_effects'=>true]];
$cases['cli-invalid'] = [$cases['valid'][0], ['status'=>'FAIL','error'=>'PROTOCOL_UNSUPPORTED']];
$cases['cli-redirect'] = [$cases['redirect'][0], ['status'=>'FAIL','error'=>'HTTP_STATUS_REJECTED']];
$hintReply = '{"status":"HINT_RECEIVED_UNCONFIRMED"}';
$cases['cli-announce'] = ["HTTP/1.1 202 Accepted\r\nContent-Type: application/json\r\nContent-Length: " . strlen($hintReply) . "\r\n\r\n" . $hintReply, ['status'=>'HINT_RECEIVED_UNCONFIRMED','direct_observation'=>'NOT_CONFIRMED','index_membership'=>'NOT_CONFIRMED','state_mutated'=>false,'network_side_effects'=>true]];
$falseReply = '{"status":"CURRENT_STATE_INCLUDED"}';
$cases['cli-announce-false-proof'] = ["HTTP/1.1 202 Accepted\r\nContent-Type: application/json\r\nContent-Length: " . strlen($falseReply) . "\r\n\r\n" . $falseReply, ['status'=>'FAIL','error'=>'ANNOUNCEMENT_RESPONSE_INVALID']];
$cases['cli-announce-rate-limit'] = ["HTTP/1.1 429 Too Many Requests\r\nContent-Type: application/json\r\nContent-Length: 2\r\n\r\n{}", ['status'=>'FAIL','error'=>'HTTP_STATUS_REJECTED']];
$checks = 0;
$isolationParameters = [];
foreach ($cases as $name => [$response, $expected]) {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($server === false) { throw new RuntimeException('local fixture unavailable'); }
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $cli = str_starts_with($name, 'cli-'); $private = null;
    $command = [PHP_BINARY, __DIR__ . '/tor-transport-worker.php', (string) $port, $onion];
    if ($cli) {
        $private = sys_get_temp_dir() . '/osd-cli-probe-' . bin2hex(random_bytes(12)); mkdir($private, 0700);
        $config = json_decode(file_get_contents(__DIR__ . '/../config/node.example.json'), true, 32, JSON_THROW_ON_ERROR);
        $config['storage']['path']=$private.'/data'; $config['export_path']=$private.'/exports'; $config['node']['key_path']=$private.'/node.key'; $config['transport']['proxy_port']=$port; $config['transport']['timeout_ms']=3000;
        file_put_contents($private.'/config.json',json_encode($config)); chmod($private.'/config.json',0600);
        $command=[PHP_BINARY,__DIR__.'/../bin/onion-service-discovery','service:verify',$private.'/config.json',$onion,'--json','--no-interaction'];
        if (str_starts_with($name, 'cli-announce')) {
            $config['features']['publish_service'] = true; $config['node']['onion'] = $onion;
            file_put_contents($private.'/config.json',json_encode($config));
            file_put_contents($private.'/manifest.json',$wire); chmod($private.'/manifest.json',0600);
            $command=[PHP_BINARY,__DIR__.'/../bin/onion-service-discovery','service:announce',$private.'/config.json',$private.'/manifest.json',$onion,'--json','--no-interaction'];
        }
    }
    $process = proc_open($command, [['pipe','r'],['pipe','w'],['pipe','w']], $pipes, null, ['NO_PROXY' => '*', 'HTTP_PROXY' => 'http://127.0.0.1:1']);
    fclose($pipes[0]);
    $socket = stream_socket_accept($server, 3);
    if ($socket === false) { throw new RuntimeException('proxy bypass or timeout'); }
    stream_set_timeout($socket, 3);
    $greeting = readExact($socket, 2); $methods = readExact($socket, ord($greeting[1]));
    if (ord($greeting[0]) !== 5 || !str_contains($methods, "\x02")) { throw new RuntimeException('SOCKS authentication unavailable'); }
    fwrite($socket, "\x05\x02");
    $auth = readExact($socket, 2); $username = readExact($socket, ord($auth[1])); $password = readExact($socket, ord(readExact($socket, 1)));
    if ($auth[0] !== "\x01" || $username !== '<torS0X>0' || preg_match('/\A[0-9a-f]{32}\z/D', $password) !== 1 || isset($isolationParameters[$password])) { throw new RuntimeException('Tor format-0 isolation parameter invalid or reused'); }
    $isolationParameters[$password] = true;
    fwrite($socket, "\x01\x00");
    $request = readExact($socket, 4);
    if ($request !== "\x05\x01\x00\x03") { throw new RuntimeException('hostname was not resolved through SOCKS'); }
    $hostname = readExact($socket, ord(readExact($socket, 1))); $destinationPort = unpack('nport', readExact($socket, 2))['port'];
    if ($hostname !== $onion || $destinationPort !== 80) { throw new RuntimeException('destination mismatch'); }
    fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50");
    $requestLines = ''; while (!str_contains($requestLines, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('HTTP request missing'); } $requestLines .= $line; }
    if (str_starts_with($name, 'cli-announce')) {
        if (!str_starts_with($requestLines, "POST /onion-service-discovery/v1/candidates/announce HTTP/1.1\r\n") || !str_contains(strtolower($requestLines), 'content-type: application/json') || !preg_match('/Content-Length: ([0-9]+)/i', $requestLines, $length)) { throw new RuntimeException('unexpected announcement request'); }
        $hint = readExact($socket, (int)$length[1]);
        if (strlen($hint)>256 || json_decode($hint,true)!==['protocol_version'=>1,'onion'=>$onion]) { throw new RuntimeException('announcement contains more than a hint'); }
    } elseif (!str_starts_with($requestLines, "GET /.well-known/onion-service-discovery.json HTTP/1.1\r\n")) { throw new RuntimeException('unexpected HTTP request'); }
    if (str_contains(strtolower($requestLines), 'cookie:')) { throw new RuntimeException('cookie exposed'); }
    fwrite($socket, $response); fclose($socket); fclose($server);
    $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($private !== null) {
        if (is_dir($private.'/data') || is_dir($private.'/exports') || is_file($private.'/node.key')) { throw new RuntimeException('read-only CLI probe created runtime state'); }
        if (is_file($private.'/manifest.json')) { unlink($private.'/manifest.json'); }
        unlink($private.'/config.json'); rmdir($private);
    }
    $expectedExit = $name === 'cli-invalid' ? 3 : (in_array($name, ['cli-redirect','cli-announce-false-proof','cli-announce-rate-limit'], true) ? 5 : 0);
    if ($exit !== $expectedExit || $stderr !== '' || json_decode($output, true) !== $expected) { throw new RuntimeException('transport case ' . $name . ': ' . $output . $stderr); }
    ++$checks;
}
try { new TorTransport('example.org'); throw new RuntimeException('remote proxy accepted'); } catch (TransportException) { ++$checks; }
try { ProtocolEndpoint::changes(-1); throw new RuntimeException('negative cursor accepted'); } catch (TransportException) { ++$checks; }
try { ProtocolEndpoint::chunk('../../file'); throw new RuntimeException('arbitrary path accepted'); } catch (TransportException) { ++$checks; }
echo 'PASS: Tor transport ' . $checks . " controlled checks (not live Tor evidence)\n";
