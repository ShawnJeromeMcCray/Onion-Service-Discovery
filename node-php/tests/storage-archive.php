<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\RecordArchive;
use OnionServiceDiscovery\Protocol\RecordVerifier;
$root = dirname(__DIR__, 2);
$verifier = new RecordVerifier($root . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($root . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $records = array_map($verifier->verify(...), $case->records); break; } }
$directory = sys_get_temp_dir() . '/osd-archive-' . bin2hex(random_bytes(12)); mkdir($directory, 0700);
$source = new FilesystemStore($directory . '/source', create: true);
$source->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } $tx->set('local_observations', 'test', ['direct' => true]); $tx->set('sync', 'peer', ['cursor' => 3]); });
$report = RecordArchive::export($source, $directory . '/evidence.ndjson');
$destination = new SQLiteStore($directory . '/target.sqlite', create: true);
$result = RecordArchive::import($destination, $directory . '/evidence.ndjson', $verifier);
if ($result['record_count'] !== 3 || $report['sha256'] !== $result['sha256'] || $source->snapshot()['records'] !== $destination->snapshot()['records']) { throw new RuntimeException('backend migration mismatch'); }
if ($destination->snapshot()['tables']['local_observations'] !== [] || $destination->snapshot()['tables']['sync'] !== []) { throw new RuntimeException('local verification/cursor forged by import'); }
try { RecordArchive::import($destination, $directory . '/evidence.ndjson', $verifier); throw new RuntimeException('nonempty import accepted'); } catch (StorageException) {}
$bytes = file_get_contents($directory . '/evidence.ndjson'); file_put_contents($directory . '/broken.ndjson', substr($bytes, 0, -10));
$empty = new FilesystemStore($directory . '/empty', create: true);
try { RecordArchive::import($empty, $directory . '/broken.ndjson', $verifier); throw new RuntimeException('truncated archive accepted'); } catch (StorageException) {}
if ($empty->snapshot()['generation'] !== 0) { throw new RuntimeException('invalid archive partly imported'); }
unset($source, $destination, $empty);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($directory);
echo "PASS: backend-neutral archive, fork preservation and invalid-import atomic rejection\n";
