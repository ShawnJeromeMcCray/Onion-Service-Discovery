<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Http\ClientAssets;
use OnionServiceDiscovery\Http\ClientHostRelay;
$project=dirname(__DIR__,2);$fixture=json_decode(file_get_contents($project.'/protocol/test-vectors/browser-dataset.json'),true);$v=json_decode(file_get_contents($project.'/node-php/config/example.json'),true);$v['sources']=array_column($fixture['sources'],'onion');
$path=sys_get_temp_dir().'/osd-client-config-'.bin2hex(random_bytes(12)).'.json';file_put_contents($path,json_encode($v,JSON_THROW_ON_ERROR));$config=NodeConfig::load($path);$assets=new ClientAssets($config,$project.'/web-local-search-client/dist');
$response=$assets->handle('GET','/onion-service-discovery/');if($response===null||$response['status']!==200||!str_contains($response['body'],'Onion Service Discovery')||!str_contains($response['headers']['Content-Security-Policy'],"connect-src 'self';"))throw new RuntimeException('Client Host assets');
$public=json_decode($assets->handle('GET','/onion-service-discovery/config.json')['body'],true);if($public['delivery_profile']!=='client-host-proxy'||$public['allowed_sources']!==$v['sources']||isset($public['node'])||isset($public['storage']))throw new RuntimeException('configuration disclosure');
if($assets->handle('GET','/onion-service-discovery/../../node.key')!==null||$assets->handle('POST','/onion-service-discovery/')['status']!==405)throw new RuntimeException('asset route boundary');
$relay=new ClientHostRelay($config);$onion=$v['sources'][0];
foreach(['/onion-service-discovery/relay/'.$onion.'/node?query=sensitive','/onion-service-discovery/relay/'.$onion.'/services/'.$onion,'/onion-service-discovery/relay/127.0.0.1/node','/onion-service-discovery/relay/'.$onion.'/chunks/../../file'] as $target){$response=$relay->handle('GET',$target);if($response['status']!==404||str_contains($response['body'],'sensitive'))throw new RuntimeException('generic relay boundary');}
if($relay->handle('POST','/onion-service-discovery/relay/'.$onion.'/node')['status']!==405)throw new RuntimeException('relay mutation method');
unlink($path);echo "PASS: keyless Client Host, closed assets and query-independent relay boundaries\n";
