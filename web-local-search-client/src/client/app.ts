import { localSearch, metadataWarnings, type AdultVisibility, type ClientView, type LocalPin, type ServiceRow } from './view-model.ts';
import { CATEGORY_IDS, CATEGORY_LABELS } from '../protocol/category-id.ts';
import { groupSimilarResults } from './result-groups.ts';
import { parseStrictJson } from '../protocol/strict-json.ts';
import { serializePins, type PinImportStatus } from './pins.ts';
import { operationMessage, sourceMessage } from './messages.ts';
import type { SourceSelection } from './source-loader.ts';
interface SourceStatus {onion:string;status:string;nodeKey:string|null;generation:number|null;error:string|null;baselineHistoryProven:boolean;}
interface VisitorSource {onion:string;expectedKey:string;}
interface ViewMessage {type:'view';view:ClientView;sources:SourceStatus[];synthetic:boolean;busy:boolean;witnessCount:number;visitorSources:VisitorSource[];}
type WorkerMessage=ViewMessage|{type:'error';code:string;op:string;origin:'profile'|'backup'|null}|{type:'visitor-error';code:string}|{type:'pin-preview';pins:{pin:LocalPin;status:PinImportStatus}[]}|{type:'pins-restored';origin:'profile'|'backup';added:number;updated:number;skipped:number};
const storageKey='onion-service-discovery-pins-v1';
const sourceStorageKey='onion-service-discovery-sources-v1';
const visitorErrors:{[code:string]:string}={ADDRESS_INVALID:'Check the onion address: it must be a 56-character v3 address ending in .onion.',PUBLIC_KEY_INVALID:'Check the expected Node key: it starts with ed25519: followed by 43 characters.',EXPECTED_NODE_KEY_REQUIRED:'Enter the Node’s expected public key. Without it, a different Node could answer in its place.',SOURCE_ALREADY_CONFIGURED:'This Node is already listed by this Client Host.',SOURCE_ALREADY_ADDED:'You have already added this Node.',VISITOR_SOURCE_LIMIT:'You can add up to 8 Nodes. Remove one first.',VISITOR_SOURCES_DISABLED:'This Client Host does not load visitor-chosen Nodes.'};
let configuredSourceCount=0; let renderedVisitorSources='';
const worker=new Worker(new URL('verifier-worker.js',document.baseURI),{type:'module'});
let view:ClientView={services:[],pins:[],recordCount:0,pendingCount:0}; let synthetic=false; let busy=false; let pendingPinImport:ArrayBuffer|null=null;
// Saved pins are overwritten only after they were restored: an empty first view or a failed
// restore must never replace them.
let profilePins:'none'|'pending'|'restored'|'failed'='none';
function el<T extends HTMLElement>(id:string):T { const element=document.getElementById(id); if(element===null) throw new Error('CLIENT_MARKUP_INVALID'); return element as T; }
function text(tag:string,value:string,className=''):HTMLElement { const node=document.createElement(tag); node.textContent=value; if(className!=='') node.className=className; return node; }
function operation(message:string):void { el('operation-status').textContent=message; }
function button(label:string,action:()=>void):HTMLButtonElement { const node=document.createElement('button'); node.type='button'; node.className='secondary'; node.textContent=label; node.addEventListener('click',action); return node; }
function visit(onion:string):HTMLAnchorElement { const node=document.createElement('a'); node.className='visit'; node.href='http://'+onion+'/'; node.rel='noreferrer noopener'; node.target='_blank'; node.textContent='Open onion service'; return node; }
function persistPins():void {
  if(!el<HTMLInputElement>('remember-pins').checked||profilePins==='pending'||profilePins==='failed') return;
  try { localStorage.setItem(storageKey,serializePins(view.pins.map(row=>row.pin))); el('storage-status').textContent='Pins saved in this browser profile for this page only. They are not sent to Nodes or shared with other Client Hosts. Tor Browser deletes them when it closes or on New Identity; use Export pins to keep a copy.'; }
  catch { el('storage-status').textContent='Profile storage unavailable. New changes stay in session memory; older saved pins may remain.'; }
}
let resultPage=0;
function renderResults(resetPage=true):void {
  if(resetPage)resultPage=0;
  const query=el<HTMLInputElement>('query').value; const language=el<HTMLSelectElement>('language').value;
  const category=el<HTMLSelectElement>('category').value;
  const adultValue=el<HTMLSelectElement>('adult-visibility').value;
  const adult:AdultVisibility=adultValue==='hide'?'hide':adultValue==='de-emphasize'?'de-emphasize':'show';
  const rows=localSearch(view.services,query,language,category,adult); const results=el('results'); results.replaceChildren();
  el('result-summary').textContent=rows.length+' matching identities'+(view.pendingCount>0?' · '+view.pendingCount+' manifests need more history':'')+'. Search terms stay on this device.';
  const groups=groupSimilarResults(rows);const pageSize=25;
  resultPage=Math.min(resultPage,Math.max(0,Math.ceil(groups.length/pageSize)-1));
  for(const group of groups.slice(resultPage*pageSize,(resultPage+1)*pageSize)) {
    const row=group.representative;
    const card=text('article','', 'result-card'); card.append(text('p','Service-declared metadata','muted')); card.append(text('h3',row.name),text('p',row.description));
    for(const warning of metadataWarnings(row.name,row.description)) card.append(text('p',warning,'metadata-warning'));
    if(row.onion!==null) card.append(text('p',row.onion,'onion-address'));
    const tags=text('div','','tags'); for(const tag of [...row.categories,...row.languages]) tags.append(text('span',tag,'tag')); card.append(tags);
    card.append(text('p',row.status==='FORKED'?'No current manifest selected':'Service-key signature validated · revision '+row.revision,'evidence-label'));
    card.append(text('p','Host presence is not confirmed by this browser. '+row.observationClaims+' signed Node statements are claims, not independent proof.','muted'));
    if(row.addressConflict) card.append(text('p','Different Service identities claim this onion address. A pinned key is never replaced by source agreement.','conflict'));
    if(row.status==='FORKED') card.append(text('p','Fork evidence is retained. Faster responses or more Nodes do not resolve it.','conflict'));
    const details=document.createElement('details'); details.append(text('summary','Identity and continuity details'),text('p',row.genesisKey,'key-value'),text('p',row.rotationCount+' key-rotation proofs · '+row.migrationCount+' migration proofs. These do not establish an operator’s real-world identity.')); card.append(details);
    const actions=text('div','','actions');
    if(row.status==='PUBLISHED') {
      const pinned=view.pins.some(item=>item.pin.genesisKey===row.genesisKey);
      const pin=button(pinned?'Pinned locally':'Pin this identity',()=>worker.postMessage({op:'pin',genesisKey:row.genesisKey})); pin.disabled=pinned; actions.append(pin);
      if(row.onion!==null&&!synthetic) actions.append(visit(row.onion));
    }
    card.append(actions);
    if(group.entries.length>1) {
      const similar=document.createElement('details');similar.append(text('summary','Similar entries ('+group.entries.length+') — distinct identities'));
      similar.append(text('p','Similar metadata does not prove common ownership or fraud. Each signed identity is preserved.','muted'));
      const list=text('div','');let offset=0;
      const show=()=>{
        list.replaceChildren();
        for(const entry of group.entries.slice(offset,offset+25)) {
          const item=text('article','','pin-card');item.append(text('p',entry.genesisKey,'key-value'));
          if(entry.onion!==null)item.append(text('p',entry.onion,'onion-address'));
          const open=button('Inspect this identity',()=>{el<HTMLInputElement>('query').value=entry.genesisKey;renderResults();});item.append(open);list.append(item);
        }
      };
      const previous=button('Previous similar identities',()=>{offset=Math.max(0,offset-25);show();});
      const next=button('Next similar identities',()=>{offset=Math.min(Math.max(0,Math.floor((group.entries.length-1)/25)*25),offset+25);show();});
      // No network work is tied to expansion; render bounded pages on demand.
      similar.addEventListener('toggle',()=>{if(similar.open)show();else list.replaceChildren();});
      similar.append(list,previous,next);card.append(similar);
    }
    results.append(card);
  }
  if(groups.length>pageSize) {
    results.append(text('p','Result groups '+(resultPage*pageSize+1)+'–'+Math.min(groups.length,(resultPage+1)*pageSize)+' of '+groups.length+'. Group size gives no ranking or trust advantage.','muted'));
    const previous=button('Previous result groups',()=>{resultPage--;renderResults(false);});previous.disabled=resultPage===0;
    const next=button('Next result groups',()=>{resultPage++;renderResults(false);});next.disabled=(resultPage+1)*pageSize>=groups.length;
    results.append(previous,next);
  }
  if(rows.length===0) results.append(text('p',view.recordCount===0?'The local index is empty. Load configured sources, import a signed bundle or try the demonstration.':'No matching published identity in the loaded data. This does not prove that a service does not exist.','muted'));
}
function renderPins():void {
  const container=el('pins'); container.replaceChildren(); el('pin-count').textContent=String(view.pins.length);
  const labels:{[status:string]:string}={CONTINUITY_VALID:'Signed continuity matches your pin',MIGRATED:'Authorized onion migration from your saved context',WITHDRAWN:'Publication withdrawn; identity history retained',FORKED:'Fork conflict retained — no current branch selected',HISTORY_INCOMPLETE:'More history is needed to connect this pin',REVISION_DOWNGRADE:'Older than your remembered revision',IDENTITY_CHANGED:'Another key claims the saved onion; pin preserved',NOT_IN_INDEX:'Pinned identity absent from this loaded view'};
  for(const row of view.pins) {
    const card=text('article','','pin-card'); card.append(text('h3',row.pin.label),text('p',labels[row.status]??row.status,'pin-status'+(row.onion===null?' blocked':'')),text('p',row.pin.genesisKey,'key-value'),text('p','Saved onion: '+row.pin.contextOnion,'onion-address'));
    if(row.onion!==null&&row.onion!==row.pin.contextOnion) card.append(text('p','Current signed onion: '+row.onion,'onion-address'));
    if(row.onion!==null&&!synthetic) card.append(visit(row.onion));
    card.append(button('Remove local pin',()=>worker.postMessage({op:'unpin',genesisKey:row.pin.genesisKey}))); container.append(card);
  }
  if(view.pins.length===0) container.append(text('p','No local pins yet. Pin a key you have chosen to remember.','muted'));
}
const sourceStatusLabels:{[status:string]:string}={COMPLETE:'Verified',STALE:'Verified, but old',PARTIAL:'Partly verified',LOADING:'Loading',NOT_STARTED:'Waiting',CANCELLED:'Cancelled',FAILED:'Not loaded',QUARANTINED:'Set aside: conflicting signed data'};
function renderSources(sources:SourceStatus[]):void {
  const container=el('source-statuses'); container.replaceChildren(); const keys=new Set(sources.filter(source=>source.status==='COMPLETE').map(source=>source.nodeKey)); keys.delete(null);
  el('coverage').textContent=keys.size+' distinct fresh signing keys · '+sources.filter(source=>source.status==='COMPLETE').length+' complete source views';
  for(const source of sources) {
    const severity=source.status==='QUARANTINED'?'danger':source.status==='COMPLETE'?'ok':'attention';
    const card=text('div','','source-status status-'+severity); card.append(text('strong',sourceStatusLabels[source.status]??source.status),text('div',source.onion));
    if(source.nodeKey!==null) card.append(text('div',source.nodeKey,'key-value'));
    if(source.error!==null) card.append(text('div',sourceMessage(source.error),'problem'),text('div','Code: '+source.error,'muted'));
    if(source.status==='CANCELLED') card.append(text('div','You cancelled loading before this source finished. Nothing from it changed.'));
    if(source.status==='COMPLETE'&&!source.baselineHistoryProven) card.append(text('div','First-contact baseline; earlier Node checkpoint history is not proven.','problem'));
    if(source.status==='STALE') card.append(text('div','Old checkpoint or clock discrepancy. Historical signatures remain evidence, not freshness.','problem'));
    if(source.status==='PARTIAL') card.append(text('div','History verification is incomplete. Refresh again to continue; the last verified local index remains available.'));
    container.append(card);
  }
  if(sources.length>0&&keys.size<3) container.append(text('p','Limited source coverage. Multiple keys still do not guarantee independent operators. Identity pins do not depend on a vote.','conflict'));
}
function persistVisitorSources(sources:readonly VisitorSource[]):void {
  if(!el<HTMLInputElement>('remember-sources').checked) return;
  try { localStorage.setItem(sourceStorageKey,JSON.stringify({format:'onion-service-discovery-visitor-sources',version:1,sources:sources.map(source=>({onion:source.onion,expected_node_key:source.expectedKey}))})); }
  catch { operation('Profile storage unavailable. Nodes you added stay in session memory only.'); }
}
function renderVisitorSources(sources:readonly VisitorSource[]):void {
  const signature=JSON.stringify(sources); if(signature===renderedVisitorSources) return; renderedVisitorSources=signature;
  const container=el('visitor-source-options'); container.replaceChildren();
  sources.forEach((source,index)=>{
    const item=text('div','','source-option'); const check=document.createElement('input'); check.type='checkbox'; check.checked=true; check.dataset.source=source.onion; check.id='visitor-source-'+index;
    const label=document.createElement('label'); label.className='check-label'; label.append(check,text('span',source.onion+' · added by you'));
    const key=document.createElement('input'); key.type='text'; key.id=check.id+'-key'; key.value=source.expectedKey; key.readOnly=true;
    const keyLabel=document.createElement('label'); keyLabel.htmlFor=key.id; keyLabel.textContent='Expected Node key you entered';
    const remove=button('Remove this Node',()=>worker.postMessage({op:'remove-source',onion:source.onion})); remove.setAttribute('aria-label','Remove the Node '+source.onion);
    item.append(label,keyLabel,key,remove); container.append(item);
  });
  el('source-empty').hidden=configuredSourceCount+sources.length>0;
  persistVisitorSources(sources);
}
worker.onmessage=(event:MessageEvent<WorkerMessage>)=>{
  const message=event.data;
  if(message.type==='error') {
    if(message.op==='restore-pins'&&message.origin==='profile') {
      // Leave the saved pins untouched; saving again would replace them with what this session holds.
      profilePins='failed'; el<HTMLInputElement>('remember-pins').checked=false;
      el('storage-status').textContent='Your saved pins could not be read ('+message.code+') and were left unchanged in this browser profile. New pins stay in this session; turning on “Remember pins” again replaces the saved ones.';
      return;
    }
    operation(operationMessage(message.code)+' (code '+message.code+')'); return;
  }
  if(message.type==='pins-restored') {
    if(message.origin==='profile') { profilePins='restored'; persistPins(); return; }
    operation('Pin import: '+message.added+' added, '+message.updated+' updated to a newer remembered revision, '+message.skipped+' skipped because they conflict with your existing pins, which were kept.'); return;
  }
  if(message.type==='visitor-error') { el('visitor-status').textContent='Node not added: '+(visitorErrors[message.code]??message.code); return; }
  if(message.type==='pin-preview') {
    const statusText:{[status:string]:string}={NEW:'New pin',SAME:'Already pinned; nothing changes',UPDATES_REVISION:'Already pinned; remembers a newer revision',CONFLICT_KEEPS_EXISTING:'Conflicts with your existing pin; yours is kept and this one is skipped'};
    const preview=el('pin-preview'); preview.replaceChildren(); for(const {pin,status} of message.pins) { const item=text('div','','preview-pin'+(status==='CONFLICT_KEEPS_EXISTING'?' conflict':'')); item.append(text('strong',pin.label),text('div',statusText[status]??status),text('div',pin.genesisKey,'key-value'),text('div',pin.contextOnion,'onion-address')); preview.append(item); }
    el<HTMLDialogElement>('pin-import-dialog').showModal(); return;
  }
  el('client-startup-notice').hidden=true;
  view=message.view; synthetic=message.synthetic; busy=message.busy; el('synthetic-notice').hidden=!synthetic;
  for(const id of ['load-sources','load-demo','clear-index','forget-all']) el<HTMLButtonElement>(id).disabled=busy;
  el<HTMLButtonElement>('cancel-load').disabled=!busy; el('record-count').textContent=view.recordCount+' signed records';
  const language=el<HTMLSelectElement>('language'); const selected=language.value; const options=[...new Set(view.services.flatMap(row=>row.languages))].sort(); language.replaceChildren();
  const all=document.createElement('option'); all.value=''; all.textContent='All languages'; language.append(all);
  for(const value of options) { const option=document.createElement('option'); option.value=value; option.textContent=value; language.append(option); } language.value=options.includes(selected)?selected:'';
  renderVisitorSources(message.visitorSources); renderSources(message.sources); renderPins(); renderResults(); persistPins();
  operation(busy?'Loading generic signed data. You can keep searching the existing local index.':'Local index ready. '+message.witnessCount+' Node continuity witnesses retained.');
};
let searchTimer:ReturnType<typeof setTimeout>|null=null;
el('query').addEventListener('input',()=>{if(searchTimer!==null)clearTimeout(searchTimer);searchTimer=setTimeout(renderResults,120);});
el('language').addEventListener('change',()=>renderResults()); el('search-form').addEventListener('submit',event=>event.preventDefault());
for(const id of CATEGORY_IDS) { const option=document.createElement('option');option.value=id;option.textContent=CATEGORY_LABELS[id];el('category').append(option); }
el('category').addEventListener('change',()=>renderResults());
el('adult-visibility').addEventListener('change',()=>renderResults());
el('source-form').addEventListener('submit',event=>{
  event.preventDefault(); const sources:SourceSelection[]=[];
  for(const node of el('source-form').querySelectorAll<HTMLInputElement>('input[data-source]')) {
    if(!node.checked) continue; const onion=node.dataset.source!; const key=el<HTMLInputElement>(node.id+'-key').value.trim(); sources.push(key===''?{onion}:{onion,expectedKey:key});
  }
  if(sources.length===0) { operation('Select a configured data Node or import signed records.'); return; }
  if(sources.length<3&&!el<HTMLInputElement>('limited-coverage').checked) {
    operation('Select at least three sources, or allow loading with fewer under “Source selection and expected keys”.');
    el<HTMLDetailsElement>('source-details').open=true; el<HTMLInputElement>('limited-coverage').focus(); return;
  }
  worker.postMessage({op:'load',sources});
});
el('cancel-load').addEventListener('click',()=>worker.postMessage({op:'cancel'}));
el('clear-index').addEventListener('click',()=>worker.postMessage({op:'clear-index'}));
el('forget-all').addEventListener('click',()=>el<HTMLDialogElement>('forget-dialog').showModal());
el('forget-dialog').addEventListener('close',()=>{
  if(el<HTMLDialogElement>('forget-dialog').returnValue!=='confirm') return;
  el<HTMLInputElement>('remember-pins').checked=false; profilePins='none';
  el<HTMLInputElement>('remember-sources').checked=false;
  try {localStorage.removeItem(storageKey);localStorage.removeItem(sourceStorageKey);el('storage-status').textContent='Saved profile pins and added Nodes removed. Session memory only.';} catch {el('storage-status').textContent='Saved profile pins could not be removed. Clear this origin’s storage in browser settings.';}
  worker.postMessage({op:'clear-all'});
});
el('remember-pins').addEventListener('change',()=>{
  // Turning it on after a failed restore is the explicit choice to replace the unreadable saved pins.
  if(el<HTMLInputElement>('remember-pins').checked){if(profilePins!=='pending')profilePins='restored';persistPins();}
  else {try{localStorage.removeItem(storageKey);el('storage-status').textContent='Session memory only. Saved profile pins removed.';}catch{el('storage-status').textContent='Session memory only for new changes; old saved pins could not be removed.';}}
});
el('visitor-form').addEventListener('submit',event=>{
  event.preventDefault(); el('visitor-status').textContent='';
  worker.postMessage({op:'add-source',onion:el<HTMLInputElement>('visitor-onion').value,expectedKey:el<HTMLInputElement>('visitor-key').value});
});
el('remember-sources').addEventListener('change',()=>{
  if(el<HTMLInputElement>('remember-sources').checked){const current=renderedVisitorSources===''?[]:JSON.parse(renderedVisitorSources) as VisitorSource[];persistVisitorSources(current);}
  else{try{localStorage.removeItem(sourceStorageKey);}catch{operation('Saved Nodes could not be removed. Clear this origin’s storage in browser settings.');}}
});
el('export-pins').addEventListener('click',()=>{el('export-count').textContent=String(view.pins.length);el<HTMLDialogElement>('export-dialog').showModal();});
el('export-dialog').addEventListener('close',()=>{
  if(el<HTMLDialogElement>('export-dialog').returnValue!=='confirm') return;
  const blob=new Blob([serializePins(view.pins.map(row=>row.pin))],{type:'application/json'});const url=URL.createObjectURL(blob);const anchor=document.createElement('a');anchor.href=url;anchor.download='onion-service-discovery-pins.json';anchor.rel='noreferrer';anchor.click();setTimeout(()=>URL.revokeObjectURL(url),1000);operation('Pin backup downloaded locally. It has not been uploaded.');
});
// The input is cleared at once so choosing the same file again after Cancel works.
async function fileBytes(input:HTMLInputElement,max:number):Promise<ArrayBuffer|null>{const file=input.files?.[0];input.value='';if(file===undefined)return null;if(file.size>max)throw new Error('FILE_TOO_LARGE');return file.arrayBuffer();}
el('import-records').addEventListener('change',()=>{void fileBytes(el<HTMLInputElement>('import-records'),1048576).then(bytes=>{if(bytes!==null)worker.postMessage({op:'import-records',bytes},[bytes]);}).catch(()=>operation('Record bundle rejected: file exceeds the local limit or could not be read.'));});
el('import-pins').addEventListener('change',()=>{void fileBytes(el<HTMLInputElement>('import-pins'),262144).then(bytes=>{if(bytes!==null){pendingPinImport=bytes;worker.postMessage({op:'preview-pins',bytes:bytes.slice(0)});}}).catch(()=>operation('Pin backup rejected: file exceeds the local limit or could not be read.'));});
el('pin-import-dialog').addEventListener('close',()=>{if(el<HTMLDialogElement>('pin-import-dialog').returnValue==='confirm'&&pendingPinImport!==null){worker.postMessage({op:'restore-pins',bytes:pendingPinImport,origin:'backup'},[pendingPinImport]);}pendingPinImport=null;});
el('load-demo').addEventListener('click',()=>{void localResource('demo.json',1048576).then(bytes=>{worker.postMessage({op:'import-records',bytes:bytes.buffer,synthetic:true},[bytes.buffer]);}).catch(()=>operation('Offline demonstration unavailable. You can import a signed record bundle.'));});
async function localResource(name:'config.json'|'demo.json',limit:number):Promise<Uint8Array>{
  const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),15000);
  try {
  const response=await fetch(new URL(name,document.baseURI),{credentials:'omit',cache:'no-store',referrerPolicy:'no-referrer',redirect:'error',signal:controller.signal});
  if(!response.ok||response.body===null||!/^application\/json(?:\s*;\s*charset=utf-8)?$/i.test(response.headers.get('content-type')??''))throw new Error('LOCAL_RESOURCE_UNAVAILABLE');
  const reader=response.body.getReader();const parts:Uint8Array[]=[];let size=0;
  try{while(true){const item=await reader.read();if(item.done)break;size+=item.value.byteLength;if(size>limit){await reader.cancel();throw new Error('LOCAL_RESOURCE_TOO_LARGE');}parts.push(item.value);}}finally{reader.releaseLock();}
  const bytes=new Uint8Array(size);let offset=0;for(const part of parts){bytes.set(part,offset);offset+=part.byteLength;}return bytes;
  }finally{clearTimeout(timeout);}
}
function restoreVisitorSources():void{
  try{
    const saved=localStorage.getItem(sourceStorageKey); if(saved===null||saved.length>16384) return;
    const value=parseStrictJson(new TextEncoder().encode(saved),16384);
    if(value.format!=='onion-service-discovery-visitor-sources'||value.version!==1||!Array.isArray(value.sources)) return;
    el<HTMLInputElement>('remember-sources').checked=true;
    for(const item of value.sources.slice(0,8)){ if(typeof item==='object'&&item!==null&&!Array.isArray(item)&&typeof item.onion==='string'&&typeof item.expected_node_key==='string') worker.postMessage({op:'add-source',onion:item.onion,expectedKey:item.expected_node_key}); }
  }catch{el('storage-status').textContent='Browser profile storage unavailable. Session memory remains available.';}
}
async function configure():Promise<void>{
  try {
    const value=parseStrictJson(await localResource('config.json',16384),16384);
    const visitorCapable='visitor_sources'in value;
    if(Object.keys(value).length!==(visitorCapable?6:5)||(visitorCapable&&typeof value.visitor_sources!=='boolean')||!('allowed_sources'in value)||!Array.isArray(value.allowed_sources)||value.allowed_sources.length>16||value.allowed_sources.some(source=>typeof source!=='string')||!('client_config_version'in value)||value.client_config_version!==1||!['client-host-proxy','direct-cross-origin'].includes(String(value.delivery_profile))||value.minimum_distinct_keys!==3)throw new Error('CONFIGURATION_INVALID');
    const sources=value.allowed_sources as string[]; const container=el('source-options');container.replaceChildren();el('source-empty').hidden=sources.length>0;configuredSourceCount=sources.length;
    const visitorSources=value.visitor_sources===true&&value.delivery_profile==='client-host-proxy';el('visitor-form').hidden=!visitorSources;el('visitor-disabled').hidden=visitorSources;
    sources.forEach((onion,index)=>{const item=text('div','','source-option');const check=document.createElement('input');check.type='checkbox';check.checked=true;check.dataset.source=onion;check.id='source-'+index;const label=document.createElement('label');label.className='check-label';label.append(check,text('span',onion));const key=document.createElement('input');key.type='text';key.id=check.id+'-key';key.maxLength=51;key.placeholder='Optional expected Node public key';key.autocomplete='off';const keyLabel=document.createElement('label');keyLabel.htmlFor=key.id;keyLabel.textContent='Expected Node key for source '+(index+1)+' (optional)';item.append(label,keyLabel,key);container.append(item);});
    if('mode'in value&&value.mode==='combined')el('host-boundary').textContent='Combined deployment: this serving Node controls the page’s code and its data. A separate self-hosted Client Host gives stronger separation.';
    worker.postMessage({op:'configure',allowedSources:sources,deliveryProfile:value.delivery_profile,visitorSources});
    if(visitorSources) restoreVisitorSources();
  } catch {worker.postMessage({op:'configure',allowedSources:[],deliveryProfile:'direct-cross-origin'});operation('No usable Client Host source configuration. Local signed-record import remains available.');}
  try{const saved=localStorage.getItem(storageKey);if(saved!==null&&saved.length<=262144){profilePins='pending';el<HTMLInputElement>('remember-pins').checked=true;const bytes=new TextEncoder().encode(saved).buffer;worker.postMessage({op:'restore-pins',bytes,origin:'profile'},[bytes]);}}catch{el('storage-status').textContent='Browser profile storage unavailable. Session memory remains available.';}
}
// A verifier that dies (for example out of memory on a small device) must not leave the page busy forever.
function verifierStopped():void {
  busy=false; if(profilePins==='pending') profilePins='failed';
  for(const id of ['load-sources','load-demo','clear-index','cancel-load']) el<HTMLButtonElement>(id).disabled=true;
  el<HTMLButtonElement>('forget-all').disabled=false;
  operation('The local verifier stopped unexpectedly, for example because the device ran out of memory. Reload the page to start again. Your saved pins in this browser profile were not changed.');
}
worker.addEventListener('error',event=>{event.preventDefault();verifierStopped();});
worker.addEventListener('messageerror',verifierStopped);
void configure();
