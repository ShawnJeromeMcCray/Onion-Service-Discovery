import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { canonicalJson } from '../src/protocol/canonical-json.ts';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { recordHash, signablePayload, signedMessage } from '../src/protocol/record-format.ts';
import { verifyWire } from '../src/protocol/record-verifier.ts';
import { parseStrictJson } from '../src/protocol/strict-json.ts';

const crypto = browserCryptoProviders();
const fixtures = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/signed-records.json', import.meta.url), 'utf8'));
for (const fixture of fixtures.cases) {
  test(`signed record: ${fixture.id}`, async () => {
    const bytes = new Uint8Array(Buffer.from(fixture.input_base64, 'base64'));
    if (fixture.error) await assert.rejects(verifyWire(bytes, crypto, fixture.expected_onion), { message: fixture.error });
    else {
      const verified = await verifyWire(bytes, crypto, fixture.expected_onion);
      assert.equal(verified.contentHash, fixture.expected_hash);
      assert.equal(verified.canonicalWire, fixture.expected_wire);
      const record = parseStrictJson(bytes, 65536);
      assert.equal(Buffer.from(signedMessage(record)).toString('hex'), fixture.expected_message_hex);
      assert.equal(Buffer.from(canonicalJson(signablePayload(record))).toString('hex'), fixture.expected_payload_hex);
      assert.equal(await recordHash(record, crypto.subtle), fixture.expected_hash);
      assert.equal(Object.isFrozen(verified), true);
    }
  });
}

const canonical = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/canonical-json.json', import.meta.url), 'utf8'));
for (const fixture of canonical.cases) {
  test(`canonical bytes: ${fixture.id}`, () => {
    const parsed = parseStrictJson(new TextEncoder().encode(fixture.input));
    assert.equal(Buffer.from(canonicalJson(parsed)).toString('hex'), fixture.expected_hex);
  });
}
