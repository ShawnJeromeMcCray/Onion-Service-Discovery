<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
try {
    $blobs = new \OnionServiceDiscovery\Storage\PublicBlobStore($argv[1], false, 100);
    $bytes = str_repeat($argv[2], 80); $blobs->put('sha256:' . hash('sha256', $bytes), $bytes); exit(0);
} catch (\OnionServiceDiscovery\Storage\StorageException $error) {
    if ($error->getMessage() === 'EXPORT_BUDGET_EXHAUSTED') { exit(2); } throw $error;
}
