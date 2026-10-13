"""Exercise an explicitly trusted local development candidate after provisioning its PHP lock."""
import argparse,json,os,re,subprocess,tarfile,tempfile
from pathlib import Path
from verify_candidate import verify
PHP='php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3'
COMPOSER='composer@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac'
def run(artifact,commit):
 checked=verify(artifact)
 if re.fullmatch('[0-9a-f]{40}',commit)is None or checked['source_commit']!=commit:raise ValueError('Source mismatch')
 results=[]
 with tempfile.TemporaryDirectory(prefix='osd-candidate-smoke-')as temporary:
  destination=Path(temporary)
  # Structure verification above rejects links, traversal and oversized entries.
  # This step executes code only from the caller's explicitly trusted own build.
  with tarfile.open(artifact,'r:gz')as archive:
   for entry in archive:
    path=destination/entry.name;path.parent.mkdir(parents=True,exist_ok=True)
    with path.open('xb')as output,archive.extractfile(entry)as source:
     while chunk:=source.read(65536):output.write(chunk)
    path.chmod(0o755 if entry.name.endswith('/node-php/bin/onion-service-discovery')else 0o644)
  root=destination/'onion-service-discovery'
  command=['docker','run','--rm','--user',f'{os.getuid()}:{os.getgid()}','--mount',f'type=bind,src={root / "node-php"},dst=/app','--mount','type=bind,src=/etc/ssl/certs/ca-certificates.crt,dst=/run/ca-bundle.crt,readonly','-e','COMPOSER_CAFILE=/run/ca-bundle.crt','-e','SSL_CERT_FILE=/run/ca-bundle.crt','-e','COMPOSER_HOME=/tmp/composer','--workdir','/app',COMPOSER,'composer','install','--no-dev','--no-plugins','--no-scripts','--no-interaction','--prefer-dist']
  php=['docker','run','--rm','--network','none','--mount',f'type=bind,src={root},dst=/app,readonly','--workdir','/app',PHP,'php']
  commands=[('pinned-php-runtime-pull',['docker','pull',PHP]),('unprovisioned-package-diagnostic',php+['node-php/bin/onion-service-discovery','help']),('locked-runtime-provisioning',command)]
  commands +=[(name,php+['node-php/tests/'+script])for name,script in [('packaged-protocol','protocol-core.php'),('packaged-client-host','client-host.php'),('packaged-operator-workflow','operator-cli.php')]]
  for name,command in commands:
   result=subprocess.run(command,capture_output=True,timeout=180)
   expected=3 if name=='unprovisioned-package-diagnostic'else 0
   passed=result.returncode==expected
   if name=='unprovisioned-package-diagnostic':passed=passed and result.stderr==b'' and json.loads(result.stdout)=={'status':'FAIL','error':'RUNTIME_DEPENDENCIES_MISSING'}
   results.append({'check':name,'status':'PASS'if passed else 'FAIL','exit_code':result.returncode})
   if not passed:
    if os.environ.get('GITHUB_ACTIONS')=='true':print('::error title=Candidate smoke::'+name+' failed; exit='+str(result.returncode)+'; stderr_present='+str(bool(result.stderr)))
    break
 return {'status':'PASS'if len(results)==6 and all(r['status']=='PASS'for r in results)else 'FAIL','source_commit':commit,'checks':results,'scope':'Explicitly trusted development package; synthetic identities; no live Tor/deployment or production approval','authentication':'NOT_PERFORMED'}
if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('artifact',type=Path);p.add_argument('--expected-source',required=True);p.add_argument('--execute-trusted-local-development-candidate',action='store_true',required=True);p.add_argument('--report',type=Path);a=p.parse_args()
 try:result=run(a.artifact,a.expected_source)
 except Exception:result={'status':'FAIL','error':'CANDIDATE_SMOKE_REJECTED_OR_TIMED_OUT'}
 if a.report:a.report.write_text(json.dumps(result,indent=2)+'\n')
 print(json.dumps(result,sort_keys=True));raise SystemExit(0 if result['status']=='PASS'else 4)
