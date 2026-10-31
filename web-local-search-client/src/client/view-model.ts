import { searchTextV1 } from './search-unicode.ts';
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
  /** The key that signs the current Manifest; differs from genesisKey after a rotation. */
  currentKey: string | null;
  /** Onion addresses of earlier revisions, in revision order. */
  previousOnions: string[];
  /** Node keys whose signed direct observation names the current Manifest, and the latest time. */
  observers: string[];
  latestObservationAt: string | null;
  /** Where the current Manifest came from: loaded Node onions, 'local file' or 'demonstration'. */
  sources: string[];
  /** For every origin that supplied any revision of this identity, the highest one. */
  sourceRevisions: { source: string; revision: number }[];
  /** Every origin of the current Manifest was later set aside for conflicting signed data. */
  onlyQuarantinedSources: boolean;
}
/** Session facts the worker knows beyond the signed records themselves. */
export interface ViewContext { provenance?: ReadonlyMap<string, ReadonlySet<string>>; quarantined?: ReadonlySet<string>; }
export interface PinRow { pin: LocalPin; status: 'CONTINUITY_VALID'|'MIGRATED'|'WITHDRAWN'|'FORKED'|'HISTORY_INCOMPLETE'|'REVISION_DOWNGRADE'|'IDENTITY_CHANGED'|'NOT_IN_INDEX'; onion: string | null; }
export interface ClientView { services: ServiceRow[]; pins: PinRow[]; recordCount: number; pendingCount: number; }
function strings(value: unknown): string[] { return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []; }

export function buildView(records: readonly VerifiedRecord[], pins: readonly LocalPin[], context: ViewContext = {}): ClientView {
  const identities = deriveIdentities(records,10000); const byHash = new Map(records.map(record => [record.contentHash,record]));
  const documents = new Map(records.map(record => [record.contentHash,parseStrictJson(new TextEncoder().encode(record.canonicalWire),65536)]));
  const observations = new Map<string,Set<string>>(); const latestObservation = new Map<string,string>();
  for (const record of records) {
    if (record.type !== 'direct_observation') continue;
    const document = documents.get(record.contentHash)!; const manifestHash = requiredString(document.manifest_hash);
    if (!observations.has(manifestHash)) observations.set(manifestHash,new Set());
    observations.get(manifestHash)!.add(requiredString(document.node_identity_public_key));
    const observedAt = requiredString(document.observed_at);
    if ((latestObservation.get(manifestHash) ?? '') < observedAt) latestObservation.set(manifestHash,observedAt);
  }
  const rows: ServiceRow[] = []; const owners = new Map<string,Set<string>>();
  for (const identity of identities.identities) {
    const current = identity.current_manifest;
    const historical = documents.get(identity.history_hashes[0] ?? '');
    const onion = current === null ? historical === undefined ? null : requiredString(historical.onion) : requiredString(current.onion);
    if (onion !== null) { if(!owners.has(onion)) owners.set(onion,new Set()); owners.get(onion)!.add(identity.genesis_key); }
    const service = current === null ? null : requiredObject(current.service);
    const observers = identity.current_hash === null ? [] : [...(observations.get(identity.current_hash) ?? [])].sort();
    const previousOnions: string[] = [];
    const revisionsBySource = new Map<string,number>();
    for (const hash of identity.history_hashes) {
      const document = documents.get(hash); if (document === undefined) continue;
      const earlier = requiredString(document.onion); if (earlier !== onion && !previousOnions.includes(earlier)) previousOnions.push(earlier);
      const revision = typeof document.revision === 'number' ? document.revision : 0;
      for (const source of context.provenance?.get(hash) ?? []) revisionsBySource.set(source,Math.max(revisionsBySource.get(source) ?? 0,revision));
    }
    const sources = identity.current_hash === null ? [] : [...(context.provenance?.get(identity.current_hash) ?? [])].sort();
    rows.push({ genesisKey: identity.genesis_key, name: service === null ? 'Conflicting identity history' : requiredString(service.name), description: service === null ? 'Multiple signed branches exist. No current manifest is selected.' : requiredString(service.description ?? ''), onion, revision: identity.highest_revision, status: identity.identity_state==='FORKED'?'FORKED':identity.discoverability==='PUBLISHED'?'PUBLISHED':'WITHDRAWN', addressConflict:false, languages:service===null?[]:strings(service.languages), categories:service===null?[]:strings(service.categories), observationClaims:observers.length, rotationCount:identity.rotation_hashes.length, migrationCount:identity.migration_hashes.length, currentHash:identity.current_hash,
      currentKey: current === null ? null : requiredString(current.service_identity_public_key), previousOnions, observers, latestObservationAt: identity.current_hash === null ? null : latestObservation.get(identity.current_hash) ?? null,
      sources, sourceRevisions: [...revisionsBySource].map(([source,revision]) => ({source,revision})).sort((a,b) => a.source < b.source ? -1 : a.source > b.source ? 1 : 0),
      onlyQuarantinedSources: sources.length > 0 && sources.every(source => context.quarantined?.has(source) === true) });
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

/** Sources whose signed snapshot was fully verified: a partly verified or failed source supplied nothing this load. */
export function loadedSourceOnions(sources:readonly {onion:string;status:string}[]):string[] { return sources.filter(source=>source.status==='COMPLETE'||source.status==='STALE').map(source=>source.onion); }
export function pinIdentity(identity:ServiceRow,label=identity.name):LocalPin {
  if(identity.status!=='PUBLISHED'||identity.onion===null||identity.currentHash===null) throw new Error('PIN_CONTEXT_UNAVAILABLE');
  return {genesisKey:identity.genesisKey,contextOnion:identity.onion,anchorHash:identity.currentHash,latestHash:identity.currentHash,highestRevision:identity.revision,label:[...label].slice(0,128).join(''),conflicted:false}; // code points: never split a surrogate pair
}
export type AdultVisibility = 'show' | 'de-emphasize' | 'hide';
// Derived presentation data only, keyed by the complete search input: the worker sends new row
// objects with every update, and normalizing 10,000 rows again took about a second of main-thread
// time here. Keying by content also keeps callers that mutate row metadata correct. At most
// SEARCH_CACHE_LIMIT inputs are kept (one full index plus changes); then the cache starts over.
const SEARCH_CACHE_LIMIT = 12000;
const searchTexts = new Map<string, {normalized:string; name:string}>();
/** Normalized search text and normalized name (whitespace collapsed), shared with the PHP server search. */
function searchFields(row:ServiceRow):{normalized:string;name:string} {
  const input=[row.name,row.description,row.onion??'',row.genesisKey,...row.categories,...row.languages].join(' ');
  const cached=searchTexts.get(input);
  if(cached!==undefined)return cached;
  if(searchTexts.size>=SEARCH_CACHE_LIMIT)searchTexts.clear();
  const fields={normalized:searchTextV1(input),name:normalizedName(row.name)};
  searchTexts.set(input,fields);
  return fields;
}
const names=new Map<string,string>();
function normalizedName(name:string):string {
  const cached=names.get(name); if(cached!==undefined)return cached;
  if(names.size>=SEARCH_CACHE_LIMIT)names.clear();
  const value=searchTextV1(name).split(/\s+/u).filter(Boolean).join(' '); names.set(name,value);
  return value;
}
/** Leading ASCII punctuation does not move a name to the front of the list. */
const LEADING_PUNCTUATION=/^[\x21-\x2f\x3a-\x40\x5b-\x60\x7b-\x7e]+/u;
/**
 * Ranking version 2 (decision D-023): text relevance only. Tier 0 is a normalized name equal to the
 * query, 1 a name that starts with it, 2 a name containing every term, 3 any other match. Ties are
 * ordered by the normalized name without leading ASCII punctuation, then by the identity key. Node
 * counts, observations and source agreement never affect the order: they are cheap to fake.
 */
export function localSearch(services:readonly ServiceRow[],query:string,language='',category='',adult:AdultVisibility='show'):ServiceRow[] {
  const terms=searchTextV1(query.slice(0,256)).split(/\s+/u).filter(Boolean).slice(0,16); const phrase=terms.join(' ');
  const ranked:{row:ServiceRow;tier:number;sortName:string}[]=[];
  for(const row of services) {
    if(row.status==='WITHDRAWN'||(language!==''&&!row.languages.includes(language))||(category!==''&&!row.categories.includes(category))||(adult==='hide'&&row.categories.includes('adult'))) continue;
    // An empty query needs only the normalized name for ordering, not the full search text.
    const fields=terms.length===0?{normalized:'',name:normalizedName(row.name)}:searchFields(row);
    if(!terms.every(term=>fields.normalized.includes(term))) continue;
    const tier=terms.length===0||fields.name===phrase?0:fields.name.startsWith(phrase)?1:terms.every(term=>fields.name.includes(term))?2:3;
    ranked.push({row,tier,sortName:fields.name.replace(LEADING_PUNCTUATION,'')});
  }
  return ranked.sort((a,b)=>{
    if(adult==='de-emphasize') { const difference=Number(a.row.categories.includes('adult'))-Number(b.row.categories.includes('adult'));if(difference!==0)return difference; }
    if(a.tier!==b.tier) return a.tier-b.tier;
    if(a.sortName!==b.sortName) return a.sortName<b.sortName?-1:1;
    return a.row.genesisKey<b.row.genesisKey?-1:a.row.genesisKey>b.row.genesisKey?1:0;
  }).map(item=>item.row);
}

/**
 * Invisible characters that can make two different names look identical: zero-width and other
 * default-ignorable characters and tag characters. Emoji variation selectors and the zero-width
 * joiner of emoji sequences are common in honest names and are not listed.
 */
const INVISIBLE=/[\u00ad\u034f\u115f\u1160\u17b4\u17b5\u180b-\u180f\u200b\u200c\u2060-\u2064\u3164\ufeff\uffa0\u{1bca0}-\u{1bca3}\u{e0000}-\u{e007f}]/u;
/** Presentation hints only: signed Unicode remains unchanged and identity never depends on these. */
export function metadataWarnings(name:string,description:string):string[] {
  const warnings:string[]=[];
  if (/[\u061c\u200e\u200f\u202a-\u202e\u2066-\u2069]/u.test(name+description)) warnings.push('Metadata contains direction-changing characters. Compare the full Onion and identity key.');
  if (INVISIBLE.test(name+description)) warnings.push('Metadata contains invisible characters, which can make different names look the same. Compare the full Onion and identity key.');
  if (/\p{Script=Latin}/u.test(name) && /[\p{Script=Cyrillic}\p{Script=Greek}]/u.test(name)) warnings.push('The name mixes visually similar writing systems. Names are self-declared; compare the full Onion and identity key.');
  return warnings;
}
/** Comparison form of a self-declared name: case, compatibility forms and spacing ignored. */
const INVISIBLE_ALL=new RegExp(INVISIBLE.source,'gu');
export function nameKey(name:string):string { return searchTextV1(name.replace(INVISIBLE_ALL,'')).replace(/\s+/gu,' ').trim(); }
/**
 * Spec 06 section 22: a self-declared name equal to a pinned label under another identity key is a
 * warning, never a match. Returns the pinned label a row imitates, if any.
 */
export function pinNameMatcher(pins:readonly LocalPin[]):(row:{genesisKey:string;name:string})=>string|undefined {
  const keys=new Set(pins.map(pin=>pin.genesisKey)); const names=new Map(pins.map(pin=>[nameKey(pin.label),pin.label] as const));
  return row=>keys.has(row.genesisKey)?undefined:names.get(nameKey(row.name));
}
