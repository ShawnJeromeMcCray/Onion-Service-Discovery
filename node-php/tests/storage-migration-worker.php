<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
$hook = static function (string $stage) use ($argv): void { if ($stage === $argv[2]) { exit(91); } };
\OnionServiceDiscovery\Storage\FilesystemStore::migrateFormat($argv[1], $hook);
exit(92);
