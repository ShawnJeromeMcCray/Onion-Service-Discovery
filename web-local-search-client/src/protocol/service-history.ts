import { deriveIdentities } from './identity-deriver.ts';
import { verifyRecord, type CryptoProviders, type VerifiedRecord } from './record-verifier.ts';
import type { WireRecord } from './record-format.ts';
import { parseStrictJson } from './strict-json.ts';

/** PCP-004 service history bundle limits; the bundle is unsigned and has no authority. */
export const HISTORY_MAX_BYTES = 1048576;
export const HISTORY_MAX_RECORDS = 256;
const TYPES = new Set(['service_manifest', 'service_identity_key_rotation', 'onion_service_migration']);
export interface ServiceHistoryResult { state: 'CONNECTED' | 'FORKED' | 'DOWNGRADE' | 'INCOMPLETE'; genesis_key: string | null; admitted_hashes: string[]; }

/** Connects a directly fetched, Onion-bound current Manifest to its signed history. */
export async function verifyServiceHistory(bundleBytes: Uint8Array, current: VerifiedRecord, known: readonly VerifiedRecord[], crypto: CryptoProviders): Promise<ServiceHistoryResult & { admitted: VerifiedRecord[] }> {
  if (bundleBytes.byteLength > HISTORY_MAX_BYTES || current.type !== 'service_manifest') throw new Error('HISTORY_BUNDLE_INVALID');
  const bundle = parseStrictJson(bundleBytes, HISTORY_MAX_BYTES);
  const members = Object.keys(bundle).sort();
  if (members.join(',') !== 'bundle_type,protocol,protocol_version,records' || bundle.protocol !== 'Onion Service Discovery' || bundle.protocol_version !== 1
    || bundle.bundle_type !== 'service_history' || !Array.isArray(bundle.records) || bundle.records.length < 1 || bundle.records.length > HISTORY_MAX_RECORDS) {
    throw new Error('HISTORY_BUNDLE_INVALID');
  }
  const records = new Map<string, VerifiedRecord>();
  for (const entry of bundle.records) {
    if (typeof entry !== 'object' || entry === null || Array.isArray(entry)) throw new Error('HISTORY_BUNDLE_INVALID');
    const record = await verifyRecord(entry as WireRecord, crypto);
    if (!TYPES.has(record.type) || records.has(record.contentHash)) throw new Error('HISTORY_BUNDLE_INVALID');
    records.set(record.contentHash, record);
  }
  const union = new Map<string, VerifiedRecord>(); for (const record of known) union.set(record.contentHash, record);
  const knownHashes = new Set(union.keys());
  for (const [hash, record] of records) if (!union.has(hash)) union.set(hash, record);
  if (!union.has(current.contentHash)) union.set(current.contentHash, current);
  for (const identity of deriveIdentities([...union.values()], 10000).identities) {
    if (!identity.history_hashes.includes(current.contentHash)) continue;
    const state = identity.identity_state === 'FORKED' ? 'FORKED' : identity.current_hash === current.contentHash ? 'CONNECTED' : 'DOWNGRADE';
    const connected = new Set([...identity.history_hashes, ...identity.rotation_hashes, ...identity.migration_hashes,
      ...identity.conflicts.flatMap(conflict => conflict.hashes), ...identity.transition_conflicts.flatMap(conflict => conflict.record_hashes)]);
    const admitted = [...connected].filter(hash => records.has(hash) && !knownHashes.has(hash) && hash !== current.contentHash).sort();
    return { state, genesis_key: identity.genesis_key, admitted_hashes: admitted, admitted: admitted.map(hash => records.get(hash)!) };
  }
  return { state: 'INCOMPLETE', genesis_key: null, admitted_hashes: [], admitted: [] };
}
