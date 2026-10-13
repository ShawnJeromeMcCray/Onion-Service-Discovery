import { test } from 'node:test';
import assert from 'node:assert/strict';
import { performance } from 'node:perf_hooks';
import { groupSimilarResults } from '../src/client/result-groups.ts';
import { localSearch } from '../src/client/view-model.ts';

const row=(key,name='Same',description='Same description')=>({genesisKey:key,name,description,onion:null,revision:1,status:'PUBLISHED',addressConflict:false,languages:['en'],categories:['other'],observationClaims:0,rotationCount:0,migrationCount:0,currentHash:null});
test('metadata groups preserve every distinct identity and do not vote',()=>{
  const input=[row('1'),row('2','Ｓａｍｅ'),row('3','Different')];
  const groups=groupSimilarResults(localSearch(input,''));
  assert.equal(groups.length,2);
  assert.equal(groups.flatMap(group=>group.entries).length,3);
  assert.deepEqual(new Set(groups.flatMap(group=>group.entries).map(entry=>entry.genesisKey)),new Set(['1','2','3']));
  assert.equal(input.length,3);
});
test('100000 synthetic presentation identities cannot occupy 100000 result groups through duplicate metadata',()=>{
  // Presentation stress only; these rows do not claim to be signature-verified records.
  const rows=Array.from({length:100000},(_,index)=>row(String(index).padStart(6,'0')));
  rows.push(row('honest','Unrelated name','Separate description'));
  const start=performance.now();const groups=groupSimilarResults(localSearch(rows,''));
  assert.equal(groups.length,2);
  assert.equal(groups.find(group=>group.representative.name==='Same').entries.length,100000);
  assert.equal(groups.flatMap(group=>group.entries).length,100001);
  assert.ok(performance.now()-start<10000,'Bounded offline presentation stress exceeded ten seconds');
});
