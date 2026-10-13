<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageTransaction;
// Refuse host paths: the runner must provision an isolated tiny /fault tmpfs.
if (($argv[1]??null)!=='/fault' || !is_dir('/fault') || disk_total_space('/fault')>4194304) { throw new RuntimeException('isolated tiny filesystem required'); }
set_error_handler(static fn():bool=>true); // ENOSPC warnings are expected; no raw paths in evidence.
$cases=json_decode(file_get_contents(dirname(__DIR__,2).'/protocol/test-vectors/identity-sequences.json'),false,64,JSON_THROW_ON_ERROR);
foreach($cases->cases as $case){if($case->id==='fork-same-revision')break;}
$verifier=new \OnionServiceDiscovery\Protocol\RecordVerifier(dirname(__DIR__,2).'/protocol/schemas');
$first=$verifier->verify($case->records[0]);$second=$verifier->verify($case->records[1]);$results=[];
foreach(['filesystem','sqlite']as $backend){
 $path='/fault/'.($backend==='filesystem'?'data':'data.sqlite');
 $store=$backend==='filesystem'?new FilesystemStore($path,create:true):new SQLiteStore($path,create:true);
 $store->transaction(static function(StorageTransaction $tx)use($first):void{$tx->putRecord($first);$tx->set('sync','disk-full',['cursor'=>1]);});
 if($store instanceof SQLiteStore)$store->checkpoint();
 $before=$store->snapshot();$padding=fopen('/fault/padding','xb');$written=0;
 while($written<4194304){$count=fwrite($padding,str_repeat('x',32768));if($count===false||$count===0)break;$written+=$count;}
 fflush($padding);fclose($padding);
 if(disk_free_space('/fault')!==0.0){throw new RuntimeException('filesystem was not exhausted');}
 $error=null;
 try{$store->transaction(static function(StorageTransaction $tx)use($second):void{$tx->putRecord($second);$tx->set('sync','disk-full',['cursor'=>2]);});}
 catch(StorageException $failure){$error=$failure->getMessage();}
 if($error===null)throw new RuntimeException('full-disk mutation accepted');
 $recordFile=tempnam(sys_get_temp_dir(),'osd-enospc-record-');file_put_contents($recordFile,$second->canonicalWire);
 $process=proc_open([PHP_BINARY,__DIR__.'/../bin/onion-service-discovery','storage:admit',$backend,$path,$recordFile,'--json','--no-interaction'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);unlink($recordFile);
 $message=json_decode($out,true,8,JSON_THROW_ON_ERROR);
 if($exit!==4||$err!==''||($message['status']??null)!=='FAIL'||!in_array($message['error']??null,['STORAGE_ERROR','STORAGE_UNAVAILABLE'],true))throw new RuntimeException('disk-full CLI leaked warning or returned wrong error class');
 // Releasing only synthetic padding allows ordinary explicit recovery.
 unlink('/fault/padding');unset($store);
 $store=$backend==='filesystem'?new FilesystemStore($path):new SQLiteStore($path);
 if($store->snapshot()!==$before)throw new RuntimeException('record/cursor changed despite failed disk-full transaction');
 $store->integrityCheck();
 $store->transaction(static function(StorageTransaction $tx)use($second):void{$tx->putRecord($second);$tx->set('sync','disk-full',['cursor'=>2]);});
 $after=$store->snapshot();if(count($after['records'])!==2||$after['tables']['sync']['key:disk-full']['cursor']!==2)throw new RuntimeException('post-recovery admission failed');
 $store->integrityCheck();unset($store);
 $results[]=['backend'=>$backend,'status'=>'PASS','error'=>$error,'prior_record_and_cursor_preserved'=>true,'admission_after_space_restored'=>true];
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/fault',FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($iterator as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}
}
// Exhaust space after durable transaction phases, including HEAD publication.
foreach (['filesystem'=>['after_objects','after_prepare','after_head'], 'sqlite'=>['before_cursor_commit']] as $backend=>$points) {
 foreach ($points as $point) {
  $armed=false;
  $hook=static function(string $stage)use(&$armed,$point):void {
   if (!$armed || $stage!==$point) return;
   $padding=fopen('/fault/padding','xb');$written=0;
   while($written<4194304){$count=fwrite($padding,str_repeat('x',32768));if($count===false||$count===0)break;$written+=$count;}
   fflush($padding);fclose($padding);
   if(disk_free_space('/fault')!==0.0)throw new RuntimeException('phase filesystem not exhausted');
  };
  $path='/fault/'.($backend==='filesystem'?'data':'data.sqlite');
  $store=$backend==='filesystem'?new FilesystemStore($path,create:true,faultHook:$hook):new SQLiteStore($path,create:true,faultHook:$hook);
  $store->transaction(static function(StorageTransaction $tx)use($first):void{$tx->putRecord($first);$tx->set('sync','disk-full',['cursor'=>1]);});
  if($store instanceof SQLiteStore)$store->checkpoint();
  if ($point==='after_head') {
   // Place the next COMMIT across a page boundary: appending inside an already
   // allocated page can legitimately succeed even when free bytes are zero.
   $aligned=false;
   for($step=0;$step<64;++$step){
    $journal=file_get_contents($path.'/journal.log');$lines=explode("\n",rtrim($journal,"\n"));$entry=json_decode($lines[count($lines)-1],true,16,JSON_THROW_ON_ERROR);$entry['generation']=$store->snapshot()['generation']+1;
    $entry['phase']='PREPARE';$prepareBytes=strlen(\OnionServiceDiscovery\Storage\StorageJson::encode($entry))+1;
    $entry['phase']='COMMIT';$commitBytes=strlen(\OnionServiceDiscovery\Storage\StorageJson::encode($entry))+1;
    if((strlen($journal)+$prepareBytes)%4096+$commitBytes>4096){$aligned=true;break;}
    $store->transaction(static function(StorageTransaction $tx)use($step):void{$tx->set('maintenance','journal-alignment',['step'=>$step]);});
   }
   if(!$aligned)throw new RuntimeException('journal boundary alignment failed');
  }
  $armed=true;$error=null;
  try{$store->transaction(static function(StorageTransaction $tx)use($second):void{$tx->putRecord($second);$tx->set('sync','disk-full',['cursor'=>2]);});}
  catch(StorageException $failure){$error=$failure->getMessage();}
  if($error===null)throw new RuntimeException('full-disk phase failure missing '.$backend.'/'.$point);
  unlink('/fault/padding');unset($store);
  $store=$backend==='filesystem'?new FilesystemStore($path):new SQLiteStore($path);
  $state=$store->snapshot();$expected=$point==='after_head'?2:1;
  if(count($state['records'])!==$expected||$state['last_sequence']!==$expected||$state['tables']['sync']['key:disk-full']['cursor']!==$expected)throw new RuntimeException('disk-full phase record/cursor atomicity lost');
  $store->integrityCheck();unset($store);
  $results[]=['backend'=>$backend,'phase'=>$point,'status'=>'PASS','error'=>$error,'recovered_record_count'=>$expected,'recovered_cursor'=>$expected,'head_is_filesystem_commit_point'=>$point==='after_head'];
  $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/fault',FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($iterator as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}
 }
}
restore_error_handler();echo json_encode(['status'=>'PASS','scope'=>'Actual ENOSPC on isolated 2 MiB tmpfs; not physical power-loss or deployed disk evidence','checks'=>$results],JSON_THROW_ON_ERROR),PHP_EOL;
