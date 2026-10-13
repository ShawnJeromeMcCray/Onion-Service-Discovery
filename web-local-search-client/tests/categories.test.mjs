import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { CATEGORY_IDS, CATEGORY_LABELS } from '../src/protocol/category-id.ts';
import { localSearch } from '../src/client/view-model.ts';

test('category constants and schema agree with the sole registry', () => {
  const registry=JSON.parse(readFileSync(new URL('../../protocol/registries/categories-v1.json',import.meta.url)));
  const schema=JSON.parse(readFileSync(new URL('../../protocol/schemas/service_manifest.json',import.meta.url)));
  assert.deepEqual(CATEGORY_IDS,registry.categories.map(item=>item.id).sort());
  assert.deepEqual(schema.properties.service.properties.categories.items.enum,CATEGORY_IDS);
  assert.ok(CATEGORY_IDS.includes('adult'));
  assert.equal(Object.keys(CATEGORY_LABELS).length,CATEGORY_IDS.length);
});
const row=(key,name,categories)=>({genesisKey:key,name,description:'fictional collection',onion:null,revision:1,status:'PUBLISHED',addressConflict:false,languages:['en'],categories,observationClaims:0,rotationCount:0,migrationCount:0,currentHash:null});
test('exact category and session-local adult visibility preserve distinct identities', () => {
  const rows=[row('1','A',['adult']),row('2','B',['books']),row('3','C',['adult','books'])];
  assert.deepEqual(localSearch(rows,'','', 'books').map(item=>item.genesisKey),['2','3']);
  assert.deepEqual(localSearch(rows,'','', '', 'hide').map(item=>item.genesisKey),['2']);
  assert.deepEqual(localSearch(rows,'','', '', 'de-emphasize').map(item=>item.genesisKey),['2','1','3']);
  assert.equal(localSearch(rows,'').length,3);
  assert.equal(rows.length,3);
});
test('category quantity and observer count give no ordering advantage', () => {
  const original=[row('2','Same',['books']),row('1','Same',['books'])];
  const enlarged=structuredClone(original);enlarged[0].categories=[...CATEGORY_IDS];enlarged[0].observationClaims=10000;
  assert.deepEqual(localSearch(original,'Same').map(item=>item.genesisKey),localSearch(enlarged,'Same').map(item=>item.genesisKey));
});
