<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Http\PublicRequestBudget;
$root = sys_get_temp_dir() . '/osd-budget-' . bin2hex(random_bytes(12)); mkdir($root,0700);
$bucket = new PublicRequestBudget($root . '/budget', 2, 1.0);
if (!$bucket->admit(1000.0) || !$bucket->admit(1000.0) || $bucket->admit(1000.1) || !$bucket->admit(1001.1) || $bucket->admit(900.0)) { throw new RuntimeException('token bucket bounds'); }
$bytes = file_get_contents($root . '/budget'); $state = json_decode($bytes,true);
if (array_keys($state) !== ['tokens','time'] || (fileperms($root . '/budget') & 0077) !== 0) { throw new RuntimeException('privacy/permission violation'); }
if (PublicRequestBudget::inspect($root . '/budget') !== 'PASS' || PublicRequestBudget::inspect($root . '/absent') !== 'ABSENT') { throw new RuntimeException('budget inspection'); }
// A damaged file (crash, short write, hand edit) refills the bucket and is rewritten instead of taking the routes offline.
foreach (['{"tokens":1,', "\0\0\0", '{"tokens":-1,"time":5}', '{"tokens":"1","time":5}', str_repeat('x', 2000), '[]'] as $damaged) {
    file_put_contents($root . '/budget', $damaged); chmod($root . '/budget', 0600);
    if (PublicRequestBudget::inspect($root . '/budget') !== 'CORRUPT') { throw new RuntimeException('damaged budget not reported: ' . json_encode($damaged)); }
    if (!$bucket->admit(2000.0) || PublicRequestBudget::inspect($root . '/budget') !== 'PASS' || (fileperms($root . '/budget') & 0077) !== 0) { throw new RuntimeException('damaged budget blocked requests'); }
}
if (glob($root . '/.tmp-*') !== []) { throw new RuntimeException('temporary budget file left behind'); }
chmod($root . '/budget', 0644);
if (PublicRequestBudget::inspect($root . '/budget') !== 'UNAVAILABLE') { throw new RuntimeException('open budget file not reported'); }
try { $bucket->admit(2001.0); throw new RuntimeException('open budget file accepted'); } catch (\OnionServiceDiscovery\Storage\StorageException $error) { if ($error->errorCode !== 'RATE_LIMIT_UNAVAILABLE') { throw $error; } }
unlink($root . '/budget'); rmdir($root); echo "PASS: aggregate request budget, privacy-minimal state and damaged-file recovery\n";
