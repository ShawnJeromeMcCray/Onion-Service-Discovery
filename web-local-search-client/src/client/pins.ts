import { parseStrictJson } from '../protocol/strict-json.ts';
import { publicKeyBytes } from '../protocol/encoding.ts';
import { validateOnionAddress } from '../protocol/onion-address.ts';
import type { CryptoProviders } from '../protocol/record-verifier.ts';
import type { LocalPin } from './view-model.ts';

export function parsePinBackup(bytes:Uint8Array,crypto:CryptoProviders):LocalPin[] {
  const root=parseStrictJson(bytes,262144);
  if(Object.keys(root).length!==2||root.pin_backup_version!==1||!Array.isArray(root.pins)||root.pins.length>256) throw new Error('PIN_BACKUP_INVALID');
  const result:LocalPin[]=[]; const seen=new Set<string>();
  for(const value of root.pins) {
    if(value===null||typeof value!=='object'||Array.isArray(value)) throw new Error('PIN_BACKUP_INVALID');
    const fields=['genesisKey','contextOnion','anchorHash','latestHash','highestRevision','label','conflicted'];
    if(Object.keys(value).length!==fields.length||Object.keys(value).some(key=>!fields.includes(key))) throw new Error('PIN_BACKUP_INVALID');
    if(typeof value.genesisKey!=='string'||!crypto.validateEd25519Point(publicKeyBytes(value.genesisKey))||seen.has(value.genesisKey)) throw new Error('PIN_BACKUP_INVALID');
    if(typeof value.contextOnion!=='string') throw new Error('PIN_BACKUP_INVALID'); validateOnionAddress(value.contextOnion,crypto.sha3);
    if(typeof value.anchorHash!=='string'||!/^sha256:[0-9a-f]{64}$/.test(value.anchorHash)||typeof value.latestHash!=='string'||!/^sha256:[0-9a-f]{64}$/.test(value.latestHash)||typeof value.highestRevision!=='number'||!Number.isSafeInteger(value.highestRevision)||value.highestRevision<1||typeof value.label!=='string'||[...value.label].length>128||/[\u0000-\u001f\u007f-\u009f]/u.test(value.label)||typeof value.conflicted!=='boolean') throw new Error('PIN_BACKUP_INVALID');
    result.push({genesisKey:value.genesisKey,contextOnion:value.contextOnion,anchorHash:value.anchorHash,latestHash:value.latestHash,highestRevision:value.highestRevision,label:value.label,conflicted:value.conflicted}); seen.add(value.genesisKey);
  }
  return result;
}
export function serializePins(pins:readonly LocalPin[]):string { return JSON.stringify({pin_backup_version:1,pins}); }
/** How an imported pin relates to a pin already held. An existing pin is never replaced. */
export type PinImportStatus='NEW'|'SAME'|'UPDATES_REVISION'|'CONFLICT_KEEPS_EXISTING';
export function classifyPinImport(existing:LocalPin|undefined,imported:LocalPin):PinImportStatus {
  if(existing===undefined) return 'NEW';
  // Another anchor, context or same-revision hash is a different remembered choice: keep the user's.
  if(existing.anchorHash!==imported.anchorHash||existing.contextOnion!==imported.contextOnion) return 'CONFLICT_KEEPS_EXISTING';
  if(imported.highestRevision>existing.highestRevision) return 'UPDATES_REVISION';
  return imported.highestRevision===existing.highestRevision&&imported.latestHash!==existing.latestHash?'CONFLICT_KEEPS_EXISTING':'SAME';
}
/** Adds new pins and raises high-water marks; conflicting imports are skipped, never merged. */
export function mergePins(current:readonly LocalPin[],imported:readonly LocalPin[]):{pins:LocalPin[];added:number;updated:number;skipped:number} {
  const merged=new Map(current.map(pin=>[pin.genesisKey,pin])); let added=0; let updated=0; let skipped=0;
  for(const pin of imported) {
    const existing=merged.get(pin.genesisKey); const status=classifyPinImport(existing,pin);
    if(status==='NEW') { merged.set(pin.genesisKey,pin); ++added; }
    else if(existing!==undefined&&status==='UPDATES_REVISION') { merged.set(pin.genesisKey,{...existing,latestHash:pin.latestHash,highestRevision:pin.highestRevision,conflicted:existing.conflicted||pin.conflicted}); ++updated; }
    else if(existing!==undefined&&status==='SAME') merged.set(pin.genesisKey,{...existing,conflicted:existing.conflicted||pin.conflicted});
    else ++skipped;
  }
  if(merged.size>256) throw new Error('PIN_BUDGET_EXHAUSTED');
  return {pins:[...merged.values()],added,updated,skipped};
}
