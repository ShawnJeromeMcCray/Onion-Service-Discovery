import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {browserCryptoProviders} from '../src/protocol/crypto-providers.ts';
import {verifyRecord,verifyWire} from '../src/protocol/record-verifier.ts';
import {buildView,pinIdentity,localSearch,metadataWarnings} from '../src/client/view-model.ts';
import {parsePinBackup,serializePins,mergePins,classifyPinImport} from '../src/client/pins.ts';
const crypto=browserCryptoProviders();const f=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/signed-records.json',import.meta.url),'utf8'));const stories=f.stories;
test('pins follow authorized rotation/migration and preserve a fork warning',async()=>{
  const genesis=await verifyRecord(stories.genesis,crypto);const initial=buildView([genesis],[]);const pin=pinIdentity(initial.services[0]);
  const rotation=await verifyRecord(stories.rotation,crypto);const successor=await verifyRecord(stories.rotated,crypto);
  const rotated=buildView([genesis,rotation,successor],[pin]);assert.equal(rotated.pins[0].status,'CONTINUITY_VALID');assert.equal(rotated.pins[0].pin.genesisKey,pin.genesisKey);assert.equal(rotated.pins[0].pin.highestRevision,2);
  const migration=await verifyRecord(stories.migration,crypto);const migrated=await verifyRecord(stories.migrated,crypto);assert.equal(buildView([genesis,migration,migrated],[pin]).pins[0].status,'MIGRATED');
  const ordinary=await verifyRecord(stories.successor,crypto);const forked=buildView([genesis,ordinary,rotation,successor],[pin]);assert.equal(forked.pins[0].status,'FORKED');assert.equal(forked.pins[0].pin.conflicted,true);
  assert.equal(buildView([genesis,ordinary],[forked.pins[0].pin]).pins[0].status,'FORKED');
  assert.equal(buildView([genesis],[rotated.pins[0].pin]).pins[0].status,'REVISION_DOWNGRADE');
});
test('pin backup is explicit, bounded and cannot lower an existing high-water mark by derivation',async()=>{
  const genesis=await verifyRecord(stories.genesis,crypto);const pin=pinIdentity(buildView([genesis],[]).services[0]);const bytes=new TextEncoder().encode(serializePins([pin]));assert.deepEqual(parsePinBackup(bytes,crypto),[pin]);
  const bad=JSON.parse(new TextDecoder().decode(bytes));bad.pins[0].highestRevision=0;assert.throws(()=>parsePinBackup(new TextEncoder().encode(JSON.stringify(bad)),crypto),{message:'PIN_BACKUP_INVALID'});
});
test('local search excludes withdrawal without sending anything to a provider',async()=>{
  const bundle=JSON.parse(readFileSync(new URL('../public/demo.json',import.meta.url),'utf8'));const records=await Promise.all(bundle.records.map(record=>verifyRecord(record,crypto)));const view=buildView(records,[]);
  assert.equal(localSearch(view.services,'library').length,1);assert.equal(localSearch(view.services,'withdrawn').length,0);assert.equal(localSearch(view.services,'climate','de').length,0);
});
test('mature browser verifier works without native WebCrypto',async()=>{
  const descriptor=Object.getOwnPropertyDescriptor(globalThis,'crypto');
  try{Object.defineProperty(globalThis,'crypto',{configurable:true,value:undefined});const fallback=browserCryptoProviders();const fixture=f.cases.find(item=>item.id==='genesis');const result=await verifyWire(new Uint8Array(Buffer.from(fixture.input_base64,'base64')),fallback);assert.equal(result.contentHash,fixture.expected_hash);}finally{if(descriptor)Object.defineProperty(globalThis,'crypto',descriptor);}
});

test('valid minimal manifests remain searchable without optional description, links or timestamp',async()=>{
  const fixture=f.cases.find(item=>item.id==='manifest-optional-fields-absent');
  const record=await verifyWire(new Uint8Array(Buffer.from(fixture.input_base64,'base64')),crypto);
  const view=buildView([record],[]);assert.equal(view.services[0].description,'');assert.equal(localSearch(view.services,'library').length,1);
});

test('Unicode presentation warnings do not normalize signed metadata',()=>{
  assert.equal(metadataWarnings('Library','Plain description').length,0);
  assert.equal(metadataWarnings('Librаry','').length,1); // Cyrillic a with Latin letters
  assert.equal(metadataWarnings('Library','\u202eDirection changed').length,1);
  assert.equal(metadataWarnings('Bibliothèque','Mehrsprachige Bücher').length,0);
});

test('a maximal emoji name is cut at code points, so the saved pin restores',async()=>{
  const genesis=await verifyRecord(stories.genesis,crypto);const row={...buildView([genesis],[]).services[0],name:'A'+'\u{1F600}'.repeat(127)};
  const pin=pinIdentity(row);assert.equal([...pin.label].length,128);assert.equal(pin.label.isWellFormed(),true);
  assert.deepEqual(parsePinBackup(new TextEncoder().encode(serializePins([pin])),crypto),[pin]);
  const long=pinIdentity({...row,name:'\u{1F600}'.repeat(200)});assert.equal([...long.label].length,128);assert.equal(long.label.isWellFormed(),true);
});

test('importing pins keeps existing ones: new pins are added, conflicts skipped, revisions only raised',async()=>{
  const genesis=await verifyRecord(stories.genesis,crypto);const pin=pinIdentity(buildView([genesis],[]).services[0]);
  const newer={...pin,latestHash:'sha256:'+'1'.repeat(64),highestRevision:pin.highestRevision+1};
  const otherAnchor={...pin,anchorHash:'sha256:'+'2'.repeat(64),label:'Imported impostor'};
  const sameRevisionOtherHash={...pin,latestHash:'sha256:'+'3'.repeat(64)};
  const stranger={...pin,genesisKey:'ed25519:'+'A'.repeat(43)};
  assert.equal(classifyPinImport(undefined,pin),'NEW');assert.equal(classifyPinImport(pin,{...pin}),'SAME');
  assert.equal(classifyPinImport(pin,newer),'UPDATES_REVISION');assert.equal(classifyPinImport(pin,otherAnchor),'CONFLICT_KEEPS_EXISTING');
  assert.equal(classifyPinImport(pin,sameRevisionOtherHash),'CONFLICT_KEEPS_EXISTING');assert.equal(classifyPinImport(newer,pin),'SAME');
  const raised=mergePins([pin],[newer]);assert.deepEqual([raised.added,raised.updated,raised.skipped],[0,1,0]);assert.equal(raised.pins[0].highestRevision,pin.highestRevision+1);
  const kept=mergePins([pin],[otherAnchor,stranger]);assert.deepEqual([kept.added,kept.updated,kept.skipped],[1,0,1]);
  assert.equal(kept.pins.find(item=>item.genesisKey===pin.genesisKey).label,pin.label);
  assert.equal(mergePins([newer],[pin]).pins[0].highestRevision,newer.highestRevision);
  const many=Array.from({length:256},(_,index)=>({...pin,genesisKey:'ed25519:'+String(index).padStart(43,'A')}));
  assert.throws(()=>mergePins(many,[stranger]),{message:'PIN_BUDGET_EXHAUSTED'});
});

