<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/node-php/vendor/autoload.php';
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Http\ServerSearch;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordFormat;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
// Public deterministic benchmark seed only. No Tor or production signing identity.
$signer=new class implements \OnionServiceDiscovery\Signing\SignerProvider{
 private string $secret;
 public function __construct(){$this->secret=sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(hash('sha256','OSD BENCHMARK PUBLIC TEST ONLY node-0',true)));}
 public function __destruct(){sodium_memzero($this->secret);}
 public function role():string{return 'node';}
 public function publicKey():string{return Encoding::encode(sodium_crypto_sign_publickey_from_secretkey($this->secret),'ed25519:');}
 public function signRecord(stdClass $record,bool $rotationConfirmation=false):stdClass{if($record->record_type!=='snapshot_descriptor'||$rotationConfirmation)throw new RuntimeException('benchmark signer restriction');$record->signature=Encoding::encode(sodium_crypto_sign_detached(RecordFormat::message($record),$this->secret),'ed25519sig:');return $record;}
};
function stats(array $values):array{sort($values,SORT_NUMERIC);return ['samples'=>count($values),'p50_ms'=>$values[(int)floor((count($values)-1)*.5)],'p95_ms'=>$values[(int)floor((count($values)-1)*.95)],'p99_ms'=>$values[(int)floor((count($values)-1)*.99)],'maximum_ms'=>max($values)];}
if(count($argv)!==2)throw new RuntimeException('signed synthetic benchmark dataset required');
$project=dirname(__DIR__,2);$verifier=new RecordVerifier($project.'/protocol/schemas');$records=[];
foreach(json_decode(file_get_contents($argv[1]),false,64,JSON_THROW_ON_ERROR) as $document)$records[]=$verifier->verify($document);
$report=['status'=>'PASS_DEVELOPMENT','profile'=>'S1000-typical-5-node-claims','scope'=>'real signed synthetic records, prepared local cache and HTML search; no Tor/network/browser/capacity certification','dataset_sha256'=>hash_file('sha256',$argv[1]),'record_count'=>count($records),'php'=>PHP_VERSION,'backends'=>[],'production_approved'=>false];
foreach(['filesystem','sqlite'] as $backend){
 $root=sys_get_temp_dir().'/osd-search-bench-'.bin2hex(random_bytes(12));mkdir($root,0700);
 try{
  $store=$backend==='filesystem'?new FilesystemStore($root.'/data',create:true):new SQLiteStore($root.'/data.sqlite',create:true);
  foreach(array_chunk($records,256) as $batch)$store->transaction(static function(StorageTransaction $tx)use($batch):void{foreach($batch as $record)$tx->putRecord($record);});
  $blobs=new PublicBlobStore($root.'/exports',true);$start=hrtime(true);$published=(new SnapshotProducer($store,$blobs,$verifier,$signer,true))->publish(1791763200);$publish=(hrtime(true)-$start)/1000000;
  $search=new ServerSearch($store,$blobs,$verifier);$before=$store->snapshot();$times=[];$bytes=[];
  for($sample=0;$sample<30;++$sample){$rare=$sample%3===2;$body=$rare?'query=absent-nojs-benchmark-probe':'query=library&language=de&category=books&adult=show';$start=hrtime(true);$reply=$search->search('POST','application/x-www-form-urlencoded',$body,$start);$times[]=(hrtime(true)-$start)/1000000;$bytes[]=strlen($reply['body']);
   if($reply['status']!==200||!str_contains($reply['body'],$rare?'0 matching identities':'1000 matching identities')||substr_count($reply['body'],'<article>')>50)throw new RuntimeException('search workload budget/result failure');
  }
  if($store->snapshot()!==$before)throw new RuntimeException('benchmark query mutated storage');
  $report['backends'][$backend]=['snapshot_and_search_cache_publish_ms'=>$publish,'prepared_search'=>stats($times),'maximum_response_bytes'=>max($bytes),'wall_budget_ms'=>500,'maximum_result_count'=>50,'snapshot_record_count'=>$published['record_count'],'query_mutated_storage'=>false];
 }finally{unset($search,$blobs,$store);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);}
}
$report['peak_php_memory_bytes']=memory_get_peak_usage(true);echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
