<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\PrivateBackup;
use OnionServiceDiscovery\Protocol\RecordVerifier;
$root = dirname(__DIR__, 2);
$verifier = new RecordVerifier($root . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($root . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $records = array_map($verifier->verify(...), $case->records); break; } }
$directory = sys_get_temp_dir() . '/osd-archive-' . bin2hex(random_bytes(12)); mkdir($directory, 0700);
$source = new FilesystemStore($directory . '/source', create: true);
$source->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } $tx->set('local_observations', 'test', ['direct' => true, 'local_checked_at' => 123]); $tx->set('sync', 'peer', ['cursor' => 3]); });
$report = PrivateBackup::export($source, $directory . '/evidence.ndjson');
$verified = PrivateBackup::verify($directory . '/evidence.ndjson', $verifier);
if ($verified['record_count'] !== 3 || $verified['destination_modified'] !== false) { throw new RuntimeException('nonmutating backup verification failed'); }
$destination = new SQLiteStore($directory . '/target.sqlite', create: true);
$result = PrivateBackup::restore($destination, $directory . '/evidence.ndjson', $verifier);
if ($result['record_count'] !== 3 || $report['sha256'] !== $result['sha256'] || $source->snapshot()['records'] !== $destination->snapshot()['records']) { throw new RuntimeException('backend migration mismatch'); }
if (\OnionServiceDiscovery\Storage\StorageJson::encode($source->snapshot()['tables']) !== \OnionServiceDiscovery\Storage\StorageJson::encode($destination->snapshot()['tables'])) { throw new RuntimeException('private operational state lost'); }
try { PrivateBackup::restore($destination, $directory . '/evidence.ndjson', $verifier); throw new RuntimeException('nonempty import accepted'); } catch (StorageException) {}
$bytes = file_get_contents($directory . '/evidence.ndjson'); file_put_contents($directory . '/broken.ndjson', substr($bytes, 0, -10)); chmod($directory . '/broken.ndjson', 0600);
$roundtrip = new FilesystemStore($directory . '/roundtrip', create: true);
PrivateBackup::export($destination, $directory . '/roundtrip.ndjson');
PrivateBackup::restore($roundtrip, $directory . '/roundtrip.ndjson', $verifier);
if (\OnionServiceDiscovery\Storage\StorageJson::encode($roundtrip->snapshot()['tables']) !== \OnionServiceDiscovery\Storage\StorageJson::encode($source->snapshot()['tables'])) { throw new RuntimeException('reverse backend restore mismatch'); }
chmod($directory . '/roundtrip.ndjson', 0644);
$unsafe = new FilesystemStore($directory . '/unsafe', create: true);
try { PrivateBackup::restore($unsafe, $directory . '/roundtrip.ndjson', $verifier); throw new RuntimeException('public backup restored'); } catch (StorageException) {}
copy($directory . '/evidence.ndjson', $directory . '/hs_ed25519_secret_key'); chmod($directory . '/hs_ed25519_secret_key', 0600);
$torBoundary = new FilesystemStore($directory . '/tor-boundary', create: true);
try { PrivateBackup::restore($torBoundary, $directory . '/hs_ed25519_secret_key', $verifier); throw new RuntimeException('Tor filename allowed as backup'); } catch (StorageException) {}
if ($torBoundary->snapshot()['generation'] !== 0) { throw new RuntimeException('forbidden input caused admission'); }
$empty = new FilesystemStore($directory . '/empty', create: true);
try { PrivateBackup::restore($empty, $directory . '/broken.ndjson', $verifier); throw new RuntimeException('truncated archive accepted'); } catch (StorageException) {}
if ($empty->snapshot()['generation'] !== 0) { throw new RuntimeException('invalid archive partly imported'); }
unset($source, $destination, $empty);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($directory);
echo "PASS: private cross-backend backup preserves forks and operational state, rejects truncation and unsafe permissions\n";
