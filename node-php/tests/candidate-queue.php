<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$fixtures = json_decode(file_get_contents(dirname(__DIR__, 2) . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $onion = OnionAddress::parse($case->records[0]->onion); break; } }
$root = sys_get_temp_dir() . '/osd-queue-' . bin2hex(random_bytes(12));
$store = new FilesystemStore($root, create: true); $queue = new CandidateQueue($store);
if (!$queue->enqueue($onion) || $queue->enqueue($onion, 'peer-a')) { throw new RuntimeException('queue deduplication'); }
$job = $queue->claim(1000);
if ($job === null || $queue->claim(1001) !== null) { throw new RuntimeException('claim exclusion'); }
$queue->fail($job, 1000, 'TOR_REQUEST_FAILED');
if ($queue->claim(1059) !== null) { throw new RuntimeException('nonzero backoff'); }
$job = $queue->claim(1200); if ($job === null) { throw new RuntimeException('retry missing'); }
if ($queue->claim(1321) !== null || $queue->claim(1370) !== null) { throw new RuntimeException('interrupted worker backoff'); }
$job = $queue->claim(1382); if ($job === null) { throw new RuntimeException('lease recovery missing'); }
$queue->fail($job, 1382, 'SIGNATURE_INVALID', true);
if ($queue->claim(100000) !== null) { throw new RuntimeException('quarantine retried before a week'); }
$store->transaction(static function (StorageTransaction $tx) use ($onion): void { $row = $tx->get('candidates', $onion->hostname); if ($row['sources'] !== ['manual', 'peer-a'] || $row['state'] !== 'QUARANTINED') { throw new RuntimeException('provenance lost'); } });
// A quarantine is a long backoff: after a week the target is checked again.
$recheck = $queue->claim(1382 + CandidateQueue::QUARANTINE_RECHECK_SECONDS + 3601);
if ($recheck === null || $recheck['attempt'] !== 4) { throw new RuntimeException('quarantine never rechecked'); }
$queue->fail($recheck, 1382 + CandidateQueue::QUARANTINE_RECHECK_SECONDS + 3601, 'SIGNATURE_INVALID', true);
// The operator can release it at once; the row keeps its sources and starts its attempts over.
$queue->retry($onion);
$released = $queue->claim(1382 + CandidateQueue::QUARANTINE_RECHECK_SECONDS + 3700);
if ($released === null || $released['attempt'] !== 1) { throw new RuntimeException('operator retry'); }
try { $queue->retry($onion); throw new RuntimeException('retry of a running check'); } catch (InvalidArgumentException $error) { if ($error->getMessage() !== 'CANDIDATE_BUSY') { throw $error; } }
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
echo "PASS: candidate claims, provenance, positive backoff and lease recovery\n";
