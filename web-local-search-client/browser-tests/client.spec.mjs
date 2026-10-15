import {test,expect} from '@playwright/test';
import {createServer} from 'node:http';
import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url));
const fixtures=JSON.parse(await readFile(new URL('../../protocol/test-vectors/browser-dataset.json',import.meta.url),'utf8'));
let server;let origin;const requests=[];let clientConfig=null;
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
    if(suffix==='config.json'){res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify(clientConfig??{client_config_version:1,mode:'client-host',allowed_sources:fixtures.sources.map(item=>item.onion),minimum_distinct_keys:3,delivery_profile:'client-host-proxy'}));return;}
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
  await page.getByRole('button',{name:/^Pin this identity/}).click();await expect(page.locator('.pin-card')).toHaveCount(1);
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
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);await page.getByRole('button',{name:/^Pin this identity/}).click();await expect(page.locator('.pin-card')).toHaveCount(1);
  await page.locator('#remember-pins').check();await expect(page.locator('#storage-status')).toContainText('Profile storage unavailable');await expect(page.locator('.pin-card')).toHaveCount(1);
  await page.screenshot({path:test.info().outputPath('browser-preview.png'),fullPage:true});
});

test('a failed refresh preserves the last verified local index and pins',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  await page.getByRole('button',{name:'Load directory',exact:true}).click();
  await expect(page.locator('#coverage')).toContainText('3 distinct fresh signing keys');
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);
  await page.getByRole('button',{name:/^Pin this identity/}).click();
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
test('visitor-chosen Nodes need the host switch and the expected key, and persist only on request',async({page})=>{
  const [first,second,third]=fixtures.sources;const keyOf=source=>source.node_descriptor.node_identity_public_key;
  await page.goto(origin+'/onion-service-discovery/');await page.getByText('Source selection and expected keys').click();
  await expect(page.locator('#visitor-form')).toBeHidden();await expect(page.locator('#visitor-disabled')).toBeVisible();
  clientConfig={client_config_version:1,mode:'client-host',allowed_sources:[first.onion,second.onion],minimum_distinct_keys:3,delivery_profile:'client-host-proxy',visitor_sources:true};
  try{
    await page.reload();await page.getByText('Source selection and expected keys').click();
    await expect(page.locator('#visitor-form')).toBeVisible();await expect(page.locator('#visitor-disabled')).toBeHidden();
    await page.locator('#visitor-onion').fill('not-an-onion.onion');await page.locator('#visitor-key').fill(keyOf(third));await page.getByRole('button',{name:'Add Node',exact:true}).click();
    await expect(page.locator('#visitor-status')).toContainText('Check the onion address');
    await page.locator('#visitor-onion').fill(third.onion);await page.locator('#visitor-key').fill('');await page.getByRole('button',{name:'Add Node',exact:true}).click();
    await expect(page.locator('#visitor-status')).toContainText('expected public key');
    await page.locator('#visitor-onion').fill(first.onion);await page.locator('#visitor-key').fill(keyOf(first));await page.getByRole('button',{name:'Add Node',exact:true}).click();
    await expect(page.locator('#visitor-status')).toContainText('already listed');
    // A wrong expected key is caught by the signed Node descriptor and quarantines that source.
    await page.locator('#visitor-onion').fill(third.onion);await page.locator('#visitor-key').fill(keyOf(first));await page.getByRole('button',{name:'Add Node',exact:true}).click();
    await expect(page.locator('#visitor-source-options .source-option')).toHaveCount(1);
    await page.getByRole('button',{name:'Load directory',exact:true}).click();
    await expect(page.locator('.source-status').filter({hasText:third.onion})).toContainText('SOURCE_NODE_KEY_CHANGED');
    await expect(page.locator('.source-status').filter({hasText:third.onion})).toContainText('Check the key you entered');
    await expect(page.locator('#coverage')).toContainText('2 distinct fresh signing keys');
    await page.getByRole('button',{name:'Remove the Node '+third.onion,exact:true}).click();await expect(page.locator('#visitor-source-options .source-option')).toHaveCount(0);
    // The correct key loads the third Node through the same-origin relay.
    await page.locator('#visitor-key').fill(keyOf(third));await page.getByRole('button',{name:'Add Node',exact:true}).click();
    await expect(page.locator('#visitor-source-options .source-option')).toContainText('added by you');
    await page.getByRole('button',{name:'Load directory',exact:true}).click();
    await expect(page.locator('#coverage')).toContainText('3 distinct fresh signing keys');
    await expect(page.locator('.result-card')).toHaveCount(3);
    expect(requests.some(url=>url.startsWith('/onion-service-discovery/relay/'+third.onion+'/node'))).toBe(true);
    // Persistence is opt-in, survives a reload and is removed by Forget local data.
    await page.locator('#remember-sources').check();await page.reload();await page.getByText('Source selection and expected keys').click();
    await expect(page.locator('#visitor-source-options .source-option')).toHaveCount(1);await expect(page.locator('#remember-sources')).toBeChecked();
    await page.getByRole('button',{name:'Forget local data',exact:true}).click();await page.locator('#forget-dialog').getByRole('button',{name:'Forget local data',exact:true}).click();
    await expect(page.locator('#visitor-source-options .source-option')).toHaveCount(0);
    await page.reload();await page.getByText('Source selection and expected keys').click();await expect(page.locator('#visitor-source-options .source-option')).toHaveCount(0);
  }finally{clientConfig=null;}
});

test('saved pins that cannot be read stay untouched until the visitor chooses to replace them',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  // A backup written by the earlier label cut: half an emoji is an escaped lone surrogate.
  const unreadable='{"pin_backup_version":1,"pins":[{"genesisKey":"ed25519:'+'A'.repeat(43)+'","contextOnion":"'+fixtures.sources[0].onion+'","anchorHash":"sha256:'+'1'.repeat(64)+'","latestHash":"sha256:'+'1'.repeat(64)+'","highestRevision":1,"label":"A\\ud83d","conflicted":false}]}';
  await page.evaluate(value=>localStorage.setItem('onion-service-discovery-pins-v1',value),unreadable);
  await page.reload();
  await expect(page.locator('#storage-status')).toContainText('could not be read');await expect(page.locator('#remember-pins')).not.toBeChecked();
  await page.getByRole('button',{name:'Load directory',exact:true}).click();await expect(page.locator('#coverage')).toContainText('3 distinct fresh signing keys');
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);
  await page.getByRole('button',{name:/^Pin this identity/}).click();await expect(page.locator('.pin-card')).toHaveCount(1);
  expect(await page.evaluate(()=>localStorage.getItem('onion-service-discovery-pins-v1'))).toBe(unreadable);
  await page.locator('#remember-pins').check();await expect(page.locator('#storage-status')).toContainText('Pins saved');
  const saved=JSON.parse(await page.evaluate(()=>localStorage.getItem('onion-service-discovery-pins-v1')));expect(saved.pins).toHaveLength(1);
  await page.reload();await expect(page.locator('.pin-card')).toHaveCount(1);await expect(page.locator('#remember-pins')).toBeChecked();
});

test('cancel marks unfinished sources as cancelled, never as loading forever',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  await page.route('**/relay/**',async route=>{await new Promise(resolve=>setTimeout(resolve,800));await route.continue();});
  await page.getByRole('button',{name:'Load directory',exact:true}).click();
  await expect(page.locator('.source-status').first()).toContainText('Loading');
  await expect(page.locator('.source-status').first()).toContainText('Downloading the signed checkpoint and history.');
  await page.getByRole('button',{name:'Cancel',exact:true}).click();
  await expect(page.getByRole('button',{name:'Load directory',exact:true})).toBeEnabled();
  await expect(page.locator('.source-status strong')).toHaveText(['Cancelled','Cancelled','Cancelled']);
});

test('pin import keeps existing pins, adds new ones and reports skipped conflicts',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  await page.getByRole('button',{name:'Load directory',exact:true}).click();await expect(page.locator('.result-card')).toHaveCount(3);
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);
  await page.getByRole('button',{name:/^Pin this identity/}).click();
  await page.locator('#remember-pins').check();
  const held=JSON.parse(await page.evaluate(()=>localStorage.getItem('onion-service-discovery-pins-v1'))).pins[0];
  await page.locator('#query').fill('');await expect(page.locator('.result-card')).toHaveCount(3);
  const other=page.locator('.result-card').filter({hasNotText:'Library'}).first();
  const otherKey=await other.locator('.key-value').first().textContent();const otherOnion=await other.locator('.onion-address').first().textContent();
  const backup={pin_backup_version:1,pins:[{...held,anchorHash:'sha256:'+'2'.repeat(64),label:'Imported impostor'},{genesisKey:otherKey,contextOnion:otherOnion,anchorHash:'sha256:'+'3'.repeat(64),latestHash:'sha256:'+'3'.repeat(64),highestRevision:1,label:'Imported pin',conflicted:false}]};
  await page.locator('#import-pins').setInputFiles({name:'pins.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(backup))});
  await expect(page.locator('#pin-import-dialog')).toBeVisible();
  await expect(page.locator('.preview-pin.conflict')).toContainText('yours is kept');await expect(page.locator('.preview-pin').filter({hasText:'Imported pin'})).toContainText('New pin');
  await page.getByRole('button',{name:'Import reviewed pins',exact:true}).click();
  await expect(page.locator('#operation-status')).toContainText('1 added, 0 updated to a newer remembered revision, 1 skipped');
  await expect(page.locator('.pin-card')).toHaveCount(2);await expect(page.locator('.pin-card').filter({hasText:'Imported impostor'})).toHaveCount(0);
  // The same file can be chosen again after the input was cleared.
  await page.locator('#import-pins').setInputFiles({name:'pins.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(backup))});
  await expect(page.locator('#pin-import-dialog')).toBeVisible();await page.getByRole('button',{name:'Cancel',exact:true}).last().click();
});

test('without JavaScript only the explanation and the server-search option are shown',async({browser})=>{
  const context=await browser.newContext({javaScriptEnabled:false});const page=await context.newPage();
  await page.goto(origin+'/onion-service-discovery/');
  await expect(page.locator('#client-startup-notice')).toBeVisible();await expect(page.locator('#client-startup-notice')).toContainText('No search has been sent');
  await expect(page.locator('#app')).toBeHidden();await expect(page.getByRole('button')).toHaveCount(0);await expect(page.getByRole('searchbox')).toHaveCount(0);
  await context.close();
});

test('keyboard focus survives updates, warnings are announced once and controls are named',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  await expect(page.locator('#client-startup-notice')).toBeHidden();await expect(page.locator('#app')).toBeVisible();
  await page.getByRole('button',{name:'Load directory',exact:true}).click();await expect(page.locator('.result-card')).toHaveCount(3);
  // The load result is summarized for screen readers instead of announcing every update.
  await expect(page.locator('#operation-status')).toContainText('Loading finished: 3 of 3 sources verified');
  await expect(page.locator('#announcer')).toContainText('Loading finished: 3 of 3 sources verified');
  await page.locator('#query').fill('library');await expect(page.locator('.result-card')).toHaveCount(1);
  await expect(page.locator('#announcer')).toContainText('1 matching identities');
  // Opened details stay open and the pin button keeps focus when the view is rebuilt.
  await page.locator('.result-card details summary').first().click();
  await page.getByRole('button',{name:/^Pin this identity: /}).focus();await page.keyboard.press('Enter');
  await expect(page.locator('.pin-card')).toHaveCount(1);
  await expect.poll(()=>page.evaluate(()=>document.activeElement?.getAttribute('aria-label')??'')).toMatch(/^Remove pin: /);
  await expect(page.locator('.result-card details').first()).toHaveAttribute('open','');
  await page.keyboard.press('Enter');await expect(page.locator('.pin-card')).toHaveCount(0);
  await expect.poll(()=>page.evaluate(()=>document.activeElement?.getAttribute('aria-label')??'')).toMatch(/^Pin this identity: /);
  // Every control has an accessible name; text fields have a boundary of at least 3:1 (WCAG 1.4.11).
  const unnamed=await page.evaluate(()=>[...document.querySelectorAll('button,a[href],input,select')].filter(node=>{
    if(node.closest('[hidden]')||node.type==='file')return false;const label=node.getAttribute('aria-label')??(node.id?document.querySelector('label[for="'+node.id+'"]')?.textContent:'')??'';
    return (label||node.textContent||node.closest('label')?.textContent||'').trim()==='';}).map(node=>node.outerHTML.slice(0,80)));
  expect(unnamed).toEqual([]);
  const ratio=await page.evaluate(()=>{const parse=value=>value.match(/\d+(\.\d+)?/g).slice(0,3).map(Number);const lum=([r,g,b])=>{const f=c=>{c/=255;return c<=0.03928?c/12.92:((c+0.055)/1.055)**2.4;};return 0.2126*f(r)+0.7152*f(g)+0.0722*f(b);};
    const input=document.getElementById('query');const border=lum(parse(getComputedStyle(input).borderTopColor));const panel=lum(parse(getComputedStyle(input.closest('section')).backgroundColor));
    return (Math.max(border,panel)+0.05)/(Math.min(border,panel)+0.05);});
  expect(ratio).toBeGreaterThanOrEqual(3);
});

test('results and sources show where the data came from and when it was signed',async({page})=>{
  await page.goto(origin+'/onion-service-discovery/');
  await expect(page.locator('#client-version')).toHaveText('Client 0.1.0-dev · protocol version 1');
  await page.getByRole('button',{name:'Load directory',exact:true}).click();await expect(page.locator('.result-card')).toHaveCount(3);
  await expect(page.locator('.result-card').first()).toContainText(/Current revision included by [1-3] of 3 loaded sources/);
  await expect(page.locator('.source-status').first()).toContainText(/Signed checkpoint generation \d+, created \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC \(60 minutes ago\)/);
  await page.locator('.result-card details summary').first().click();
  await expect(page.locator('.result-card details').first()).toContainText('Current revision supplied by');
  await expect(page.locator('.result-card details').first()).toContainText('Signed Manifest of revision');
  await expect(page.locator('.result-card details').first()).toContainText(/Identity key fingerprint: [A-Z2-7]{5}(-[A-Z2-7]{5}){5}/);
  // D-025: the same fingerprint the shared vectors (and node:identity) give for this Node key.
  const fingerprints=JSON.parse(await readFile(new URL('../../protocol/test-vectors/key-fingerprint-v1.json',import.meta.url),'utf8')).cases;
  const nodeKey=fixtures.sources[0].node_descriptor.node_identity_public_key;const expected=fingerprints.find(item=>item.public_key===nodeKey);
  expect(expected).toBeTruthy();
  await expect(page.locator('.source-status').first()).toContainText('Node key fingerprint: '+expected.fingerprint);
});

test('expected Node keys from the Client Host are used from the first load on',async({page})=>{
  const [first,second,third]=fixtures.sources;const keyOf=source=>source.node_descriptor.node_identity_public_key;
  clientConfig={client_config_version:1,mode:'client-host',allowed_sources:[first.onion,second.onion,third.onion],minimum_distinct_keys:3,delivery_profile:'client-host-proxy',expected_node_keys:{[first.onion]:keyOf(first),[second.onion]:keyOf(second),[third.onion]:keyOf(first)}};
  try{
    await page.goto(origin+'/onion-service-discovery/');await page.getByText('Source selection and expected keys').click();
    await expect(page.locator('#source-0-key')).toHaveValue(keyOf(first));await expect(page.locator('#source-0-key')).toHaveAttribute('readonly','');
    await expect(page.locator('label[for="source-2-key"]')).toContainText('provided by this Client Host');
    await page.getByRole('button',{name:'Load directory',exact:true}).click();
    // The third source signs with its own key, not the one the host vouched for: refused, not trusted on first contact.
    await expect(page.locator('.source-status').filter({hasText:third.onion})).toContainText('SOURCE_NODE_KEY_CHANGED');
    await expect(page.locator('#coverage')).toContainText('2 distinct fresh signing keys');
  }finally{clientConfig=null;}
});
