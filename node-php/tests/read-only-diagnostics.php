<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Configuration\Diagnostics;
use OnionServiceDiscovery\Protocol\RecordVerifier;
$project=dirname(__DIR__,2);$root=sys_get_temp_dir().'/osd-readonly-'.bin2hex(random_bytes(12));mkdir($root,0700);mkdir($root.'/exports',0700);
foreach(['filesystem','sqlite'] as $backend){
 $path=$root.($backend==='filesystem'?'/data':'/data.sqlite');$source=$backend==='filesystem'?new FilesystemStore($path,create:true):new SQLiteStore($path,create:true);
 $source->transaction(static function(StorageTransaction $tx):void{$tx->set('sync','test',['cursor'=>3]);});$before=$source->snapshot();
 $reader=$backend==='filesystem'?new FilesystemStore($path,readOnly:true):new SQLiteStore($path,readOnly:true);$reader->integrityCheck();
 try{$reader->transaction(static function(StorageTransaction $tx):void{$tx->set('sync','test',['cursor'=>4]);});throw new RuntimeException('readonly mutation accepted');}catch(StorageException $error){if($error->getMessage()!=='STORAGE_READ_ONLY')throw $error;}
 if($source->snapshot()!==$before)throw new RuntimeException('readonly logical state changed');
 $config=json_decode(file_get_contents($project.'/node-php/config/node.example.json'),true,32,JSON_THROW_ON_ERROR);$config['storage']=['backend'=>$backend,'path'=>$path];$config['export_path']=$root.'/exports';$config['node']['key_path']=$root.'/missing-node.key';file_put_contents($root.'/config.json',json_encode($config));
 $health=Diagnostics::inspect(NodeConfig::load($root.'/config.json'),new RecordVerifier($project.'/protocol/schemas'));
 if($health['status']!=='WARN'||$health['security_check_summary']['storage_integrity']!=='PASS'||$health['network_side_effects']!==false||$source->snapshot()!==$before)throw new RuntimeException('diagnostic status/readonly mismatch');
 unset($source,$reader);
}
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
echo "PASS: read-only backend and diagnostics preserve state and refuse mutation\n";
