<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Search\UnicodeSearch;
$root=dirname(__DIR__,2);$wire=gzdecode(file_get_contents($root.'/protocol/test-vectors/unicode-nfkc-v1.json.gz'));
$cases=json_decode($wire,true,32,JSON_THROW_ON_ERROR);
foreach($cases as [$source,$expected]){
 $decode=static fn(string $hex):string=>UnicodeSearch::text(array_map(static fn(string $item):int=>hexdec($item),array_filter(explode(' ',$hex))));
 if(UnicodeSearch::nfkc($decode($source))!==$decode($expected))throw new RuntimeException('official NFKC input mismatch: '.$source);
}
foreach(['ΟΣ'=>'ος','ΟΣΑ'=>'οσα','İ'=>"i\u{0307}",'ẞ'=>'ß','K'=>'k','ＦＯＯ'=>'foo',"e\u{0301}"=>'é'] as $source=>$expected){if(UnicodeSearch::normalize($source)!==$expected)throw new RuntimeException('default lowercase mismatch');}
echo 'PASS: '.count($cases)." official Unicode 17 NFKC inputs and full/contextual lowercase\n";
