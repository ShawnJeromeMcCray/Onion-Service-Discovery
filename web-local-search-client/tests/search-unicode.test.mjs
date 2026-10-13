import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {gunzipSync} from 'node:zlib';
import {nfkcV1,searchTextV1} from '../src/client/search-unicode.ts';
const cases=JSON.parse(gunzipSync(readFileSync(new URL('../../protocol/test-vectors/unicode-nfkc-v1.json.gz',import.meta.url))));
const decode=value=>value.split(' ').filter(Boolean).map(part=>String.fromCodePoint(parseInt(part,16))).join('');
test('frozen Unicode 17 normalization passes every distinct official NFKC input',()=>{
  for(const [source,expected] of cases)assert.equal(nfkcV1(decode(source)),decode(expected),'official NFKC input '+source);
});
test('language-neutral full lowercase and contextual sigma match independent native Unicode 17 oracle',()=>{
  assert.equal(process.versions.unicode,'17.0','run native oracle on the pinned Unicode 17 Node runtime');
  for(const input of ['ΟΣ','ΟΣΑ','Ο\u0301Σ','Ο\u0301Σ\u0301Α','\u0130','\u1e9e','K','ＦＯＯ','e\u0301','\uD800','𐐀'])assert.equal(searchTextV1(input),input.normalize('NFKC').toLowerCase());
  for(let point=0;point<=0x10ffff;++point){if(point>=0xd800&&point<=0xdfff)continue;const input=String.fromCodePoint(point);assert.equal(searchTextV1(input),input.normalize('NFKC').toLowerCase(),'scalar '+point.toString(16));}
});
