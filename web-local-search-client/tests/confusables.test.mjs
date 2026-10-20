import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {gunzipSync} from 'node:zlib';
import {nfdV1,skeletonV1} from '../src/client/confusables.ts';
import {nfkcV1} from '../src/client/search-unicode.ts';
import {confusablesData} from '../src/client/confusables-data.ts';
const confusable=(a,b)=>skeletonV1(a)===skeletonV1(b);
const scalars=function*(){for(let point=0;point<=0x10ffff;++point)if(point<0xd800||point>0xdfff)yield point;};
test('real pairs from Unicode 17 confusables.txt share a skeleton',()=>{
  // Source lines: 0430 а→a, 0440 р→p, 006D m→rn, 216C Ⅼ→L, 2160 Ⅰ→l, 04CF ӏ→l, 0435 е→e, 0030 0→O, 017F ſ→f.
  assert.ok(confusable('paypal','pаypal'),'Latin and one Cyrillic а');
  assert.ok(confusable('modern','rnodern'),'m and rn');
  assert.equal(skeletonV1('m'),'rn');
  assert.ok(confusable('Ⅼ','L'),'Roman numeral fifty and capital L');
  assert.ok(confusable('Ⅰ','l'),'Roman numeral one and small l');
  assert.ok(confusable('аррӏе','apple'),'whole-script Cyrillic аррӏе');
  assert.ok(confusable('G00GLE','GOOGLE'),'digit zero and capital O');
  assert.ok(confusable('ſhop','fhop'),'long s and f');
});
test('the skeleton decomposes both sides and drops default-ignorable characters',()=>{
  assert.ok(confusable('café','café'),'precomposed and combining é');
  assert.ok(confusable('cafе́','café'),'Cyrillic е with a combining acute');
  for(const hidden of ['​','­','⁠','﻿','‍','️','\u{e0041}','‮']) assert.ok(confusable('pay'+hidden+'pal','paypal'),'U+'+hidden.codePointAt(0).toString(16));
  assert.equal(skeletonV1('ㅤ'),'','Hangul filler');
});
test('identical names and genuinely different names are not confused',()=>{
  for(const name of ['Synthetic Library','Bibliothèque','Ελληνική βιβλιοθήκη','東京','']) assert.equal(skeletonV1(name),skeletonV1(name));
  for(const [a,b] of [['Synthetic Library','Synthetic Climate Archive'],['Library','Librarian'],['Bibliothèque','Bibliotheque'],['mail','mall'],['paypal','PayPal']]) assert.ok(!confusable(a,b),a+' / '+b);
  // Prototypes are plain letters, so an ordinary Latin name is its own skeleton.
  assert.equal(skeletonV1('Synthetic Library'),'Synthetic Library');
});
test('frozen NFD equals the runtime normalization for every character the runtime knows',()=>{
  // Canonical decompositions never change once assigned; characters new to the runtime are skipped.
  const unassigned=/\p{Cn}/u;const wrong=[];let compared=0;
  for(const point of scalars()){const input=String.fromCodePoint(point);if(unassigned.test(input))continue;++compared;if(nfdV1(input)!==input.normalize('NFD'))wrong.push(point.toString(16));}
  assert.deepEqual(wrong.slice(0,20),[]);assert.ok(compared>280000);
});
test('frozen NFD orders combining marks like the runtime for every official normalization input',()=>{
  const cases=JSON.parse(gunzipSync(readFileSync(new URL('../../protocol/test-vectors/unicode-nfkc-v1.json.gz',import.meta.url))));
  const decode=value=>value.split(' ').filter(Boolean).map(part=>String.fromCodePoint(parseInt(part,16))).join('');
  const wrong=[];let compared=0;
  for(const [source] of cases){const input=decode(source);if(/\p{Cn}/u.test(input))continue;++compared;if(nfdV1(input)!==input.normalize('NFD'))wrong.push(source);}
  assert.deepEqual(wrong.slice(0,20),[]);assert.ok(compared>30000);
});
test('the frozen default-ignorable list equals the runtime property and NFKC is unchanged',()=>{
  const property=/\p{Default_Ignorable_Code_Point}/u;const listed=point=>confusablesData.default_ignorable.some(([start,end])=>point>=start&&point<=end);
  const wrong=[];for(const point of scalars())if(listed(point)!==property.test(String.fromCodePoint(point)))wrong.push(point.toString(16));
  assert.deepEqual(wrong,[]);
  // The shared decomposition path still gives compatibility forms for search.
  assert.equal(nfkcV1('ｆｉﬁé'),'fifié');assert.equal(nfdV1('ｆｉﬁé'),'ｆｉﬁé');
});
