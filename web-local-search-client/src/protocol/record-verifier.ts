import * as schemas from '../../generated/validators.mjs';
import { canonicalJson } from './canonical-json.ts';
import { publicKeyBytes, signatureBytes } from './encoding.ts';
import { validateOnionAddress, type Sha3Provider } from './onion-address.ts';
import { parseStrictJson, type JsonValue } from './strict-json.ts';
import { recordFormats, recordHash, recordType, signedMessage, type RecordType, type WireRecord } from './record-format.ts';

export interface VerifiedRecord {
  readonly type: RecordType;
  readonly contentHash: string;
  readonly canonicalWire: string;
}

export interface CryptoProviders { readonly subtle: SubtleCrypto; readonly sha3: Sha3Provider; }
export function requiredString(value: JsonValue | undefined): string {
  if (typeof value !== 'string') throw new Error('SCHEMA_INVALID');
  return value;
}
export function requiredObject(value: JsonValue | undefined): WireRecord {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) throw new Error('SCHEMA_INVALID');
  return value;
}
function requiredArray(value: JsonValue | undefined): JsonValue[] {
  if (!Array.isArray(value)) throw new Error('SCHEMA_INVALID');
  return value;
}

export async function verifyWire(bytes: Uint8Array, crypto: CryptoProviders, expectedOnion?: string): Promise<VerifiedRecord> {
  const record = parseStrictJson(bytes, 65536);
  const type = recordType(record);
  if (bytes.byteLength > recordFormats[type].bytes) throw new Error('DOCUMENT_TOO_LARGE');
  return verifyRecord(record, crypto, expectedOnion);
}

export async function verifyRecord(input: WireRecord, crypto: CryptoProviders, expectedOnion?: string): Promise<VerifiedRecord> {
  // Isolate input before any await; callers cannot mutate a record during verification.
  const canonicalWire = canonicalJson(input);
  const record = parseStrictJson(new TextEncoder().encode(canonicalWire), 65536);
  const type = recordType(record);
  const format = recordFormats[type];
  if (new TextEncoder().encode(canonicalWire).byteLength > format.bytes) throw new Error('DOCUMENT_TOO_LARGE');
  if (!schemas[type](record)) throw new Error('SCHEMA_INVALID');
  for (const field of ['onion', 'node_onion', 'from_onion', 'to_onion']) {
    if (record[field] !== undefined) validateOnionAddress(requiredString(record[field]), crypto.sha3);
  }
  for (const field of ['service_identity_public_key', 'node_identity_public_key', 'old_public_key', 'new_public_key']) {
    if (record[field] !== undefined) publicKeyBytes(requiredString(record[field]));
  }
  for (const field of format.excluded) signatureBytes(requiredString(record[field]));
  for (const field of ['published_at', 'observed_at', 'created_at']) {
    if (record[field] !== undefined) {
      const text = requiredString(record[field]);
      const date = new Date(text);
      if (!Number.isFinite(date.getTime()) || date.toISOString().replace('.000Z', 'Z') !== text) throw new Error('SCHEMA_INVALID');
    }
  }
  const sets: JsonValue[][] = [];
  if (record.service !== undefined) {
    const service = requiredObject(record.service);
    sets.push(requiredArray(service.languages), requiredArray(service.categories));
  }
  if (record.links !== undefined) {
    const links = requiredObject(record.links);
    if (links.related_onion_services !== undefined) {
      const related = requiredArray(links.related_onion_services);
      sets.push(related);
      for (const onion of related) validateOnionAddress(requiredString(onion), crypto.sha3);
    }
  }
  for (const field of ['capabilities', 'supported_protocol_versions']) {
    if (record[field] !== undefined) sets.push(requiredArray(record[field]));
  }
  for (const values of sets) {
    const sorted = [...values].sort((a, b) => typeof a === 'number' && typeof b === 'number' ? a - b : String(a) < String(b) ? -1 : String(a) > String(b) ? 1 : 0);
    if (values.some((item, index) => item !== sorted[index]) || new Set(values).size !== values.length) throw new Error('SCHEMA_INVALID');
  }
  if (type === 'service_identity_key_rotation' && (record.old_public_key === record.new_public_key || Number(record.effective_revision) < 2)) throw new Error('KEY_ROTATION_INVALID');
  if (type === 'onion_service_migration' && (record.from_onion === record.to_onion || Number(record.effective_revision) < 2)) throw new Error('MIGRATION_INVALID');
  if (expectedOnion !== undefined) {
    validateOnionAddress(expectedOnion, crypto.sha3);
    if (type === 'service_manifest' && record.onion !== expectedOnion) throw new Error('ONION_BINDING_MISMATCH');
  }
  const message = new Uint8Array(signedMessage(record));
  let key: CryptoKey;
  try {
    key = await crypto.subtle.importKey('raw', new Uint8Array(publicKeyBytes(requiredString(record[format.key]))), 'Ed25519', false, ['verify']);
  } catch { throw new Error('PUBLIC_KEY_INVALID'); }
  if (!await crypto.subtle.verify('Ed25519', key, new Uint8Array(signatureBytes(requiredString(record[format.excluded[0]]))), message)) throw new Error('SIGNATURE_INVALID');
  if (type === 'service_identity_key_rotation') {
    let nextKey: CryptoKey;
    try { nextKey = await crypto.subtle.importKey('raw', new Uint8Array(publicKeyBytes(requiredString(record.new_public_key))), 'Ed25519', false, ['verify']); }
    catch { throw new Error('PUBLIC_KEY_INVALID'); }
    if (!await crypto.subtle.verify('Ed25519', nextKey, new Uint8Array(signatureBytes(requiredString(record.new_key_confirmation))), message)) throw new Error('KEY_ROTATION_INVALID');
  }
  return Object.freeze({ type, contentHash: await recordHash(record, crypto.subtle), canonicalWire });
}
