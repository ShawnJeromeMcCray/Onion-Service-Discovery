<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Http\ClientAssets;
use OnionServiceDiscovery\Http\ClientHostRelay;
use OnionServiceDiscovery\Http\PublicRequestBudget;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Storage\PublicBlobStore;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $configPath = getenv('ONION_SERVICE_DISCOVERY_CONFIG');
    if ($configPath === false || $configPath === '') { throw new RuntimeException('Configuration unavailable'); }
    $config = NodeConfig::load($configPath);
    $target = $_SERVER['REQUEST_URI'] ?? '/';
    $asset = (new ClientAssets($config, dirname(__DIR__, 2) . '/web-local-search-client/dist'))->handle($method, $target);
    if ($asset !== null) { $response = $asset; }
    elseif (str_starts_with($target, '/onion-service-discovery/relay/')) {
        if (!(new PublicRequestBudget($config->exportPath . '/.relay-budget'))->admit(microtime(true))) { $response = ['status' => 429, 'headers' => ['Retry-After' => '1'], 'body' => '{"error":"REQUEST_BUDGET_EXHAUSTED"}']; }
        else { $response = (new ClientHostRelay($config))->handle($method, $target); }
    }
    elseif ($target === '/.well-known/onion-service-discovery.json' && $config->features['publish_service'] && $config->nodeOnion !== null && in_array($method,['GET','HEAD'],true)) {
        $path=__DIR__.'/.well-known/onion-service-discovery.json';
        if(is_link($path)||!is_file($path)||filesize($path)>16384)throw new RuntimeException('Public manifest unavailable');
        $wire=file_get_contents($path);if($wire===false)throw new RuntimeException('Public manifest unavailable');
        $verifier=new RecordVerifier(dirname(__DIR__,2).'/protocol/schemas');$record=$verifier->verifyWire($wire,OnionAddress::parse($config->nodeOnion));
        if($record->type!=='service_manifest')throw new RuntimeException('Public manifest invalid');
        $store=$config->store(readOnly: true);$descriptor=$store->snapshot()['tables']['node']['key:descriptor']??null;
        if($descriptor!==null){$nodeWire=$store->readRecord($descriptor['hash']);if($nodeWire===null)throw new RuntimeException('Node descriptor unavailable');$node=$verifier->verifyWire($nodeWire)->document();if($node->node_identity_public_key===$record->document()->service_identity_public_key)throw new RuntimeException('Node and Service keys must differ');}
        $response=['status'=>200,'headers'=>['Content-Length'=>(string)strlen($record->canonicalWire)],'body'=>$method==='HEAD'?'':$record->canonicalWire];
    }
    elseif ($config->role === 'client-host') { $response = ['status' => 404, 'headers' => [], 'body' => '{"error":"NOT_FOUND"}']; }
    elseif (!(new PublicRequestBudget($config->exportPath . '/.request-budget'))->admit(microtime(true))) { $response = ['status' => 429, 'headers' => ['Retry-After' => '1'], 'body' => '{"error":"REQUEST_BUDGET_EXHAUSTED"}']; }
    else {
        $api = new DataApi($config->store(readOnly: true), new PublicBlobStore($config->exportPath), new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas'), $config->features['mirror_directory'], $config->allowedClientOrigins);
        $response = $api->handle($method, $target, $_SERVER['HTTP_ORIGIN'] ?? null);
    }

} catch (Throwable) { $response = ['status' => 503, 'headers' => [], 'body' => '{"error":"NODE_UNAVAILABLE"}']; }
http_response_code($response['status']);
foreach (array_merge(['Content-Type' => 'application/json; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'"], $response['headers']) as $name => $value) { header($name . ': ' . $value); }
if ($method !== 'HEAD') { echo $response['body']; }
