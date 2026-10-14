import {test,expect} from '@playwright/test';
import {createDemoServer} from '../tools/serve-demo.mjs';
let server;let origin;
test.beforeAll(async()=>{
 server=await createDemoServer();
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 origin='http://127.0.0.1:'+server.address().port;
});
test.afterAll(async()=>{server.closeAllConnections();await new Promise(resolve=>server.close(resolve));});
test('GitHub visitor quickstart verifies synthetic data, searches locally and pins an identity',async({page})=>{
 const requests=[];page.on('request',request=>requests.push(request.url()));
 await page.goto(origin);
 await page.getByRole('button',{name:'Try offline demo',exact:true}).click();
 await expect(page.locator('#synthetic-notice')).toBeVisible();
 await expect(page.locator('.result-card')).toHaveCount(3);
 const before=requests.length;
 await page.locator('#query').fill('library');
 await expect(page.locator('.result-card')).toHaveCount(1);
 await page.getByRole('button',{name:'Pin this identity',exact:true}).click();
 await expect(page.locator('.pin-card')).toHaveCount(1);
 await expect(page.locator('.visit')).toHaveCount(0);
 expect(requests.length).toBe(before);
 expect(requests.every(url=>url.startsWith(origin+'/'))).toBe(true);
 await page.screenshot({path:test.info().outputPath('local-demo.png'),fullPage:true});
});
