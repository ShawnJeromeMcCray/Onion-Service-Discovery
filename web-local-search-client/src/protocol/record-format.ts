import { canonicalJson } from './canonical-json.ts';
import type { JsonValue } from './strict-json.ts';

export type WireRecord = { [key: string]: JsonValue };
export const recordFormats = {
  service_manifest: { domain: 'service-manifest', excluded: ['signature'], key: 'service_identity_public_key', bytes: 16384 },
  service_identity_key_rotation: { domain: 'service-key-rotation', excluded: ['old_key_signature', 'new_key_confirmation'], key: 'old_public_key', bytes: 8192 },
  onion_service_migration: { domain: 'onion-migration', excluded: ['service_identity_signature'], key: 'service_identity_public_key', bytes: 8192 },
  node_descriptor: { domain: 'node-descriptor', excluded: ['signature'], key: 'node_identity_public_key', bytes: 16384 },
  direct_observation: { domain: 'direct-observation', excluded: ['signature'], key: 'node_identity_public_key', bytes: 8192 },
  snapshot_descriptor: { domain: 'snapshot-descriptor', excluded: ['signature'], key: 'node_identity_public_key', bytes: 65536 },
} as const;
export type RecordType = keyof typeof recordFormats;

export function recordType(record: WireRecord): RecordType {
  if (record.protocol !== 'Onion Service Discovery' || record.protocol_version !== 1) throw new Error('PROTOCOL_UNSUPPORTED');
  const type = record.record_type;
  if (typeof type !== 'string' || !Object.hasOwn(recordFormats, type)) throw new Error('SCHEMA_INVALID');
  return type as RecordType;
}

export function signablePayload(record: WireRecord): WireRecord {
  const format = recordFormats[recordType(record)];
  const payload: WireRecord = Object.assign(Object.create(null) as WireRecord, record);
  for (const field of format.excluded) delete payload[field];
  return payload;
}

export function signedMessage(record: WireRecord): Uint8Array {
  const format = recordFormats[recordType(record)];
  return new TextEncoder().encode('Onion Service Discovery/' + format.domain + '/v1\0' + canonicalJson(signablePayload(record)));
}

export async function recordHash(record: WireRecord, subtle: Pick<SubtleCrypto, 'digest'>): Promise<string> {
  const type = recordType(record);
  const domain = type === 'service_manifest' ? 'service-manifest-hash' : recordFormats[type].domain + '-hash';
  const bytes = new TextEncoder().encode('Onion Service Discovery/' + domain + '/v1\0' + canonicalJson(signablePayload(record)));
  const hash = new Uint8Array(await subtle.digest('SHA-256', bytes));
  return 'sha256:' + Array.from(hash, byte => byte.toString(16).padStart(2, '0')).join('');
}
