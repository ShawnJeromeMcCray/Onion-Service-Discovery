<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Storage\PublicBlobStore;
$project=dirname(__DIR__,2);$root=sys_get_temp_dir().'/osd-hint-http-'.bin2hex(random_bytes(12));mkdir($root,0700);
try {
 foreach(['filesystem','sqlite'] as $backend){foreach([false,true] as $enabled){
  $dir=$root.'/'.$backend.'-'.(int)$enabled;mkdir($dir,0700);
  $config=json_decode(file_get_contents(__DIR__.'/../config/node.example.json'),true);
  $config['storage']=['backend'=>$backend,'path'=>$dir.'/data'];$config['export_path']=$dir.'/exports';$config['node']['key_path']=$dir.'/node.key';
  $config['features']['discover_services']=true;$config['features']['accept_announcements']=$enabled;
  file_put_contents($dir.'/config.json',json_encode($config));chmod($dir.'/config.json',0600);
  $node=NodeConfig::load($dir.'/config.json');$store=$node->store(true);new PublicBlobStore($node->exportPath,true);
  $listener=stream_socket_server('tcp://127.0.0.1:0');$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
  $process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$project.'/node-php/public',$project.'/node-php/public/index.php'],[['pipe','r'],['file',$dir.'/server.log','a'],['file',$dir.'/server.log','a']],$pipes,null,['ONION_SERVICE_DISCOVERY_CONFIG'=>$dir.'/config.json']);fclose($pipes[0]);
  try{
   $deadline=microtime(true)+5;do{$socket=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.1);if($socket!==false){fclose($socket);break;}usleep(100000);}while(microtime(true)<$deadline);
   if($socket===false)throw new RuntimeException('HTTP fixture startup failed');
   $body=json_encode(['protocol_version'=>1,'onion'=>$config['node']['onion']]);
   foreach([['POST','application/json',$body,$enabled?202:404],['GET','application/json','',$enabled?405:404],['POST','text/plain',$body,$enabled?415:404],['POST','application/json',str_repeat('x',257),$enabled?413:404]] as [$method,$type,$input,$expected]){
    $socket=stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,2);stream_set_timeout($socket,3);
    fwrite($socket,$method." /onion-service-discovery/v1/candidates/announce HTTP/1.1\r\nHost: localhost\r\nContent-Type: ".$type."\r\nContent-Length: ".strlen($input)."\r\nConnection: close\r\n\r\n".$input);
    $reply=stream_get_contents($socket,4096);fclose($socket);
    if(!str_starts_with($reply,'HTTP/1.1 '.$expected.' '))throw new RuntimeException('production entrypoint mismatch: '.$backend.' enabled='.(int)$enabled.' method='.$method.' expected='.$expected.' got='.substr($reply,0,32));
   }
   $state=$store->snapshot();
   if(count($state['tables']['candidates'])!==($enabled?1:0)||$state['records']!==[]||$state['tables']['local_observations']!==[])throw new RuntimeException('HTTP receiver elevated hint or bypassed opt-in');
   if(is_file($dir.'/node.key'))throw new RuntimeException('HTTP receiver created signer');
  }finally{proc_terminate($process);proc_close($process);unset($store,$node);}
 }}
 echo "PASS: production HTTP announcement route, explicit opt-in, body/media/method limits and no signing/observation on both backends\n";
}finally{
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
