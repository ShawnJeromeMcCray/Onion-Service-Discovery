<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Configuration\NodeStatus;
use OnionServiceDiscovery\Configuration\PublisherStatus;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\StatusPage;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;

// Public Node card and node:identity: identity and aggregates only, never peers or paths.
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$root = sys_get_temp_dir() . '/osd-node-status-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$cli = static function (array $arguments, int $expected): array {
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/onion-service-discovery', ...$arguments, '--json'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $arguments[0] . ' exit ' . $exit . ' ' . $out . $err); }
    return json_decode($out, true, 32, JSON_THROW_ON_ERROR);
};
$checks = 0;
try {
    $vectors = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
    foreach ($vectors->cases as $case) { if ($case->id === 'valid-successor') { $records = array_map($verifier->verify(...), $case->records); } }
    $onion = $records[0]->document()->onion; $peer = 'pg6mmjiyjmcrsslvykfwnntlaru7p5svn6y2ymmju6nubxndf4pscryd.onion';
    $v = json_decode(file_get_contents($project . '/node-php/config/node.example.json'), true, 32, JSON_THROW_ON_ERROR);
    $v['node'] = ['onion' => $onion, 'key_path' => $root . '/node.key']; $v['storage']['path'] = $root . '/data'; $v['export_path'] = $root . '/exports';
    $v['features']['mirror_directory'] = true; $v['features']['discover_services'] = true; $v['features']['sync_with_peers'] = true;
    file_put_contents($root . '/config.json', json_encode($v, JSON_THROW_ON_ERROR)); chmod($root . '/config.json', 0600);
    $config = NodeConfig::load($root . '/config.json');
    $assert(NodeStatus::inspect($config, $verifier, 1800000000)['storage_state'] === 'NOT_INITIALIZED', 'a fresh Node is setup in progress, not an error'); ++$checks;
    $assert($cli(['status', $root . '/config.json', '--check'], 6)['node']['identity'] === 'NOT_INITIALIZED', 'status --check before node:init waits'); ++$checks;
    $signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY node status passphrase', $verifier);
    NodeInitializer::initialize($config, $signer, $verifier);
    $assert($cli(['node:identity', $root . '/config.json'], 0)['node_identity_public_key'] === $signer->publicKey(), 'identity before snapshot'); ++$checks;
    $store = $config->store();
    $store->transaction(static function (StorageTransaction $tx) use ($records, $onion): void {
        foreach ($records as $record) { $tx->putRecord($record); }
        $tx->set('candidates', $onion, ['state' => 'VERIFIED', 'attempts' => 1, 'next_attempt' => 0, 'lease_until' => 0, 'claim' => null, 'sources' => [], 'error' => null]);
        $tx->set('local_observations', $onion, ['manifest_hash' => 'sha256:' . str_repeat('0', 64), 'observation_hash' => 'sha256:' . str_repeat('1', 64), 'observed_at' => 1799990000]);
    });
    (new SnapshotProducer($store, new PublicBlobStore($root . '/exports'), $verifier, $signer))->publish(1799996400);
    PeerRegistry::add($store, OnionAddress::parse($peer), $signer->publicKey()); unset($store);

    $node = NodeStatus::inspect($config, $verifier, 1800000000);
    $assert($node['node_onion'] === $onion && $node['node_identity_public_key'] === $signer->publicKey() && $node['identity'] === 'INITIALIZED', 'identity'); ++$checks;
    $assert($node['storage_state'] === 'READABLE' && $node['snapshot'] === 'PUBLISHED' && $node['snapshot_generation'] === 1 && $node['snapshot_age_seconds'] === 3600, 'snapshot ' . json_encode($node)); ++$checks;
    $assert($node['candidates'] === ['VERIFIED' => 1] && $node['last_direct_verification_at'] === '2027-01-15T05:13:20Z', 'discovery aggregates'); ++$checks;
    $assert($node['peers'] === ['configured' => 1, 'active' => 0, 'quarantined' => 0] && $node['last_successful_sync_at'] === null, 'peer aggregates'); ++$checks;

    $facts = PublisherStatus::inspect($config, $verifier, $root . '/no-manifest.json', $root . '/config.json', 1800000000);
    $assert($facts['answer'] === 'NOT APPLICABLE' && str_contains($facts['next_action'], 'Discovery Node'), 'node answer'); ++$checks;
    $facts['node'] = $node; $page = StatusPage::render($facts)['body'];
    $assert(str_contains($page, 'Discovery Node') && str_contains($page, $signer->publicKey()) && str_contains($page, $onion), 'node card'); ++$checks;
    $fingerprint = \OnionServiceDiscovery\Crypto\KeyFingerprint::of($signer->publicKey());
    $assert(str_contains($page, 'Node key fingerprint') && str_contains($page, $fingerprint), 'node card fingerprint (D-025)'); ++$checks;
    $assert(!str_contains($page, $peer) && !str_contains($page, $root) && !str_contains($page, 'sha256:' . str_repeat('0', 64)), 'node card leaked peer, path or private hash'); ++$checks;

    $identity = $cli(['node:identity', $root . '/config.json'], 0);
    $assert($identity['share']['publishers'] === '--node ' . $onion . ' ' . $signer->publicKey() && str_contains($identity['share']['peers'], '--expected-node-key ' . $signer->publicKey()), 'share lines'); ++$checks;
    $assert($identity['node_key_fingerprint'] === $fingerprint && str_contains($identity['share']['visitors'], $fingerprint), 'identity fingerprint (D-025)'); ++$checks;
    $publisher = json_decode(file_get_contents($project . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
    $publisher['role'] = 'publisher'; $publisher['node']['onion'] = $onion; $publisher['storage']['path'] = $root . '/p-data'; $publisher['export_path'] = $root . '/p-exports';
    file_put_contents($root . '/publisher.json', json_encode($publisher, JSON_THROW_ON_ERROR));
    $assert($cli(['node:identity', $root . '/publisher.json'], 3)['error'] === 'ROLE_CONFIGURATION_INVALID', 'publisher has no Node identity'); ++$checks;
    $assert(NodeStatus::inspect(NodeConfig::load($root . '/publisher.json'), $verifier, 1800000000) === null, 'publisher card'); ++$checks;
    // `status CONFIG --check` runs the dashboard derivation for monitoring.
    $check = $cli(['status', $root . '/config.json', '--check'], 0);
    $assert($check['answer'] === 'NOT APPLICABLE' && $check['node']['node_identity_public_key'] === $signer->publicKey() && $check['network_side_effects'] === false, 'status --check node'); ++$checks;
    $publisher['features']['publish_service'] = true; file_put_contents($root . '/publisher.json', json_encode($publisher, JSON_THROW_ON_ERROR));
    $failing = $cli(['status', $root . '/publisher.json', '--check'], 4);
    $assert($failing['overall'] === 'ERROR' && $failing['node'] === null && $failing['pipeline']['manifest'] === 'FAIL', 'status --check missing Manifest'); ++$checks;

    // Production front controller renders the Node card from the read-only store.
    $listener = stream_socket_server('tcp://127.0.0.1:0'); $port = (int) substr(strrchr(stream_socket_get_name($listener, false), ':'), 1); fclose($listener);
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project . '/node-php/public', $project . '/node-php/public/index.php'], [['pipe', 'r'], ['file', $root . '/server.log', 'a'], ['file', $root . '/server.log', 'a']], $pipes, null, ['ONION_SERVICE_DISCOVERY_CONFIG' => $root . '/config.json']); fclose($pipes[0]);
    try {
        $deadline = microtime(true) + 5; $reply = '';
        do { $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, .1); if ($socket !== false) { break; } usleep(100000); } while (microtime(true) < $deadline);
        if ($socket === false) { throw new RuntimeException('HTTP fixture startup failed'); }
        stream_set_timeout($socket, 5); fwrite($socket, "GET /onion-service-discovery/status/ HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n"); $reply = (string) stream_get_contents($socket); fclose($socket);
        $assert(str_starts_with($reply, 'HTTP/1.1 200') && str_contains($reply, 'NOT APPLICABLE') && str_contains($reply, $signer->publicKey()) && !str_contains($reply, $peer) && !str_contains($reply, $root), 'front controller node card'); ++$checks;
    } finally { proc_terminate($process); proc_close($process); }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: public Discovery Node card and node:identity {$checks} checks\n";
