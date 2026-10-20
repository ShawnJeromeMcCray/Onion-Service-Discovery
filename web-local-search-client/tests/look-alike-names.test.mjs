import {test} from 'node:test';
import assert from 'node:assert/strict';
import {pinNameMatcher} from '../src/client/view-model.ts';
import {importedPinMatcher,lookAlikeEntries,lookAlikePins,pinLookAlikeMatcher,sameNameEntries} from '../src/client/look-alike-names.ts';
const pin=(genesisKey,label)=>({genesisKey,contextOnion:'x.onion',anchorHash:'sha256:a',latestHash:'sha256:a',highestRevision:1,label,conflicted:false});
const row=(genesisKey,name,status='PUBLISHED')=>({genesisKey,name,status});
test('a look-alike of a pinned label under another key is flagged; the same name stays with the existing warning',()=>{
  const pins=[pin('ed25519:pinned','paypal')];const resembles=pinLookAlikeMatcher(pins,[]);const same=pinNameMatcher(pins);
  assert.equal(resembles(row('ed25519:other','pаypal')),'paypal','Cyrillic а');
  assert.equal(resembles(row('ed25519:other','PaypaI')),'paypal','capital I for small l');
  assert.equal(resembles(row('ed25519:other','раураӏ')),'paypal','whole-script Cyrillic');
  assert.equal(resembles(row('ed25519:other','pay‍pal')),'paypal','an invisible joiner the same-name form keeps');
  for(const name of ['paypal','PAYPAL',' paypal ']){assert.equal(resembles(row('ed25519:other',name)),undefined,name);assert.equal(same(row('ed25519:other',name)),'paypal',name);}
  assert.equal(resembles(row('ed25519:pinned','pаypal')),undefined,'the pinned key itself');
  assert.equal(resembles(row('ed25519:other','pаypal','FORKED')),undefined,'a fork shows no self-declared name');
  for(const name of ['paypa','PayPal Fan Club','Synthetic Library']) assert.equal(resembles(row('ed25519:other',name)),undefined,name);
  assert.equal(pinLookAlikeMatcher([pin('ed25519:pinned','mail')],[])(row('ed25519:other','MАIL')),'mail','case folded before the skeleton');
});
test('the current name of a pinned identity counts as well as its label',()=>{
  const pins=[pin('ed25519:pinned','My bank')];const rows=[row('ed25519:pinned','Paypal')];const resembles=pinLookAlikeMatcher(pins,rows);
  assert.equal(resembles(row('ed25519:other','Pаypal')),'My bank');
  assert.equal(resembles(row('ed25519:other','Paypal')),'My bank','the same current name under another key');
  assert.equal(pinNameMatcher(pins)(row('ed25519:other','Paypal')),undefined,'the label check alone would miss it');
  assert.equal(pinLookAlikeMatcher(pins,[row('ed25519:pinned','Paypal','FORKED')])(row('ed25519:other','Pаypal')),undefined);
});
test('entries whose names look alike under different keys are marked on both sides, identical names are not',()=>{
  const rows=[row('a','paypal'),row('b','pаypal'),row('c','PAYPAL'),row('d','Synthetic Library'),row('e','Synthetic Library'),row('f','modern'),row('g','rnodern'),row('h','Synthetic Climate Archive')];
  assert.deepEqual([...lookAlikeEntries(rows)].sort(),['a','b','c','f','g']);
  assert.deepEqual([...lookAlikeEntries([row('a','Library'),row('b','Librаry','WITHDRAWN')])],[],'withdrawn entries are not shown');
  assert.deepEqual([...lookAlikeEntries([row('a','Library'),row('b','Librarian'),row('c','Bibliothèque'),row('d','Bibliotheque')])],[]);
});
test('ten thousand entries are compared by grouping, not by pairs',()=>{
  // Letters the skeleton leaves alone and never merges: no 0/o, 1/l/I, m/rn.
  const letters='bcdfghjkpqstvwxyzaeu';const suffix=index=>{let text='';do{text+=letters[index%20];index=Math.floor(index/20);}while(index>0);return text;};
  const rows=Array.from({length:10000},(_,index)=>row('key-'+index,'Synthetic Service '+suffix(index)));
  rows.push(row('impostor','Synthetic Sеrvice '+suffix(1234)));
  const started=performance.now();const flagged=lookAlikeEntries(rows);const elapsed=performance.now()-started;
  assert.deepEqual([...flagged].sort(),['impostor','key-1234']);assert.ok(elapsed<5000,'took '+Math.round(elapsed)+' ms');
});
test('pin labels and imported pins are compared with the pins already held',()=>{
  assert.deepEqual([...lookAlikePins([pin('a','Synthetic Library'),pin('b','Synthetic Librаry'),pin('c','Synthetic Climate Archive')])].sort(),['a','b']);
  assert.deepEqual([...lookAlikePins([pin('a','Library'),pin('b','LIBRARY')])],[],'the same label is not a look-alike');
  const resembles=importedPinMatcher([pin('ed25519:held','Synthetic Library')],[]);
  assert.deepEqual(resembles(pin('ed25519:new','Synthetic Librаry')),{label:'Synthetic Library',same:false});
  assert.deepEqual(resembles(pin('ed25519:new','SYNTHETIC LIBRARY')),{label:'Synthetic Library',same:true});
  assert.equal(resembles(pin('ed25519:held','Synthetic Librаry')),undefined,'the same key is an update, not an impostor');
  assert.equal(resembles(pin('ed25519:new','Synthetic Climate Archive')),undefined);
});

test('the same name under two identity keys is flagged on both entries; withdrawn entries are not',()=>{
  assert.deepEqual([...sameNameEntries([row('a','Riseup Mail'),row('b','riseup  MAIL'),row('c','Example'),row('d','Riseup Mail','WITHDRAWN')])].sort(),['a','b']);
  assert.deepEqual([...sameNameEntries([row('a','Example'),row('b','Exarnple')])],[],'a look-alike is not the same name');
});
