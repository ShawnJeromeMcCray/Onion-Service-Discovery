<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
function exactRelay($socket,int $length):string{$value='';while(strlen($value)<$length){$part=fread($socket,$length-strlen($value));if($part===false||$part==='')throw new RuntimeException('short fixture');$value.=$part;}return $value;}
/** Runs the relay worker against a controlled SOCKS listener and returns the relayed response. */
function relayThroughSocks(array $config,string $onion,string $body):array{
 $server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if($server===false)throw new RuntimeException('fixture unavailable');$port=(int)substr(strrchr(stream_socket_get_name($server,false),':'),1);
 $config['transport']['proxy_port']=$port;$config['transport']['timeout_ms']=1500;$path=sys_get_temp_dir().'/osd-relay-'.bin2hex(random_bytes(12)).'.json';file_put_contents($path,json_encode($config,JSON_THROW_ON_ERROR));
 try{
  $process=proc_open([PHP_BINARY,__DIR__.'/client-relay-worker.php',$path,'/onion-service-discovery/relay/'.$onion.'/node'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$socket=stream_socket_accept($server,5);if($socket===false)throw new RuntimeException('relay did not use proxy');stream_set_timeout($socket,3);
  $greeting=exactRelay($socket,2);exactRelay($socket,ord($greeting[1]));fwrite($socket,"\x05\x02");$auth=exactRelay($socket,2);exactRelay($socket,ord($auth[1]));exactRelay($socket,ord(exactRelay($socket,1)));fwrite($socket,"\x01\x00");if(exactRelay($socket,4)!=="\x05\x01\x00\x03"||exactRelay($socket,ord(exactRelay($socket,1)))!==$onion||exactRelay($socket,2)!=="\x00\x50")throw new RuntimeException('relay destination');fwrite($socket,"\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50");$request='';while(!str_contains($request,"\r\n\r\n")){$line=fgets($socket);if($line===false)throw new RuntimeException('request missing');$request.=$line;}if(!str_starts_with($request,"GET /onion-service-discovery/v1/node HTTP/1.1\r\n")||str_contains(strtolower($request),'cookie:'))throw new RuntimeException('unexpected relay request');
  fwrite($socket,"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);fclose($socket);$output=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($stderr);
  return json_decode($output,true,32,JSON_THROW_ON_ERROR);
 }finally{fclose($server);unlink($path);}
}
$project=dirname(__DIR__,2);$f=json_decode(file_get_contents($project.'/protocol/test-vectors/browser-dataset.json'),true);$onion=$f['sources'][0]['onion'];
$v=json_decode(file_get_contents($project.'/node-php/config/example.json'),true);$v['sources']=[$onion];
$body=json_encode($f['sources'][0]['node_descriptor'],JSON_THROW_ON_ERROR);$response=relayThroughSocks($v,$onion,$body);
if($response['status']!==200||$response['body']!==$body)throw new RuntimeException('relay altered public data');
// A Node the operator did not configure is relayed only after the explicit visitor_sources switch.
$visitor=$f['sources'][1]['onion'];$v['features']['visitor_sources']=true;
$visitorBody=json_encode($f['sources'][1]['node_descriptor'],JSON_THROW_ON_ERROR);$response=relayThroughSocks($v,$visitor,$visitorBody);
if($response['status']!==200||$response['body']!==$visitorBody)throw new RuntimeException('visitor-chosen Node relay');
echo "PASS: keyless generic relay uses hostname SOCKS, preserves public response bytes and relays visitor-chosen Nodes only when enabled\n";
