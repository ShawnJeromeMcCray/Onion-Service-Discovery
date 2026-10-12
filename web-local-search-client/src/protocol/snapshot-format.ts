import { canonicalJson } from './canonical-json.ts';
import type { JsonValue } from './strict-json.ts';

async function digest(domain: string, bytes: string, subtle: Pick<SubtleCrypto, 'digest'>): Promise<string> {
  const message = new TextEncoder().encode('Onion Service Discovery/' + domain + '/v1\0' + bytes);
  const hash = new Uint8Array(await subtle.digest('SHA-256', message));
  return 'sha256:' + Array.from(hash, byte => byte.toString(16).padStart(2, '0')).join('');
}
export function snapshotChunkHash(canonicalBytes: string, subtle: Pick<SubtleCrypto, 'digest'>): Promise<string> {
  return digest('snapshot-chunk', canonicalBytes, subtle);
}
export function snapshotRootHash(chunks: JsonValue[], subtle: Pick<SubtleCrypto, 'digest'>): Promise<string> {
  return digest('snapshot-root', canonicalJson(chunks), subtle);
}
