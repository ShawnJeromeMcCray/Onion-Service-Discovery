<?php
declare(strict_types=1);
// D-045 through the production front controller: a Discovery Node whose store cannot be read (an
// interrupted write that needs recovery, a corrupted store) keeps serving its last published signed
// snapshot. Snapshot, chunk, Node and state answers stay byte-identical; routes that need the store
// and announcements answer 503 NODE_DEGRADED_READ_ONLY without touching the store; server search
// answers its 503 page; the status page shows DEGRADED; doctor and status name the next step.
// storage:check or a restore makes the Node healthy again. Without a usable published state, with
// a missing export directory or a broken configuration, nothing is served, as before.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/health-report.inc.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\PublishedState;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageJson;
use OnionServiceDiscovery\Storage\StorageTransaction;

$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$root = sys_get_temp_dir() . '/osd-degraded-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$cli = static function (array $arguments, int $expected) use ($project): array {
    $process = proc_open([PHP_BINARY, $project . '/node-php/bin/onion-service-discovery', ...$arguments, '--json'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = (string) stream_get_contents($pipes[1]); $err = (string) stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $arguments[0] . ' exit ' . $exit . ' ' . $out . $err); }
    $decoded = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
    if (in_array($arguments[0], ['doctor', 'security:check'], true) && isset($decoded['software_version'])) { assertHealthReport($decoded); }
    return $decoded;
};
// Content of every file below a path: a store that nobody touched hashes the same.
$fingerprint = static function (string $path): string {
    if (is_file($path)) { return hash_file('sha256', $path); }
    $hashes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) { $hashes[substr($file->getPathname(), strlen($path))] = hash_file('sha256', $file->getPathname()); }
    ksort($hashes); return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
};
$vectors = json_decode((string) file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($vectors->cases as $case) { if ($case->id === 'valid-successor') { $records = array_map($verifier->verify(...), $case->records); } }
$service = $records[0]->document()->onion; $genesis = $records[0]->document()->service_identity_public_key; $origin = 'http://' . $service;
$checks = 0;
try {
    $signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY degraded mode passphrase', $verifier);
    foreach (['filesystem', 'sqlite'] as $backend) {
        $dir = $root . '/' . $backend; mkdir($dir, 0700); $exports = $dir . '/exports'; $configPath = $dir . '/config.json';
        $storage = $dir . ($backend === 'filesystem' ? '/data' : '/data.sqlite');
        $v = json_decode((string) file_get_contents($project . '/node-php/config/node.example.json'), true, 32, JSON_THROW_ON_ERROR);
        $v['storage'] = ['backend' => $backend, 'path' => $storage]; $v['export_path'] = $exports; $v['node']['key_path'] = $root . '/node.key'; $v['allowed_client_origins'] = [$origin];
        $v['features'] = array_replace($v['features'], ['mirror_directory' => true, 'discover_services' => true, 'accept_announcements' => true, 'server_search' => true]);
        file_put_contents($configPath, json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)); chmod($configPath, 0600);
        $config = NodeConfig::load($configPath);
        NodeInitializer::initialize($config, $signer, $verifier);
        $store = $config->store();
        $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
        $published = (new SnapshotProducer($store, new PublicBlobStore($exports), $verifier, $signer, true))->publish(time());
        unset($store);
        // node:snapshot leaves the last published state beside the blobs; pruning never removes it.
        $copy = PublishedState::load(new PublicBlobStore($exports), $verifier, $config->nodeOnion);
        $assert($published['status'] === 'PUBLISHED' && $copy !== null && $copy->checkpoint->contentHash === $published['checkpoint_hash'] && $copy->nodeDescriptor !== null, 'published state written'); ++$checks;
        $cli(['exports:prune', $configPath, '--workers-stopped'], 0);
        $assert(is_file($exports . '/' . PublicBlobStore::PUBLISHED_STATE) && PublishedState::load(new PublicBlobStore($exports), $verifier, $config->nodeOnion) !== null, 'pruning removed the published state'); ++$checks;

        $listener = stream_socket_server('tcp://127.0.0.1:0'); $port = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1); fclose($listener);
        $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project . '/node-php/public', $project . '/node-php/public/index.php'], [['pipe', 'r'], ['file', $dir . '/server.log', 'a'], ['file', $dir . '/server.log', 'a']], $pipes, null, ['ONION_SERVICE_DISCOVERY_CONFIG' => $configPath]);
        fclose($pipes[0]);
        try {
            $deadline = microtime(true) + 5;
            do { $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, .1); if ($probe !== false) { fclose($probe); break; } usleep(100000); } while (microtime(true) < $deadline);
            if ($probe === false) { throw new RuntimeException('front controller startup failed'); }
            // The full answer without its Date line. The shared request budget is reset first unless a test needs it.
            $request = static function (string $method, string $path, array $headers = [], string $body = '', bool $resetBudget = true) use ($port, $exports): array {
                if ($resetBudget && is_file($exports . '/.request-budget')) { unlink($exports . '/.request-budget'); }
                $socket = stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 2); stream_set_timeout($socket, 10);
                $head = $method . ' ' . $path . " HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n";
                foreach ($headers as $name => $value) { $head .= $name . ': ' . $value . "\r\n"; }
                if ($method === 'POST') { $head .= 'Content-Length: ' . strlen($body) . "\r\n"; }
                fwrite($socket, $head . "\r\n" . $body);
                $reply = (string) stream_get_contents($socket, 2097152); fclose($socket);
                [$lines, $content] = array_pad(explode("\r\n\r\n", $reply, 2), 2, '');
                if (preg_match('~\AHTTP/1\.[01] ([0-9]{3}) ~', $lines, $match) !== 1) { throw new RuntimeException('invalid HTTP response for ' . $path); }
                $lines = implode("\r\n", array_filter(explode("\r\n", $lines), static fn (string $line): bool => stripos($line, 'Date:') !== 0));
                return [(int) $match[1], $lines, $content];
            };
            $v1 = '/onion-service-discovery/v1/';
            $state = json_decode($request('GET', $v1 . 'state')[2], false, 8, JSON_THROW_ON_ERROR);
            $checkpoint = $verifier->verifyWire($request('GET', $v1 . 'snapshots/' . substr($state->checkpoint_hash, 7))[2])->document();
            // What a healthy Node serves from the export directory alone, and what needs its store.
            $exported = ['node', 'state', 'snapshots/' . substr($state->checkpoint_hash, 7)];
            foreach ($checkpoint->chunks as $chunk) { $exported[] = 'chunks/' . substr($chunk->hash, 7); }
            $storeBacked = ['peers', 'changes?after=0', 'services/' . $service, 'services/by-id/' . $genesis, 'services/' . $genesis];
            $answers = static function (array $targets) use ($request, $v1, $origin): array {
                $result = [];
                foreach ($targets as $target) { foreach (['GET', 'HEAD'] as $method) { $result[$method . ' ' . $target] = $request($method, $v1 . $target, ['Origin' => $origin]); } }
                return $result;
            };
            $healthy = $answers($exported); $healthyStore = $answers($storeBacked);
            foreach ($healthy + $healthyStore as $name => [$status, $lines, $body]) { $assert($status === 200 && str_contains($lines, 'Access-Control-Allow-Origin: ' . $origin), 'healthy ' . $name . ' answered ' . $status); }
            $assert($healthy['GET state'][2] === $copy->stateWire && $healthy['GET node'][2] === $copy->nodeDescriptor->canonicalWire, 'published state differs from the healthy answers'); ++$checks;
            // Answers that do not depend on the store's health.
            $invalidService = $request('GET', $v1 . 'services/' . str_repeat('a', 56) . '.onion'); $unknownChunk = $request('GET', $v1 . 'chunks/' . str_repeat('f', 64));
            $assert($invalidService[0] === 400 && $unknownChunk[0] === 404 && $request('GET', $v1 . 'snapshots/' . str_repeat('f', 64))[0] === 404, 'healthy refusals'); ++$checks;
            $announce = static fn (): array => $request('POST', $v1 . 'candidates/announce', ['Content-Type' => 'application/json'], json_encode(['protocol_version' => 1, 'onion' => $service], JSON_THROW_ON_ERROR));
            $search = static fn (): array => $request('POST', '/onion-service-discovery/search/server', ['Content-Type' => 'application/x-www-form-urlencoded'], 'query=example');
            $status = static fn (): string => $request('GET', '/onion-service-discovery/status/')[2];
            $assert($announce()[0] === 202 && $search()[0] === 200 && str_contains($status(), '<dt>Storage</dt><dd>HEALTHY</dd>'), 'healthy announcement, search or status'); ++$checks;
            $doctor = $cli(['doctor', $configPath], 6);
            $assert($doctor['node_health'] === 'HEALTHY' && $doctor['next_action'] === null && $doctor['security_check_summary']['published_state_copy'] === 'PASS', 'healthy doctor ' . json_encode($doctor)); ++$checks;
            $healthyAgain = static function (string $phase) use ($answers, $exported, $storeBacked, $healthy, $healthyStore, $status, $cli, $configPath, $assert, &$checks): void {
                $assert($answers($exported) === $healthy && $answers($storeBacked) === $healthyStore, $phase . ': answers changed after recovery'); ++$checks;
                $assert(str_contains($status(), '<dt>Storage</dt><dd>HEALTHY</dd>') && $cli(['status', $configPath], 0)['node']['storage_state'] === 'HEALTHY' && $cli(['doctor', $configPath], 6)['node_health'] === 'HEALTHY', $phase . ': not healthy after recovery'); ++$checks;
            };
            // Everything D-045 promises while the store cannot be read. $storage must stay untouched.
            $degraded = static function (string $phase, string $cause) use ($answers, $exported, $storeBacked, $healthy, $request, $v1, $invalidService, $unknownChunk, $announce, $search, $status, $cli, $configPath, $fingerprint, $storage, $root, $signer, $assert, &$checks): void {
                $before = $fingerprint($storage);
                $assert($answers($exported) === $healthy, $phase . ': snapshot, chunk, Node or state answer changed'); ++$checks;
                foreach ($answers($storeBacked) as $name => [$code, $lines, $body]) {
                    $assert($code === 503 && $body === (str_starts_with($name, 'HEAD') ? '' : '{"error":"NODE_DEGRADED_READ_ONLY"}') && str_contains($lines, 'Content-Type: application/json'), $phase . ': ' . $name . ' answered ' . $code . ' ' . $body);
                }
                ++$checks;
                // A checkpoint the exports no longer hold may still be in the store: unavailable, never "not found".
                $assert($request('GET', $v1 . 'snapshots/' . str_repeat('f', 64))[2] === '{"error":"NODE_DEGRADED_READ_ONLY"}' && $request('GET', $v1 . 'services/' . str_repeat('a', 56) . '.onion') === $invalidService && $request('GET', $v1 . 'chunks/' . str_repeat('f', 64)) === $unknownChunk, $phase . ': refusals'); ++$checks;
                [$code, , $body] = $announce();
                $assert($code === 503 && $body === '{"error":"NODE_DEGRADED_READ_ONLY"}', $phase . ': announcement answered ' . $code . ' ' . $body); ++$checks;
                [$code, , $body] = $search();
                $assert($code === 503 && str_contains($body, 'Server search configuration or local index is unavailable.'), $phase . ': server search answered ' . $code); ++$checks;
                $page = $status();
                $assert(str_contains($page, '<dt>Storage</dt><dd>DEGRADED</dd>') && str_contains($page, 'It keeps serving its last published signed snapshot') && str_contains($page, $signer->publicKey()) && str_contains($page, '<dt>Snapshot generation</dt><dd>1</dd>'), $phase . ': status page'); ++$checks;
                $assert(!str_contains($page, $root) && !str_contains($page, $cause) && !str_contains($page, 'Signed records') && !str_contains($page, 'Discovery queue'), $phase . ': status page shows private or store details'); ++$checks;
                $node = $cli(['status', $configPath], 4);
                $assert($node['error'] === $cause && $node['node']['storage_state'] === 'DEGRADED' && str_contains($node['next_action'], 'incident-runbook.md section 4') && $node['help'] === 'docs/operations/incident-runbook.md', $phase . ': status ' . json_encode($node)); ++$checks;
                $check = $cli(['status', $configPath, '--check'], 4);
                $assert($check['node']['storage_state'] === 'DEGRADED' && str_contains($check['node']['next_action'], 'storage:check'), $phase . ': status --check'); ++$checks;
                $doctor = $cli(['doctor', $configPath], 4);
                $assert($doctor['status'] === 'FAIL' && in_array($cause, $doctor['failures'], true) && $doctor['node_health'] === 'DEGRADED' && str_contains((string) $doctor['next_action'], 'incident-runbook.md section 4'), $phase . ': doctor ' . json_encode($doctor)); ++$checks;
                $assert($fingerprint($storage) === $before, $phase . ': a request or a read-only command changed the store'); ++$checks;
            };
            // Nothing can be served without a usable published state or export directory, and degraded
            // serving never hides a broken configuration: every data route then fails as before.
            $unavailable = static function (string $phase) use ($request, $v1, $exports, $configPath, $status, $announce, $assert, &$checks): void {
                $statePath = $exports . '/' . PublicBlobStore::PUBLISHED_STATE; $saved = (string) file_get_contents($statePath);
                $expectUnavailable = static function (string $why) use ($request, $v1, $announce, $assert, &$checks, $phase): void {
                    foreach (['node', 'state', 'peers'] as $target) { $assert($request('GET', $v1 . $target)[2] === '{"error":"NODE_UNAVAILABLE"}', $phase . ', ' . $why . ': ' . $target . ' served'); }
                    $assert($announce()[2] === '{"error":"NODE_UNAVAILABLE"}', $phase . ', ' . $why . ': announcement'); ++$checks;
                };
                unlink($statePath);
                $expectUnavailable('no published state');
                $assert(str_contains($status(), '<dt>Storage</dt><dd>ERROR</dd>'), $phase . ': status without a published state'); ++$checks;
                // A copy whose state does not match the signed checkpoint serves nothing either.
                $file = StorageJson::decode($saved, PublicBlobStore::PUBLISHED_STATE_MAX_BYTES);
                $file['state'] = str_replace('"generation":1,', '"generation":2,', $file['state']);
                file_put_contents($statePath, StorageJson::encode($file)); chmod($statePath, 0600);
                $expectUnavailable('a damaged published state');
                file_put_contents($statePath, $saved); chmod($statePath, 0600);
                rename($exports, $exports . '.moved');
                $assert($request('GET', $v1 . 'state')[2] === '{"error":"NODE_UNAVAILABLE"}', $phase . ': served without an export directory'); ++$checks;
                rename($exports . '.moved', $exports);
                $configuration = (string) file_get_contents($configPath);
                file_put_contents($configPath, '{');
                $assert($request('GET', $v1 . 'state')[2] === '{"error":"NODE_UNAVAILABLE"}' && str_contains($status(), 'NOT_CONFIGURED'), $phase . ': a broken configuration was masked'); ++$checks;
                file_put_contents($configPath, $configuration);
            };

            if ($backend === 'filesystem') {
                // An interrupted write: PREPARE in the journal, no COMMIT, as after a power loss.
                $writer = new FilesystemStore($storage, faultHook: static function (string $stage): void { if ($stage === 'after_prepare') { throw new RuntimeException('simulated power loss'); } });
                try { $writer->transaction(static function (StorageTransaction $tx): void { $tx->set('sync', 'degraded-mode-test', ['cursor' => 1]); }); throw new LogicException('interrupted write not reached'); }
                catch (RuntimeException $error) { if ($error->getMessage() !== 'simulated power loss') { throw $error; } }
                unset($writer);
                try { new FilesystemStore($storage, readOnly: true); throw new LogicException('store still readable'); }
                catch (StorageException $error) { $assert($error->getMessage() === 'STORAGE_RECOVERY_REQUIRED', 'interrupted write fixture: ' . $error->getMessage()); }
                $degraded('interrupted write', 'STORAGE_RECOVERY_REQUIRED');
                // The request budget still applies to degraded answers.
                $limited = 0; for ($i = 0; $i < 30; ++$i) { $limited += $request('GET', $v1 . 'state', [], '', false)[2] === '{"error":"REQUEST_BUDGET_EXHAUSTED"}' ? 1 : 0; }
                $assert($limited > 0, 'degraded answers bypass the request budget'); ++$checks;
                $unavailable('interrupted write');
                $assert($cli(['storage:check', 'filesystem', $storage], 0)['integrity']['integrity'] === 'PASS', 'storage:check recovery'); ++$checks;
                $healthyAgain('interrupted write');
            }
            // Corruption: a damaged checkpoint file or SQLite header. storage:check refuses; a restore heals.
            $damaged = $backend === 'filesystem' ? $storage . '/checkpoints/' . json_decode((string) file_get_contents($storage . '/HEAD'), true, 4, JSON_THROW_ON_ERROR)['checkpoint'] . '.json' : $storage;
            $original = (string) file_get_contents($damaged);
            file_put_contents($damaged, str_repeat("\x00", 32) . substr($original, 32));
            $cause = $backend === 'filesystem' ? 'STORAGE_CORRUPT' : 'STORAGE_UNAVAILABLE';
            $degraded('corrupted store', $cause);
            $assert($cli(['storage:check', $backend, $storage], 4)['error'] === $cause && $answers($exported) === $healthy, 'storage:check on a corrupted store'); ++$checks;
            $unavailable('corrupted store');
            file_put_contents($damaged, $original);
            $healthyAgain('corrupted store');
        } finally { proc_terminate($process); proc_close($process); }
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: degraded read-only serving (D-045) {$checks} checks: interrupted write and corrupted store on both backends, byte-identical snapshots, refusals, status, recovery\n";
