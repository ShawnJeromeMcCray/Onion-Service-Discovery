<?php
declare(strict_types=1);
require dirname(__DIR__) . '/node-php/vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Node\DirectVerifier;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\PeerSync;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotMembership;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TorTransport;
if (count($argv) !== 4 || !is_readable('/proc/net/route')) { throw new RuntimeException('usage: private-tor-onion.php PUBLIC_HOSTNAME_FILE SOCKS_PORT HTTP_PORT in isolated Docker network none'); }
foreach (array_slice(file('/proc/net/route', FILE_IGNORE_NEW_LINES), 1) as $route) {
    $fields = preg_split('/\s+/', trim($route));
    if (($fields[1] ?? null) === '00000000') { throw new RuntimeException('external route forbidden'); }
}
foreach ([$argv[2], $argv[3]] as $port) { if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) { throw new RuntimeException('invalid lab port'); } }
$address = OnionAddress::parse(trim(file_get_contents($argv[1])));
$project = dirname(__DIR__); $verifier = new RecordVerifier($project . '/protocol/schemas');
$root = sys_get_temp_dir() . '/osd-private-tor-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$server = null;
try {
    // Fresh Service and Node keys are separate from Tor's ephemeral lab keys.
    $service = ProtectedFileSigner::create($root . '/service.key', 'service', 'TEST ONLY private Tor service passphrase', $verifier);
    $node = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY private Tor Node passphrase', $verifier);
    $fixture = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'));
    $doc = clone $fixture->cases[0]->records[0]; unset($doc->signature);
    $doc->onion = $address->hostname; $doc->service_identity_public_key = $service->publicKey();
    $manifest = $verifier->verify($service->signRecord($doc));
    file_put_contents($root . '/manifest.json', $manifest->canonicalWire); chmod($root . '/manifest.json', 0600);
    $config = json_decode(file_get_contents($project . '/node-php/config/node.example.json'), true);
    $config['node'] = ['onion' => $address->hostname, 'key_path' => $root . '/node.key'];
    $config['storage']['path'] = $root . '/source'; $config['export_path'] = $root . '/exports';
    $config['features']['mirror_directory'] = true; $config['transport']['proxy_port'] = (int)$argv[2];
    file_put_contents($root . '/node.json', json_encode($config)); chmod($root . '/node.json', 0600);
    NodeInitializer::initialize(NodeConfig::load($root . '/node.json'), $node, $verifier);
    putenv('OSD_PRIVATE_TOR_LAB_ROOT=' . $root);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $argv[3], $project . '/lab/private-tor-router.php'], [['pipe','r'],['file',$root . '/server.log','a'],['file',$root . '/server.log','a']], $pipes);
    if (!is_resource($server)) { throw new RuntimeException('lab HTTP server unavailable'); } fclose($pipes[0]);
    $deadline = microtime(true) + 5;
    do { $socket = @stream_socket_client('tcp://127.0.0.1:' . $argv[3], $errno, $error, 0.1); if ($socket !== false) { fclose($socket); break; } usleep(100000); } while (microtime(true) < $deadline);
    if ($socket === false) { throw new RuntimeException('lab HTTP server startup failed'); }
    $transport = new TorTransport('127.0.0.1', (int)$argv[2], 30000);
    $fetched = $verifier->verifyWire($transport->get($address, ProtocolEndpoint::manifest()), $address);
    if ($fetched->contentHash !== $manifest->contentHash) { throw new RuntimeException('onion manifest mismatch'); }
    $source = new FilesystemStore($root . '/source');
    (new CandidateQueue($source))->enqueue($address);
    if ((new DirectVerifier($source, $verifier, $transport, $node))->runOne(time())['status'] !== 'VERIFIED') { throw new RuntimeException('real Tor direct verification failed'); }
    if (count($source->snapshot()['tables']['local_observations']) !== 1) { throw new RuntimeException('missing real direct observation'); }
    $blobs = new PublicBlobStore($root . '/exports');
    (new SnapshotProducer($source, $blobs, $verifier, $node))->publish(time());
    $state = json_decode($transport->get($address, ProtocolEndpoint::state()));
    $wire = $transport->get($address, ProtocolEndpoint::snapshot($state->checkpoint_hash));
    $checkpoint = $verifier->verifyWire($wire)->document(); $chunks = [];
    foreach ($checkpoint->chunks as $chunk) { $chunks[] = $transport->get($address, ProtocolEndpoint::chunk($chunk->hash)); }
    $membership = (new SnapshotMembership($verifier))->verify($wire, $chunks, $node->publicKey(), [$manifest]);
    if ($membership['status'] !== 'CURRENT_STATE_INCLUDED') { throw new RuntimeException('real Tor membership mismatch'); }
    foreach (['filesystem', 'sqlite'] as $backend) {
        $receiver = $backend === 'filesystem' ? new FilesystemStore($root . '/receiver', create: true) : new SQLiteStore($root . '/receiver.sqlite', create: true);
        PeerRegistry::add($receiver, $address, $node->publicKey());
        $result = (new PeerSync($receiver, $verifier, $transport))->step($address, time());
        if ($result['status'] !== 'CURRENT' || $receiver->readRecord($manifest->contentHash) === null) { throw new RuntimeException('real Tor sync failed: ' . $backend); }
        if ($receiver->snapshot()['tables']['local_observations'] !== []) { throw new RuntimeException('imported claim elevated to local observation'); }
        $receiver->integrityCheck(); unset($receiver);
    }
    echo json_encode(['status'=>'PASS_DEVELOPMENT', 'network'=>'private Tor network in Docker network none', 'checks'=>['SIGNED_ONION_MANIFEST_FETCH','REAL_TOR_DIRECT_OBSERVATION','PINNED_SIGNED_SNAPSHOT_FETCH','EXACT_OWNER_STATE_MEMBERSHIP','FILESYSTEM_PEER_SYNC','SQLITE_PEER_SYNC','IMPORTED_OBSERVATION_NOT_LOCAL'], 'public_tor_verified'=>false, 'browser_verified'=>false, 'production_approved'=>false], JSON_THROW_ON_ERROR), PHP_EOL;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    putenv('OSD_PRIVATE_TOR_LAB_ROOT'); unset($source, $blobs, $service, $node);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
}
