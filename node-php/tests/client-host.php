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
// Expected Node keys the host vouches for reach the browser only when configured.
if(isset($public['expected_node_keys']))throw new RuntimeException('expected keys invented');
$keyed=$v;$keyed['source_node_keys']=[$v['sources'][0]=>$fixture['sources'][0]['node_descriptor']['node_identity_public_key']];file_put_contents($path,json_encode($keyed,JSON_THROW_ON_ERROR));
if(json_decode((new ClientAssets(NodeConfig::load($path),$project.'/web-local-search-client/dist'))->handle('GET','/onion-service-discovery/config.json')['body'],true)['expected_node_keys']!==$keyed['source_node_keys'])throw new RuntimeException('expected keys not handed to the client');
file_put_contents($path,json_encode($v,JSON_THROW_ON_ERROR));
$relay=new ClientHostRelay($config);$onion=$v['sources'][0];
foreach(['/onion-service-discovery/relay/'.$onion.'/node?query=sensitive','/onion-service-discovery/relay/'.$onion.'/services/'.$onion,'/onion-service-discovery/relay/127.0.0.1/node','/onion-service-discovery/relay/'.$onion.'/chunks/../../file'] as $target){$response=$relay->handle('GET',$target);if($response['status']!==404||str_contains($response['body'],'sensitive'))throw new RuntimeException('generic relay boundary');}
if($relay->handle('POST','/onion-service-discovery/relay/'.$onion.'/node')['status']!==405)throw new RuntimeException('relay mutation method');
// Visitor-chosen Nodes: closed by default, explicit opt-in, canonical checksum-valid v3 only.
if($public['visitor_sources']!==false)throw new RuntimeException('visitor sources enabled by default');
$stranger='pg6mmjiyjmcrsslvykfwnntlaru7p5svn6y2ymmju6nubxndf4pscryd.onion';
if($relay->handle('GET','/onion-service-discovery/relay/'.$stranger.'/node')['status']!==404||!$relay->isVisitorSource('/onion-service-discovery/relay/'.$stranger.'/node')||$relay->isVisitorSource('/onion-service-discovery/relay/'.$onion.'/node'))throw new RuntimeException('visitor relay closed by default');
$v['features']['visitor_sources']=true;file_put_contents($path,json_encode($v,JSON_THROW_ON_ERROR));$open=NodeConfig::load($path);
if(json_decode((new ClientAssets($open,$project.'/web-local-search-client/dist'))->handle('GET','/onion-service-discovery/config.json')['body'],true)['visitor_sources']!==true)throw new RuntimeException('visitor capability not announced');
$openRelay=new ClientHostRelay($open);$badChecksum=substr($stranger,0,54).($stranger[54]==='a'?'b':'a').'d.onion';
foreach(['/onion-service-discovery/relay/'.$badChecksum.'/node','/onion-service-discovery/relay/'.strtoupper($stranger).'/node','/onion-service-discovery/relay/'.$stranger.'/services/'.$stranger,'/onion-service-discovery/relay/'.$stranger.'/node?query=sensitive'] as $target){$response=$openRelay->handle('GET',$target);if($response['status']!==404||str_contains($response['body'],'sensitive'))throw new RuntimeException('visitor relay boundary '.$target);}
$health=\OnionServiceDiscovery\Configuration\Diagnostics::inspect($open,new \OnionServiceDiscovery\Protocol\RecordVerifier($project.'/protocol/schemas'));require_once __DIR__.'/health-report.inc.php';assertHealthReport($health);
if(($health['security_check_summary']['visitor_source_relay']??null)!=='WARN'||!in_array('VISITOR_SOURCE_RELAY_ENABLED_OUTBOUND_TO_VISITOR_CHOSEN_NODES',$health['warnings'],true))throw new RuntimeException('visitor relay not visible in doctor');
unlink($path);echo "PASS: keyless Client Host, closed assets, query-independent relay boundaries and opt-in visitor-chosen Nodes\n";
