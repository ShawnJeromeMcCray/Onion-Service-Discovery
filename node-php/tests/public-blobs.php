<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageException;
$root=sys_get_temp_dir().'/osd-blobs-'.bin2hex(random_bytes(12));mkdir($root,0700);
$processes=[];foreach(['a','b'] as $letter){$process=proc_open([PHP_BINARY,__DIR__.'/public-blob-worker.php',$root,$letter],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$process,$pipes];}
$exits=[];foreach($processes as [$process,$pipes]){stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exits[]=proc_close($process);if($errors!=='')throw new RuntimeException('worker failure');}sort($exits);if($exits!==[0,2])throw new RuntimeException('concurrent writers bypassed aggregate quota');
$store=new PublicBlobStore($root,false,100);$files=glob($root.'/*.json');if(count($files)!==1)throw new RuntimeException('quota bypass');$hash='sha256:'.basename($files[0],'.json');$before=$store->get($hash);
// Every lock or budget file the front controller writes beside the blobs survives pruning,
// including the visitor relay budget of a Client Host with visitor_sources.
foreach(PublicBlobStore::CONTROL_FILES as $control){if($control!=='.writer.lock')file_put_contents($root.'/'.$control,'SYNTHETIC RATE STATE');}
$result=$store->pruneUnreferenced([$hash]);if($result['blobs_removed']!==0||$store->get($hash)!==$before)throw new RuntimeException('current blob deleted');
try{$store->pruneUnreferenced(['sha256:'.str_repeat('0',64)]);throw new RuntimeException('missing keep set allowed');}catch(StorageException){}
// Automatic pruning spares blobs written within the grace period: a snapshot may be writing them.
$result=$store->pruneUnreferenced([],time()-3600);if($result['blobs_removed']!==0)throw new RuntimeException('fresh orphan pruned');
foreach(glob($root.'/*.json') as $blob){touch($blob,time()-7200);}
$result=$store->pruneUnreferenced([]);if($result['blobs_removed']!==1||$result['bytes_freed']!==80)throw new RuntimeException('orphan cleanup failed');
foreach(PublicBlobStore::CONTROL_FILES as $control){if($control!=='.writer.lock'&&!is_file($root.'/'.$control))throw new RuntimeException('control file pruned: '.$control);}
foreach(new DirectoryIterator($root) as $file){if(!$file->isDot())unlink($file->getPathname());}rmdir($root);
echo "PASS: concurrent export quotas and bounded reference-preserving cleanup\n";
