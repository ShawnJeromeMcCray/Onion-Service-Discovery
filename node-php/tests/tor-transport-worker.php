<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Transport\TorTransport;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TransportException;
try {
    $body = (new TorTransport('127.0.0.1', (int) $argv[1], 1500))->get(OnionAddress::parse($argv[2]), ProtocolEndpoint::manifest());
    echo json_encode(['body' => $body], JSON_THROW_ON_ERROR);
} catch (TransportException $error) { echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR); }
