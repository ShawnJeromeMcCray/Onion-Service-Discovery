import { createInterface } from 'node:readline';
import { canonicalJson } from '../src/protocol/canonical-json.ts';
import { browserCryptoProviders } from '../src/protocol/crypto-providers.ts';
import { deriveIdentities } from '../src/protocol/identity-deriver.ts';
import { validateOnionAddress } from '../src/protocol/onion-address.ts';
import { signablePayload, signedMessage } from '../src/protocol/record-format.ts';
import { verifyWire, verifyRecord } from '../src/protocol/record-verifier.ts';
import { parseStrictJson } from '../src/protocol/strict-json.ts';
import { verifySnapshotMembership } from '../src/protocol/snapshot-membership.ts';

const crypto = browserCryptoProviders();
const input = createInterface({ input: process.stdin, crlfDelay: Infinity });
let count = 0;
let total = 0;
for await (const line of input) {
  total += Buffer.byteLength(line) + 1;
  if (++count > 256 || total > 1048576 || Buffer.byteLength(line) > 262144) {
    console.log(JSON.stringify({ status: 'FAIL', error: 'WORK_BUDGET_EXHAUSTED' }));
    process.exitCode = 3;
    input.close();
    break;
  }
  try {
    const request = parseStrictJson(new TextEncoder().encode(line), 262144);
    if (Object.keys(request).some(key => !['operation', 'input_chunks', 'expected_onion', 'max_bytes'].includes(key)) || !Array.isArray(request.input_chunks) || request.input_chunks.some(chunk => typeof chunk !== 'string')) throw new Error('SCHEMA_INVALID');
    const encoded = request.input_chunks.join('');
    const decoded = Buffer.from(encoded, 'base64');
    if (decoded.toString('base64') !== encoded) throw new Error('ENCODING_INVALID');
    const bytes = new Uint8Array(decoded);
    let output;
    if (request.operation === 'validate') {
      const record = await verifyWire(bytes, crypto, request.expected_onion);
      const document = parseStrictJson(bytes, 65536);
      output = { status: 'PASS', hash: record.contentHash, wire: record.canonicalWire,
        payload_hex: Buffer.from(canonicalJson(signablePayload(document))).toString('hex'), message_hex: Buffer.from(signedMessage(document)).toString('hex') };
    } else if (request.operation === 'address') {
      output = { status: 'PASS', address: validateOnionAddress(new TextDecoder('utf-8', { fatal: true }).decode(bytes), crypto.sha3) };
    } else if (request.operation === 'strict-json') {
      output = { status: 'PASS', value: parseStrictJson(bytes, request.max_bytes ?? 16384) };
    } else if (request.operation === 'canonicalize') {
      output = { status: 'PASS', canonical_hex: Buffer.from(canonicalJson(parseStrictJson(bytes, 65536))).toString('hex') };
    } else if (request.operation === 'sequence') {
      const sequence = parseStrictJson(bytes, 1048576);
      if (!Array.isArray(sequence.records)) throw new Error('SCHEMA_INVALID');
      const records = await Promise.all(sequence.records.map(record => verifyRecord(record, crypto)));
      output = { status: 'PASS', state: deriveIdentities(records, sequence.max_records ?? 100000) };
    } else if (request.operation === 'snapshot-membership') {
      const fixture = parseStrictJson(bytes, 1048576);
      if (!Array.isArray(fixture.local_records) || !Array.isArray(fixture.chunks) || !Array.isArray(fixture.checkpoint_wire) || typeof fixture.expected_node_key !== 'string' || fixture.chunks.some(wire => typeof wire !== 'string') || fixture.checkpoint_wire.some(wire => typeof wire !== 'string')) throw new Error('SCHEMA_INVALID');
      const history = await Promise.all(fixture.local_records.map(record => verifyRecord(record, crypto)));
      output = { status: 'PASS', result: await verifySnapshotMembership(new TextEncoder().encode(fixture.checkpoint_wire.join('')), fixture.chunks.map(wire => new TextEncoder().encode(wire)), fixture.expected_node_key, history, crypto) };
    } else throw new Error('USAGE_ERROR');
    console.log(JSON.stringify(output));
  } catch (error) {
    const code = error instanceof Error && /^[A-Z][A-Z_]+$/.test(error.message) ? error.message : 'INTERNAL_ERROR';
    console.log(JSON.stringify({ status: 'FAIL', error: code }));
  }
}
