// Loopback preview of built public assets. No Tor, proxy, signing or data store.
import {createServer} from 'node:http';
import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {resolve} from 'node:path';

export async function createDemoServer() {
  const assets=new Map();
  for(const [name,type] of [['index.html','text/html; charset=utf-8'],['client.js','text/javascript; charset=utf-8'],['verifier-worker.js','text/javascript; charset=utf-8'],['client.css','text/css; charset=utf-8'],['demo.json','application/json'],['config.json','application/json']]) {
    assets.set(name,{type,body:await readFile(new URL('../dist/'+name,import.meta.url))});
  }
  const config=JSON.parse(assets.get('config.json').body);
  if(config.allowed_sources.length!==0)throw new Error('Demo requires the default empty source configuration; run npm run build');
  return createServer((request,response)=>{
    response.setHeader('Cache-Control','no-store');
    response.setHeader('Referrer-Policy','no-referrer');
    response.setHeader('X-Content-Type-Options','nosniff');
    response.setHeader('Content-Security-Policy',"default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; worker-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    if(!['GET','HEAD'].includes(request.method)){response.writeHead(405,{Allow:'GET, HEAD'});response.end();return;}
    if(request.url==='/'){response.writeHead(302,{Location:'/onion-service-discovery/'});response.end();return;}
    const prefix='/onion-service-discovery/';
    const name=request.url?.startsWith(prefix)?request.url.slice(prefix.length)||'index.html':'';
    const asset=assets.get(name);
    if(!asset){response.writeHead(404);response.end();return;}
    response.writeHead(200,{'Content-Type':asset.type,'Content-Length':asset.body.length});
    response.end(request.method==='HEAD'?undefined:asset.body);
  });
}

if(process.argv[1]&&resolve(process.argv[1])===fileURLToPath(import.meta.url)) {
  const args=process.argv.slice(2);
  const port=args.length===0?8080:args.length===2&&args[0]==='--port'&&/^\d{1,5}$/.test(args[1])?Number(args[1]):-1;
  if(port<0||port>65535){console.error('Usage: npm run demo -- --port 8080');process.exitCode=2;}
  else {
    try {
      const server=await createDemoServer();
      server.on('error',error=>{console.error('Demo listener failed: '+error.code);process.exitCode=1;});
      server.listen(port,'127.0.0.1',()=>console.log('Synthetic local demo: http://127.0.0.1:'+server.address().port+'/onion-service-discovery/ — press Ctrl+C to stop'));
      for(const signal of ['SIGINT','SIGTERM'])process.once(signal,()=>{server.close();server.closeAllConnections();});
    } catch {console.error('Demo assets unavailable. Run npm run build with the default configuration.');process.exitCode=1;}
  }
}
