<?php
declare(strict_types=1);
require __DIR__.'/health-report.inc.php';
$project=dirname(__DIR__,2);$cli=$project.'/node-php/bin/onion-service-discovery';$root=sys_get_temp_dir().'/osd-cli-'.bin2hex(random_bytes(12));mkdir($root,0700);
function command(array $arguments,int $expected=0):array{
 global $cli;$process=proc_open([PHP_BINARY,$cli,...$arguments,'--json','--no-interaction'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit!==$expected||$err!=='')throw new RuntimeException('CLI failed '.$arguments[0].' exit '.$exit.' '.$out.' '.$err);$decoded=json_decode($out,true,32,JSON_THROW_ON_ERROR);
 if(in_array($arguments[0],['doctor','security:check'],true)&&isset($decoded['software_version']))assertHealthReport($decoded);return $decoded;
}
file_put_contents($root.'/passphrase','TEST ONLY private operator passphrase');chmod($root.'/passphrase',0600);
// Synthetic filenames only: no Tor identity is created or read in this test.
foreach (['hs_ed25519_secret_key', 'private_key'] as $forbidden) {
 file_put_contents($root.'/'.$forbidden, '{}');
 symlink($root.'/'.$forbidden, $root.'/alias-'.$forbidden.'.json');
 foreach ([$root.'/'.$forbidden, $root.'/alias-'.$forbidden.'.json'] as $input) {
  $rejected=command(['protocol:inspect',$input],3);
  if ($rejected['error']!=='TOR_KEY_ACCESS_FORBIDDEN') throw new RuntimeException('Tor key filename alias was read');
 }
}
$v=json_decode(file_get_contents($project.'/node-php/config/node.example.json'),true,32,JSON_THROW_ON_ERROR);$v['storage']['path']=$root.'/data';$v['export_path']=$root.'/exports';$v['node']['key_path']=$root.'/node.key';$v['features']['mirror_directory']=true;file_put_contents($root.'/config.json',json_encode($v));chmod($root.'/config.json',0600);$config=$root.'/config.json';
command(['config:validate',$config]);command(['key:node:generate',$root.'/node.key','--passphrase-file',$root.'/passphrase']);command(['node:init',$config,'--passphrase-file',$root.'/passphrase']);command(['doctor',$config],6);
$published=command(['node:snapshot',$config,'--passphrase-file',$root.'/passphrase']);if($published['status']!=='PUBLISHED')throw new RuntimeException('first snapshot failed');
command(['doctor',$config]);
// security:check names the synthetic Tor key files beside the configuration, and passes once they are gone.
$found=command(['security:check',$config],4);$findings=array_column($found['security_findings'],'status','check');
if($findings['tor_secret_key_absent']!=='FAIL'||$findings['configuration_file_private']!=='PASS'||!in_array($findings['release_files_unchanged'],['NOT_APPLICABLE','PASS'],true)||$findings['web_root_contents']!=='PASS')throw new RuntimeException('security:check findings '.json_encode($findings));
foreach(['hs_ed25519_secret_key','private_key'] as $forbidden){unlink($root.'/alias-'.$forbidden.'.json');unlink($root.'/'.$forbidden);}
command(['security:check',$config]);
$v['features']['discover_services'] = true; file_put_contents($config, json_encode($v));
// config:validate names the key and points to the configuration reference, without printing the value.
$broken = $v; $broken['transport']['timeout_ms'] = 0; file_put_contents($root . '/broken.json', json_encode($broken)); chmod($root . '/broken.json', 0600);
$refused = command(['config:validate', $root . '/broken.json'], 3);
if ($refused['error'] !== 'CONFIGURATION_INVALID' || $refused['detail'] !== 'transport.timeout_ms: must be an integer from 100 to 60000' || $refused['help'] !== 'docs/operations/configuration.md' || !is_string($refused['meaning'])) { throw new RuntimeException('config:validate detail'); }
unlink($root . '/broken.json');
$queued = command(['candidate:add', $config, $v['node']['onion']]);
$duplicate = command(['candidate:add', $config, $v['node']['onion']]);
if ($queued['status'] !== 'QUEUED' || !$queued['inserted'] || $duplicate['status'] !== 'NOT_INSERTED' || $duplicate['inserted']) { throw new RuntimeException('candidate CLI reported insertion without admission'); }
// An operator can release a candidate for the next run; an unknown address is refused, nothing is created.
if (command(['candidate:retry', $config, $v['node']['onion'], '--acknowledge-quarantine'])['status'] !== 'QUEUED') { throw new RuntimeException('candidate retry'); }
if (command(['candidate:retry', $config, $v['node']['onion']], 2)['error'] !== 'USAGE_ERROR') { throw new RuntimeException('candidate retry without acknowledgement'); }
if (command(['candidate:retry', $config, '32o2tuiv2yycgn2o5qv5ff4tfsb7ytiats25pzx2i4gvrjl3tyop7mqd.onion', '--acknowledge-quarantine'], 3)['error'] !== 'CANDIDATE_NOT_FOUND') { throw new RuntimeException('candidate retry created a row'); }
$unchanged=command(['node:snapshot',$config,'--passphrase-file',$root.'/passphrase']);if($unchanged['status']!=='UNCHANGED')throw new RuntimeException('snapshot idempotence lost');
$status=command(['status',$config]);if($status['role']!=='node'||$status['onion']!==$v['node']['onion']||$status['node']['snapshot_generation']!==1)throw new RuntimeException('role-aware status '.json_encode($status));
command(['candidate:list',$config]);command(['sync:status',$config]);
// Automatic housekeeping keeps every referenced and every fresh export blob (spec 04 section 29).
$maintained=command(['node:maintain',$config]);if($maintained['exports']['blobs_removed']!==0)throw new RuntimeException('maintenance pruned fresh exports');
command(['exports:prune',$config,'--workers-stopped']);
// doctor derives the export budget and the snapshot timer's freshness from what is stored.
$health=command(['doctor',$config]);if($health['export_usage']['blob_files']<2||$health['scheduler_state']['snapshot']!=='RECENT')throw new RuntimeException('doctor usage and scheduler '.json_encode([$health['export_usage'],$health['scheduler_state']]));
// A quarantined peer is listed with the hashes of its conflicting signed checkpoints.
require_once $project.'/node-php/vendor/autoload.php';
$peerOnion='32o2tuiv2yycgn2o5qv5ff4tfsb7ytiats25pzx2i4gvrjl3tyop7mqd.onion';$peerKey='ed25519:'.rtrim(strtr(base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat("\x05",32)))),'+/','-_'),'=');
$v['features']['sync_with_peers']=true;file_put_contents($config,json_encode($v));
if(command(['sync:run',$config])['status']!=='NOTHING_DUE')throw new RuntimeException('sync:run without peers');
command(['peers:add',$config,$peerOnion,'--expected-node-key',$peerKey]);
$evidence=['sha256:'.str_repeat('a',64),'sha256:'.str_repeat('b',64)];
\OnionServiceDiscovery\Configuration\NodeConfig::load($config)->store()->transaction(static function(\OnionServiceDiscovery\Storage\StorageTransaction $tx)use($peerOnion,$peerKey,$evidence):void{
 $row=$tx->get('peers',$peerOnion);$row['state']='QUARANTINED';$row['error']='SOURCE_CHECKPOINT_EQUIVOCATION';$tx->set('peers',$peerOnion,$row);
 $tx->set('checkpoint_evidence','checkpoint:'.hash('sha256',$peerKey).':7',['hashes'=>$evidence,'equivocated'=>true]);
});
$quarantine=command(['quarantine:list',$config]);$peer=array_values(array_filter($quarantine['rows'],static fn(array $row):bool=>$row['kind']==='peer'))[0]??null;
if($peer===null||$peer['onion']!==$peerOnion||$peer['error']!=='SOURCE_CHECKPOINT_EQUIVOCATION'||$peer['evidence_hashes']!==$evidence)throw new RuntimeException('quarantined peer listing '.json_encode($quarantine));
// The only peer is quarantined, so nothing is due; the next due peer is chosen otherwise.
if(command(['sync:run',$config])['status']!=='NOTHING_DUE')throw new RuntimeException('sync:run picked a quarantined peer');
$backup=command(['backup:create','filesystem',$root.'/data',$root.'/backup.ndjson']);$verified=command(['backup:verify',$root.'/backup.ndjson']);if($backup['backup']['sha256']!==$verified['backup']['sha256'])throw new RuntimeException('backup verify mismatch');
command(['storage:restore','sqlite',$root.'/staging.sqlite',$root.'/backup.ndjson','--trusted-private-backup']);command(['restore:verify','sqlite',$root.'/staging.sqlite']);command(['db:migrate','sqlite',$root.'/staging.sqlite']);
// help COMMAND explains one command and what it touches.
$help=command(['help','service:membership']);if($help['tor_requests']!==true||$help['writes_local_state']!==false||$help['syntax']===[]||command(['help','node:snapshot'])['reads_private_keys']!==true||command(['help','no:such-command'],3)['error']!=='COMMAND_UNKNOWN')throw new RuntimeException('help COMMAND');
$release=command(['release:info']);if($release['production_approved']!==false||!in_array($release['build']['state'],['SOURCE_CHECKOUT','RELEASE_METADATA'],true)||($release['build']['state']==='RELEASE_METADATA'&&preg_match('/\A[0-9a-f]{40}\z/D',(string)$release['build']['source_commit'])!==1))throw new RuntimeException('invented release approval or build identity');
$oldKey=$v['node']['key_path'];$v['node']['key_path']=$root.'/replacement.key';file_put_contents($config,json_encode($v));command(['key:create','node',$v['node']['key_path'],'--passphrase-file',$root.'/passphrase']);command(['doctor',$config],4);
$changed=command(['node:snapshot',$config,'--passphrase-file',$root.'/passphrase'],3);if($changed['error']!=='NODE_IDENTITY_CHANGED')throw new RuntimeException('silent Node key replacement');
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
echo "PASS: automated CLI initialize, diagnostics, snapshot, staged restore and key-replacement refusal\n";
