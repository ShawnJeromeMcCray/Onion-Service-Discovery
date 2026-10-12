import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { deriveIdentities } from '../src/protocol/identity-deriver.ts';
import { verifyRecord } from '../src/protocol/record-verifier.ts';

const crypto = browserCryptoProviders();
const fixtures = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/identity-sequences.json', import.meta.url), 'utf8'));
for (const fixture of fixtures.cases) {
  test(`identity: ${fixture.id}`, async () => {
    const records = await Promise.all(fixture.records.map(record => verifyRecord(record, crypto)));
    if (fixture.error) assert.throws(() => deriveIdentities(records, fixture.max_records), { message: fixture.error });
    else {
      const result = deriveIdentities(records);
      assert.deepEqual(JSON.parse(JSON.stringify(result)), fixture.expected);
      assert.deepEqual(deriveIdentities([...records, ...records].reverse()), result);
    }
  });
}
