import { canonicalJson } from './canonical-json.ts';
import { parseStrictValue } from './strict-json.ts';
import { snapshotChunkHash } from './snapshot-format.ts';
import { verifyRecord, requiredObject, assertVerifiedRecord, type CryptoProviders, type VerifiedRecord } from './record-verifier.ts';

export async function verifySnapshotChunk(bytes: Uint8Array, checkpoint: VerifiedRecord, index: number, crypto: CryptoProviders, previousHash: string | null = null): Promise<{ records: VerifiedRecord[]; lastHash: string | null }> {
  assertVerifiedRecord(checkpoint);
  if (checkpoint.type !== 'snapshot_descriptor' || !Number.isSafeInteger(index) || index < 0) throw new Error('SCHEMA_INVALID');
  const descriptor = requiredObject(parseStrictValue(new TextEncoder().encode(checkpoint.canonicalWire), 65536));
  if (!Array.isArray(descriptor.chunks)) throw new Error('SCHEMA_INVALID');
  const chunk = requiredObject(descriptor.chunks[index]);
  if (bytes.byteLength !== chunk.bytes) throw new Error('SNAPSHOT_CHUNK_HASH_INVALID');
  let wire: string;
  try { wire = new TextDecoder('utf-8', { fatal: true }).decode(bytes); } catch { throw new Error('ENCODING_INVALID'); }
  if (await snapshotChunkHash(wire, crypto.subtle) !== chunk.hash) throw new Error('SNAPSHOT_CHUNK_HASH_INVALID');
  const records = parseStrictValue(bytes, 524288);
  if (!Array.isArray(records) || records.length !== chunk.record_count || canonicalJson(records) !== wire) throw new Error('SCHEMA_INVALID');
  const result: VerifiedRecord[] = []; let lastHash = previousHash;
  for (const item of records) {
    const verified = await verifyRecord(requiredObject(item), crypto);
    if (verified.type === 'snapshot_descriptor' || (lastHash !== null && lastHash >= verified.contentHash)) throw new Error('SNAPSHOT_RECORD_ORDER_INVALID');
    result.push(verified); lastHash = verified.contentHash;
  }
  return { records: result, lastHash };
}
