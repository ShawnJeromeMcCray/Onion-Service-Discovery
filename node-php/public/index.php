<?php
declare(strict_types=1);
ini_set('display_errors', '0');
if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { echo '{"error":"RUNTIME_DEPENDENCIES_MISSING"}'; }
    exit;
}
require dirname(__DIR__) . '/vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Http\ClientAssets;
use OnionServiceDiscovery\Http\ClientHostRelay;
use OnionServiceDiscovery\Http\PublicRequestBudget;
use OnionServiceDiscovery\Http\StatusPage;
use OnionServiceDiscovery\Configuration\PublisherStatus;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Storage\PublicBlobStore;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$target = $_SERVER['REQUEST_URI'] ?? '/';
try {
    $configPath = getenv('ONION_SERVICE_DISCOVERY_CONFIG');
    if ($configPath === false || $configPath === '') { throw new RuntimeException('Configuration unavailable'); }
    $config = NodeConfig::load($configPath);
    $asset = (new ClientAssets($config, dirname(__DIR__, 2) . '/web-local-search-client/dist'))->handle($method, $target);
    if ($target === '/onion-service-discovery/status/') {
        $response = StatusPage::render(PublisherStatus::inspect($config, new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas'), __DIR__ . '/.well-known/onion-service-discovery.json'), $method);
    }
    elseif ($asset !== null) { $response = $asset; }
    elseif (str_starts_with($target, '/onion-service-discovery/relay/')) {
        if (!(new PublicRequestBudget($config->exportPath . '/.relay-budget'))->admit(microtime(true))) { $response = ['status' => 429, 'headers' => ['Retry-After' => '1'], 'body' => '{"error":"REQUEST_BUDGET_EXHAUSTED"}']; }
        else { $response = (new ClientHostRelay($config))->handle($method, $target); }
    }
    elseif ($target === '/.well-known/onion-service-discovery.json' && $config->nodeOnion !== null && in_array($method,['GET','HEAD'],true)) {
        $path=__DIR__.'/.well-known/onion-service-discovery.json';
        if(is_link($path)||!is_file($path)||filesize($path)>16384)throw new RuntimeException('Public manifest unavailable');
        $wire=file_get_contents($path,false,null,0,16385);if($wire===false||strlen($wire)>16384)throw new RuntimeException('Public manifest unavailable');
        $verifier=new RecordVerifier(dirname(__DIR__,2).'/protocol/schemas');$record=$verifier->verifyWire($wire,OnionAddress::parse($config->nodeOnion));
        if($record->type!=='service_manifest')throw new RuntimeException('Public manifest invalid');
        if(!$config->features['publish_service'] && $record->document()->discovery->publish_service)throw new RuntimeException('Publishing disabled');
        $descriptor = null;
        if ($config->role !== 'publisher') { $store=$config->store(readOnly: true);$descriptor=$store->snapshot()['tables']['node']['key:descriptor']??null; }
        if($descriptor!==null){$nodeWire=$store->readRecord($descriptor['hash']);if($nodeWire===null)throw new RuntimeException('Node descriptor unavailable');$node=$verifier->verifyWire($nodeWire)->document();if($node->node_identity_public_key===$record->document()->service_identity_public_key)throw new RuntimeException('Node and Service keys must differ');}
        $response=['status'=>200,'headers'=>['Content-Length'=>(string)strlen($record->canonicalWire)],'body'=>$method==='HEAD'?'':$record->canonicalWire];
    }
    elseif (in_array($config->role, ['client-host','publisher'], true)) { $response = ['status' => 404, 'headers' => [], 'body' => '{"error":"NOT_FOUND"}']; }
    elseif (!(new PublicRequestBudget($config->exportPath . '/.request-budget'))->admit(microtime(true))) { $response = ['status' => 429, 'headers' => ['Retry-After' => '1'], 'body' => '{"error":"REQUEST_BUDGET_EXHAUSTED"}']; }
    else {
        $api = new DataApi($config->store(readOnly: true), new PublicBlobStore($config->exportPath), new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas'), $config->features['mirror_directory'], $config->allowedClientOrigins);
        $response = $api->handle($method, $target, $_SERVER['HTTP_ORIGIN'] ?? null);
    }

} catch (Throwable) {
    $response = $target === '/onion-service-discovery/status/'
        ? StatusPage::render(['overall' => 'NOT_CONFIGURED', 'next_action' => 'Configuration is missing or invalid. Run config:validate locally; no private configuration details are exposed here.'], $method)
        : ['status' => 503, 'headers' => [], 'body' => '{"error":"NODE_UNAVAILABLE"}'];
}
http_response_code($response['status']);
foreach (array_merge(['Content-Type' => 'application/json; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'"], $response['headers']) as $name => $value) { header($name . ': ' . $value); }
if ($method !== 'HEAD') { echo $response['body']; }
