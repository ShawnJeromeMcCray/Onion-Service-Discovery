<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Http\ClientHostRelay;
echo json_encode((new ClientHostRelay(NodeConfig::load($argv[1])))->handle('GET',$argv[2]),JSON_THROW_ON_ERROR);
