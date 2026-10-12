<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ManifestInstaller;
$project=dirname(__DIR__,2);$verifier=new RecordVerifier($project.'/protocol/schemas');$f=json_decode(file_get_contents($project.'/protocol/test-vectors/signed-records.json'),false,64,JSON_THROW_ON_ERROR);$genesis=$verifier->verify($f->stories->genesis);$successor=$verifier->verify($f->stories->successor);
$root=sys_get_temp_dir().'/osd-install-'.bin2hex(random_bytes(12));mkdir($root,0700);mkdir($root.'/web',0755);file_put_contents($root.'/hostname',$genesis->document()->onion."\n");$installer=new ManifestInstaller($verifier);
if($installer->install($genesis,$root.'/hostname',$root.'/web')['status']!=='INSTALLED'||$installer->install($genesis,$root.'/hostname',$root.'/web')['status']!=='UNCHANGED')throw new RuntimeException('installation/idempotence');
$installer->install($successor,$root.'/hostname',$root.'/web');$path=$root.'/web/.well-known/onion-service-discovery.json';$before=file_get_contents($path);
try{$installer->install($genesis,$root.'/hostname',$root.'/web');throw new RuntimeException('downgrade installed');}catch(InvalidArgumentException $e){if($e->getMessage()!=='REVISION_CHAIN_BROKEN')throw $e;}
if(file_get_contents($path)!==$before)throw new RuntimeException('failed install mutated current');
file_put_contents($root.'/hostname',$f->stories->migrated->onion."\n");try{$installer->install($successor,$root.'/hostname',$root.'/web');throw new RuntimeException('host mismatch installed');}catch(InvalidArgumentException $e){if($e->getMessage()!=='ONION_BINDING_MISMATCH')throw $e;}
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);echo "PASS: public-only atomic manifest installation, host binding and downgrade refusal\n";
