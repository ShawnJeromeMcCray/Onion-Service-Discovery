import { open } from 'node:fs/promises';
import { canonicalJson } from '../src/protocol/canonical-json.ts';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { deriveIdentities } from '../src/protocol/identity-deriver.ts';
import { verifyWire, verifyRecord } from '../src/protocol/record-verifier.ts';
import { parseStrictJson } from '../src/protocol/strict-json.ts';

async function boundedFile(name, maximum) {
  if (typeof name !== 'string' || name.includes('://')) throw new Error('INPUT_FILE_UNAVAILABLE');
  let file;
  try { file = await open(name, 'r'); } catch { throw new Error('INPUT_FILE_UNAVAILABLE'); }
  try {
    const bytes = new Uint8Array(maximum + 1);
    let offset = 0;
    while (offset < bytes.length) {
      const { bytesRead } = await file.read(bytes, offset, bytes.length - offset, offset);
      if (bytesRead === 0) break;
      offset += bytesRead;
    }
    if (offset > maximum) throw new Error('DOCUMENT_TOO_LARGE');
    return bytes.subarray(0, offset);
  } finally { await file.close(); }
}

try {
  const args = process.argv.slice(2).filter(argument => argument !== '--json');
  const [command, file, ...options] = args;
  if (typeof command !== 'string' || typeof file !== 'string' || (options.length && (options.length !== 2 || options[0] !== '--expected-onion'))) throw new Error('USAGE_ERROR');
  const crypto = browserCryptoProviders();
  let output;
  if (['canonicalize', 'protocol:canonicalize'].includes(command)) {
    output = { status: 'PASS', canonical_hex: Buffer.from(canonicalJson(parseStrictJson(await boundedFile(file, 65536), 65536))).toString('hex') };
  } else if (['apply-sequence', 'protocol:apply-sequence'].includes(command)) {
    const fixture = parseStrictJson(await boundedFile(file, 1048576), 1048576);
    if (!Array.isArray(fixture.records) || fixture.records.length > 256) throw new Error('SCHEMA_INVALID');
    const records = await Promise.all(fixture.records.map(record => verifyRecord(record, crypto)));
    output = { status: 'PASS', state: deriveIdentities(records, fixture.max_records ?? 100000) };
  } else if (['validate-manifest', 'manifest:verify', 'protocol:inspect', 'protocol:hash'].includes(command)) {
    const record = await verifyWire(await boundedFile(file, 65536), crypto, options[1]);
    if (['validate-manifest', 'manifest:verify'].includes(command) && record.type !== 'service_manifest') throw new Error('SCHEMA_INVALID');
    output = { status: 'PASS', record_type: record.type, content_hash: record.contentHash, signature_valid: true, directly_observed: false };
  } else throw new Error('USAGE_ERROR');
  process.stdout.write(JSON.stringify(output) + '\n');
} catch (error) {
  const message = error instanceof Error && /^[A-Z][A-Z_]+$/.test(error.message) ? error.message : 'INTERNAL_ERROR';
  process.stdout.write(JSON.stringify({ status: 'FAIL', error: message }) + '\n');
  process.exitCode = message === 'USAGE_ERROR' ? 2 : 3;
}
