import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createDemoServer} from '../tools/serve-demo.mjs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

test('visitor demo serves only built assets on loopback without query logging or proxy routes',async()=>{
 execFileSync(process.execPath,[fileURLToPath(new URL('../tools/build-client.mjs',import.meta.url))],{stdio:'pipe'});
 const server=await createDemoServer();
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 try {
  assert.equal(server.address().address,'127.0.0.1');
  const origin='http://127.0.0.1:'+server.address().port;
  const page=await fetch(origin+'/onion-service-discovery/');
  assert.equal(page.status,200);assert.match(await page.text(),/Try offline demo/);
  assert.equal(page.headers.get('referrer-policy'),'no-referrer');
  assert.match(page.headers.get('content-security-policy'),/connect-src 'self'/);
  const head=await fetch(origin+'/onion-service-discovery/demo.json',{method:'HEAD'});
  assert.equal(head.status,200);assert.equal(await head.text(),'');
  assert.ok(Number(head.headers.get('content-length'))>0);
  for(const path of ['config.json?query=probe','relay/example/node','%2e%2e/package.json','../package.json'])assert.equal((await fetch(origin+'/onion-service-discovery/'+path)).status,404);
  assert.equal((await fetch(origin+'/onion-service-discovery/demo.json',{method:'POST'})).status,405);
 } finally {server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
});
