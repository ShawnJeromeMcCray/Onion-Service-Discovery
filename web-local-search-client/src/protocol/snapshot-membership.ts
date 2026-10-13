import { publicKeyBytes } from './encoding.ts';
import { deriveIdentities, type DerivedIdentity } from './identity-deriver.ts';
import { assertVerifiedRecord, requiredObject, requiredString, verifyWire, type CryptoProviders, type VerifiedRecord } from './record-verifier.ts';
import { parseStrictJson } from './strict-json.ts';
import { verifySnapshotChunk } from './snapshot-verifier.ts';
import type { WireRecord } from './record-format.ts';

const MAX_RECORDS = 10000;
const MAX_BYTES = 33554432;
function document(record: VerifiedRecord): WireRecord {
  assertVerifiedRecord(record);
  return parseStrictJson(new TextEncoder().encode(record.canonicalWire), 65536);
}
function tuple(identity: DerivedIdentity, records: readonly VerifiedRecord[]): WireRecord {
  const manifest = identity.current_manifest;
  let epoch: number | null = null;
  if (manifest !== null) {
    const manifests = new Map(records.filter(record => record.type === 'service_manifest').map(record => [record.contentHash, document(record)]));
    epoch = 1; let key = identity.genesis_key;
    for (const hash of identity.history_hashes) {
      const next = requiredString(manifests.get(hash)?.service_identity_public_key);
      if (next !== key) { ++epoch; key = next; }
    }
  }
  return { logical_service_id: identity.genesis_key, current_service_identity_public_key: manifest?.service_identity_public_key ?? null,
    key_epoch: epoch, onion: manifest?.onion ?? null, revision: identity.highest_revision,
    manifest_hash: identity.current_hash, protocol_version: 1, active: identity.discoverability === 'PUBLISHED' };
}

/** Checks exact owner state in the complete signed draft snapshot. No network or storage capability. */
export async function verifySnapshotMembership(checkpointBytes: Uint8Array, chunks: AsyncIterable<Uint8Array> | Iterable<Uint8Array>, expectedNodeKey: string, localHistory: readonly VerifiedRecord[], crypto: CryptoProviders): Promise<WireRecord> {
  if (!crypto.validateEd25519Point(publicKeyBytes(expectedNodeKey))) throw new Error('PUBLIC_KEY_INVALID');
  const checkpoint = await verifyWire(checkpointBytes, crypto);
  const descriptor = document(checkpoint);
  if (checkpoint.type !== 'snapshot_descriptor' || descriptor.node_identity_public_key !== expectedNodeKey) throw new Error('CHECKPOINT_NODE_MISMATCH');
  if (!Array.isArray(descriptor.chunks)) throw new Error('SCHEMA_INVALID');
  const metadata = descriptor.chunks.map(requiredObject);
  const bytes = metadata.reduce((sum, chunk) => sum + Number(chunk.bytes), 0);
  if (Number(descriptor.record_count) > MAX_RECORDS || bytes > MAX_BYTES) throw new Error('WORK_BUDGET_EXHAUSTED');
  const local = deriveIdentities(localHistory, MAX_RECORDS);
  if (local.identities.length !== 1 || local.pending_hashes.length !== 0 || Object.keys(local.rejected).length !== 0) throw new Error('LOCAL_CONTINUITY_INCOMPLETE');
  const own = local.identities[0]!;
  if (own.identity_state !== 'VALID' || own.current_hash === null) throw new Error('LOCAL_IDENTITY_CONFLICT');
  const records: VerifiedRecord[] = []; let position = 0; let lastHash: string | null = null;
  for await (const wire of chunks) {
    if (position >= metadata.length) throw new Error('SNAPSHOT_INCOMPLETE');
    const result = await verifySnapshotChunk(wire, checkpoint, position, crypto, lastHash);
    for (const record of result.records) records.push(record);
    lastHash = result.lastHash; ++position;
  }
  if (position !== metadata.length || records.length !== descriptor.record_count) throw new Error('SNAPSHOT_INCOMPLETE');
  const view = deriveIdentities(records, MAX_RECORDS);
  const combined = new Map([...localHistory, ...records].map(record => [record.contentHash, record]));
  const conflicting = deriveIdentities([...combined.values()], 2 * MAX_RECORDS).identities.some(identity => identity.genesis_key === own.genesis_key && identity.identity_state === 'FORKED');
  const found = view.identities.find(identity => identity.genesis_key === own.genesis_key);
  let status = 'IDENTITY_NOT_PRESENT';
  if (conflicting) status = 'CONFLICTING_SERVICE_STATE';
  else if (found !== undefined) {
    if (found.identity_state !== 'VALID') status = 'CONFLICTING_SERVICE_STATE';
    else if (found.current_hash === own.current_hash) status = found.discoverability === 'PUBLISHED' ? 'CURRENT_STATE_INCLUDED' : 'WITHDRAWN_IN_SNAPSHOT';
    else if (found.highest_revision < own.highest_revision) status = 'STALE_REVISION_INCLUDED';
    else status = 'DIFFERENT_SERVICE_STATE';
  }
  let observation: string | null = null;
  for (const record of records) {
    if (record.type !== 'direct_observation') continue;
    const doc = document(record); const manifest = own.current_manifest!;
    if (doc.node_identity_public_key === expectedNodeKey && doc.manifest_hash === own.current_hash &&
        doc.onion === manifest.onion && doc.service_identity_public_key === manifest.service_identity_public_key && doc.revision === manifest.revision) {
      const time = requiredString(doc.observed_at);
      if (observation === null || time > observation) observation = time;
    }
  }
  return { status, expected_state: tuple(own, localHistory), snapshot_state: found === undefined ? null : tuple(found, records),
    checkpoint_hash: checkpoint.contentHash, node_identity_public_key: expectedNodeKey, checkpoint_generation: descriptor.generation!,
    checkpoint_created_at: descriptor.created_at!, checkpoint_root: descriptor.root_hash!, evidence_type: 'COMPLETE_SIGNED_DRAFT_SNAPSHOT',
    node_claimed_direct_observation_at: observation, checkpoint_consistency: 'NOT_EVALUATED', freshness: 'NOT_EVALUATED', network_side_effects: false };
}
