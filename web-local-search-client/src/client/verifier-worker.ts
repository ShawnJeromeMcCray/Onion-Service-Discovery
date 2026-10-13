import { browserCryptoProviders } from '../protocol/crypto-providers.ts';
import { parseStrictJson } from '../protocol/strict-json.ts';
import { verifyRecord, requiredObject, type VerifiedRecord } from '../protocol/record-verifier.ts';
import { SourceLoader, type SourceSelection, type SourceLedger, type DeliveryProfile } from './source-loader.ts';
import { buildView, pinIdentity, type LocalPin } from './view-model.ts';
import { parsePinBackup } from './pins.ts';

type Command = {op:'configure';allowedSources:string[];deliveryProfile:DeliveryProfile}|{op:'load';sources:SourceSelection[]}|{op:'cancel'}|{op:'import-records';bytes:ArrayBuffer;synthetic?:boolean}|{op:'pin';genesisKey:string}|{op:'unpin';genesisKey:string}|{op:'restore-pins';bytes:ArrayBuffer}|{op:'preview-pins';bytes:ArrayBuffer}|{op:'clear-index'}|{op:'clear-all'};
const scope=globalThis as unknown as {onmessage:((event:MessageEvent<Command>)=>void)|null;postMessage:(data:unknown)=>void};
const crypto=browserCryptoProviders(); let loader=new SourceLoader(crypto);
const records=new Map<string,VerifiedRecord>(); const ledgers=new Map<string,SourceLedger>(); const witnesses=new Map<string,VerifiedRecord>();
let pins:LocalPin[]=[]; let allowed=new Set<string>(); let controller:AbortController|null=null; let busy=false; let synthetic=false;
interface SourceStatus {onion:string;status:'LOADING'|'PARTIAL'|'COMPLETE'|'STALE'|'FAILED'|'QUARANTINED';nodeKey:string|null;generation:number|null;error:string|null;baselineHistoryProven:boolean;}
let statuses:SourceStatus[]=[];
function code(error:unknown):string { return error instanceof Error&&/^[A-Z_]{1,80}$/.test(error.message)?error.message:'CLIENT_OPERATION_FAILED'; }
function publish():void {
  const view=buildView([...records.values()],pins); pins=view.pins.map(row=>row.pin);
  scope.postMessage({type:'view',view,sources:statuses,synthetic,busy,witnessCount:witnesses.size});
}
function admit(incoming:readonly VerifiedRecord[]):void {
  const added=new Set(incoming.filter(record=>!records.has(record.contentHash)).map(record=>record.contentHash));
  if(records.size+added.size>10000) throw new Error('CLIENT_RECORD_BUDGET_EXHAUSTED');
  for(const record of incoming) records.set(record.contentHash,record);
}
async function command(message:Command):Promise<void> {
  if(message.op==='cancel') { controller?.abort(); return; }
  if(message.op==='configure') {
    if(busy||message.allowedSources.length>16||!['client-host-proxy','direct-cross-origin'].includes(message.deliveryProfile)) throw new Error('CLIENT_CONFIGURATION_INVALID');
    loader=new SourceLoader(crypto,message.deliveryProfile);
    allowed=new Set(message.allowedSources.map(onion=>loader.validate({onion}).onion)); publish(); return;
  }
  if(message.op==='pin') {
    const row=buildView([...records.values()],pins).services.find(item=>item.genesisKey===message.genesisKey);
    if(row===undefined||pins.length>=256) throw new Error('PIN_CONTEXT_UNAVAILABLE');
    if(!pins.some(pin=>pin.genesisKey===row.genesisKey)) pins.push(pinIdentity(row)); publish(); return;
  }
  if(message.op==='unpin') { pins=pins.filter(pin=>pin.genesisKey!==message.genesisKey); publish(); return; }
  if(message.op==='restore-pins'||message.op==='preview-pins') {
    const imported=parsePinBackup(new Uint8Array(message.bytes),crypto);
    if(message.op==='preview-pins') { scope.postMessage({type:'pin-preview',pins:imported}); return; }
    const merged=new Map(pins.map(pin=>[pin.genesisKey,pin]));
    for(const pin of imported) {
      const existing=merged.get(pin.genesisKey);
      if(existing===undefined) merged.set(pin.genesisKey,pin);
      else if(existing.latestHash===pin.latestHash&&existing.contextOnion===pin.contextOnion) merged.set(pin.genesisKey,{...existing,highestRevision:Math.max(existing.highestRevision,pin.highestRevision),conflicted:existing.conflicted||pin.conflicted});
      else throw new Error('PIN_IMPORT_CONFLICT');
    }
    if(merged.size>256) throw new Error('PIN_BUDGET_EXHAUSTED'); pins=[...merged.values()]; publish(); return;
  }
  if(busy) throw new Error('CLIENT_BUSY');
  if(message.op==='clear-all') { records.clear(); ledgers.clear(); witnesses.clear(); loader.clear(); pins=[]; statuses=[]; synthetic=false; publish(); return; }
  if(message.op==='clear-index') { records.clear(); statuses=[]; synthetic=false; publish(); return; }
  busy=true; publish();
  try {
    if(message.op==='import-records') {
      const bundle=parseStrictJson(new Uint8Array(message.bytes),1048576);
      const incoming=bundle.protocol==='Onion Service Discovery'?[bundle]:bundle.bundle_version===1&&Object.keys(bundle).length===2&&Array.isArray(bundle.records)?bundle.records:null;
      if(incoming===null)throw new Error('IMPORT_FORMAT_INVALID');
      const verified:VerifiedRecord[]=[];
      for(const item of incoming) { verified.push(await verifyRecord(requiredObject(item),crypto)); if(verified.length%32===0) await new Promise(resolve=>setTimeout(resolve,0)); }
      if(message.synthetic===true) { records.clear(); statuses=[]; synthetic=true; }
      admit(verified);
    } else if(message.op==='load') {
      if(message.sources.length<1||message.sources.length>16||new Set(message.sources.map(source=>source.onion)).size!==message.sources.length) throw new Error('CLIENT_CONFIGURATION_INVALID');
      for(const selection of message.sources) { loader.validate(selection); if(!allowed.has(selection.onion)) throw new Error('SOURCE_NOT_IN_HOST_ALLOWLIST'); }
      if(synthetic) { records.clear(); synthetic=false; }
      controller=new AbortController();
      statuses=message.sources.map(selection=>({onion:selection.onion,status:'LOADING',nodeKey:null,generation:null,error:null,baselineHistoryProven:false}));
      for(let index=0;index<message.sources.length;++index) {
        const selection=message.sources[index]!; const status=statuses[index]!;
        try {
          const result=await loader.load(selection,ledgers.get(selection.onion),controller.signal,(ledger,evidence)=>{if(!witnesses.has(evidence.contentHash)&&witnesses.size>=512) throw new Error('WITNESS_BUDGET_EXHAUSTED');ledgers.set(selection.onion,structuredClone(ledger));witnesses.set(evidence.contentHash,evidence);});
          admit(result.records); ledgers.set(selection.onion,result.ledger);
          const age=Date.now()-Date.parse(result.createdAt); status.status=age>604800000||age< -300000?'STALE':'COMPLETE'; status.nodeKey=result.ledger.nodeKey; status.generation=result.ledger.generation; status.baselineHistoryProven=result.baselineHistoryProven;
        } catch(error) { status.error=code(error); status.status=status.error==='SOURCE_HISTORY_PROGRESS'?'PARTIAL':/EQUIVOCATION|ROLLBACK|FORK|KEY_CHANGED/.test(status.error)?'QUARANTINED':'FAILED'; }
        publish(); if(controller.signal.aborted) break;
      }
    }
  } finally { controller=null; busy=false; publish(); }
}
scope.onmessage=event=>{void command(event.data).catch(error=>{scope.postMessage({type:'error',code:code(error)});});};
