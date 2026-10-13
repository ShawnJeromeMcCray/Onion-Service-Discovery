<?php
declare(strict_types=1);
// Only used by the isolated private Tor lab; no deployment entrypoint.
require dirname(__DIR__) . '/node-php/vendor/autoload.php';
$root = getenv('OSD_PRIVATE_TOR_LAB_ROOT');
if (!is_string($root) || $root === '') { http_response_code(503); exit; }
$target = $_SERVER['REQUEST_URI'] ?? '';
if ($target === '/.well-known/onion-service-discovery.json') {
    $wire = file_get_contents($root . '/manifest.json');
    header('Content-Type: application/json'); header('Content-Length: ' . strlen($wire)); echo $wire; exit;
}
putenv('ONION_SERVICE_DISCOVERY_CONFIG=' . $root . '/node.json');
require dirname(__DIR__) . '/node-php/public/index.php';
