<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Configuration\PublisherMembership;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TransportException;

/** Deterministic synthetic v3 Onion for a TEST Node; no Tor identity is created. */
function testOnion(int $seed): string
{
    $key = substr(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat(chr($seed), 32))), 0, 32);
    $bytes = $key . substr(hash('sha3-256', '.onion checksum' . $key . "\x03", true), 0, 2) . "\x03";
    $alphabet = 'abcdefghijklmnopqrstuvwxyz234567'; $bits = ''; $out = '';
    foreach (str_split($bytes) as $byte) { $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT); }
    foreach (str_split($bits, 5) as $chunk) { $out .= $alphabet[bindec($chunk)]; }
    return OnionAddress::parse($out . '.onion')->hostname;
}
function cli(array $arguments, int $expected): array
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/onion-service-discovery', ...$arguments, '--json'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $exit . ' ' . $out . $err); }
    return json_decode($out, true, 64, JSON_THROW_ON_ERROR);
}

$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$vectors = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($vectors->cases as $case) { if ($case->id === 'fork-same-revision') { $fixture = array_map($verifier->verify(...), $case->records); } }
[$genesis, $current, $fork] = $fixture;
$own = [$genesis, $current];
$root = sys_get_temp_dir() . '/osd-publisher-membership-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$checks = 0;
try {
    // Each Node holds a different, explicitly constructed signed view.
    $views = ['included' => [$genesis, $current], 'stale' => [$genesis], 'absent' => [], 'forked' => [$genesis, $fork], 'tampered' => [$genesis, $current]];
    $nodes = []; $seed = 1;
    foreach ($views as $name => $records) {
        $onion = testOnion($seed++); $dir = $root . '/' . $name; mkdir($dir, 0700);
        $signer = ProtectedFileSigner::create($dir . '/node.key', 'node', 'TEST ONLY publisher membership passphrase', $verifier);
        $v = json_decode(file_get_contents($project . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
        $v['role'] = 'node'; $v['node'] = ['onion' => $onion, 'key_path' => $dir . '/node.key']; $v['storage']['path'] = $dir . '/data';
        $v['export_path'] = $dir . '/exports'; $v['features']['mirror_directory'] = true;
        file_put_contents($dir . '/config.json', json_encode($v, JSON_THROW_ON_ERROR));
        $config = NodeConfig::load($dir . '/config.json'); NodeInitializer::initialize($config, $signer, $verifier);
        $store = $config->store(); $blobs = new PublicBlobStore($dir . '/exports');
        $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
        (new SnapshotProducer($store, $blobs, $verifier, $signer))->publish(1700000000);
        $nodes[$name] = ['onion' => $onion, 'key' => $signer->publicKey(), 'api' => new DataApi($store, $blobs, $verifier, true)];
    }
    $unreachable = testOnion(99); $requests = [];
    $fetch = static function (OnionAddress $onion, ProtocolEndpoint $endpoint) use (&$nodes, &$requests): string {
        $requests[] = $onion->hostname . $endpoint->path;
        foreach ($nodes as $name => $node) {
            if ($node['onion'] !== $onion->hostname) { continue; }
            $response = $node['api']->handle('GET', $endpoint->path);
            if ($response['status'] !== 200) { throw new TransportException('HTTP_STATUS_REJECTED'); }
            if (strlen($response['body']) > $endpoint->maxBytes) { throw new TransportException('RESPONSE_TOO_LARGE'); }
            $body = $response['body'];
            if ($name === 'tampered' && str_contains($endpoint->path, '/chunks/')) { $body[strlen($body) - 2] = chr(ord($body[strlen($body) - 2]) ^ 1); }
            return $body;
        }
        throw new TransportException('TOR_REQUEST_FAILED');
    };
    $checker = new PublisherMembership($verifier, \Closure::fromCallable($fetch));
    $sources = [];
    foreach ($nodes as $node) { $sources[] = [$node['onion'], $node['key']]; }
    $sources[] = [$unreachable, $nodes['included']['key']];
    $sources[] = [$nodes['stale']['onion'], $nodes['included']['key']];
    try { $checker->check($own, $sources, 1700000100); $assert(false, 'duplicate source accepted'); }
    catch (InvalidArgumentException $error) { $assert($error->getMessage() === 'SOURCE_DUPLICATE', 'duplicate source code'); } ++$checks;
    array_pop($sources);
    $wrongKey = testOnion(50); $nodes['wrong-key'] = $nodes['included']; $nodes['wrong-key']['onion'] = $wrongKey;
    $sources[] = [$wrongKey, $nodes['stale']['key']];

    $result = $checker->check($own, $sources, 1700000100);
    $byOnion = array_column($result['sources'], null, 'node_onion');
    $expect = [
        'included' => ['included', 'CURRENT_STATE_INCLUDED', null], 'stale' => ['stale', 'STALE_REVISION_INCLUDED', null],
        'absent' => ['not_present', 'IDENTITY_NOT_PRESENT', null], 'forked' => ['contradictory', 'CONFLICTING_SERVICE_STATE', null],
        'tampered' => ['rejected', 'SOURCE_EVIDENCE_REJECTED', 'CHUNK_HASH_MISMATCH'],
    ];
    foreach ($expect as $name => [$category, $status, $error]) {
        $row = $byOnion[$nodes[$name]['onion']];
        $assert($row['category'] === $category && $row['status'] === $status, 'classification ' . $name . ' ' . json_encode($row));
        if ($error !== null) { $assert(($row['error'] ?? null) !== null, 'rejection reason ' . $name); }
        ++$checks;
    }
    $assert($byOnion[$unreachable]['category'] === 'unavailable' && $byOnion[$unreachable]['error'] === 'TOR_REQUEST_FAILED', 'unavailable'); ++$checks;
    $assert($byOnion[$wrongKey]['category'] === 'rejected' && $byOnion[$wrongKey]['error'] === 'SOURCE_KEY_MISMATCH', 'wrong expected key'); ++$checks;
    $assert(!in_array($wrongKey . '/onion-service-discovery/v1/chunks/', array_map(static fn (string $r): string => preg_replace('~[0-9a-f]{64}\z~', '', $r), $requests), true), 'chunks fetched under wrong key'); ++$checks;
    $included = $byOnion[$nodes['included']['onion']];
    $assert($included['snapshot_state']['manifest_hash'] === $current->contentHash && $included['checkpoint_age_seconds'] === 100, 'included evidence'); ++$checks;
    $assert($result['status'] === 'CURRENT_STATE_INCLUDED_AT_SOME_SELECTED' && $result['counts'] === ['included' => 1, 'stale' => 1, 'not_present' => 1, 'contradictory' => 1, 'unavailable' => 1, 'rejected' => 2], 'summary ' . json_encode($result['counts'])); ++$checks;
    $assert($result['expected_state']['manifest_hash'] === $current->contentHash && $result['expected_state']['revision'] === 2, 'expected state'); ++$checks;
    $assert($result['operator_independence'] === 'NOT_ESTABLISHED' && $result['freshness'] === 'NOT_EVALUATED' && $result['checkpoint_consistency'] === 'NOT_EVALUATED' && $result['state_mutated'] === false, 'scope boundaries'); ++$checks;
    $assert($result['selected_sources'] === 7 && $result['distinct_node_keys'] === 5, 'key count is reported, not trusted'); ++$checks;

    $all = $checker->check($own, [[$nodes['included']['onion'], $nodes['included']['key']]], 1700000100);
    $assert($all['status'] === 'CURRENT_STATE_INCLUDED_AT_ALL_SELECTED', 'all selected'); ++$checks;
    $none = $checker->check($own, [[$unreachable, $nodes['included']['key']]], 1700000100);
    $assert($none['status'] === 'CURRENT_STATE_NOT_CONFIRMED', 'none confirmed'); ++$checks;
    try { $checker->check([$genesis, $current, $fork], [[$nodes['included']['onion'], $nodes['included']['key']]], 1700000100); $assert(false, 'forked own history accepted'); }
    catch (InvalidArgumentException $error) { $assert($error->getMessage() === 'LOCAL_IDENTITY_CONFLICT', 'own fork code ' . $error->getMessage()); } ++$checks;

    // CLI wiring: unreachable local SOCKS port means unavailable, never included; no network beyond loopback.
    $v = json_decode(file_get_contents($project . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
    $v['role'] = 'publisher'; $v['node']['onion'] = $genesis->document()->onion; $v['features']['publish_service'] = true;
    $v['storage']['path'] = $root . '/must-not-be-created'; $v['export_path'] = $root . '/exports-must-not-be-created';
    $v['transport']['proxy_port'] = 1; $v['transport']['timeout_ms'] = 2000;
    file_put_contents($root . '/publisher.json', json_encode($v, JSON_THROW_ON_ERROR));
    file_put_contents($root . '/history.json', json_encode(['records' => [$genesis->document(), $current->document()]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/genesis.json', json_encode($genesis->document(), JSON_THROW_ON_ERROR));
    foreach (['history.json', 'genesis.json'] as $file) {
        $out = cli(['service:membership', $root . '/publisher.json', $root . '/' . $file, '--node', $nodes['included']['onion'], $nodes['included']['key']], 6);
        $assert($out['status'] === 'CURRENT_STATE_NOT_CONFIRMED' && $out['counts']['unavailable'] === 1 && $out['network_side_effects'] === true, 'cli unavailable ' . $file); ++$checks;
    }
    $assert(!file_exists($root . '/must-not-be-created') && !file_exists($root . '/exports-must-not-be-created'), 'publisher storage created'); ++$checks;
    $assert(cli(['service:membership', $root . '/publisher.json', $root . '/history.json'], 2)['error'] === 'USAGE_ERROR', 'no sources'); ++$checks;
    $assert(cli(['service:membership', $root . '/publisher.json', $root . '/history.json', '--peer', $unreachable, $nodes['included']['key']], 2)['error'] === 'USAGE_ERROR', 'wrong flag'); ++$checks;
    $assert(cli(['service:membership', $root . '/publisher.json', $root . '/history.json', '--node', $unreachable, 'ed25519:invalid'], 3)['error'] === 'PUBLIC_KEY_INVALID', 'invalid key'); ++$checks;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: publisher multi-Node membership summary {$checks} controlled checks (not live Tor evidence)\n";
