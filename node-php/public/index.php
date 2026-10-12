<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Http\PublicRequestBudget;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\PublicBlobStore;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $configPath = getenv('ONION_SERVICE_DISCOVERY_CONFIG');
    if ($configPath === false || $configPath === '') { throw new RuntimeException('Configuration unavailable'); }
    $config = NodeConfig::load($configPath);
    if ($config->role === 'client-host') { $response = ['status' => 404, 'headers' => [], 'body' => '{"error":"NOT_FOUND"}']; }
    elseif (!(new PublicRequestBudget($config->exportPath . '/.request-budget'))->admit(microtime(true))) { $response = ['status' => 429, 'headers' => ['Retry-After' => '1'], 'body' => '{"error":"REQUEST_BUDGET_EXHAUSTED"}']; }
    else {
        $api = new DataApi($config->store(), new PublicBlobStore($config->exportPath), new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas'), $config->features['mirror_directory'], $config->allowedClientOrigins);
        $response = $api->handle($method, $_SERVER['REQUEST_URI'] ?? '/', $_SERVER['HTTP_ORIGIN'] ?? null);
    }
} catch (Throwable) { $response = ['status' => 503, 'headers' => [], 'body' => '{"error":"NODE_UNAVAILABLE"}']; }
http_response_code($response['status']);
foreach (['Content-Type' => 'application/json; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'"] + $response['headers'] as $name => $value) { header($name . ': ' . $value); }
if ($method !== 'HEAD') { echo $response['body']; }
