<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ManifestDraftBuilder;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
$project=dirname(__DIR__,2); $verifier=new RecordVerifier($project.'/protocol/schemas'); $builder=new ManifestDraftBuilder($verifier);
$f=json_decode(file_get_contents($project.'/protocol/test-vectors/signed-records.json'),false,64,JSON_THROW_ON_ERROR);
$root=sys_get_temp_dir().'/osd-drafts-'.bin2hex(random_bytes(12)); mkdir($root,0700);
$old=ProtectedFileSigner::create($root.'/old.key','service','TEST ONLY manifest draft passphrase',$verifier); $new=ProtectedFileSigner::create($root.'/new.key','service','TEST ONLY new service passphrase',$verifier);
$metadata=(object)['service'=>$f->stories->genesis->service];
$draft=$builder->genesis(OnionAddress::parse($f->stories->genesis->onion),$old->publicKey(),$metadata,1700000000);
if($draft->discovery->publish_service!==false) { throw new RuntimeException('implicit publication'); }
$draft->discovery->publish_service=true; $genesis=$verifier->verify($old->signRecord($draft));
$unchanged=$builder->revise($genesis,$metadata,1800000000);
if($verifier->verify($unchanged)->canonicalWire!==$genesis->canonicalWire) { throw new RuntimeException('unnecessary renewal/revision'); }
$withdrawn=$verifier->verify($old->signRecord($builder->withdraw($genesis,1700000100)));
if(IdentityDeriver::derive([$genesis,$withdrawn])['identities'][0]['discoverability']!=='WITHDRAWN') { throw new RuntimeException('withdrawal'); }
$rotation=$builder->rotate($genesis,$new->publicKey(),1700000100);
$proof=$verifier->verify($new->signRecord($old->signRecord($rotation['rotation_record']),true)); $successor=$verifier->verify($new->signRecord($rotation['successor_manifest']));
$view=IdentityDeriver::derive([$successor,$proof,$genesis]); if($view['identities'][0]['genesis_key']!==$old->publicKey()||$view['identities'][0]['current_hash']!==$successor->contentHash) { throw new RuntimeException('rotation continuity'); }
$migration=$builder->migrate($genesis,OnionAddress::parse($f->stories->migrated->onion),1700000100);
$proof=$verifier->verify($old->signRecord($migration['migration_record'])); $successor=$verifier->verify($old->signRecord($migration['successor_manifest']));
if(IdentityDeriver::derive([$genesis,$proof,$successor])['identities'][0]['current_hash']!==$successor->contentHash) { throw new RuntimeException('migration continuity'); }
unset($old,$new); unlink($root.'/old.key'); unlink($root.'/new.key'); rmdir($root);
echo "PASS: opt-in, unchanged zero-renewal, withdrawal, offline rotation and migration\n";
