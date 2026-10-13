<?php
declare(strict_types=1);
$project = dirname(__DIR__);
require $project . '/node-php/vendor/autoload.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Transport\TorTransport;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TransportException;
// A real SOCKS daemon is permitted here only with no external IPv4 route.
if (!is_readable('/proc/net/route')) { throw new RuntimeException('isolated Linux network namespace required'); }
foreach (array_slice(file('/proc/net/route', FILE_IGNORE_NEW_LINES), 1) as $route) {
    $fields = preg_split('/\s+/', trim($route));
    if (($fields[1] ?? null) === '00000000') { throw new RuntimeException('external default route present; use Docker network none'); }
}
$root=sys_get_temp_dir().'/onion-service-discovery-tor-probe-'.bin2hex(random_bytes(8));mkdir($root,0700);
$listener=stream_socket_server('tcp://127.0.0.1:0');$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
$config=$root.'/torrc';file_put_contents($config,"DataDirectory $root/data\nSocksPort 127.0.0.1:$port IsolateSOCKSAuth\nControlSocket $root/control.sock\nCookieAuthentication 0\nDisableNetwork 0\nClientOnly 1\nClientUseIPv6 0\nUseDefaultFallbackDirs 0\nDirAuthority synthetic orport=19001 v3ident=1111111111111111111111111111111111111111 127.0.0.1:19002 2222222222222222222222222222222222222222\nAvoidDiskWrites 1\nLog notice file $root/tor.log\n");chmod($config,0600);
$torExecutable=$argv[1]??'/usr/bin/tor';
$tor=isset($argv[2])?['/usr/bin/env','LD_LIBRARY_PATH='.$argv[2],$torExecutable]:[$torExecutable];
$verification=proc_open([...$tor,'-f',$config,'--verify-config'],[['pipe','r'],['pipe','w'],['pipe','w']],$vp);fclose($vp[0]);$out=stream_get_contents($vp[1]);$err=stream_get_contents($vp[2]);fclose($vp[1]);fclose($vp[2]);if(proc_close($verification)!==0)throw new RuntimeException('Tor configuration rejected');
$process=proc_open([...$tor,'-f',$config],[['pipe','r'],['file',$root.'/startup.log','a'],['file',$root.'/startup.log','a']],$pipes);fclose($pipes[0]);
try{
 $control=false;$deadline=microtime(true)+10;
 while(microtime(true)<$deadline){$control=@stream_socket_client('unix://'.$root.'/control.sock',$errno,$message,0.2);if($control!==false)break;usleep(100000);}
 if($control===false)throw new RuntimeException('Tor daemon unavailable');stream_set_timeout($control,2);
 fwrite($control,"AUTHENTICATE\r\nGETINFO version\r\nGETCONF DisableNetwork\r\nGETCONF DirAuthority\r\nGETCONF UseDefaultFallbackDirs\r\nSETEVENTS STREAM\r\n");$controlReply='';for($index=0;$index<6;++$index){while(($line=fgets($control))!==false){$controlReply.=$line;if(str_starts_with($line,'250 '))break;}}
 if(!str_contains($controlReply,'127.0.0.1:19002')||!str_contains($controlReply,'250 UseDefaultFallbackDirs=0'))throw new RuntimeException('isolated authority configuration not confirmed');
 if(!preg_match('/250[- ]version=([^\r\n]+)/',$controlReply,$version)||!str_contains($controlReply,'250 DisableNetwork=0'))throw new RuntimeException('Tor offline state not confirmed');
 $cases=json_decode(file_get_contents($project.'/protocol/test-vectors/identity-sequences.json'),true,64,JSON_THROW_ON_ERROR);$onion=$cases['cases'][0]['records'][0]['onion'];
 $trap=stream_socket_server('tcp://127.0.0.1:0');$trapPort=(int)substr(strrchr(stream_socket_get_name($trap,false),':'),1);putenv('NO_PROXY=*');putenv('HTTP_PROXY=http://127.0.0.1:'.$trapPort);
 $error=null;$started=microtime(true);try{(new TorTransport('127.0.0.1',$port,1000))->get(OnionAddress::parse($onion),ProtocolEndpoint::manifest());}catch(TransportException $failure){$error=$failure->getMessage();}
 if($error!=='TOR_REQUEST_FAILED')throw new RuntimeException('Real Tor unavailable request not normalized');
 $v=json_decode(file_get_contents($project.'/node-php/config/node.example.json'),true,32,JSON_THROW_ON_ERROR);$v['storage']['path']=$root.'/store';$v['export_path']=$root.'/exports';$v['node']['key_path']=$root.'/node.key';$v['transport']['proxy_port']=$port;$v['transport']['timeout_ms']=1000;
 file_put_contents($root.'/node.json',json_encode($v));chmod($root.'/node.json',0600);
 $cli=proc_open([PHP_BINARY,$project.'/node-php/bin/onion-service-discovery','service:verify',$root.'/node.json',$onion,'--json','--no-interaction'],[['pipe','r'],['pipe','w'],['pipe','w']],$cp);fclose($cp[0]);$out=stream_get_contents($cp[1]);$err=stream_get_contents($cp[2]);fclose($cp[1]);fclose($cp[2]);$exit=proc_close($cli);
 if($exit!==5||$err!==''||json_decode($out,true)!==['status'=>'FAIL','error'=>'TOR_REQUEST_FAILED'])throw new RuntimeException('CLI real Tor failure contract invalid');
 if(is_dir($root.'/store')||is_dir($root.'/exports')||is_file($root.'/node.key'))throw new RuntimeException('Read-only probe created application state');
 $events='';stream_set_blocking($control,false);$eventDeadline=microtime(true)+1;while(microtime(true)<$eventDeadline){$events.=stream_get_contents($control);if(str_contains($events,$onion.':80'))break;usleep(10000);}fclose($control);
 if(!str_contains($events,'650 STREAM ')||!str_contains($events,$onion.':80'))throw new RuntimeException('Real Tor SOCKS hostname stream not observed');
 if(@stream_socket_accept($trap,0)!==false)throw new RuntimeException('Direct HTTP proxy fallback detected');fclose($trap);
 echo json_encode(['status'=>'PASS_DEVELOPMENT','tor_version'=>$version[1],'checks'=>['REAL_TOR_CONFIG_VALID','REAL_TOR_DAEMON_CONTROL_CONFIRMED','LOOPBACK_ONLY_SYNTHETIC_AUTHORITY_CONFIRMED','REAL_TOR_SOCKS_ONION_STREAM_OBSERVED','REAL_SOCKS_FAILURE_NORMALIZED','CLI_EXIT_5_TYPED_JSON','NO_DIRECT_HTTP_PROXY_FALLBACK','NO_APPLICATION_STATE_OR_KEYS_CREATED'],'duration_ms'=>round((microtime(true)-$started)*1000),'network_mode'=>'Docker network none; network-enabled Tor with only synthetic loopback authority and no default fallback directories','live_tor_bootstrap_verified'=>false,'onion_fetch_verified'=>false,'browser_verified'=>false,'production_approved'=>false],JSON_THROW_ON_ERROR),PHP_EOL;
}finally{
 proc_terminate($process);proc_close($process);
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
