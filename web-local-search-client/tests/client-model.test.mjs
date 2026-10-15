import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {browserCryptoProviders} from '../src/protocol/crypto-providers.ts';
import {verifyRecord,verifyWire} from '../src/protocol/record-verifier.ts';
import {buildView,pinIdentity,localSearch,metadataWarnings,pinNameMatcher} from '../src/client/view-model.ts';
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
test('a pinned name under another identity key is flagged, case, width and invisible characters ignored',()=>{
  const pin={genesisKey:'ed25519:pinned',contextOnion:'x.onion',anchorHash:'sha256:a',latestHash:'sha256:a',highestRevision:1,label:'Library',conflicted:false};
  const imitates=pinNameMatcher([pin]);
  assert.equal(imitates({genesisKey:'ed25519:pinned',name:'Library'}),undefined);
  for(const name of ['LIBRARY','Ｌｉｂｒａｒｙ','Li\u200bbrary',' Library ']) assert.equal(imitates({genesisKey:'ed25519:other',name}),'Library',name);
  assert.equal(imitates({genesisKey:'ed25519:other',name:'Library of Things'}),undefined);
  assert.equal(pinNameMatcher([])({genesisKey:'ed25519:other',name:'Library'}),undefined);
});
test('invisible characters are reported; emoji presentation selectors are not',()=>{
  for(const name of ['Li\u200bbrary','Library\u00ad','Lib\u{e0041}rary','\ufeffLibrary']) assert.ok(metadataWarnings(name,'').some(warning=>warning.includes('invisible')),JSON.stringify(name));
  assert.ok(metadataWarnings('Library','A quiet\u2060room').some(warning=>warning.includes('invisible')));
  for(const name of ['Café ❤\ufe0f','Family 👨\u200d👩\u200d👧','Library']) assert.ok(!metadataWarnings(name,'').some(warning=>warning.includes('invisible')),name);
});
test('pin states for withdrawal, absence, incomplete history and another key on the pinned Onion',async()=>{
  const sequences=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/identity-sequences.json',import.meta.url),'utf8')).cases;
  const records=id=>Promise.all(sequences.find(item=>item.id===id).records.map(record=>verifyRecord(record,crypto)));
  const withdrawal=await records('withdrawal-retains-history');
  const genesis=withdrawal.find(record=>JSON.parse(record.canonicalWire).revision===1);
  const pin=pinIdentity(buildView([genesis],[]).services[0]);
  assert.equal(buildView(withdrawal,[pin]).pins[0].status,'WITHDRAWN');
  assert.equal(buildView([],[pin]).pins[0].status,'NOT_IN_INDEX');
  assert.equal(buildView([genesis],[{...pin,anchorHash:'sha256:'+'9'.repeat(64)}]).pins[0].status,'HISTORY_INCOMPLETE');
  const [first,second]=await records('same-onion-distinct-identities-remain-separate');
  assert.ok(buildView([first,second],[]).services.every(row=>row.addressConflict));
  assert.equal(buildView([second],[pinIdentity(buildView([first],[]).services[0])]).pins[0].status,'IDENTITY_CHANGED');
});
test('direction controls and mixed writing systems are reported',()=>{
  assert.ok(metadataWarnings('Library\u202e','').some(warning=>warning.includes('direction-changing')));
  assert.ok(metadataWarnings('L\u0456brary','').some(warning=>warning.includes('writing systems')));
  assert.deepEqual(metadataWarnings('Library','A plain description'),[]);
});
test('the view says which source supplied which revision, the current key, earlier onions and observers',async()=>{
  const genesis=await verifyRecord(stories.genesis,crypto);const rotation=await verifyRecord(stories.rotation,crypto);const rotated=await verifyRecord(stories.rotated,crypto);
  const provenance=new Map([[genesis.contentHash,new Set(['a.onion','b.onion'])],[rotation.contentHash,new Set(['a.onion'])],[rotated.contentHash,new Set(['a.onion'])]]);
  const row=buildView([genesis,rotation,rotated],[],{provenance,quarantined:new Set(['a.onion'])}).services[0];
  assert.equal(row.revision,2);assert.notEqual(row.currentKey,row.genesisKey);assert.equal(row.currentKey,stories.rotated.service_identity_public_key);
  assert.deepEqual(row.sources,['a.onion']);assert.deepEqual(row.sourceRevisions,[{source:'a.onion',revision:2},{source:'b.onion',revision:1}]);
  assert.equal(row.onlyQuarantinedSources,true);
  assert.equal(buildView([genesis,rotation,rotated],[],{provenance}).services[0].onlyQuarantinedSources,false);
  assert.deepEqual(buildView([genesis],[]).services[0].sources,[]);
  const moved=buildView([genesis,await verifyRecord(stories.migration,crypto),await verifyRecord(stories.migrated,crypto)],[]).services[0];
  assert.equal(moved.onion,stories.migrated.onion);assert.deepEqual(moved.previousOnions,[stories.genesis.onion]);
  const sequences=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/identity-sequences.json',import.meta.url),'utf8')).cases;
  const records=await Promise.all(sequences.find(item=>item.id==='imported-observation-does-not-change-identity').records.map(record=>verifyRecord(record,crypto)));
  const observation=JSON.parse(records.find(record=>record.type==='direct_observation').canonicalWire);
  const observed=buildView(records,[]).services[0];
  assert.deepEqual(observed.observers,[observation.node_identity_public_key]);assert.equal(observed.latestObservationAt,observation.observed_at);assert.equal(observed.observationClaims,1);
});
