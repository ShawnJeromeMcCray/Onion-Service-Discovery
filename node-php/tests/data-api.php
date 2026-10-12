<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $records = array_map($verifier->verify(...), $case->records); break; } }
$root = sys_get_temp_dir() . '/osd-api-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY data API passphrase', $verifier);
$store = new FilesystemStore($root . '/data', create: true); $blobs = new PublicBlobStore($root . '/exports', true);
$store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
$producer = new SnapshotProducer($store, $blobs, $verifier, $signer); $published = $producer->publish(1700000000);
$api = new DataApi($store, $blobs, $verifier, true);
$stateResponse = $api->handle('GET', '/onion-service-discovery/v1/state'); $state = json_decode($stateResponse['body'], true);
if ($stateResponse['status'] !== 200 || $state['checkpoint_hash'] !== $published['checkpoint_hash']) { throw new RuntimeException('state failed'); }
$snapshotResponse = $api->handle('GET', '/onion-service-discovery/v1/snapshots/' . substr($state['checkpoint_hash'], 7));
$checkpoint = $verifier->verifyWire($snapshotResponse['body'])->document();
$chunkTarget = '/onion-service-discovery/v1/chunks/' . substr($checkpoint->chunks[0]->hash, 7);
if ($api->handle('GET', $chunkTarget)['status'] !== 200 || $api->handle('HEAD', $chunkTarget)['body'] !== '') { throw new RuntimeException('chunk/head failed'); }
$serviceResponse=$api->handle('GET','/onion-service-discovery/v1/services/'.$records[0]->document()->onion);
if($serviceResponse['status']!==200||count(json_decode($serviceResponse['body'])->records)!==3) { throw new RuntimeException('derived indexed service lookup failed'); }
$changes = json_decode($api->handle('GET', '/onion-service-discovery/v1/changes?after=0')['body'], true);
if (count($changes['records']) !== 4 || $changes['cursor'] !== 4 || $changes['has_more']) { throw new RuntimeException('delta failed'); }
foreach (['POST','DELETE','PUT','PATCH'] as $method) { if ($api->handle($method, '/onion-service-discovery/v1/state')['status'] !== 405) { throw new RuntimeException('mutation surface'); } }
if ($api->handle('GET', '/onion-service-discovery/v1/changes?after=0&url=http://example.org')['status'] !== 404 || $api->handle('GET', '/onion-service-discovery/v1/chunks/' . str_repeat('0',64))['status'] !== 404) { throw new RuntimeException('unknown request accepted'); }
$disabled = new DataApi($store, $blobs, $verifier, false); if ($disabled->handle('GET', '/onion-service-discovery/v1/state')['status'] !== 404) { throw new RuntimeException('mirror flag bypass'); }
if (isset($stateResponse['headers']['Set-Cookie']) || isset($stateResponse['headers']['Access-Control-Allow-Origin'])) { throw new RuntimeException('cookie/CORS default'); }
if ($api->handle('HEAD', '/missing')['body'] !== '') { throw new RuntimeException('HEAD error body'); }
$origin = 'http://' . $records[0]->document()->onion;
$cors = new DataApi($store, $blobs, $verifier, true, [$origin]);
if (($cors->handle('GET', '/onion-service-discovery/v1/state', $origin)['headers']['Access-Control-Allow-Origin'] ?? null) !== $origin || isset($cors->handle('GET', '/onion-service-discovery/v1/state', 'http://example.org')['headers']['Access-Control-Allow-Origin'])) { throw new RuntimeException('CORS allowlist'); }
unset($cors);
unset($store, $api, $disabled, $producer, $blobs, $signer);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
echo "PASS: read-only data API, snapshot/chunk/delta, mirror flag and HEAD\n";
