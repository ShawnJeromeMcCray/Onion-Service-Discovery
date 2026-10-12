import { deriveIdentities } from '../protocol/identity-deriver.ts';
import { parseStrictJson } from '../protocol/strict-json.ts';
import { requiredObject, requiredString, type VerifiedRecord } from '../protocol/record-verifier.ts';

export interface LocalPin {
  genesisKey: string;
  contextOnion: string;
  anchorHash: string;
  latestHash: string;
  highestRevision: number;
  label: string;
  conflicted: boolean;
}
export interface ServiceRow {
  genesisKey: string;
  name: string;
  description: string;
  onion: string | null;
  revision: number;
  status: 'PUBLISHED' | 'WITHDRAWN' | 'FORKED';
  addressConflict: boolean;
  languages: string[];
  categories: string[];
  observationClaims: number;
  rotationCount: number;
  migrationCount: number;
  currentHash: string | null;
}
export interface PinRow { pin: LocalPin; status: 'CONTINUITY_VALID'|'MIGRATED'|'WITHDRAWN'|'FORKED'|'HISTORY_INCOMPLETE'|'REVISION_DOWNGRADE'|'IDENTITY_CHANGED'|'NOT_IN_INDEX'; onion: string | null; }
export interface ClientView { services: ServiceRow[]; pins: PinRow[]; recordCount: number; pendingCount: number; }
function strings(value: unknown): string[] { return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []; }

export function buildView(records: readonly VerifiedRecord[], pins: readonly LocalPin[]): ClientView {
  const identities = deriveIdentities(records,10000); const byHash = new Map(records.map(record => [record.contentHash,record]));
  const documents = new Map(records.map(record => [record.contentHash,parseStrictJson(new TextEncoder().encode(record.canonicalWire),65536)]));
  const observations = new Map<string,Set<string>>();
  for (const record of records) {
    if (record.type !== 'direct_observation') continue;
    const document = documents.get(record.contentHash)!; const manifestHash = requiredString(document.manifest_hash);
    if (!observations.has(manifestHash)) observations.set(manifestHash,new Set());
    observations.get(manifestHash)!.add(requiredString(document.node_identity_public_key));
  }
  const rows: ServiceRow[] = []; const owners = new Map<string,Set<string>>();
  for (const identity of identities.identities) {
    const current = identity.current_manifest;
    const historical = documents.get(identity.history_hashes[0] ?? '');
    const onion = current === null ? historical === undefined ? null : requiredString(historical.onion) : requiredString(current.onion);
    if (onion !== null) { if(!owners.has(onion)) owners.set(onion,new Set()); owners.get(onion)!.add(identity.genesis_key); }
    const service = current === null ? null : requiredObject(current.service);
    rows.push({ genesisKey: identity.genesis_key, name: service === null ? 'Conflicting identity history' : requiredString(service.name), description: service === null ? 'Multiple signed branches exist. No current manifest is selected.' : requiredString(service.description ?? ''), onion, revision: identity.highest_revision, status: identity.identity_state==='FORKED'?'FORKED':identity.discoverability==='PUBLISHED'?'PUBLISHED':'WITHDRAWN', addressConflict:false, languages:service===null?[]:strings(service.languages), categories:service===null?[]:strings(service.categories), observationClaims:identity.current_hash===null?0:observations.get(identity.current_hash)?.size??0, rotationCount:identity.rotation_hashes.length, migrationCount:identity.migration_hashes.length, currentHash:identity.current_hash });
  }
  for(const row of rows) row.addressConflict=row.onion!==null&&(owners.get(row.onion)?.size??0)>1;
  const roots=new Map(identities.identities.map(identity=>[identity.genesis_key,identity]));
  const pinRows:PinRow[]=pins.map(original=>{
    const pin={...original}; const identity=roots.get(pin.genesisKey); let status:PinRow['status']='NOT_IN_INDEX'; let onion:string|null=null;
    if(pin.conflicted||identity?.identity_state==='FORKED') { pin.conflicted=true; status='FORKED'; }
    else if(identity===undefined) status=(owners.get(pin.contextOnion)?.size??0)>0?'IDENTITY_CHANGED':'NOT_IN_INDEX';
    else if(identity.highest_revision<pin.highestRevision) status='REVISION_DOWNGRADE';
    else if(!identity.history_hashes.includes(pin.anchorHash)||!identity.history_hashes.includes(pin.latestHash)) status='HISTORY_INCOMPLETE';
    else {
      const anchor=byHash.get(pin.anchorHash); const anchorDocument=anchor===undefined?undefined:documents.get(anchor.contentHash);
      if(anchorDocument?.onion!==pin.contextOnion||identity.current_manifest===null||identity.current_hash===null) status='HISTORY_INCOMPLETE';
      else {
        onion=requiredString(identity.current_manifest.onion);
        if(identity.highest_revision===pin.highestRevision&&identity.current_hash!==pin.latestHash) { pin.conflicted=true; status='FORKED'; onion=null; }
        else {
          pin.highestRevision=identity.highest_revision; pin.latestHash=identity.current_hash;
          if(identity.discoverability==='WITHDRAWN') { status='WITHDRAWN'; onion=null; }
          else status=onion===pin.contextOnion?'CONTINUITY_VALID':'MIGRATED';
        }
      }
    }
    return {pin,status,onion};
  });
  return {services:rows,pins:pinRows,recordCount:records.length,pendingCount:identities.pending_hashes.length};
}

export function pinIdentity(identity:ServiceRow,label=identity.name):LocalPin {
  if(identity.status!=='PUBLISHED'||identity.onion===null||identity.currentHash===null) throw new Error('PIN_CONTEXT_UNAVAILABLE');
  return {genesisKey:identity.genesisKey,contextOnion:identity.onion,anchorHash:identity.currentHash,latestHash:identity.currentHash,highestRevision:identity.revision,label:label.slice(0,128),conflicted:false};
}
export function localSearch(services:readonly ServiceRow[],query:string,language=''):ServiceRow[] {
  const terms=query.slice(0,256).normalize('NFKC').toLowerCase().split(/\s+/u).filter(Boolean).slice(0,16);
  return services.filter(row=>{if(row.status==='WITHDRAWN'||(language!==''&&!row.languages.includes(language)))return false;const haystack=[row.name,row.description,row.onion??'',row.genesisKey,...row.categories,...row.languages].join(' ').normalize('NFKC').toLowerCase();return terms.every(term=>haystack.includes(term));}).sort((a,b)=>a.name<b.name?-1:a.name>b.name?1:a.genesisKey<b.genesisKey?-1:1);
}

/** Presentation hints only: signed Unicode remains unchanged and identity never depends on these. */
export function metadataWarnings(name:string,description:string):string[] {
  const warnings:string[]=[];
  if (/[\u061c\u200e\u200f\u202a-\u202e\u2066-\u2069]/u.test(name+description)) warnings.push('Metadata contains direction-changing characters. Compare the full Onion and identity key.');
  if (/\p{Script=Latin}/u.test(name) && /[\p{Script=Cyrillic}\p{Script=Greek}]/u.test(name)) warnings.push('The name mixes visually similar writing systems. Names are self-declared; compare the full Onion and identity key.');
  return warnings;
}
