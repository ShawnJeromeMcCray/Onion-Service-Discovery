<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotFormat;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
function exactPeer($socket,int $length): string { $bytes=''; while(strlen($bytes)<$length) { $part=fread($socket,$length-strlen($bytes)); if($part===false||$part==='') { throw new RuntimeException('short SOCKS'); } $bytes.=$part; } return $bytes; }
function runPeer(DataApi $api,string $path,string $onion,int $now,bool $tamper=false,?int $maxRecords=null): array {
    $server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); if($server===false) { throw new RuntimeException('fixture unavailable'); }
    $port=substr(strrchr(stream_socket_get_name($server,false),':'),1);
    $command=[PHP_BINARY,__DIR__.'/peer-sync-worker.php',$path,$onion,$port,(string)$now]; if($maxRecords!==null)$command[]=(string)$maxRecords;
    $process=proc_open($command,[['pipe','r'],['pipe','w'],['pipe','w']],$pipes); fclose($pipes[0]); $requests=0; $exit=null; $deadline=microtime(true)+20;
    while(microtime(true)<$deadline) {
        $status=proc_get_status($process); if(!$status['running']) { $exit=$status['exitcode']; break; }
        $socket=@stream_socket_accept($server,0.1); if($socket===false) { continue; } stream_set_timeout($socket,3);
        if(++$requests>6) { throw new RuntimeException('request budget exceeded'); }
        $greeting=exactPeer($socket,2); exactPeer($socket,ord($greeting[1])); fwrite($socket,"\x05\x02");
        $auth=exactPeer($socket,2); exactPeer($socket,ord($auth[1])); exactPeer($socket,ord(exactPeer($socket,1))); fwrite($socket,"\x01\x00");
        if(exactPeer($socket,4)!=="\x05\x01\x00\x03"||exactPeer($socket,ord(exactPeer($socket,1)))!==$onion||exactPeer($socket,2)!=="\x00\x50") { throw new RuntimeException('Tor destination mismatch'); }
        fwrite($socket,"\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50"); $request=''; while(!str_contains($request,"\r\n\r\n")) { $line=fgets($socket); if($line===false) { throw new RuntimeException('HTTP request missing'); } $request.=$line; }
        if(preg_match('~\AGET ([^ ]+) HTTP/1.1\r\n~',$request,$match)!==1) { throw new RuntimeException('HTTP method mismatch'); }
        $response=$api->handle('GET',$match[1]); $body=$response['body'];
        if($tamper&&str_contains($match[1],'/chunks/')) { $body[strlen($body)-2]=chr(ord($body[strlen($body)-2])^1); }
        $wire='HTTP/1.1 '.$response['status']." Response\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body; fwrite($socket,$wire); fclose($socket);
    }
    fclose($server); if($exit===null) { proc_terminate($process); throw new RuntimeException('worker deadline'); }
    $output=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    if($exit!==0) { throw new RuntimeException('peer worker failed: '.$output.$stderr); } return json_decode($output,true,32,JSON_THROW_ON_ERROR);
}
$project=dirname(__DIR__,2); $verifier=new RecordVerifier($project.'/protocol/schemas');
$f=json_decode(file_get_contents($project.'/protocol/test-vectors/identity-sequences.json'),false,64,JSON_THROW_ON_ERROR);
foreach($f->cases as $case) { if($case->id==='fork-same-revision') { $records=array_map($verifier->verify(...),$case->records); break; } }
$onion=$records[0]->document()->onion; $root=sys_get_temp_dir().'/osd-peer-'.bin2hex(random_bytes(12)); mkdir($root,0700);
$signer=ProtectedFileSigner::create($root.'/node.key','node','TEST ONLY peer sync passphrase',$verifier);
$v=json_decode(file_get_contents($project.'/node-php/config/example.json'),true); $v['role']='node'; $v['node']=['onion'=>$onion,'key_path'=>$root.'/node.key']; $v['storage']['path']=$root.'/source'; $v['export_path']=$root.'/exports'; $v['features']['mirror_directory']=true;
file_put_contents($root.'/config.json',json_encode($v,JSON_THROW_ON_ERROR)); $config=NodeConfig::load($root.'/config.json'); NodeInitializer::initialize($config,$signer,$verifier);
$source=$config->store(); $blobs=new PublicBlobStore($root.'/exports'); $source->transaction(static function(StorageTransaction $tx) use($records): void { foreach($records as $record) { $tx->putRecord($record); } });
$producer=new SnapshotProducer($source,$blobs,$verifier,$signer); $first=$producer->publish(1700000000); $api=new DataApi($source,$blobs,$verifier,true);
// Real local admission saturation must not accumulate remote failure penalties.
$limited=new SQLiteStore($root.'/limited.sqlite',create:true); PeerRegistry::add($limited,OnionAddress::parse($onion),$signer->publicKey());
for($attempt=0;$attempt<9;++$attempt){
 if($attempt===8)$limited->transaction(static function(StorageTransaction $tx) use($onion): void { $row=$tx->get('peers',$onion);$row['failures']=7;$tx->set('peers',$onion,$row); });
 $blocked=runPeer($api,$root.'/limited.sqlite',$onion,1699900000+$attempt*4000,false,1);
 $state=$limited->snapshot(); $peer=$state['tables']['peers']['key:'.$onion];
 if($blocked['status']!=='BACKOFF'||$blocked['error']!=='STORAGE_BUDGET_EXHAUSTED'||$peer['failures']!==($attempt===8?7:0)||$peer['claim']!==null||isset($state['tables']['sync']['key:'.$onion]['generation']))throw new RuntimeException('local saturation blamed peer or advanced anchor');
}
$recovered=runPeer($api,$root.'/limited.sqlite',$onion,1700000000);
if($recovered['status']!=='CURRENT'||$recovered['generation']!==1)throw new RuntimeException('local capacity recovery required peer readmission');
$limited->integrityCheck();unset($limited);
$target=new SQLiteStore($root.'/target.sqlite',create:true); PeerRegistry::add($target,OnionAddress::parse($onion),$signer->publicKey());
$result=runPeer($api,$root.'/target.sqlite',$onion,1700000000); if($result['status']!=='CURRENT'||$result['generation']!==1) { throw new RuntimeException('initial sync: '.json_encode($result)); }
$identity=[]; foreach($target->snapshot()['records'] as $hash=>$metadata) { $identity[]=$verifier->verifyWire($target->readRecord($hash)); }
$privateState=$target->snapshot();
if(count($privateState['tables']['assertions'])<3||count($privateState['tables']['candidates'])!==1) { throw new RuntimeException('remote provenance/discovery queue missing'); }
foreach($privateState['tables']['assertions'] as $assertion) { if($assertion['kind']!=='REMOTE_PROVENANCE'||$assertion['sources'][0]['expires_at']!==1700604800) { throw new RuntimeException('assertion retention/boundary'); } }
if(IdentityDeriver::derive($identity)['identities'][0]['identity_state']!=='FORKED'||$target->snapshot()['tables']['local_observations']!==[]) { throw new RuntimeException('fork/local boundary'); }
// A valid snapshot must not bypass exhausted global candidate admission budgets.
$blocked = new SQLiteStore($root . '/blocked.sqlite', create: true);
PeerRegistry::add($blocked, OnionAddress::parse($onion), $signer->publicKey());
$blocked->transaction(static function (StorageTransaction $tx): void {
    $tx->set('maintenance', 'candidate-admissions', ['start' => 1700000000, 'last_seen' => 1700000000, 'count' => 256, 'sources' => []]);
});
$denied = runPeer($api, $root . '/blocked.sqlite', $onion, 1700000000);
$blockedState = $blocked->snapshot();
if ($denied['status'] !== 'CURRENT' || $blockedState['tables']['candidates'] !== [] || count($blockedState['tables']['assertions']) < 3 ||
    ($blockedState['tables']['sync']['key:' . $onion]['generation'] ?? null) !== 1) { throw new RuntimeException('sync bypassed budget or lost committed evidence/cursor'); }
unset($blocked);
// A signed higher-generation empty source view must not erase global evidence.
$base=$verifier->verifyWire($source->readRecord($first['checkpoint_hash']))->document(); $base->generation=2; $base->previous_checkpoint_hash=$first['checkpoint_hash']; $base->root_hash=SnapshotFormat::rootHash([]); $base->record_count=0; $base->chunks=[]; $base->cursor='empty-view';
$empty=$verifier->verify($signer->signRecord($base)); $source->transaction(static function(StorageTransaction $tx) use($empty): void { $tx->putRecord($empty); $tx->set('node','published-snapshot',['hash'=>$empty->contentHash]); });
$result=runPeer($api,$root.'/target.sqlite',$onion,1700010000); if($result['status']!=='CURRENT'||$result['generation']!==2) { throw new RuntimeException('empty sync'); }
foreach($records as $record) { if($target->readRecord($record->contentHash)===null) { throw new RuntimeException('omission erased record'); } }
// Replay a previously signed older checkpoint.
$source->transaction(static fn(StorageTransaction $tx)=>$tx->set('node','published-snapshot',['hash'=>$first['checkpoint_hash']]));
$result=runPeer($api,$root.'/target.sqlite',$onion,1700020000); if($result['error']!=='SOURCE_CHECKPOINT_ROLLBACK'||$target->snapshot()['tables']['sync']['key:'.$onion]['generation']!==2) { throw new RuntimeException('rollback accepted'); }
// Explicit test-only requeue retains the evidence; a conflicting generation is sticky.
$target->transaction(static function(StorageTransaction $tx) use($onion): void { $p=$tx->get('peers',$onion); $p['state']='CANDIDATE'; $p['next_attempt']=0; $tx->set('peers',$onion,$p); });
$fork=clone $base; $fork->cursor='different-view'; $fork=$verifier->verify($signer->signRecord($fork)); $source->transaction(static function(StorageTransaction $tx) use($fork): void { $tx->putRecord($fork); $tx->set('node','published-snapshot',['hash'=>$fork->contentHash]); });
$result=runPeer($api,$root.'/target.sqlite',$onion,1700030000); if($result['error']!=='SOURCE_CHECKPOINT_EQUIVOCATION'||$target->readRecord($fork->contentHash)===null||$target->readRecord($empty->contentHash)===null) { throw new RuntimeException('equivocation evidence lost'); }
// Three small chunks permit resumable admission, then a broken final chunk cannot activate its cursor.
$ordered=$records; usort($ordered,static fn($a,$b)=>strcmp($a->contentHash,$b->contentHash)); $chunks=[];
foreach($ordered as $record) { $wire=CanonicalJson::encode([$record->document()]); $hash=SnapshotFormat::chunkHash($wire); $blobs->put($hash,$wire); $chunks[]=(object)['hash'=>$hash,'bytes'=>strlen($wire),'record_count'=>1]; }
$partial=clone $base; $partial->generation=3; $partial->previous_checkpoint_hash=$empty->contentHash; $partial->chunks=$chunks; $partial->root_hash=SnapshotFormat::rootHash($chunks); $partial->record_count=3; $partial->cursor='three-chunks'; $partial=$verifier->verify($signer->signRecord($partial));
$source->transaction(static function(StorageTransaction $tx) use($partial): void { $tx->putRecord($partial); $tx->set('node','published-snapshot',['hash'=>$partial->contentHash]); });
$staging=new SQLiteStore($root.'/partial.sqlite',create:true); PeerRegistry::add($staging,OnionAddress::parse($onion),$signer->publicKey());
$result=runPeer($api,$root.'/partial.sqlite',$onion,1700040000); if($result['status']!=='PROGRESS'||$result['next_chunk']!==2||isset($staging->snapshot()['tables']['sync']['key:'.$onion]['generation'])) { throw new RuntimeException('partial snapshot activated'); }
$result=runPeer($api,$root.'/partial.sqlite',$onion,1700040010,true); if($result['error']!=='SNAPSHOT_CHUNK_HASH_INVALID'||isset($staging->snapshot()['tables']['sync']['key:'.$onion]['generation'])) { throw new RuntimeException('broken final chunk activated'); }
$beforeMaintenance=count($target->snapshot()['records']);
$maintenance=(new \OnionServiceDiscovery\Node\LocalMaintenance($target))->run(1700700000);
if($maintenance['assertions_removed']<3||$target->snapshot()['tables']['assertions']!==[]||count($target->snapshot()['records'])!==$beforeMaintenance) { throw new RuntimeException('assertion cleanup removed identity evidence or failed expiry'); }
// A long absence must be catchable in finite steps without resetting trust.
$source->transaction(static function(StorageTransaction $tx) use($first): void { $tx->set('node','published-snapshot',['hash'=>$first['checkpoint_hash']]); });
$long=new SQLiteStore($root.'/long.sqlite',create:true); PeerRegistry::add($long,OnionAddress::parse($onion),$signer->publicKey());
$baseline=runPeer($api,$root.'/long.sqlite',$onion,1700800000);
if($baseline['status']!=='CURRENT'||$baseline['generation']!==1)throw new RuntimeException('long-history baseline');
$parent=$first['checkpoint_hash']; $longRecords=[];
for($generation=2;$generation<=36;++$generation){
 $next=clone $base; $next->generation=$generation; $next->previous_checkpoint_hash=$parent; $next->cursor='long-'.$generation;
 $next=$verifier->verify($signer->signRecord($next));
 $source->transaction(static function(StorageTransaction $tx) use($next): void { $tx->putRecord($next); $tx->set('node','published-snapshot',['hash'=>$next->contentHash]); });
 $parent=$next->contentHash;
 $longRecords[]=$next;
}
$progress=0;
for($step=1;$step<=20;++$step){
 $caught=runPeer($api,$root.'/long.sqlite',$onion,1700800000+$step*4000);
 if($caught['status']==='CURRENT')break;
 if($caught['status']!=='PROGRESS'||$caught['phase']!=='CHECKPOINT_CHAIN'||$long->snapshot()['tables']['sync']['key:'.$onion]['generation']!==1)throw new RuntimeException('long catch-up discarded or activated unverified anchor: '.json_encode($caught));
 ++$progress;
}
if($caught['status']!=='CURRENT'||$caught['generation']!==36||$progress<2)throw new RuntimeException('long catch-up failed bounded progress');
$source->transaction(static function(StorageTransaction $tx) use($first): void { $tx->set('node','published-snapshot',['hash'=>$first['checkpoint_hash']]); });
$cached=new SQLiteStore($root.'/cached-long.sqlite',create:true); PeerRegistry::add($cached,OnionAddress::parse($onion),$signer->publicKey());
runPeer($api,$root.'/cached-long.sqlite',$onion,1700900000);
$cached->transaction(static function(StorageTransaction $tx) use($longRecords): void { foreach($longRecords as $record)$tx->putRecord($record); });
$source->transaction(static function(StorageTransaction $tx) use($parent): void { $tx->set('node','published-snapshot',['hash'=>$parent]); });
$bounded=runPeer($api,$root.'/cached-long.sqlite',$onion,1700904000);
if($bounded['status']!=='PROGRESS'||$bounded['phase']!=='CHECKPOINT_CHAIN'||$cached->snapshot()['tables']['sync']['key:'.$onion]['generation']!==1)throw new RuntimeException('cached chain bypassed per-step work bound');
$completed=runPeer($api,$root.'/cached-long.sqlite',$onion,1700908000);
if($completed['status']!=='CURRENT'||$completed['generation']!==36)throw new RuntimeException('cached chain failed resume');
$cached->integrityCheck();
// Continuity after late first contact must not invent proof back to genesis.
$late=new SQLiteStore($root.'/late.sqlite',create:true); PeerRegistry::add($late,OnionAddress::parse($onion),$signer->publicKey());
runPeer($api,$root.'/late.sqlite',$onion,1701000000);
if($late->snapshot()['tables']['sync']['key:'.$onion]['baseline_history_proven']!==false)throw new RuntimeException('late baseline invented genesis history');
$late->transaction(static function(StorageTransaction $tx) use($onion): void { $row=$tx->get('sync',$onion); $row['baseline_history_proven']=true; unset($row['baseline_history_proven_version']); $tx->set('sync',$onion,$row); });
$legacy=runPeer($api,$root.'/late.sqlite',$onion,1701004000);
if($legacy['status']!=='CURRENT'||$late->snapshot()['tables']['sync']['key:'.$onion]['baseline_history_proven']!==false)throw new RuntimeException('legacy overclaimed baseline was retained');
$later=clone $base; $later->generation=37; $later->previous_checkpoint_hash=$parent; $later->cursor='long-37';
$later=$verifier->verify($signer->signRecord($later));
$source->transaction(static function(StorageTransaction $tx) use($later): void { $tx->putRecord($later); $tx->set('node','published-snapshot',['hash'=>$later->contentHash]); });
$followed=runPeer($api,$root.'/late.sqlite',$onion,1701008000);
if($followed['status']!=='CURRENT'||$late->snapshot()['tables']['sync']['key:'.$onion]['baseline_history_proven']!==false)throw new RuntimeException('one successor invented unavailable genesis ancestry');
$late->integrityCheck();
$long->integrityCheck();
$target->integrityCheck(); $staging->integrityCheck(); unset($target,$staging,$cached,$late,$long,$source,$api,$producer,$blobs,$signer,$config);
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $file) { $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname()); } rmdir($root);
echo "PASS: controlled peer sync, fork union, omission, rollback, equivocation partial-chunk cursor safety and bounded long-absence catch-up\n";
