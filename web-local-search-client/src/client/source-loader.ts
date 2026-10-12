import { validateOnionAddress } from '../protocol/onion-address.ts';
import { publicKeyBytes } from '../protocol/encoding.ts';
import { parseStrictJson } from '../protocol/strict-json.ts';
import { verifyWire, requiredObject, requiredString, type VerifiedRecord, type CryptoProviders } from '../protocol/record-verifier.ts';
import { verifySnapshotChunk } from '../protocol/snapshot-verifier.ts';

export interface SourceSelection { onion: string; expectedKey?: string; }
export interface SourceLedger {
  nodeKey: string;
  generation: number;
  seenGeneration: number;
  checkpointHash: string;
  descriptorRevision: number;
  descriptorHash: string;
  equivocated: boolean;
  observed: { [position: string]: string[] };
}
export interface LoadedSource { records: VerifiedRecord[]; checkpoint: VerifiedRecord; ledger: SourceLedger; createdAt: string; baselineHistoryProven: boolean; }
const prefix = '/onion-service-discovery/v1/';
export type DeliveryProfile='client-host-proxy'|'direct-cross-origin';
type Endpoint = 'node' | 'state' | { snapshot: string } | { chunk: string };
function hash(value: string): string { if (!/^sha256:[0-9a-f]{64}$/.test(value)) throw new Error('HASH_INVALID'); return value; }
function path(endpoint: Endpoint): string {
  if (typeof endpoint === 'string') return prefix + endpoint;
  if ('snapshot' in endpoint) return prefix + 'snapshots/' + hash(endpoint.snapshot).slice(7);
  return prefix + 'chunks/' + hash(endpoint.chunk).slice(7);
}

/** All outbound requests are generic, bounded and independent of search/pins. */
async function fetchBytes(onion: string, endpoint: Endpoint, limit: number, parent: AbortSignal, deadline: number, delivery:DeliveryProfile): Promise<Uint8Array> {
  if (performance.now() >= deadline) throw new Error('SOURCE_TIME_BUDGET_EXHAUSTED');
  const controller = new AbortController();
  const abort = () => controller.abort(); parent.addEventListener('abort', abort, { once: true });
  if (parent.aborted) controller.abort();
  const timeout = setTimeout(abort, Math.min(15000, Math.max(1, deadline - performance.now())));
  try {
    const destination=delivery==='client-host-proxy'?new URL('relay/'+onion+'/'+path(endpoint).slice(prefix.length),new URL('./',import.meta.url)).href:'http://'+onion+path(endpoint);
    const response = await fetch(destination, { method: 'GET', credentials: 'omit', redirect: 'error', referrerPolicy: 'no-referrer', cache: 'no-store', mode: 'cors', headers: { Accept: 'application/json' }, signal: controller.signal });
    if (response.status !== 200) throw new Error('SOURCE_HTTP_STATUS_REJECTED');
    let headerBytes = 0;
    for (const [name, value] of response.headers) headerBytes += name.length + value.length + 4;
    if (headerBytes > 8192) throw new Error('HEADERS_TOO_LARGE');
    if (!/^application\/json(?:\s*;\s*charset=utf-8)?$/i.test(response.headers.get('content-type') ?? '')) throw new Error('CONTENT_TYPE_REJECTED');
    if (!['identity', ''].includes(response.headers.get('content-encoding') ?? '')) throw new Error('CONTENT_ENCODING_REJECTED');
    const size = response.headers.get('content-length');
    if (size !== null && (!/^(0|[1-9][0-9]{0,7})$/.test(size) || Number(size) > limit)) throw new Error('RESPONSE_TOO_LARGE');
    if (response.body === null) throw new Error('STREAMING_UNAVAILABLE');
    const reader = response.body.getReader(); const chunks: Uint8Array[] = []; let total = 0;
    try {
      while (true) {
        const item = await reader.read(); if (item.done) break;
        total += item.value.byteLength; if (total > limit) { controller.abort(); throw new Error('RESPONSE_TOO_LARGE'); }
        chunks.push(item.value);
      }
    } finally { reader.releaseLock(); }
    const result = new Uint8Array(total); let offset = 0;
    for (const chunk of chunks) { result.set(chunk, offset); offset += chunk.byteLength; }
    return result;
  } catch (error) {
    if (error instanceof Error && /^(?:SOURCE_|HEADERS_|CONTENT_|RESPONSE_|STREAMING_)/.test(error.message)) throw error;
    throw new Error(parent.aborted ? 'SOURCE_CANCELLED' : controller.signal.aborted ? 'SOURCE_TIMEOUT' : 'SOURCE_FETCH_FAILED');
  } finally { clearTimeout(timeout); parent.removeEventListener('abort', abort); }
}

export class SourceLoader {
  private readonly crypto:CryptoProviders; private readonly delivery:DeliveryProfile;
  constructor(crypto:CryptoProviders,delivery:DeliveryProfile='direct-cross-origin'){this.crypto=crypto;this.delivery=delivery;}
  validate(selection: SourceSelection): SourceSelection {
    validateOnionAddress(selection.onion, this.crypto.sha3);
    if (selection.expectedKey !== undefined && !this.crypto.validateEd25519Point(publicKeyBytes(selection.expectedKey))) throw new Error('PUBLIC_KEY_INVALID');
    return selection;
  }
  async load(selection: SourceSelection, prior: SourceLedger | undefined, signal: AbortSignal, observe: (ledger: SourceLedger, evidence: VerifiedRecord) => void = () => {}): Promise<LoadedSource> {
    this.validate(selection); const deadline = performance.now() + 90000;
    if (prior?.equivocated) throw new Error('SOURCE_EQUIVOCATION_PRESERVED');
    const descriptor = await verifyWire(await fetchBytes(selection.onion, 'node', 16384, signal, deadline,this.delivery), this.crypto);
    const node = requiredObject(parseStrictJson(new TextEncoder().encode(descriptor.canonicalWire), 16384));
    if (descriptor.type !== 'node_descriptor' || node.node_onion !== selection.onion) throw new Error('SOURCE_NODE_BINDING_MISMATCH');
    const nodeKey = requiredString(node.node_identity_public_key);
    if ((selection.expectedKey !== undefined && nodeKey !== selection.expectedKey) || (prior !== undefined && nodeKey !== prior.nodeKey)) throw new Error('SOURCE_NODE_KEY_CHANGED');
    if (prior !== undefined && (Number(node.descriptor_revision) < prior.descriptorRevision || (Number(node.descriptor_revision) === prior.descriptorRevision && descriptor.contentHash !== prior.descriptorHash))) throw new Error('SOURCE_DESCRIPTOR_ROLLBACK_OR_FORK');
    const remote = parseStrictJson(await fetchBytes(selection.onion, 'state', 16384, signal, deadline,this.delivery), 16384);
    if (Object.keys(remote).length !== 6 || Object.keys(remote).some(key => !['protocol','protocol_version','checkpoint_hash','generation','cursor','node_identity_public_key'].includes(key)) || remote.protocol !== 'Onion Service Discovery' || remote.protocol_version !== 1 || remote.node_identity_public_key !== nodeKey) throw new Error('SCHEMA_INVALID');
    const checkpointHash = hash(requiredString(remote.checkpoint_hash));
    const checkpoint = await verifyWire(await fetchBytes(selection.onion, { snapshot: checkpointHash }, 65536, signal, deadline,this.delivery), this.crypto);
    const document = parseStrictJson(new TextEncoder().encode(checkpoint.canonicalWire), 65536);
    if (checkpoint.type !== 'snapshot_descriptor' || checkpoint.contentHash !== checkpointHash || document.node_identity_public_key !== nodeKey || document.generation !== remote.generation || document.cursor !== remote.cursor) throw new Error('SOURCE_CHECKPOINT_BINDING_MISMATCH');
    const ledger: SourceLedger = prior === undefined ? { nodeKey, generation: 0, seenGeneration:0, checkpointHash: '', descriptorRevision: Number(node.descriptor_revision), descriptorHash: descriptor.contentHash, equivocated: false, observed: Object.create(null) as { [position: string]: string[] } } : structuredClone(prior);
    const position = String(document.generation); const seen = ledger.observed[position] ?? [];
    if (!seen.includes(checkpointHash)) seen.push(checkpointHash);
    ledger.observed[position] = seen.slice(0, 8); ledger.equivocated ||= seen.length > 1;
    ledger.seenGeneration=Math.max(ledger.seenGeneration,Number(document.generation));
    const observedPositions=Object.keys(ledger.observed).sort((a,b)=>Number(b)-Number(a));
    for(const old of observedPositions.slice(32)) delete ledger.observed[old];
    observe(ledger, checkpoint);
    if (ledger.equivocated) throw new Error('SOURCE_CHECKPOINT_EQUIVOCATION');
    if (Number(document.generation) < ledger.seenGeneration || Number(document.generation) < ledger.generation || (Number(document.generation) === ledger.generation && checkpointHash !== ledger.checkpointHash)) throw new Error('SOURCE_CHECKPOINT_ROLLBACK');
    if (!Array.isArray(document.chunks) || document.chunks.length > 64 || typeof document.record_count !== 'number' || document.record_count > 10000) throw new Error('SOURCE_WORK_BUDGET_EXHAUSTED');
    let sum = 0; for (const item of document.chunks) sum += Number(requiredObject(item).bytes);
    if (sum > 33554432) throw new Error('SOURCE_WORK_BUDGET_EXHAUSTED');
    let ancestor = document; let walks = 0;
    if (ledger.generation > 0 && Number(document.generation) > ledger.generation) {
      if (Number(document.generation) - ledger.generation > 32) throw new Error('SOURCE_HISTORY_BUDGET_EXHAUSTED');
      while (ancestor.previous_checkpoint_hash !== ledger.checkpointHash) {
        if (++walks > 32 || Number(ancestor.generation) <= ledger.generation + 1) throw new Error('SOURCE_CHECKPOINT_CHAIN_BROKEN');
        const parentHash = hash(requiredString(ancestor.previous_checkpoint_hash));
        const parent = await verifyWire(await fetchBytes(selection.onion, { snapshot: parentHash }, 65536, signal, deadline,this.delivery), this.crypto);
        const value = parseStrictJson(new TextEncoder().encode(parent.canonicalWire), 65536);
        if (parent.type !== 'snapshot_descriptor' || parent.contentHash !== parentHash || value.node_identity_public_key !== nodeKey || Number(value.generation) !== Number(ancestor.generation) - 1) throw new Error('SOURCE_CHECKPOINT_CHAIN_BROKEN');
        ancestor = value;
      }
      if (Number(ancestor.generation) !== ledger.generation + 1) throw new Error('SOURCE_CHECKPOINT_CHAIN_BROKEN');
    }
    const records: VerifiedRecord[] = [descriptor]; let count = 0; let lastHash: string | null = null;
    for (let index = 0; index < document.chunks.length; ++index) {
      const metadata = requiredObject(document.chunks[index]);
      const verified = await verifySnapshotChunk(await fetchBytes(selection.onion, { chunk: hash(requiredString(metadata.hash)) }, Number(metadata.bytes), signal, deadline,this.delivery), checkpoint, index, this.crypto, lastHash);
      records.push(...verified.records); count += verified.records.length; lastHash = verified.lastHash;
      await new Promise(resolve => setTimeout(resolve, 0));
    }
    if (count !== document.record_count) throw new Error('SOURCE_RECORD_COUNT_INVALID');
    if (prior !== undefined && Number(node.descriptor_revision) > prior.descriptorRevision) {
      const descriptors = new Map(records.filter(item => item.type === 'node_descriptor').map(item => [item.contentHash, item]));
      let current = node; let hops = 0;
      while (current.previous_descriptor_hash !== prior.descriptorHash) {
        if (++hops > 32 || Number(current.descriptor_revision) <= prior.descriptorRevision + 1) throw new Error('SOURCE_DESCRIPTOR_HISTORY_INCOMPLETE');
        const parent = descriptors.get(requiredString(current.previous_descriptor_hash));
        if (parent === undefined) throw new Error('SOURCE_DESCRIPTOR_HISTORY_INCOMPLETE');
        const value = parseStrictJson(new TextEncoder().encode(parent.canonicalWire), 16384);
        if (value.node_identity_public_key !== nodeKey || value.node_onion !== selection.onion || Number(value.descriptor_revision) !== Number(current.descriptor_revision) - 1) throw new Error('SOURCE_DESCRIPTOR_HISTORY_INCOMPLETE');
        current = value;
      }
      if (Number(current.descriptor_revision) !== prior.descriptorRevision + 1) throw new Error('SOURCE_DESCRIPTOR_HISTORY_INCOMPLETE');
    }
    ledger.generation = Number(document.generation); ledger.checkpointHash = checkpointHash; ledger.descriptorRevision = Number(node.descriptor_revision); ledger.descriptorHash = descriptor.contentHash;
    // Bound witness positions while preserving any equivocation; signed evidence is separately retained.
    const positions = Object.keys(ledger.observed).sort((a,b) => Number(b)-Number(a));
    for (const old of positions.slice(32)) delete ledger.observed[old];
    return { records, checkpoint, ledger, createdAt: requiredString(document.created_at), baselineHistoryProven: prior !== undefined || document.generation === 1 };
  }
}
