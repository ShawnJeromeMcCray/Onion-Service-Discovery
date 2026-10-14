import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {localSearch} from '../src/client/view-model.ts';
const vectors=JSON.parse(readFileSync(new URL('../../protocol/test-vectors/local-search-v1.json',import.meta.url),'utf8'));
for(const vector of vectors.cases)test('version-1 shared ranking: '+vector.id,()=>{
 const before=JSON.stringify(vectors.rows);
 assert.deepEqual(localSearch(vectors.rows,vector.query,vector.language,vector.category,vector.adult).map(row=>row.genesisKey),vector.expected_ids);
 assert.equal(JSON.stringify(vectors.rows),before);
});
test('cached Unicode search reflects changed metadata and visibility',()=>{
 const row=structuredClone(vectors.rows.find(row=>row.status==='PUBLISHED'));
 row.name='Ｃａｃｈｅ';row.description='old description';row.categories=[];row.languages=[];
 assert.equal(localSearch([row],'cache').length,1);
 assert.equal(localSearch([row],'cache').length,1);
 row.name='Replacement';row.description='new description';
 assert.equal(localSearch([row],'cache').length,0);
 assert.equal(localSearch([row],'replacement').length,1);
 row.categories.push('fresh-category');row.languages.push('fr');
 assert.equal(localSearch([row],'fresh-category','fr').length,1);
 row.status='WITHDRAWN';
 assert.equal(localSearch([row],'replacement').length,0);
});
