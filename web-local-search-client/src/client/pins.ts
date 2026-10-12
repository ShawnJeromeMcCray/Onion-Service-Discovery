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
