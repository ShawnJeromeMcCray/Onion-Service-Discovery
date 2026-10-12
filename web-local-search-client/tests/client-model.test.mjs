import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {browserCryptoProviders} from '../src/protocol/crypto-providers.ts';
import {verifyRecord,verifyWire} from '../src/protocol/record-verifier.ts';
import {buildView,pinIdentity,localSearch} from '../src/client/view-model.ts';
import {parsePinBackup,serializePins} from '../src/client/pins.ts';
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
