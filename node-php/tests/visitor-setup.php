<?php
declare(strict_types=1);
// Fresh-copy walkthrough, synthetic public address, no Tor or outbound requests.
$project=dirname(__DIR__,2);
$root=sys_get_temp_dir().'/osd-visitor-'.bin2hex(random_bytes(12));mkdir($root,0700);
function copyVisitorTree(string $from,string $to):void {
    mkdir($to,0700,true);
    foreach(new DirectoryIterator($from) as $entry){
        if($entry->isDot())continue;
        if($entry->isLink())throw new RuntimeException('unexpected source link');
        $target=$to.'/'.$entry->getFilename();
        if($entry->isDir())copyVisitorTree($entry->getPathname(),$target);
        elseif(!copy($entry->getPathname(),$target))throw new RuntimeException('copy failed');
    }
}
function visitorCommand(array $arguments):array {
    global $app;
    $process=proc_open([PHP_BINARY,$app.'/node-php/bin/onion-service-discovery',...$arguments],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('CLI unavailable');
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    if($exit!==0||$errors!=='')throw new RuntimeException('visitor command failed: '.$arguments[0].' '.$output.$errors);
    return json_decode($output,true,32,JSON_THROW_ON_ERROR);
}
function visitorWrite(string $path,array $value):void {file_put_contents($path,json_encode($value,JSON_THROW_ON_ERROR));chmod($path,0600);}
try {
    $app=$root.'/app';mkdir($app,0700);mkdir($app.'/node-php',0700);
    foreach(['bin','src','config','public'] as $directory)copyVisitorTree($project.'/node-php/'.$directory,$app.'/node-php/'.$directory);
    copyVisitorTree($project.'/protocol',$app.'/protocol');
    // Reuse only provisioned public PHP dependencies; operational state is fresh.
    symlink($project.'/node-php/vendor',$app.'/node-php/vendor');
    foreach(['offline','publisher','node','client'] as $directory)mkdir($root.'/'.$directory,0700);
    file_put_contents($root.'/offline/passphrase','TEST ONLY visitor setup passphrase');chmod($root.'/offline/passphrase',0600);
    $onion=json_decode(file_get_contents($project.'/node-php/config/node.example.json'),true)['node']['onion'];
    $key=visitorCommand(['key:create','service',$root.'/offline/service.key','--passphrase-file',$root.'/offline/passphrase']);
    $metadata=['service'=>['name'=>'Synthetic visitor service','description'=>'Fresh-install walkthrough fixture','languages'=>['en'],'categories'=>[]],'discovery'=>['publish_service'=>true,'mirror_directory'=>false,'discover_services'=>false,'sync_with_peers'=>false]];
    visitorWrite($root.'/offline/metadata.json',$metadata);
    $draft=visitorCommand(['manifest:init',$onion,$key['public_key'],$root.'/offline/metadata.json']);visitorWrite($root.'/offline/draft.json',$draft);
    $manifest=visitorCommand(['record:sign','service',$root.'/offline/service.key',$root.'/offline/draft.json','--passphrase-file',$root.'/offline/passphrase']);visitorWrite($root.'/publisher/manifest.json',$manifest);
    visitorCommand(['manifest:verify',$root.'/publisher/manifest.json']);
    file_put_contents($root.'/publisher/hostname',$onion."\n");
    visitorCommand(['init',$root.'/publisher/config.json',$onion,'--publish']);
    $installed=visitorCommand(['manifest:install',$root.'/publisher/manifest.json',$root.'/publisher/hostname',$app.'/node-php/public']);
    $status=visitorCommand(['publisher:status',$root.'/publisher/config.json']);
    if($installed['status']!=='INSTALLED'||$installed['history']!=='CONNECTED'||$status['overall']!=='READY_LOCALLY'||$status['manifest']['revision']!==1||$status['sampled_discoverability']!=='UNCONFIRMED')throw new RuntimeException('publisher acceptance failed');
    // PCP-004: the front controller serves the history bundle that manifest:install maintains.
    $listener=stream_socket_server('tcp://127.0.0.1:0');$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$app.'/node-php/public',$app.'/node-php/public/index.php'],[['pipe','r'],['file',$root.'/server.log','a'],['file',$root.'/server.log','a']],$serverPipes,null,['ONION_SERVICE_DISCOVERY_CONFIG'=>$root.'/publisher/config.json']);fclose($serverPipes[0]);
    try{
        $deadline=microtime(true)+5;do{$probe=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.1);if($probe!==false){fclose($probe);break;}usleep(100000);}while(microtime(true)<$deadline);
        $socket=stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,2);stream_set_timeout($socket,5);fwrite($socket,"GET /.well-known/onion-service-discovery-history.json HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");$reply=(string)stream_get_contents($socket);fclose($socket);
        $bundle=json_decode(substr($reply,strpos($reply,"\r\n\r\n")+4),true,64,JSON_THROW_ON_ERROR);
        if(!str_starts_with($reply,'HTTP/1.1 200')||$bundle['bundle_type']!=='service_history'||count($bundle['records'])!==1||$bundle['records'][0]['signature']!==$manifest['signature'])throw new RuntimeException('history bundle not served');
    }finally{proc_terminate($server);proc_close($server);}
    $config=json_decode(file_get_contents($app.'/node-php/config/node.example.json'),true);
    $config['node']['key_path']=$root.'/node/node.key';$config['storage']['path']=$root.'/node/data';$config['export_path']=$root.'/node/exports';$config['features']['mirror_directory']=true;
    visitorWrite($root.'/node/config.json',$config);
    visitorCommand(['config:validate',$root.'/node/config.json']);
    visitorCommand(['key:create','node',$root.'/node/node.key','--passphrase-file',$root.'/offline/passphrase']);
    visitorCommand(['node:init',$root.'/node/config.json','--passphrase-file',$root.'/offline/passphrase']);
    $snapshot=visitorCommand(['node:snapshot',$root.'/node/config.json','--passphrase-file',$root.'/offline/passphrase']);
    if($snapshot['status']!=='PUBLISHED')throw new RuntimeException('Node acceptance failed');
    $config=json_decode(file_get_contents($app.'/node-php/config/example.json'),true);
    $config['storage']['path']=$root.'/client/data';$config['export_path']=$root.'/client/exports';visitorWrite($root.'/client/config.json',$config);
    visitorCommand(['config:validate',$root.'/client/config.json']);$client=visitorCommand(['client:init',$root.'/client/config.json']);
    if($client['status']!=='INITIALIZED'||$client['signing_keys_required']!==false||file_exists($root.'/client/data'))throw new RuntimeException('Client Host acceptance failed');
    echo "PASS: fresh-copy Publisher signing/install/local status, Node init/snapshot and keyless Client Host walkthroughs; no Tor/public discoverability claim\n";
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $file){if($file->isLink()||!$file->isDir())unlink($file->getPathname());else rmdir($file->getPathname());}
    rmdir($root);
}
