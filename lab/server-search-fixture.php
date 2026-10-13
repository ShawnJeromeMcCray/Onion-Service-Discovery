<?php
declare(strict_types=1);
// Synthetic isolated-browser fixture only; never a deployment initializer.
require dirname(__DIR__).'/node-php/vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
if(count($argv)!==2||!is_dir($argv[1])||(fileperms($argv[1])&0077)!==0)throw new RuntimeException('private temporary fixture directory required');
$root=$argv[1];$project=dirname(__DIR__);$verifier=new RecordVerifier($project.'/protocol/schemas');
$signer=ProtectedFileSigner::create($root.'/node.key','node','TEST ONLY No-JS browser fixture passphrase',$verifier);
$v=json_decode(file_get_contents($project.'/node-php/config/combined.example.json'),true);
$v['storage']=['backend'=>'filesystem','path'=>$root.'/data'];$v['export_path']=$root.'/exports';$v['node']['key_path']=$root.'/node.key';$v['features']['server_search']=true;$v['features']['mirror_directory']=true;
file_put_contents($root.'/config.json',json_encode($v));chmod($root.'/config.json',0600);$config=NodeConfig::load($root.'/config.json');NodeInitializer::initialize($config,$signer,$verifier);
$dataset=json_decode(file_get_contents($project.'/protocol/test-vectors/browser-dataset.json'));
$records=array_map($verifier->verify(...),json_decode(base64_decode($dataset->sources[0]->chunk_base64)));$store=$config->store();
$store->transaction(static function(StorageTransaction $tx)use($records):void{foreach($records as $record)$tx->putRecord($record);});
(new SnapshotProducer($store,new PublicBlobStore($root.'/exports'),$verifier,$signer,true))->publish(time());
echo "SYNTHETIC_BROWSER_FIXTURE_READY\n";
