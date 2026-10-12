<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Http\PublicRequestBudget;
$root = sys_get_temp_dir() . '/osd-budget-' . bin2hex(random_bytes(12)); mkdir($root,0700);
$bucket = new PublicRequestBudget($root . '/budget', 2, 1.0);
if (!$bucket->admit(1000.0) || !$bucket->admit(1000.0) || $bucket->admit(1000.1) || !$bucket->admit(1001.1) || $bucket->admit(900.0)) { throw new RuntimeException('token bucket bounds'); }
$bytes = file_get_contents($root . '/budget'); $state = json_decode($bytes,true);
if (array_keys($state) !== ['tokens','time'] || (fileperms($root . '/budget') & 0077) !== 0) { throw new RuntimeException('privacy/permission violation'); }
unlink($root . '/budget'); rmdir($root); echo "PASS: aggregate request budget and privacy-minimal state\n";
