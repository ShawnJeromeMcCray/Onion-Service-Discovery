import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parseStrictJson } from '../src/protocol/strict-json.ts';

const fixtures = JSON.parse(readFileSync(new URL('../../protocol/test-vectors/strict-json.json', import.meta.url), 'utf8'));
for (const fixture of fixtures.cases) {
  test(`strict JSON: ${fixture.id}`, () => {
    const bytes = new Uint8Array(Buffer.from(fixture.input_base64, 'base64'));
    if (fixture.error) assert.throws(() => parseStrictJson(bytes, fixture.max_bytes ?? 16384), { message: fixture.error });
    else assert.deepEqual(JSON.parse(JSON.stringify(parseStrictJson(bytes, fixture.max_bytes ?? 16384))), fixture.expected);
  });
}

test('prototype-looking keys remain inert data', () => {
  const parsed = parseStrictJson(new TextEncoder().encode('{"__proto__":{"polluted":true},"constructor":42}'));
  assert.equal(Object.getPrototypeOf(parsed), null);
  assert.equal(parsed.__proto__.polluted, true);
  assert.equal({}.polluted, undefined);
});
