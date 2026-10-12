<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/node-php/vendor/autoload.php';
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\PrivateBackup;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Http\DataApi;
// PUBLIC TEST seed, matching generate_dataset.py; never a production signer.
$signer=new class implements \OnionServiceDiscovery\Signing\SignerProvider {
    private string $secret;
    public function __construct(){ $this->secret=sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(hash('sha256','OSD BENCHMARK PUBLIC TEST ONLY node-0',true))); }
    public function role():string{return 'node';}
    public function publicKey():string{return \OnionServiceDiscovery\Crypto\Encoding::encode(sodium_crypto_sign_publickey_from_secretkey($this->secret),'ed25519:');}
    public function signRecord(\stdClass $record,bool $rotationConfirmation=false):\stdClass{
        if($record->record_type!=='snapshot_descriptor'||$rotationConfirmation)throw new RuntimeException('benchmark signer restriction');
        $record->signature=\OnionServiceDiscovery\Crypto\Encoding::encode(sodium_crypto_sign_detached(\OnionServiceDiscovery\Protocol\RecordFormat::message($record),$this->secret),'ed25519sig:');return $record;
    }
};
function elapsed(int $start): float { return (hrtime(true)-$start)/1000000; }
function stats(array $values): array { sort($values,SORT_NUMERIC);return ['samples'=>count($values),'p50_ms'=>$values[(int)floor((count($values)-1)*0.50)],'p95_ms'=>$values[(int)floor((count($values)-1)*0.95)],'p99_ms'=>$values[(int)floor((count($values)-1)*0.99)]]; }
function diskBytes(string $path): int { $total=0;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS)) as $file){if($file->isFile())$total+=$file->getSize();}return $total; }
$dataset=$argv[1];$verifier=new RecordVerifier(dirname(__DIR__,2).'/protocol/schemas');$wires=json_decode(file_get_contents($dataset),false,64,JSON_THROW_ON_ERROR);$records=[];$verify=[];$all=hrtime(true);
foreach($wires as $wire){$start=hrtime(true);$records[]=$verifier->verify($wire);$verify[]=elapsed($start);}unset($wires);
$report=['profile'=>'S1000-typical-5-node-claims','dataset_sha256'=>hash_file('sha256',$dataset),'dataset_bytes'=>filesize($dataset),'php'=>PHP_VERSION,'platform'=>php_uname(),'record_count'=>count($records),'verification'=>stats($verify),'verification_total_ms'=>elapsed($all),'backends'=>[]];
foreach(['filesystem','sqlite'] as $backend){
 $root=sys_get_temp_dir().'/osd-benchmark-'.bin2hex(random_bytes(12));mkdir($root,0700);$store=$backend==='filesystem'?new FilesystemStore($root.'/data',create:true):new SQLiteStore($root.'/data.sqlite',create:true);
 $batches=[];$all=hrtime(true);foreach(array_chunk($records,256) as $batch){$start=hrtime(true);$store->transaction(static function(StorageTransaction $tx)use($batch):void{foreach($batch as $r)$tx->putRecord($r);});$batches[]=elapsed($start);}$import=elapsed($all);
 $reads=[];$all=hrtime(true);foreach(array_chunk(array_keys($store->snapshot()['records']),256) as $batch){$start=hrtime(true);$store->readRecords($batch);$reads[]=elapsed($start);}$read=elapsed($all);
 $derive=[];for($i=0;$i<10;$i++){$start=hrtime(true);$identities=IdentityDeriver::derive($records);$derive[]=elapsed($start);}if(count($identities['identities'])!==1000)throw new RuntimeException('benchmark identity mismatch');
 $blobs=new PublicBlobStore($root.'/exports',true);$start=hrtime(true);$snapshot=(new SnapshotProducer($store,$blobs,$verifier,$signer))->publish(1791763200);$publish=elapsed($start);
 $api=new DataApi($store,$blobs,$verifier,true);$apiTimes=[];$sample=array_values(array_filter($records,static fn($r)=>$r->type==='service_manifest'));
 for($i=0;$i<100;$i++){$start=hrtime(true);$response=$api->handle('GET','/onion-service-discovery/v1/services/'.$sample[$i*2]->document()->onion);$apiTimes[]=elapsed($start);if(($response['status']!==200 && $response['status']!==413)||($response['status']===200 && count(json_decode($response['body'])->records)!==7))throw new RuntimeException('indexed service API benchmark failed');}
 $start=hrtime(true);$store->integrityCheck();$integrity=elapsed($start);$start=hrtime(true);PrivateBackup::export($store,$root.'/backup.ndjson');$backup=elapsed($start);
 $diskBefore=diskBytes($root)-filesize($root.'/backup.ndjson');$start=hrtime(true);$checkpoint=$store instanceof SQLiteStore?$store->checkpoint():null;$maintenance=elapsed($start);
 $report['backends'][$backend]=['import_total_ms'=>$import,'import_batch_256'=>stats($batches),'read_all_ms'=>$read,'read_batch_256'=>stats($reads),'derive_all'=>stats($derive),'integrity_ms'=>$integrity,'backup_ms'=>$backup,'snapshot_publish_ms'=>$publish,'snapshot_record_count'=>$snapshot['record_count'],'service_api'=>stats($apiTimes),'service_api_last_status'=>$response['status'],'maintenance_ms'=>$maintenance,'sqlite_checkpoint'=>$checkpoint,'disk_before_maintenance_bytes'=>$diskBefore,'backend_disk_bytes'=>diskBytes($root)-filesize($root.'/backup.ndjson')];unset($api,$blobs,$store);
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
$usage=getrusage();$report['peak_php_memory_bytes']=memory_get_peak_usage(true);$report['cpu_user_seconds']=$usage['ru_utime.tv_sec']+$usage['ru_utime.tv_usec']/1000000;$report['cpu_system_seconds']=$usage['ru_stime.tv_sec']+$usage['ru_stime.tv_usec']/1000000;
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
