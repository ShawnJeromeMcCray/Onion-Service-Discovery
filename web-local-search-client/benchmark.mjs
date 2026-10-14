// Node executes the same bundled browser verifier/view code; not a browser UI or Tor measurement.
import {readFileSync} from 'node:fs';
import {performance} from 'node:perf_hooks';
import {browserCryptoProviders} from './src/protocol/crypto-providers.ts';
import {verifyRecord} from './src/protocol/record-verifier.ts';
import {buildView,localSearch} from './src/client/view-model.ts';
const dataset=JSON.parse(readFileSync(process.argv[2],'utf8'));const crypto=browserCryptoProviders();const records=[];const verify=[];let started=performance.now();
for(const item of dataset){const start=performance.now();records.push(await verifyRecord(item,crypto));verify.push(performance.now()-start);}
const total=performance.now()-started;const derive=[];let view;
for(let index=0;index<10;index++){const start=performance.now();view=buildView(records,[]);derive.push(performance.now()-start);}
if(view.services.length!==1000)throw new Error('Dataset identity mismatch');
const searches=[];for(let index=0;index<100;index++){const start=performance.now();localSearch(view.services,index%2?'library 123':'multilingual books','en');searches.push(performance.now()-start);}
const firstSearch=searches[0];
function stats(values){values.sort((a,b)=>a-b);return {samples:values.length,p50_ms:values[Math.floor((values.length-1)*0.5)],p95_ms:values[Math.floor((values.length-1)*0.95)],p99_ms:values[Math.floor((values.length-1)*0.99)]};}
console.log(JSON.stringify({profile:'S1000-typical-5-node-claims',execution:'Node.js runs browser crypto/view code; no DOM, Tor or network',node:process.version,record_count:records.length,verification_total_ms:total,verification:stats(verify),derive:stats(derive),local_search:{...stats(searches),first_query_ms:firstSearch,max_ms:Math.max(...searches)},memory_bytes:process.memoryUsage(),cpu_microseconds:process.cpuUsage()},null,2));
