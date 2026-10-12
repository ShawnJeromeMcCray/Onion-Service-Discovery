import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { validateOnionAddress } from '../src/protocol/onion-address.ts';

const vectors = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/onion-addresses.json', import.meta.url), 'utf8'));
const sha3 = input => new Uint8Array(createHash('sha3-256').update(input).digest());

for (const fixture of vectors.cases) {
  test(fixture.id, () => {
    if (fixture.valid) assert.equal(validateOnionAddress(fixture.input, sha3), fixture.input);
    else assert.throws(() => validateOnionAddress(fixture.input, sha3), { message: 'ADDRESS_INVALID' });
  });
}

test('non-string values rejected', () => {
  for (const input of [null, {}, [], 42, true]) {
    assert.throws(() => validateOnionAddress(input, sha3), { message: 'ADDRESS_INVALID' });
  }
});

test('invalid checksum provider fails closed', () => {
  const valid = vectors.cases.find(fixture => fixture.valid).input;
  assert.throws(() => validateOnionAddress(valid, () => new Uint8Array(2)), { message: 'HASH_INVALID' });
});
