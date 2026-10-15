import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { canonicalJson } from '../src/protocol/canonical-json.ts';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { recordHash } from '../src/protocol/record-format.ts';
import { assertVerifiedRecord, verifyWire, VerifiedRecordCache } from '../src/protocol/record-verifier.ts';
import { verifySnapshotChunk } from '../src/protocol/snapshot-verifier.ts';
import { deriveIdentities } from '../src/protocol/identity-deriver.ts';
const fixtures = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/signed-records.json', import.meta.url), 'utf8'));
const crypto = browserCryptoProviders();
test('independent Python-generated chunk bytes verify and tampering fails', async () => {
  const fixture = fixtures.cases.find(item => item.id === 'snapshot-with-chunk');
  const checkpoint = await verifyWire(new Uint8Array(Buffer.from(fixture.input_base64, 'base64')), crypto);
  const records = [fixtures.stories.genesis, fixtures.stories.successor];
  const hashes = await Promise.all(records.map(record => recordHash(record, crypto.subtle)));
  records.sort((a, b) => hashes[[fixtures.stories.genesis, fixtures.stories.successor].indexOf(a)].localeCompare(hashes[[fixtures.stories.genesis, fixtures.stories.successor].indexOf(b)]));
  const bytes = new TextEncoder().encode(canonicalJson(records));
  const result = await verifySnapshotChunk(bytes, checkpoint, 0, crypto);
  assert.equal(result.records.length, 2);
  assert.equal(deriveIdentities(result.records).identities[0].highest_revision, 2);
  const damaged = bytes.slice(); damaged[damaged.length - 2] ^= 1;
  await assert.rejects(verifySnapshotChunk(damaged, checkpoint, 0, crypto), { message: 'SNAPSHOT_CHUNK_HASH_INVALID' });
});
test('unverified and copied records cannot enter identity derivation', async () => {
  const fixture = fixtures.cases.find(item => item.id === 'genesis');
  const verified = await verifyWire(new Uint8Array(Buffer.from(fixture.input_base64, 'base64')), crypto);
  assertVerifiedRecord(verified);
  assert.throws(() => deriveIdentities([{ ...verified }]), { message: 'UNVERIFIED_RECORD' });
  assert.throws(() => assertVerifiedRecord(JSON.parse(JSON.stringify(verified))), { message: 'UNVERIFIED_RECORD' });
});
test('the verified-record cache reuses exact bytes only and checks every other record in full', async () => {
  let calls = 0; const counting = { ...crypto, verifyEd25519: async (...args) => { calls++; return crypto.verifyEd25519(...args); } };
  const cache = new VerifiedRecordCache(2);
  const genesis = fixtures.stories.genesis; const successor = fixtures.stories.successor;
  const first = await cache.verify(structuredClone(genesis), counting);
  // Same canonical bytes in another key order: the earlier result, without a second signature check.
  const reordered = Object.fromEntries(Object.entries(structuredClone(genesis)).reverse());
  assert.equal(await cache.verify(reordered, counting), first); assert.equal(calls, 1);
  // Same payload with another valid-looking signature is a different key and fails on its own.
  await assert.rejects(cache.verify({ ...structuredClone(genesis), signature: successor.signature }, counting), { message: 'SIGNATURE_INVALID' });
  assert.equal(calls, 2); assert.equal(cache.size, 1);
  // The size limit drops the oldest entry.
  await cache.verify(structuredClone(successor), counting);
  const fixture = fixtures.cases.find(item => item.id === 'snapshot-with-chunk');
  await cache.verify(JSON.parse(Buffer.from(fixture.input_base64, 'base64').toString('utf8')), counting);
  assert.equal(cache.size, 2); calls = 0;
  await cache.verify(structuredClone(genesis), counting); assert.equal(calls, 1);
});
test('a chunk checked with the cache needs no signature checks the second time', async () => {
  const fixture = fixtures.cases.find(item => item.id === 'snapshot-with-chunk');
  const checkpoint = await verifyWire(new Uint8Array(Buffer.from(fixture.input_base64, 'base64')), crypto);
  const records = [fixtures.stories.genesis, fixtures.stories.successor];
  const hashes = new Map(await Promise.all(records.map(async record => [record, await recordHash(record, crypto.subtle)])));
  records.sort((a, b) => hashes.get(a).localeCompare(hashes.get(b)));
  const bytes = new TextEncoder().encode(canonicalJson(records));
  let calls = 0; const counting = { ...crypto, verifyEd25519: async (...args) => { calls++; return crypto.verifyEd25519(...args); } };
  const cache = new VerifiedRecordCache();
  const once = await verifySnapshotChunk(bytes, checkpoint, 0, counting, null, { cache });
  const checked = calls; calls = 0;
  const twice = await verifySnapshotChunk(bytes, checkpoint, 0, counting, null, { cache });
  assert.ok(checked >= 2); assert.equal(calls, 0);
  assert.deepEqual(twice.records.map(record => record.contentHash), once.records.map(record => record.contentHash));
  const stopped = new AbortController(); stopped.abort();
  // Fewer than 64 records: the chunk completes; the loader checks the signal after each chunk.
  assert.equal((await verifySnapshotChunk(bytes, checkpoint, 0, counting, null, { cache, signal: stopped.signal })).records.length, 2);
});
