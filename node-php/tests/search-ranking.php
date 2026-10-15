<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use OnionServiceDiscovery\Search\LocalSearch;
use OnionServiceDiscovery\Search\UnicodeSearch;
$v=json_decode(file_get_contents(dirname(__DIR__,2).'/protocol/test-vectors/local-search-v2.json'),true,64,JSON_THROW_ON_ERROR);
$rows=$v['rows'];foreach($rows as &$row){$row['searchText']=UnicodeSearch::normalize(implode(' ',[$row['name'],$row['description'],$row['onion']??'',$row['genesisKey'],...$row['categories'],...$row['languages']]));$row['searchName']=LocalSearch::collapse(UnicodeSearch::normalize($row['name']));}unset($row);
$before=$rows;
foreach($v['cases'] as $case){
 $result=LocalSearch::filter($rows,$case['query'],$case['language'],$case['category'],$case['adult']);
 if(array_column($result,'genesisKey')!==$case['expected_ids']||$rows!==$before)throw new RuntimeException('shared ranking mismatch: '.$case['id']);
}
echo 'PASS: '.count($v['cases'])." shared search/ranking/filter fixtures, immutable input and UTF-16 ordering\n";
