<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Node\PeerSync;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Transport\TorTransport;
$sync = new PeerSync(new SQLiteStore($argv[1]), new RecordVerifier(dirname(__DIR__,2) . '/protocol/schemas'), new TorTransport('127.0.0.1',(int)$argv[3],1500));
echo json_encode($sync->step(OnionAddress::parse($argv[2]),(int)$argv[4]),JSON_THROW_ON_ERROR);
