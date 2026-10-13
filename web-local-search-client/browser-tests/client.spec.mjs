import {test,expect} from '@playwright/test';
import {createServer} from 'node:http';
import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url));
const fixtures=JSON.parse(await readFile(new URL('../../protocol/test-vectors/browser-dataset.json',import.meta.url),'utf8'));
let server;let origin;const requests=[];
const allowed=new Set(['index.html','client.js','verifier-worker.js','client.css','demo.json','config.json']);
test.beforeAll(async()=>{
  server=createServer(async(req,res)=>{
    requests.push(req.url);const prefix='/onion-service-discovery/';
    if(!req.url.startsWith(prefix)||req.url.includes('?')){res.writeHead(404);res.end();return;}
    const suffix=req.url.slice(prefix.length)||'index.html';
    if(suffix.startsWith('relay/')){
      const parts=suffix.split('/');const source=fixtures.sources.find(item=>item.onion===parts[1]);const endpoint=parts.slice(2).join('/');let body;
      if(source===undefined){res.writeHead(404);res.end();return;}
      if(endpoint==='node')body=JSON.stringify(source.node_descriptor);
      else if(endpoint==='state')body=JSON.stringify(source.state);
      else if(endpoint==='snapshots/'+source.state.checkpoint_hash.slice(7))body=JSON.stringify(source.checkpoint);
      else if(endpoint==='chunks/'+source.checkpoint.chunks[0].hash.slice(7))body=Buffer.from(source.chunk_base64,'base64');
      else{res.writeHead(404);res.end();return;}
      res.writeHead(200,{'Content-Type':'application/json','Cache-Control':'no-store'});res.end(body);return;
    }
    if(!allowed.has(suffix)){res.writeHead(404);res.end();return;}
    if(suffix==='config.json'){res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify({client_config_version:1,mode:'client-host',allowed_sources:fixtures.sources.map(item=>item.onion),minimum_distinct_keys:3,delivery_profile:'client-host-proxy'}));return;}
    try{const body=await readFile(root+'dist/'+suffix);res.writeHead(200,{'Content-Type':suffix.endsWith('.js')?'application/javascript':suffix.endsWith('.css')?'text/css':suffix.endsWith('.json')?'application/json':'text/html','X-Frame-Options':'DENY'});res.end(body);}catch{res.writeHead(503);res.end();}
  });
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));origin='http://127.0.0.1:'+server.address().port;
});
test.beforeEach(async({page})=>{await page.clock.setFixedTime(new Date(Date.parse(fixtures.sources[0].checkpoint.created_at)+3600000));});
test.afterAll(async()=>{await new Promise(resolve=>server.close(resolve));});

test('local search, signed source union, text-only metadata and local pins',async({page})=>{
  const network=[];const alerts=[];page.on('request',request=>network.push(request.url()));page.on('dialog',async dialog=>{alerts.push(dialog.message());await dialog.dismiss();});
  await page.goto(origin+'/onion-service-discovery/');
  await expect(page.locator('.source-option')).toHaveCount(3);
  await page.getByRole('button',{name:'Load directory',exact:true}).click();
  await expect(page.locator('#coverage')).toContainText('3 distinct fresh signing keys');
  await expect(page.locator('.result-card')).toHaveCount(3);
  await expect(page.locator('.result-card').filter({hasText:'<img src=x onerror=alert(1)>'})).toHaveCount(1);
  await expect(page.locator('img')).toHaveCount(0);expect(alerts).toEqual([]);
  await expect(page.locator('.result-card').filter({hasText:'Synthetic Withdrawn Service'})).toHaveCount(0);
  const before=network.length;await page.locator('#query').fill('sensitive-local-probe-013');await expect(page.locator('.result-card')).toHaveCount(0);await page.waitForTimeout(300);
  expect(network.length).toBe(before);expect(network.some(url=>url.includes('sensitive-local-probe-013'))).toBe(false);
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);
  await page.getByRole('button',{name:'Pin this identity',exact:true}).click();await expect(page.locator('.pin-card')).toHaveCount(1);
  await expect(page.locator('.pin-status')).toContainText('Signed continuity matches your pin');
  await page.locator('#remember-pins').check();await expect(page.locator('#storage-status')).toContainText('Pins saved');
  await page.reload();await expect(page.locator('.pin-card')).toHaveCount(1);await expect(page.locator('.pin-status')).toContainText('absent');
  await page.getByRole('button',{name:'Load directory',exact:true}).click();await expect(page.locator('.pin-status')).toContainText('Signed continuity matches');
  await page.getByRole('button',{name:'Forget local data',exact:true}).click();await expect(page.locator('#forget-dialog')).toBeVisible();await page.locator('#forget-dialog').getByRole('button',{name:'Forget local data',exact:true}).click();await expect(page.locator('.pin-card')).toHaveCount(0);
  expect(network.every(url=>url.startsWith(origin+'/onion-service-discovery/'))).toBe(true);
  expect(requests.some(url=>url.includes('sensitive-local-probe-013'))).toBe(false);
});
test('blocked profile storage keeps session verification and pins usable',async({page})=>{
  await page.addInitScript(()=>{Object.defineProperty(window,'localStorage',{get(){throw new Error('blocked');}});});
  await page.goto(origin+'/onion-service-discovery/');await page.getByRole('button',{name:'Try offline demo'}).click();await expect(page.locator('.result-card')).toHaveCount(3);
  await expect(page.locator('#synthetic-notice')).toBeVisible();await expect(page.locator('.visit')).toHaveCount(0);
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);await page.getByRole('button',{name:'Pin this identity',exact:true}).click();await expect(page.locator('.pin-card')).toHaveCount(1);
  await page.locator('#remember-pins').check();await expect(page.locator('#storage-status')).toContainText('Profile storage unavailable');await expect(page.locator('.pin-card')).toHaveCount(1);
  await page.screenshot({path:test.info().outputPath('browser-preview.png'),fullPage:true});
});

test('a failed refresh preserves the last verified local index and pins',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  await page.getByRole('button',{name:'Load directory',exact:true}).click();
  await expect(page.locator('#coverage')).toContainText('3 distinct fresh signing keys');
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);
  await page.getByRole('button',{name:'Pin this identity',exact:true}).click();
  await page.route('**/relay/**',route=>route.abort());
  await page.getByRole('button',{name:'Load directory',exact:true}).click();
  await expect(page.locator('#coverage')).toContainText('0 distinct fresh signing keys');
  await expect(page.locator('.result-card')).toHaveCount(1);
  await expect(page.locator('.pin-status')).toContainText('Signed continuity matches');
  const network=[];page.on('request',request=>network.push(request.url()));
  await page.locator('#query').fill('climate');await expect(page.locator('.result-card')).toHaveCount(1);
  expect(network).toEqual([]);
});

test('registry filters and adult preference cause no query-dependent network work',async({page})=>{
  const network=[];page.on('request',request=>network.push(request.url()));
  await page.goto(origin+'/onion-service-discovery/');
  await page.getByRole('button',{name:'Load directory',exact:true}).click();
  await expect(page.locator('#coverage')).toContainText('3 distinct fresh signing keys');
  await expect(page.locator('.result-card')).toHaveCount(3);
  await expect(page.locator('#category option')).toHaveCount(39);
  const before=network.length;
  await page.locator('#category').selectOption('research');
  await expect(page.locator('.result-card')).toHaveCount(1);
  await expect(page.locator('.result-card')).toContainText('Synthetic Climate Archive');
  await page.locator('#category').selectOption('adult');
  await expect(page.locator('.result-card')).toHaveCount(0);
  await page.locator('#adult-visibility').selectOption('hide');
  await page.locator('#category').selectOption('');
  await expect(page.locator('.result-card')).toHaveCount(3);
  await page.waitForTimeout(150);
  expect(network.length).toBe(before);
  await expect(page.locator('img,iframe,video,audio')).toHaveCount(0);
});
