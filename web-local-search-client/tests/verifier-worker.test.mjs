import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { ed25519 } from '@noble/curves/ed25519.js';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { canonicalJson } from '../src/protocol/canonical-json.ts';
import { recordHash, signedMessage } from '../src/protocol/record-format.ts';

// The worker module runs here as in a browser worker: commands in through onmessage, updates out through postMessage.
const data=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/browser-dataset.json',import.meta.url),'utf8'));
const crypto=browserCryptoProviders(); const source=data.sources[0];
// Public synthetic fixture seed; never a production identity.
const seed=createHash('sha256').update('Onion Service Discovery TEST ONLY browser-source-1').digest();
const records=new Map(); const hashes=[];
for(let generation=1;generation<=6;generation++){
 const record=structuredClone(source.checkpoint); record.generation=generation; record.previous_checkpoint_hash=hashes.at(-1)??null; record.cursor='test-'+generation;
 record.signature='ed25519sig:'+Buffer.from(ed25519.sign(signedMessage(record),seed)).toString('base64url');
 const hash=await recordHash(record,crypto.subtle); records.set(hash,record); hashes.push(hash);
}
let generation=1; let failing=false;
globalThis.fetch=async url=>{
 const parsed=new URL(url); assert.equal(parsed.hostname,source.onion); if(failing) throw new TypeError('offline');
 let body;
 if(parsed.pathname.endsWith('/node'))body=canonicalJson(source.node_descriptor);
 else if(parsed.pathname.endsWith('/state'))body=JSON.stringify({...source.state,checkpoint_hash:hashes[generation-1],generation,cursor:'test-'+generation});
 else if(parsed.pathname.includes('/snapshots/'))body=canonicalJson(records.get('sha256:'+parsed.pathname.split('/').at(-1)));
 else body=Buffer.from(source.chunk_base64,'base64').toString('utf8');
 return new Response(body,{headers:{'content-type':'application/json'}});
};
const out=[]; globalThis.postMessage=message=>out.push(message);
await import('../src/client/verifier-worker.ts');
// Sends one command and collects what the worker posted until it is idle again.
async function send(command){ const start=out.length; globalThis.onmessage({data:command}); return idle(start); }
async function idle(start){
 for(let tries=0;tries<2000;++tries){
  await new Promise(resolve=>setTimeout(resolve,2));
  const views=out.slice(start).filter(message=>message.type==='view');
  if(views.length>0&&!views.at(-1).busy) return out.slice(start);
 }
 throw new Error('worker did not finish');
}
const lastView=messages=>messages.filter(message=>message.type==='view').at(-1);
const flags=view=>view.view.services.map(row=>row.onlyQuarantinedSources);
const now=Date.parse('2026-10-13T00:00:00Z'); const selection=[{onion:source.onion}];

test('records of a Node set aside stay marked until that Node loads again successfully',async()=>{
 await send({op:'configure',allowedSources:data.sources.map(item=>item.onion),deliveryProfile:'direct-cross-origin'});
 generation=5; let view=lastView(await send({op:'load',sources:selection,now}));
 assert.equal(view.sources[0].status,'COMPLETE'); assert.ok(view.view.services.length>0); assert.ok(flags(view).every(flag=>!flag));
 generation=3; view=lastView(await send({op:'load',sources:selection,now}));
 assert.equal(view.sources[0].status,'QUARANTINED'); assert.ok(flags(view).every(flag=>flag));
 // A later failed load, a reload in progress or an import keeps the mark: the records are still only from that Node.
 failing=true; const failed=await send({op:'load',sources:selection,now}); failing=false;
 assert.equal(lastView(failed).sources[0].status,'FAILED');
 for(const update of failed.filter(message=>message.type==='view')) assert.ok(flags(update).every(flag=>flag));
 generation=6; view=lastView(await send({op:'load',sources:selection,now}));
 assert.equal(view.sources[0].status,'COMPLETE'); assert.ok(flags(view).every(flag=>!flag));
 generation=5; view=lastView(await send({op:'load',sources:selection,now}));
 assert.equal(view.sources[0].status,'QUARANTINED');
 view=lastView(await send({op:'clear-index'})); assert.equal(view.view.recordCount,0);
});

test('a cancelled import adds nothing and its error comes before the final update',async()=>{
 const bytes=new TextEncoder().encode(JSON.stringify(data.bundle)).buffer;
 const start=out.length; globalThis.onmessage({data:{op:'import-records',bytes}}); globalThis.onmessage({data:{op:'cancel'}});
 const messages=await idle(start);
 const error=messages.findIndex(message=>message.type==='error'&&message.op==='import-records');
 assert.equal(messages[error].code,'IMPORT_CANCELLED');
 const ended=messages.findIndex(message=>message.type==='view'&&!message.busy);
 assert.ok(error<ended,'the error precedes the update that ends the operation');
 assert.equal(lastView(messages).view.recordCount,0);
 // Without a cancel the same bundle is imported.
 assert.ok(lastView(await send({op:'import-records',bytes})).view.recordCount>0);
});
