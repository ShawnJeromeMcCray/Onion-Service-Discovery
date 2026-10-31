<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\CandidateAnnouncement;
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
function hintAddress(int $n): string {
    $key = hash('sha256', 'TEST ONLY announcement address:' . $n, true);
    $bytes = $key . substr(hash('sha3-256', '.onion checksum' . $key . "\x03", true), 0, 2) . "\x03";
    $alphabet = 'abcdefghijklmnopqrstuvwxyz234567'; $bits=0; $value=0; $encoded='';
    foreach(str_split($bytes) as $byte){$value=($value<<8)|ord($byte);$bits+=8;while($bits>=5){$bits-=5;$encoded.=$alphabet[($value>>$bits)&31];}$value&=(1<<$bits)-1;}
    return OnionAddress::parse($encoded . '.onion')->hostname;
}
$root=sys_get_temp_dir().'/osd-announcement-'.bin2hex(random_bytes(12));mkdir($root,0700);
try {
    foreach(['filesystem','sqlite'] as $backend){
        $store=$backend==='filesystem'?new FilesystemStore($root.'/data',create:true):new SQLiteStore($root.'/data.sqlite',create:true);
        $api=new CandidateAnnouncement($store);$valid=json_encode(['protocol_version'=>1,'onion'=>hintAddress(0)]);
        $before=$store->snapshot();
        foreach([
            ['GET','application/json',$valid,405], ['POST','text/plain',$valid,415],
            ['POST','application/json',str_repeat('x',257),413], ['POST','application/json','{}',400],
            ['POST','application/json','{"protocol_version":1,"protocol_version":1,"onion":"'.hintAddress(0).'"}',400],
            ['POST','application/json',json_encode(['protocol_version'=>1,'onion'=>hintAddress(0),'source'=>'new-identity']),400],
            ['POST','application/json',json_encode(['protocol_version'=>1,'onion'=>'http://'.hintAddress(0)]),400],
            ['POST','application/json',json_encode(['protocol_version'=>2,'onion'=>hintAddress(0)]),400],
            ['POST','application/json',json_encode(['protocol_version'=>1,'onion'=>strtoupper(hintAddress(0))]),400],
        ] as [$method,$type,$body,$status]){
            $reply=$api->handle($method,$type,$body,1000);
            if($reply['status']!==$status)throw new RuntimeException('invalid hint accepted');
            if(array_keys(json_decode($reply['body'],true))!==['error'])throw new RuntimeException('refusal without {"error":CODE}: '.$reply['body']);
            if($store->snapshot()!==$before)throw new RuntimeException('invalid hint mutated storage');
        }
        for($i=0;$i<40;++$i){
            $reply=$api->handle('POST','application/json; charset=utf-8',json_encode(['protocol_version'=>1,'onion'=>hintAddress($i)]),1000);
            if($reply['status']!==($i<32?202:429))throw new RuntimeException('anonymous source cap bypass');
            if($i<32&&json_decode($reply['body'],true)!==['status'=>'HINT_RECEIVED_UNCONFIRMED'])throw new RuntimeException('hint claimed verification');
            if($i>=32&&json_decode($reply['body'],true)!==['error'=>'ADMISSION_DEFERRED'])throw new RuntimeException('deferred hint body: '.$reply['body']);
        }
        $state=$store->snapshot();
        if(count($state['tables']['candidates'])!==32||$state['records']!==[]||$state['tables']['local_observations']!==[])throw new RuntimeException('announcement verified/signed evidence');
        $queue=new CandidateQueue($store);$job=$queue->claim(1000);$queue->fail($job,1000,'TEST_QUARANTINE',true);
        $row=$store->snapshot()['tables']['candidates']['key:'.$job['onion']];
        for($i=0;$i<100;++$i)$api->handle('POST','application/json',json_encode(['protocol_version'=>1,'onion'=>$job['onion']]),1001);
        if($store->snapshot()['tables']['candidates']['key:'.$job['onion']]!==$row)throw new RuntimeException('duplicate cleared quarantine/backoff/lease');
        if($api->handle('POST','application/json',json_encode(['protocol_version'=>1,'onion'=>hintAddress(40)]),999)['status']!==429)throw new RuntimeException('clock rollback bypass');
        if($api->handle('POST','application/json',json_encode(['protocol_version'=>1,'onion'=>hintAddress(40)]),4600)['status']!==202)throw new RuntimeException('budget failed to resume');
        $store->integrityCheck();unset($api,$queue,$store);
    }
    $config=json_decode(file_get_contents(__DIR__.'/../config/node.example.json'),true);$path=$root.'/config.json';
    unset($config['features']['accept_announcements']);file_put_contents($path,json_encode($config));
    if(NodeConfig::load($path)->features['accept_announcements'])throw new RuntimeException('old config enabled receiver');
    $config['features']['accept_announcements']=true;file_put_contents($path,json_encode($config));
    try{NodeConfig::load($path);throw new RuntimeException('receiver without discovery accepted');}catch(InvalidArgumentException){}
    $config['features']['discover_services']=true;file_put_contents($path,json_encode($config));
    if(!NodeConfig::load($path)->features['accept_announcements'])throw new RuntimeException('explicit receiver rejected');
    echo "PASS: bounded announcement hints, both backends, invalid-input immutability, anonymous budget and target-state preservation\n";
}finally{
    unset($api,$queue,$store);
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
