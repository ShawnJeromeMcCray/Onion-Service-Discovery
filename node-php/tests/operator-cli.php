<?php
declare(strict_types=1);
$project=dirname(__DIR__,2);$cli=$project.'/node-php/bin/onion-service-discovery';$root=sys_get_temp_dir().'/osd-cli-'.bin2hex(random_bytes(12));mkdir($root,0700);
function command(array $arguments,int $expected=0):array{
 global $cli;$process=proc_open([PHP_BINARY,$cli,...$arguments,'--json','--no-interaction'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit!==$expected||$err!=='')throw new RuntimeException('CLI failed '.$arguments[0].' exit '.$exit.' '.$out.' '.$err);return json_decode($out,true,32,JSON_THROW_ON_ERROR);
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
command(['doctor',$config]);command(['security:check',$config]);
$v['features']['discover_services'] = true; file_put_contents($config, json_encode($v));
$queued = command(['candidate:add', $config, $v['node']['onion']]);
$duplicate = command(['candidate:add', $config, $v['node']['onion']]);
if ($queued['status'] !== 'QUEUED' || !$queued['inserted'] || $duplicate['status'] !== 'NOT_INSERTED' || $duplicate['inserted']) { throw new RuntimeException('candidate CLI reported insertion without admission'); }
// An operator can release a candidate for the next run; an unknown address is refused, nothing is created.
if (command(['candidate:retry', $config, $v['node']['onion'], '--acknowledge-quarantine'])['status'] !== 'QUEUED') { throw new RuntimeException('candidate retry'); }
if (command(['candidate:retry', $config, $v['node']['onion']], 2)['error'] !== 'USAGE_ERROR') { throw new RuntimeException('candidate retry without acknowledgement'); }
if (command(['candidate:retry', $config, '32o2tuiv2yycgn2o5qv5ff4tfsb7ytiats25pzx2i4gvrjl3tyop7mqd.onion', '--acknowledge-quarantine'], 3)['error'] !== 'CANDIDATE_NOT_FOUND') { throw new RuntimeException('candidate retry created a row'); }
$unchanged=command(['node:snapshot',$config,'--passphrase-file',$root.'/passphrase']);if($unchanged['status']!=='UNCHANGED')throw new RuntimeException('snapshot idempotence lost');
command(['status',$config]);command(['candidate:list',$config]);command(['quarantine:list',$config]);command(['sync:status',$config]);command(['node:maintain',$config]);command(['exports:prune',$config,'--workers-stopped']);
$backup=command(['backup:create','filesystem',$root.'/data',$root.'/backup.ndjson']);$verified=command(['backup:verify',$root.'/backup.ndjson']);if($backup['backup']['sha256']!==$verified['backup']['sha256'])throw new RuntimeException('backup verify mismatch');
command(['storage:restore','sqlite',$root.'/staging.sqlite',$root.'/backup.ndjson','--trusted-private-backup']);command(['restore:verify','sqlite',$root.'/staging.sqlite']);command(['db:migrate','sqlite',$root.'/staging.sqlite']);
$release=command(['release:info']);if($release['production_approved']!==false)throw new RuntimeException('invented release approval');
$oldKey=$v['node']['key_path'];$v['node']['key_path']=$root.'/replacement.key';file_put_contents($config,json_encode($v));command(['key:create','node',$v['node']['key_path'],'--passphrase-file',$root.'/passphrase']);command(['doctor',$config],4);
$changed=command(['node:snapshot',$config,'--passphrase-file',$root.'/passphrase'],3);if($changed['error']!=='NODE_IDENTITY_CHANGED')throw new RuntimeException('silent Node key replacement');
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
echo "PASS: automated CLI initialize, diagnostics, snapshot, staged restore and key-replacement refusal\n";
