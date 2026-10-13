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
