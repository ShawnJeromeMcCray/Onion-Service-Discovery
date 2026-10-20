<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Http\ServerSearch;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Search\SearchIndex;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$project=dirname(__DIR__,2);$verifier=new RecordVerifier($project.'/protocol/schemas');
$dataset=json_decode(file_get_contents($project.'/protocol/test-vectors/browser-dataset.json'));
$records=array_map($verifier->verify(...),json_decode(base64_decode($dataset->sources[0]->chunk_base64)));
$identity=json_decode(file_get_contents($project.'/protocol/test-vectors/identity-sequences.json'));
foreach($identity->cases as $case){if($case->id==='fork-same-revision')$fork=array_map($verifier->verify(...),$case->records);if($case->id==='orphan-is-not-continuity')$orphan=array_map($verifier->verify(...),$case->records);}
$root=sys_get_temp_dir().'/osd-server-search-'.bin2hex(random_bytes(12));mkdir($root,0700);
try{
 $signer=ProtectedFileSigner::create($root.'/node.key','node','TEST ONLY server search Node passphrase',$verifier);
 $service=ProtectedFileSigner::create($root.'/service.key','service','TEST ONLY server search Service passphrase',$verifier);
 $signed=json_decode(file_get_contents($project.'/protocol/test-vectors/signed-records.json'));
 $private=json_decode(json_encode($signed->stories->genesis));$private->service_identity_public_key=$service->publicKey();$private->discovery->publish_service=false;$private->service->name='PRIVATE_SEARCH_METADATA_MARKER';$private=$verifier->verify($service->signRecord($private));
 // A published name with a Cyrillic а and a zero-width space: the same notes as the browser client.
 $lookalikeKey=ProtectedFileSigner::create($root.'/lookalike.key','service','TEST ONLY server search look-alike passphrase',$verifier);
 $lookalike=json_decode(json_encode($signed->stories->genesis));$lookalike->service_identity_public_key=$lookalikeKey->publicKey();$lookalike->discovery->publish_service=true;$lookalike->service->name="Synthetic Libr\u{0430}ry\u{200b}";$lookalike=$verifier->verify($lookalikeKey->signRecord($lookalike));
 foreach(['filesystem','sqlite'] as $backend){foreach(['browser','fork','orphan','lookalike'] as $scenario){
  $dir=$root.'/'.$backend.'-'.$scenario;mkdir($dir,0700);
  $store=$backend==='filesystem'?new FilesystemStore($dir.'/data',create:true):new SQLiteStore($dir.'/data.sqlite',create:true);
  $selected=$scenario==='browser'?[...$records,$private]:($scenario==='fork'?$fork:($scenario==='lookalike'?[$lookalike]:$orphan));
  $store->transaction(static function(StorageTransaction $tx)use($selected):void{foreach($selected as $record)$tx->putRecord($record);});
  $blobs=new PublicBlobStore($dir.'/exports',true);$producer=new SnapshotProducer($store,$blobs,$verifier,$signer,true);$published=$producer->publish(1700000000);
  if($producer->publish(1700000001)['status']!=='UNCHANGED')throw new RuntimeException('search publication not idempotent');
  $before=$store->snapshot();$search=new ServerSearch($store,$blobs,$verifier);
  $query=static fn(string $body,int $start=0):array=>$search->search('POST','application/x-www-form-urlencoded',$body,$start?:hrtime(true));
  $empty=$query('query=');if($empty['status']!==200)throw new RuntimeException('prepared search failed: '.$backend.' '.$scenario);
  if($scenario==='browser'){
   if(!str_contains($empty['body'],'3 matching identities')||str_contains($empty['body'],'Synthetic Withdrawn Service')||str_contains($empty['body'],'PRIVATE_SEARCH_METADATA_MARKER')||str_contains($empty['body'],'<img ')||!str_contains($empty['body'],'&lt;img src=x onerror=alert(1)&gt;'))throw new RuntimeException('publication/privacy/XSS gate');
   $reply=$query('query=library&language=en&category=books&adult=show');
   if($reply['status']!==200||!str_contains($reply['body'],'1 matching identities')||!str_contains($reply['body'],'Synthetic Library')||str_contains($reply['body'],'Synthetic Climate Archive'))throw new RuntimeException('semantic search filter');
  }elseif($scenario==='fork'){
   if(!str_contains($empty['body'],'Signed fork evidence is retained')||str_contains($empty['body'],'Visit Onion Service'))throw new RuntimeException('fork gained visit/current state');
  }elseif($scenario==='lookalike'){
   if(!str_contains($empty['body'],'1 matching identities')||!str_contains($empty['body'],'Metadata contains invisible characters')||!str_contains($empty['body'],'The name mixes visually similar writing systems'))throw new RuntimeException('look-alike notes missing from server search');
  }elseif(!str_contains($empty['body'],'0 matching identities'))throw new RuntimeException('orphan minted identity');
  $rare=$query('query=sensitive-local-search-probe-9341');if($rare['status']!==200||str_contains($rare['body'],'sensitive-local-search-probe-9341')||!str_contains($rare['body'],'0 matching identities'))throw new RuntimeException('query echoed or persisted in result');
  foreach(['query=a&query=b','query=a&que%72y=b','query%5B%5D=a','query=%ZZ','query=%C0%AF','query=%00','query=a&extra=1','language=en','query=a&category=unknown','query=a&language=EN','query=a&adult=rank','query='.str_repeat('a',257),'query='.urlencode(str_repeat('😀',129))] as $input){if($query($input)['status']!==400)throw new RuntimeException('unsafe form accepted');}
  if($query('query='.urlencode(str_repeat('😀',128)))['status']!==200)throw new RuntimeException('UTF-16 query bound differs');
  if($query(str_repeat('x',8193))['status']!==413||$search->search('POST','application/json','{}',hrtime(true))['status']!==415||$search->search('GET','application/x-www-form-urlencoded','query=a',hrtime(true))['status']!==405)throw new RuntimeException('method/media/byte guard');
  $expired=$query('query=',hrtime(true)-1000000000);if($expired['status']!==503||str_contains($expired['body'],'<article>'))throw new RuntimeException('expired budget returned partial results');
  if($store->snapshot()!==$before)throw new RuntimeException('search mutated operational/signed state');
  $pointer=$before['tables']['node']['key:server-search-index'];$references=SearchIndex::references($blobs,$pointer['hash']);
  if(count($references)>1){$path=$dir.'/exports/'.substr($references[1],7).'.json';$wire=file_get_contents($path);file_put_contents($path,'corrupt');if($query('query=')['status']!==503)throw new RuntimeException('corrupt cache trusted');
   try{$blobs->put($references[1],$wire);throw new RuntimeException('ordinary writer repaired corruption implicitly');}catch(\OnionServiceDiscovery\Storage\StorageException $error){if($error->errorCode!=='EXPORT_CORRUPT')throw $error;}
   $repair=new PublicBlobStore($dir.'/exports',repairDerived:true);
   $repaired=(new SnapshotProducer($store,$repair,$verifier,$signer,true,true))->publish(1700000002);
   if($repaired['status']!=='PUBLISHED'||$query('query=')['status']!==200||file_get_contents($path)!==$wire)throw new RuntimeException('explicit rebuild did not recover cache');
   unset($repair);}
  $next=$producer->publish(1700100000);
  // An index built by an earlier release (format 1, before the D-023 ranking) is stale, not trusted.
  $fresh=$store->snapshot()['tables']['node']['key:server-search-index'];
  $store->transaction(static fn(StorageTransaction $tx)=>$tx->set('node','server-search-index',['format_version'=>1]+$fresh));
  try{SearchIndex::load($store,$blobs,$verifier,static function():void{});throw new RuntimeException('format-1 index accepted');}catch(\OnionServiceDiscovery\Protocol\ProtocolException $error){if($error->getMessage()!=='SEARCH_INDEX_STALE')throw $error;}
  if($query('query=')['status']!==503)throw new RuntimeException('format-1 index served');
  $store->transaction(static fn(StorageTransaction $tx)=>$tx->set('node','server-search-index',$pointer));
  if($query('query=')['status']!==503)throw new RuntimeException('stale cache silently reused');
  $store->integrityCheck();unset($search,$producer,$store,$blobs);
 }}
 echo "PASS: prepared server search, both backends, privacy/XSS/fork/orphan behavior, strict form parsing, finite work and corrupt/stale cache rejection\n";
}finally{
 unset($search,$producer,$store,$blobs,$signer,$service);
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
