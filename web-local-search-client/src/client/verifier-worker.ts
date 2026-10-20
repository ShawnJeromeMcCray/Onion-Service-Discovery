import { browserCryptoProviders } from '../protocol/crypto-providers.ts';
import { parseStrictJson } from '../protocol/strict-json.ts';
import { verifyRecord, requiredObject, type VerifiedRecord } from '../protocol/record-verifier.ts';
import { SourceLoader, type SourceSelection, type SourceLedger, type DeliveryProfile } from './source-loader.ts';
import { buildView, pinIdentity, type LocalPin } from './view-model.ts';
import { classifyPinImport, mergePins, parsePinBackup } from './pins.ts';
import { SourceWitnesses } from './source-witnesses.ts';

type Command = {op:'configure';allowedSources:string[];deliveryProfile:DeliveryProfile;visitorSources?:boolean}|{op:'add-source';onion:string;expectedKey:string}|{op:'remove-source';onion:string}|{op:'export-evidence';onion:string}|{op:'load';sources:SourceSelection[];now?:number}|{op:'cancel'}|{op:'import-records';bytes:ArrayBuffer;synthetic?:boolean}|{op:'pin';genesisKey:string}|{op:'unpin';genesisKey:string}|{op:'restore-pins';bytes:ArrayBuffer;origin:'profile'|'backup'}|{op:'preview-pins';bytes:ArrayBuffer}|{op:'clear-index'}|{op:'clear-all'};
const scope=globalThis as unknown as {onmessage:((event:MessageEvent<Command>)=>void)|null;postMessage:(data:unknown)=>void};
const crypto=browserCryptoProviders(); let loader=new SourceLoader(crypto);
const records=new Map<string,VerifiedRecord>(); const ledgers=new Map<string,SourceLedger>(); const witnesses=new SourceWitnesses();
// Which origin supplied each admitted record: a loaded Node's onion, 'local file' or 'demonstration'.
const provenance=new Map<string,Set<string>>();
// Visitor-chosen Nodes (onion -> expected key) exist only when the Client Host explicitly relays them.
const MAX_VISITOR_SOURCES=8; const visitor=new Map<string,string>(); let visitorEnabled=false;
let pins:LocalPin[]=[]; let allowed=new Set<string>(); let controller:AbortController|null=null; let busy=false; let synthetic=false;
interface SourceStatus {onion:string;status:'LOADING'|'PARTIAL'|'COMPLETE'|'STALE'|'FAILED'|'QUARANTINED'|'CANCELLED'|'NOT_STARTED';nodeKey:string|null;generation:number|null;createdAt:string|null;error:string|null;baselineHistoryProven:boolean;evidenceAvailable:boolean;}
let statuses:SourceStatus[]=[];
// The view is rebuilt only when its inputs change; status-only updates reuse it, and viewVersion lets the page skip re-rendering results.
let recordsVersion=0; let pinsVersion=0; let viewVersion=0; let cached:{key:string;view:ReturnType<typeof buildView>}|null=null;
function currentView(quarantined:ReadonlySet<string>):ReturnType<typeof buildView> {
  const key=recordsVersion+'/'+pinsVersion+'/'+[...quarantined].sort().join(',');
  if(cached===null||cached.key!==key) { cached={key,view:buildView([...records.values()],pins,{provenance,quarantined})}; ++viewVersion; }
  return cached.view;
}
function code(error:unknown):string { return error instanceof Error&&/^[A-Z_]{1,80}$/.test(error.message)?error.message:'CLIENT_OPERATION_FAILED'; }
function publish():void {
  const quarantined=new Set(statuses.filter(status=>status.status==='QUARANTINED').map(status=>status.onion));
  for(const status of statuses){const key=ledgers.get(status.onion)?.nodeKey??null;status.evidenceAvailable=key!==null&&witnesses.forkEvidence(key).length===2;}
  const view=currentView(quarantined); pins=view.pins.map(row=>row.pin);
  scope.postMessage({type:'view',view,viewVersion,sources:statuses,synthetic,busy,witnessCount:witnesses.size,visitorSources:[...visitor].map(([onion,expectedKey])=>({onion,expectedKey}))});
}
function admit(incoming:readonly VerifiedRecord[],origin:string,replace=false):void {
  const added=new Set(incoming.filter(record=>!records.has(record.contentHash)).map(record=>record.contentHash));
  // A reloaded Node replaces what only it supplied before: superseded records do not pile up toward the limit.
  const incomingHashes=new Set(incoming.map(record=>record.contentHash));
  const superseded=replace?[...provenance].filter(([hash,origins])=>origins.size===1&&origins.has(origin)&&!incomingHashes.has(hash)).map(([hash])=>hash):[];
  if(records.size-superseded.length+added.size>10000) throw new Error('CLIENT_RECORD_BUDGET_EXHAUSTED');
  for(const hash of superseded) { records.delete(hash); provenance.delete(hash); }
  ++recordsVersion;
  for(const record of incoming) { records.set(record.contentHash,record); const origins=provenance.get(record.contentHash)??new Set<string>(); origins.add(origin); provenance.set(record.contentHash,origins); }
}
async function command(message:Command):Promise<void> {
  if(message.op==='cancel') { controller?.abort(); return; }
  if(message.op==='configure') {
    if(busy||message.allowedSources.length>16||!['client-host-proxy','direct-cross-origin'].includes(message.deliveryProfile)) throw new Error('CLIENT_CONFIGURATION_INVALID');
    loader=new SourceLoader(crypto,message.deliveryProfile);
    allowed=new Set(message.allowedSources.map(onion=>loader.validate({onion}).onion));
    // Only the same-origin relay profile can reach a Node the host did not list.
    visitorEnabled=message.visitorSources===true&&message.deliveryProfile==='client-host-proxy'; if(!visitorEnabled) visitor.clear(); publish(); return;
  }
  if(message.op==='add-source') {
    try {
      if(!visitorEnabled) throw new Error('VISITOR_SOURCES_DISABLED');
      const onion=message.onion.trim().toLowerCase(); const expectedKey=message.expectedKey.trim();
      if(expectedKey==='') throw new Error('EXPECTED_NODE_KEY_REQUIRED');
      loader.validate({onion,expectedKey});
      if(allowed.has(onion)) throw new Error('SOURCE_ALREADY_CONFIGURED');
      if(visitor.has(onion)) throw new Error('SOURCE_ALREADY_ADDED');
      if(visitor.size>=MAX_VISITOR_SOURCES) throw new Error('VISITOR_SOURCE_LIMIT');
      visitor.set(onion,expectedKey); publish();
    } catch(error) { scope.postMessage({type:'visitor-error',code:code(error)}); }
    return;
  }
  if(message.op==='remove-source') { visitor.delete(message.onion); publish(); return; }
  if(message.op==='export-evidence') {
    // The two conflicting signed records a Node produced, for reporting; nothing else is included.
    const key=ledgers.get(message.onion)?.nodeKey??null; const pair=key===null?[]:witnesses.forkEvidence(key);
    if(pair.length!==2) throw new Error('EVIDENCE_UNAVAILABLE');
    scope.postMessage({type:'evidence',onion:message.onion,wires:pair.map(record=>record.canonicalWire)}); return;
  }
  if(message.op==='pin') {
    // The last published view already holds every row; rebuilding it here would verify nothing new.
    const row=cached?.view.services.find(item=>item.genesisKey===message.genesisKey);
    if(row===undefined||pins.length>=256) throw new Error('PIN_CONTEXT_UNAVAILABLE');
    if(!pins.some(pin=>pin.genesisKey===row.genesisKey)) { pins.push(pinIdentity(row)); ++pinsVersion; } publish(); return;
  }
  if(message.op==='unpin') { pins=pins.filter(pin=>pin.genesisKey!==message.genesisKey); ++pinsVersion; publish(); return; }
  if(message.op==='restore-pins'||message.op==='preview-pins') {
    const imported=parsePinBackup(new Uint8Array(message.bytes),crypto);
    if(message.op==='preview-pins') {
      const held=new Map(pins.map(pin=>[pin.genesisKey,pin]));
      scope.postMessage({type:'pin-preview',pins:imported.map(pin=>({pin,status:classifyPinImport(held.get(pin.genesisKey),pin)}))}); return;
    }
    const result=mergePins(pins,imported); pins=result.pins; ++pinsVersion; publish();
    scope.postMessage({type:'pins-restored',origin:message.origin,added:result.added,updated:result.updated,skipped:result.skipped}); return;
  }
  if(busy) throw new Error('CLIENT_BUSY');
  if(message.op==='clear-all') { records.clear(); provenance.clear(); ledgers.clear(); witnesses.clear(); loader.clear(); pins=[]; statuses=[]; synthetic=false; visitor.clear(); ++recordsVersion; ++pinsVersion; publish(); return; }
  if(message.op==='clear-index') { records.clear(); provenance.clear(); loader.forgetVerified(); statuses=[]; synthetic=false; ++recordsVersion; publish(); return; }
  busy=true; publish();
  try {
    if(message.op==='import-records') {
      // A bundle's record list may be as long as the 1 MiB file allows; every record inside keeps the protocol limits.
      const bundle=parseStrictJson(new Uint8Array(message.bytes),1048576,10000);
      const incoming=bundle.protocol==='Onion Service Discovery'?[bundle]:bundle.bundle_version===1&&Object.keys(bundle).length===2&&Array.isArray(bundle.records)?bundle.records:null;
      if(incoming===null)throw new Error('IMPORT_FORMAT_INVALID');
      const verified:VerifiedRecord[]=[];
      for(const item of incoming) { verified.push(await verifyRecord(requiredObject(item),crypto)); if(verified.length%32===0) await new Promise(resolve=>setTimeout(resolve,0)); }
      // Demo data and real records never mix: either kind replaces the other.
      if(message.synthetic===true||synthetic) { records.clear(); provenance.clear(); statuses=[]; synthetic=message.synthetic===true; ++recordsVersion; }
      admit(verified,message.synthetic===true?'demonstration':'local file');
    } else if(message.op==='load') {
      if(message.sources.length<1||message.sources.length>16||new Set(message.sources.map(source=>source.onion)).size!==message.sources.length) throw new Error('CLIENT_CONFIGURATION_INVALID');
      // A visitor-chosen Node loads only with exactly the key the visitor entered for it.
      for(const selection of message.sources) { loader.validate(selection); if(!allowed.has(selection.onion)&&!(visitorEnabled&&visitor.get(selection.onion)!==undefined&&visitor.get(selection.onion)===selection.expectedKey)) throw new Error('SOURCE_NOT_IN_HOST_ALLOWLIST'); }
      if(synthetic) { records.clear(); provenance.clear(); synthetic=false; ++recordsVersion; }
      controller=new AbortController();
      statuses=message.sources.map(selection=>({onion:selection.onion,status:'NOT_STARTED',nodeKey:null,generation:null,createdAt:null,error:null,baselineHistoryProven:false,evidenceAvailable:false}));
      for(let index=0;index<message.sources.length;++index) {
        const selection=message.sources[index]!; const status=statuses[index]!; status.status='LOADING'; publish();
        try {
          const result=await loader.load(selection,ledgers.get(selection.onion),controller.signal,(ledger,evidence)=>{const fork=witnesses.observe(evidence);ledger.equivocated ||= fork;ledgers.set(selection.onion,structuredClone(ledger));if(ledger.equivocated)throw new Error(evidence.type==='node_descriptor'?'SOURCE_DESCRIPTOR_EQUIVOCATION':'SOURCE_CHECKPOINT_EQUIVOCATION');},
            // A small message per verified chunk; the full view is rebuilt only when the source ends.
            (done,total)=>scope.postMessage({type:'progress',onion:selection.onion,records:done,total}));
          admit(result.records,selection.onion,true); ledgers.set(selection.onion,result.ledger);
          const now=typeof message.now==='number'&&Number.isFinite(message.now)?message.now:Date.now();
          const age=now-Date.parse(result.createdAt); status.status=age>604800000||age< -300000?'STALE':'COMPLETE'; status.nodeKey=result.ledger.nodeKey; status.generation=result.ledger.generation; status.createdAt=result.createdAt; status.baselineHistoryProven=result.baselineHistoryProven;
        } catch(error) {
          status.error=code(error);
          status.status=status.error==='SOURCE_CANCELLED'?'CANCELLED':status.error==='SOURCE_HISTORY_PROGRESS'?'PARTIAL':/EQUIVOCATION|ROLLBACK|FORK|KEY_CHANGED|CHAIN_BROKEN/.test(status.error)?'QUARANTINED':'FAILED';
          if(status.status==='CANCELLED') status.error=null;
        }
        publish(); if(controller.signal.aborted) break;
      }
      // Sources the user's cancel stopped before they began are reported as such, not as loading.
      for(const status of statuses) if(status.status==='NOT_STARTED') status.status='CANCELLED';
    }
  } finally { controller=null; busy=false; publish(); }
}
scope.onmessage=event=>{void command(event.data).catch(error=>{scope.postMessage({type:'error',code:code(error),op:event.data.op,origin:event.data.op==='restore-pins'?event.data.origin:null});});};
