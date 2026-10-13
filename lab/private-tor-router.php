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
$api = new OnionServiceDiscovery\Http\DataApi(
    new OnionServiceDiscovery\Storage\FilesystemStore($root . '/source', readOnly: true),
    new OnionServiceDiscovery\Storage\PublicBlobStore($root . '/exports'),
    new OnionServiceDiscovery\Protocol\RecordVerifier(dirname(__DIR__) . '/protocol/schemas'), true);
$response = $api->handle($_SERVER['REQUEST_METHOD'], $target);
http_response_code($response['status']);
foreach ($response['headers'] as $name => $value) { header($name . ': ' . $value); }
echo $response['body'];
