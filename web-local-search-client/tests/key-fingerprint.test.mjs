import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { keyFingerprint } from '../src/protocol/key-fingerprint.ts';

const vectors = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/key-fingerprint-v1.json', import.meta.url), 'utf8'));
test('key fingerprints match the shared D-025 vectors', () => {
  for (const item of vectors.cases) assert.equal(keyFingerprint(item.public_key), item.fingerprint);
});
test('an invalid key has no fingerprint', () => {
  assert.throws(() => keyFingerprint('ed25519:short'), { message: 'PUBLIC_KEY_INVALID' });
  assert.throws(() => keyFingerprint(vectors.cases[0].public_key.replace('ed25519:', 'x25519:')), { message: 'PUBLIC_KEY_INVALID' });
});
