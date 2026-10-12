"""Seeded differential malformed-input smoke fuzzing, not exhaustive fuzz assurance."""
import argparse,base64,copy,hashlib,json,random,subprocess,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def corpus():
 rng=random.Random(20261012);fixture=json.loads((ROOT/'protocol/test-vectors/signed-records.json').read_text())['stories']['genesis'];items=[]
 for i in range(512):
  record=copy.deepcopy(fixture)
  mode=i%4
  if mode==0:record['service']['description']='Mutated unsigned description '+str(rng.randrange(1000000000))
  elif mode==1:record['service']['name']='Mutated unsigned name '+str(rng.randrange(1000000000))
  elif mode==2:record['discovery']['publish_service']=False
  else:record['unexpected_'+str(i)]=rng.randrange(1000)
  items.append(('validate',json.dumps(record,separators=(',',':')).encode()))
 for i in range(512):
  key='property'+str(i);valid=json.dumps({key:rng.randrange(1000000)},separators=(',',':'))
  escaped=''.join('\\u%04x'%ord(c) for c in key)
  # Exact and escape-equivalent duplicate keys must both be rejected.
  duplicate=valid[:-1]+',"'+(key if i%2 else escaped)+'":0}'
  items.append(('strict-json',duplicate.encode()))
 return items
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--report',type=Path);a=p.parse_args();data=corpus();digest=hashlib.sha256(b''.join(operation.encode()+b'\0'+raw for operation,raw in data)).hexdigest()
 php=['docker','run','--rm','-i','--network','none','--mount',f'type=bind,src={ROOT},dst=/app,readonly','--workdir','/app','php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3','php','node-php/bin/conformance-adapter.php']
 commands={'PHP':php,'TypeScript':['node','web-local-search-client/tools/conformance-adapter.mjs'],'Python':[sys.executable,'validator-python/conformance_adapter.py']};outputs={};timings={}
 for language,command in commands.items():
  results=[];start=time.monotonic()
  for offset in range(0,len(data),128):
   requests=[{'operation':operation,'input_chunks':[base64.b64encode(raw).decode()]} for operation,raw in data[offset:offset+128]]
   completed=subprocess.run(command,cwd=ROOT,input=''.join(json.dumps(r)+'\n' for r in requests),text=True,capture_output=True,timeout=60)
   if completed.returncode or completed.stderr:raise RuntimeError(language+' adapter process failed: '+completed.stderr[:256])
   batch=[json.loads(line) for line in completed.stdout.splitlines()]
   if len(batch)!=len(requests) or any(row.get('status')!='FAIL' for row in batch):raise RuntimeError(language+' accepted malformed corpus')
   results.extend(batch)
  outputs[language]=results;timings[language]=time.monotonic()-start;print(f'{language}: {len(results)} malformed inputs rejected',flush=True)
 if not outputs['PHP']==outputs['TypeScript']==outputs['Python']:raise RuntimeError('Differential error classification mismatch')
 report={'status':'PASS','seed':20261012,'cases_per_language':len(data),'corpus_sha256':digest,'seconds':timings,'scope':'semantic signed-payload mutations and duplicate/escaped-equivalent object keys; no exhaustive or coverage-guided fuzz claim'}
 if a.report:a.report.write_text(json.dumps(report,indent=2)+'\n')
