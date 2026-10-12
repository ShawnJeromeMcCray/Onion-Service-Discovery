<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotFormat;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $records = array_map($verifier->verify(...), $case->records); break; } }
$root = sys_get_temp_dir() . '/osd-snapshot-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY snapshot signing passphrase', $verifier);
$store = new SQLiteStore($root . '/data.sqlite', create: true); $blobs = new PublicBlobStore($root . '/exports', true);
$store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
$producer = new SnapshotProducer($store, $blobs, $verifier, $signer); $first = $producer->publish(1700000000);
if ($first['status'] !== 'PUBLISHED' || $first['record_count'] !== 3) { throw new RuntimeException('snapshot build failed'); }
$second = $producer->publish(1700001000);
if ($second['status'] !== 'UNCHANGED' || $second['checkpoint_hash'] !== $first['checkpoint_hash']) { throw new RuntimeException('snapshot not idempotent'); }
$wire = $blobs->get($first['checkpoint_hash']); $descriptor = $verifier->verifyWire($wire)->document();
if ($descriptor->root_hash !== SnapshotFormat::rootHash($descriptor->chunks) || $descriptor->record_count !== 3 || $descriptor->generation !== 1) { throw new RuntimeException('root mismatch'); }
$chunk = $blobs->get($descriptor->chunks[0]->hash);
if (SnapshotFormat::chunkHash($chunk) !== $descriptor->chunks[0]->hash || strlen($chunk) !== $descriptor->chunks[0]->bytes || count(json_decode($chunk)) !== 3) { throw new RuntimeException('chunk mismatch'); }
$store->integrityCheck(); unset($store, $signer, $producer, $blobs);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
echo "PASS: signed deterministic snapshot, idempotence and complete fork evidence\n";
