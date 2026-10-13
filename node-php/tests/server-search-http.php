<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$project=dirname(__DIR__,2);$verifier=new RecordVerifier($project.'/protocol/schemas');
$dataset=json_decode(file_get_contents($project.'/protocol/test-vectors/browser-dataset.json'));
$records=array_map($verifier->verify(...),json_decode(base64_decode($dataset->sources[0]->chunk_base64)));
$root=sys_get_temp_dir().'/osd-search-http-'.bin2hex(random_bytes(12));mkdir($root,0700);
try{
 $signer=ProtectedFileSigner::create($root.'/node.key','node','TEST ONLY No-JS HTTP Node passphrase',$verifier);
 foreach(['filesystem','sqlite'] as $backend){foreach([false,true] as $enabled){
  $dir=$root.'/'.$backend.'-'.(int)$enabled;mkdir($dir,0700);
  $config=json_decode(file_get_contents(__DIR__.'/../config/combined.example.json'),true);
  $config['storage']=['backend'=>$backend,'path'=>$dir.'/data'];$config['export_path']=$dir.'/exports';$config['node']['key_path']=$root.'/node.key';
  $config['features']['server_search']=$enabled;$config['features']['mirror_directory']=true;
  file_put_contents($dir.'/config.json',json_encode($config));chmod($dir.'/config.json',0600);
  $node=NodeConfig::load($dir.'/config.json');NodeInitializer::initialize($node,$signer,$verifier);$store=$node->store();
  $store->transaction(static function(StorageTransaction $tx)use($records):void{foreach($records as $record)$tx->putRecord($record);});
  $blobs=new PublicBlobStore($dir.'/exports');(new SnapshotProducer($store,$blobs,$verifier,$signer,$enabled))->publish(time());
  $before=$store->snapshot();
  $listener=stream_socket_server('tcp://127.0.0.1:0');$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
  $process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$project.'/node-php/public',$project.'/node-php/public/index.php'],[['pipe','r'],['file',$dir.'/server.log','a'],['file',$dir.'/server.log','a']],$pipes,null,['ONION_SERVICE_DISCOVERY_CONFIG'=>$dir.'/config.json']);fclose($pipes[0]);
  try{
   $deadline=microtime(true)+5;do{$socket=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.1);if($socket!==false){fclose($socket);break;}usleep(100000);}while(microtime(true)<$deadline);
   if($socket===false)throw new RuntimeException('search HTTP startup failed');
   $request=static function(string $method,string $path,string $body='',string $type='application/x-www-form-urlencoded')use($port):array{
    $socket=stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,2);stream_set_timeout($socket,5);
    fwrite($socket,$method.' '.$path." HTTP/1.1\r\nHost: localhost\r\nContent-Type: ".$type."\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
    $wire=stream_get_contents($socket,262145);fclose($socket);[$headers,$content]=array_pad(explode("\r\n\r\n",$wire,2),2,'');
    if(!preg_match('/\AHTTP\/1\.1 ([0-9]{3}) /',$headers,$match))throw new RuntimeException('invalid HTTP response');return [(int)$match[1],$headers,$content];
   };
   [$status,$headers,$body]=$request('GET','/onion-service-discovery/search/');
   if($status!==($enabled?200:404)||!str_contains($body,'Server-side search: your search terms are sent to this Onion Service Discovery Node')||str_contains(strtolower($headers),'set-cookie:'))throw new RuntimeException('privacy/enablement/cookie boundary');
   if($enabled&&!str_contains($body,'method="post" action="/onion-service-discovery/search/server"'))throw new RuntimeException('form puts query in URL');
   [$status,,$body]=$request('GET','/onion-service-discovery/');
   if($status!==200||($enabled&&!str_contains($body,'Use server search: your query is sent to this Node'))||(!$enabled&&!str_contains($body,'Server search is not enabled on this serving host')))throw new RuntimeException('No-JS client recovery link');
   if(!$enabled){if($request('POST','/onion-service-discovery/search/server','query=library')[0]!==404)throw new RuntimeException('server search bypassed default-off flag');}
   else{
    [$status,$headers,$body]=$request('POST','/onion-service-discovery/search/server','query=library&language=en&category=books&adult=show');
    if($status!==200||!str_contains($body,'1 matching identities')||!str_contains($body,'Synthetic Library')||!str_contains($headers,"form-action 'self'")||!str_contains(strtolower($headers),'cache-control: no-store'))throw new RuntimeException('POST search result/CSP/cache');
    $probe='sensitive-server-search-http-probe-723';[$status,,$body]=$request('POST','/onion-service-discovery/search/server','query='.$probe);
    if($status!==200||str_contains($body,$probe)||!str_contains($body,'0 matching identities'))throw new RuntimeException('query echo');
    foreach([
     ['GET','/onion-service-discovery/search/server','',405,'application/x-www-form-urlencoded'],
     ['POST','/onion-service-discovery/search/server','query=a&query=b',400,'application/x-www-form-urlencoded'],
     ['POST','/onion-service-discovery/search/server',str_repeat('x',8193),413,'application/x-www-form-urlencoded'],
     ['POST','/onion-service-discovery/search/server','{}',415,'application/json'],
     ['GET','/onion-service-discovery/search/server?query=a','',400,'application/x-www-form-urlencoded']
    ] as [$method,$target,$input,$expected,$type]){if($request($method,$target,$input,$type)[0]!==$expected)throw new RuntimeException('HTTP request guard mismatch');}
    [$status,,$body]=$request('HEAD','/onion-service-discovery/search/');if($status!==200||$body!=='')throw new RuntimeException('HEAD result leaked body');
    if($request('GET','/onion-service-discovery/search/server.css')[0]!==200)throw new RuntimeException('local accessible stylesheet');
    if(str_contains(file_get_contents($dir.'/server.log'),$probe))throw new RuntimeException('query logged by reference application harness');
   }
   if($store->snapshot()!==$before)throw new RuntimeException('search touched storage/queue/observation state');
  }finally{proc_terminate($process);proc_close($process);unset($store,$node,$blobs);}
 }}
 echo "PASS: actual No-JS public HTTP profile, disclosure, opt-in, POST-only query, guards, no query echo/log or operational mutation on both backends\n";
}finally{
 unset($store,$node,$blobs,$signer);
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
