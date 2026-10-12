<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Node\DirectVerifier;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Transport\TorTransport;
$verifier = new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas');
$signer = ProtectedFileSigner::open($argv[1] . '/node.key', 'node', 'TEST ONLY direct verifier passphrase', $verifier);
$worker = new DirectVerifier(new SQLiteStore($argv[1] . '/data.sqlite'), $verifier, new TorTransport('127.0.0.1', (int) $argv[2], 2000), $signer);
echo json_encode($worker->runOne(1700000000), JSON_THROW_ON_ERROR);
