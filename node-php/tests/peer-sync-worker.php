<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Node\PeerSync;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Transport\TorTransport;
$limits = isset($argv[5]) ? new \OnionServiceDiscovery\Storage\StorageLimits(maxRecords:(int)$argv[5]) : null;
// A directory is a filesystem store, anything else a SQLite database.
$sync = new PeerSync(is_dir($argv[1]) ? new \OnionServiceDiscovery\Storage\FilesystemStore($argv[1],$limits) : new SQLiteStore($argv[1],$limits), new RecordVerifier(dirname(__DIR__,2) . '/protocol/schemas'), new TorTransport('127.0.0.1',(int)$argv[3],1500),true);
echo json_encode($sync->step(OnionAddress::parse($argv[2]),(int)$argv[4]),JSON_THROW_ON_ERROR);
