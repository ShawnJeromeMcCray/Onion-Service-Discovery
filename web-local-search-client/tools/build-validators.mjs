import Ajv2020 from 'ajv/dist/2020.js';
import standaloneCode from 'ajv/dist/standalone/index.js';
import { build } from 'esbuild';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const schemaDirectory = new URL('../../protocol/schemas/', import.meta.url);
const registry = JSON.parse(readFileSync(new URL('manifest.json', schemaDirectory), 'utf8'));
const ajv = new Ajv2020({ strict: true, strictTypes: false, code: { source: true, esm: true }, allErrors: false });
const exports = {};
for (const type of registry.record_types) {
  const schema = JSON.parse(readFileSync(new URL(type + '.json', schemaDirectory), 'utf8'));
  ajv.addSchema(schema);
  exports[type] = schema.$id;
}
const generated = standaloneCode(ajv, exports);
const output = new URL('../generated/validators.mjs', import.meta.url);
await build({ absWorkingDir: fileURLToPath(new URL('..', import.meta.url)), stdin: { contents: generated, resolveDir: fileURLToPath(new URL('..', import.meta.url)), sourcefile: 'compiled-schemas.mjs' }, bundle: true, format: 'esm', platform: 'browser', target: 'es2022', outfile: fileURLToPath(output), legalComments: 'inline' });
writeFileSync(new URL('../generated/validators.d.mts', import.meta.url), registry.record_types.map(type => `export function ${type}(value: unknown): boolean;`).join('\n') + '\n');
console.log('Compiled ' + registry.record_types.length + ' local schema validators without browser eval or remote imports');
