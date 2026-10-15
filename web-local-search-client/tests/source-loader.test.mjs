import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { ed25519 } from '@noble/curves/ed25519.js';
import { SourceLoader } from '../src/client/source-loader.ts';
import { SourceWitnesses } from '../src/client/source-witnesses.ts';
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
test('a conflicting signed ancestor is retained and quarantines the source before admission',async()=>{
 const fixture=await setup(); const loader=new SourceLoader(crypto); const witnesses=new SourceWitnesses(); let ledger;
 const observe=(value,evidence)=>{
  const conflict=witnesses.observe(evidence); value.equivocated ||= conflict; ledger=structuredClone(value);
  if(value.equivocated)throw new Error('SOURCE_CHECKPOINT_EQUIVOCATION');
 };
 try{
  ledger=(await loader.load(selection,undefined,new AbortController().signal,observe)).ledger;
  fixture.setGeneration(36);
  await assert.rejects(loader.load(selection,ledger,new AbortController().signal,observe),{message:'SOURCE_HISTORY_PROGRESS'});
  const fork=structuredClone(fixture.records.get(fixture.hashes[35])); fork.cursor='different-36';
  fork.signature='ed25519sig:'+Buffer.from(ed25519.sign(signedMessage(fork),seed)).toString('base64url');
  const forkHash=await recordHash(fork,crypto.subtle); fixture.records.set(forkHash,fork);
  const next=structuredClone(fixture.records.get(fixture.hashes[36])); next.previous_checkpoint_hash=forkHash;
  next.signature='ed25519sig:'+Buffer.from(ed25519.sign(signedMessage(next),seed)).toString('base64url');
  const nextHash=await recordHash(next,crypto.subtle); fixture.records.set(nextHash,next); fixture.hashes[36]=nextHash;
  fixture.setGeneration(37);
  await assert.rejects(loader.load(selection,ledger,new AbortController().signal,observe),{message:'SOURCE_CHECKPOINT_EQUIVOCATION'});
  assert.equal(ledger.generation,1); assert.equal(ledger.equivocated,true);
  const pair=witnesses.forkEvidence(selection.expectedKey);
  assert.deepEqual(pair.map(record=>record.contentHash),[fixture.hashes[35],forkHash]);
  await assert.rejects(loader.load(selection,ledger,new AbortController().signal,observe),{message:'SOURCE_EQUIVOCATION_PRESERVED'});
 }finally{fixture.restore();}
});

// Serves all three fixture Nodes by host name; `delay` holds every answer back (ignoring aborts, like a slow circuit).
function serveAll({delay=0}={}){
 const sources=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/browser-dataset.json',import.meta.url),'utf8')).sources;
 const byOnion=new Map(sources.map(item=>[item.onion,item])); const original=globalThis.fetch;
 globalThis.fetch=async url=>{
  const parsed=new URL(url); const node=byOnion.get(parsed.hostname); if(node===undefined)throw new Error('unexpected host');
  if(delay>0)await new Promise(resolve=>setTimeout(resolve,delay));
  let body;
  if(parsed.pathname.endsWith('/node'))body=canonicalJson(node.node_descriptor);
  else if(parsed.pathname.endsWith('/state'))body=JSON.stringify(node.state);
  else if(parsed.pathname.includes('/snapshots/'))body=canonicalJson(node.checkpoint);
  else if(parsed.pathname.includes('/chunks/'))body=Buffer.from(node.chunk_base64,'base64').toString('utf8');
  else throw new Error('unexpected generic endpoint');
  return new Response(body,{headers:{'content-type':'application/json'}});
 };
 return {sources,restore:()=>{globalThis.fetch=original;}};
}
function instrumented({before=async()=>{}}={}){
 const counter={calls:0};
 return {counter,crypto:{...crypto,verifyEd25519:async(...args)=>{counter.calls++;await before(counter.calls);return crypto.verifyEd25519(...args);}}};
}
const signatures=record=>record.record_type==='service_identity_key_rotation'?2:1;
const pick=node=>({onion:node.onion,expectedKey:node.node_descriptor.node_identity_public_key});

test('a record verified for one Node is not verified again for the next',async()=>{
 const served=serveAll(); const {counter,crypto:counting}=instrumented(); const loader=new SourceLoader(counting);
 try{
  const [first,second]=served.sources; const chunk=node=>JSON.parse(Buffer.from(node.chunk_base64,'base64').toString('utf8'));
  await loader.load(pick(first),undefined,new AbortController().signal);
  // Descriptor and checkpoint are always checked; chunk records once each.
  assert.equal(counter.calls,2+chunk(first).reduce((sum,record)=>sum+signatures(record),0));
  const seen=new Set(chunk(first).map(record=>canonicalJson(record))); counter.calls=0;
  const result=await loader.load(pick(second),undefined,new AbortController().signal);
  const fresh=chunk(second).filter(record=>!seen.has(canonicalJson(record)));
  assert.ok(fresh.length<chunk(second).length,'the fixture Nodes share records');
  assert.equal(counter.calls,2+fresh.reduce((sum,record)=>sum+signatures(record),0));
  assert.equal(result.records.length,1+chunk(second).length);
  // Forgetting drops the memory: the same Node is checked in full again.
  loader.forgetVerified(); counter.calls=0;
  await loader.load(pick(second),undefined,new AbortController().signal);
  assert.equal(counter.calls,2+chunk(second).reduce((sum,record)=>sum+signatures(record),0));
 }finally{served.restore();}
});

test('slow signature checks do not use up the network time of a source',async()=>{
 const served=serveAll(); const {crypto:slow}=instrumented({before:()=>new Promise(resolve=>setTimeout(resolve,40))});
 const loader=new SourceLoader(slow,'direct-cross-origin',{networkBudgetMs:30}); const progress=[];
 try{
  const result=await loader.load(pick(served.sources[0]),undefined,new AbortController().signal,undefined,(records,total)=>progress.push([records,total]));
  assert.equal(result.records.length,8);
  assert.deepEqual(progress,[[7,7]]);
 }finally{served.restore();}
});

test('network time beyond the budget refuses the source without partial data',async()=>{
 const served=serveAll({delay:20}); const loader=new SourceLoader(crypto,'direct-cross-origin',{networkBudgetMs:30});
 try{
  await assert.rejects(loader.load(pick(served.sources[0]),undefined,new AbortController().signal),{message:'SOURCE_TIME_BUDGET_EXHAUSTED'});
 }finally{served.restore();}
});

test('cancel while signatures are checked stops the load',async()=>{
 const served=serveAll(); const controller=new AbortController();
 const {crypto:cancelling}=instrumented({before:async calls=>{if(calls===4)controller.abort();}});
 try{
  await assert.rejects(new SourceLoader(cancelling).load(pick(served.sources[0]),undefined,controller.signal),{message:'SOURCE_CANCELLED'});
 }finally{served.restore();}
});
