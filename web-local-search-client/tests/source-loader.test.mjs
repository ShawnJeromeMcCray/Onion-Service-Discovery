import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { ed25519 } from '@noble/curves/ed25519.js';
import { SourceLoader } from '../src/client/source-loader.ts';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { canonicalJson } from '../src/protocol/canonical-json.ts';
import { recordHash, signedMessage } from '../src/protocol/record-format.ts';

const source=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/browser-dataset.json',import.meta.url),'utf8')).sources[0];
const crypto=browserCryptoProviders();
// Public synthetic fixture seed; never a production identity.
const seed=createHash('sha256').update('Onion Service Discovery TEST ONLY browser-source-1').digest();
async function setup(){
 const records=new Map(); const hashes=[];
 for(let generation=1;generation<=37;generation++){
  const record=structuredClone(source.checkpoint);
  record.generation=generation; record.previous_checkpoint_hash=hashes.at(-1)??null; record.cursor='test-'+generation;
  record.signature='ed25519sig:'+Buffer.from(ed25519.sign(signedMessage(record),seed)).toString('base64url');
  const hash=await recordHash(record,crypto.subtle); records.set(hash,record); hashes.push(hash);
 }
 let generation=1; let tamperChunk=false; const requests=[];
 const original=globalThis.fetch;
 globalThis.fetch=async(url,options)=>{
  const parsed=new URL(url); requests.push(parsed.pathname);
  assert.equal(parsed.hostname,source.onion); assert.equal(parsed.search,'');
  assert.equal(options.method,'GET'); assert.equal(options.credentials,'omit'); assert.equal(options.referrerPolicy,'no-referrer');
  let body;
  if(parsed.pathname.endsWith('/node'))body=canonicalJson(source.node_descriptor);
  else if(parsed.pathname.endsWith('/state'))body=JSON.stringify({...source.state,checkpoint_hash:hashes[generation-1],generation,cursor:'test-'+generation});
  else if(parsed.pathname.includes('/snapshots/'))body=canonicalJson(records.get('sha256:'+parsed.pathname.split('/').at(-1)));
  else if(parsed.pathname.includes('/chunks/'))body=tamperChunk?'[]':Buffer.from(source.chunk_base64,'base64').toString('utf8');
  else throw new Error('unexpected generic endpoint');
  return new Response(body,{headers:{'content-type':'application/json'}});
 };
 return {records,hashes,requests,setGeneration:value=>{generation=value;},setTamper:value=>{tamperChunk=value;},restore:()=>{globalThis.fetch=original;}};
}
const selection={onion:source.onion,expectedKey:source.node_descriptor.node_identity_public_key};
test('35 missed generations resume across bounded browser refreshes without accepting partial state',async()=>{
 const fixture=await setup(); const loader=new SourceLoader(crypto);
 try{
  let ledger=(await loader.load(selection,undefined,new AbortController().signal)).ledger;
  fixture.setGeneration(36); fixture.requests.length=0;
  await assert.rejects(loader.load(selection,ledger,new AbortController().signal,value=>{ledger=structuredClone(value);}),{message:'SOURCE_HISTORY_PROGRESS'});
  assert.equal(ledger.generation,1); assert.equal(ledger.checkpointHash,fixture.hashes[0]);
  assert.equal(fixture.requests.filter(path=>path.includes('/snapshots/')).length,33);
  assert.equal(fixture.requests.some(path=>path.includes('/chunks/')),false);
  fixture.requests.length=0;
  const completed=await loader.load(selection,ledger,new AbortController().signal);
  assert.equal(completed.ledger.generation,36); assert.equal(completed.baselineHistoryProven,true);
  assert.ok(fixture.requests.filter(path=>path.includes('/snapshots/')).length<10);
 }finally{fixture.restore();}
});
test('late initial baseline and retry after failed chunks never acquire invented genesis proof',async()=>{
 const fixture=await setup(); const loader=new SourceLoader(crypto);
 try{
  fixture.setGeneration(36); fixture.setTamper(true); let observed;
  await assert.rejects(loader.load(selection,undefined,new AbortController().signal,value=>{observed=structuredClone(value);}),{message:'SNAPSHOT_CHUNK_HASH_INVALID'});
  assert.equal(observed.generation,0); fixture.setTamper(false);
  const baseline=await loader.load(selection,observed,new AbortController().signal);
  assert.equal(baseline.baselineHistoryProven,false);
  fixture.setGeneration(37);
  const next=await loader.load(selection,baseline.ledger,new AbortController().signal);
  assert.equal(next.ledger.generation,37); assert.equal(next.baselineHistoryProven,false);
 }finally{fixture.restore();}
});
test('a changed target cannot reuse an unrelated verified cursor to bypass a broken link',async()=>{
 const fixture=await setup(); const loader=new SourceLoader(crypto);
 try{
  let ledger=(await loader.load(selection,undefined,new AbortController().signal)).ledger;
  fixture.setGeneration(36);
  await assert.rejects(loader.load(selection,ledger,new AbortController().signal,value=>{ledger=structuredClone(value);}),{message:'SOURCE_HISTORY_PROGRESS'});
  const broken=structuredClone(fixture.records.get(fixture.hashes[36]));
  broken.previous_checkpoint_hash=fixture.hashes[29];
  broken.signature='ed25519sig:'+Buffer.from(ed25519.sign(signedMessage(broken),seed)).toString('base64url');
  const hash=await recordHash(broken,crypto.subtle); fixture.records.set(hash,broken); fixture.hashes[36]=hash;
  fixture.setGeneration(37);
  await assert.rejects(loader.load(selection,ledger,new AbortController().signal,value=>{ledger=structuredClone(value);}),{message:'SOURCE_CHECKPOINT_CHAIN_BROKEN'});
  assert.equal(ledger.generation,1); assert.equal(ledger.checkpointHash,fixture.hashes[0]);
 }finally{fixture.restore();}
});
