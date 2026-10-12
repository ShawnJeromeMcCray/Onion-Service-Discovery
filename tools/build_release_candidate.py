"""Deterministic source + built-browser candidate; no signing keys or auto-publish."""
from __future__ import annotations
import argparse,base64,gzip,hashlib,io,json,re,subprocess,tarfile,uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def git(*args):return subprocess.check_output(['git',*args],cwd=ROOT,text=True).strip()
def encoded(value):return (json.dumps(value,sort_keys=True,indent=2,ensure_ascii=False)+'\n').encode()
def sbom(commit,epoch):
 components={}
 npm=json.loads((ROOT/'web-local-search-client/package-lock.json').read_text())
 for location,item in npm['packages'].items():
  if not location:continue
  name=item.get('name',location.rsplit('node_modules/',1)[-1]);version=item['version'];purl=f'pkg:npm/{name.replace("@","%40")}@{version}'
  row={'type':'library','name':name,'version':version,'purl':purl,'bom-ref':purl,'scope':'optional' if item.get('dev') else 'required'}
  integrity=item.get('integrity','')
  if integrity.startswith(('sha512-','sha256-')):
   algorithm,data=integrity.split('-',1);row['hashes']=[{'alg':'SHA-512' if algorithm=='sha512' else 'SHA-256','content':base64.b64decode(data).hex()}]
  license=item.get('license')
  if isinstance(license,str) and re.fullmatch('[A-Za-z0-9.+-]+',license):row['licenses']=[{'license':{'id':license}}]
  components[purl]=row
 composer=json.loads((ROOT/'node-php/composer.lock').read_text())
 for group in ('packages','packages-dev'):
  for item in composer[group]:
   purl=f"pkg:composer/{item['name']}@{item['version'].lstrip('v')}";row={'type':'library','name':item['name'],'version':item['version'].lstrip('v'),'purl':purl,'bom-ref':purl,'scope':'required' if group=='packages' else 'optional'}
   if item.get('license'):row['licenses']=[{'license':{'id':name}} for name in item['license']]
   components[purl]=row
 for lock in ('validator-python/requirements.lock','release-tools/requirements.lock','tools/audit-requirements.lock'):
  source=(ROOT/lock).read_text().replace('\\\n',' ')
  for line in source.splitlines():
   match=re.match(r'^([a-zA-Z0-9_.-]+)(?:\[[^]]+\])?==([^\s]+)',line)
   if not match:continue
   name,version=match.groups();purl=f'pkg:pypi/{name.lower().replace("_","-")}@{version}'
   row={'type':'library','name':name,'version':version,'purl':purl,'bom-ref':purl,'scope':'optional' if lock.startswith('tools/') else 'required','hashes':[{'alg':'SHA-256','content':value} for value in sorted(set(re.findall(r'--hash=sha256:([0-9a-f]{64})',line)))]}
   components[purl]=row
 return {'bomFormat':'CycloneDX','specVersion':'1.6','version':1,'serialNumber':'urn:uuid:'+str(uuid.uuid5(uuid.NAMESPACE_URL,'OSD-SBOM-'+commit)),'metadata':{'component':{'type':'application','name':'Onion Service Discovery','version':'development-'+commit[:12]}},'components':[components[key] for key in sorted(components)]}
def build(destination):
 commit=git('rev-parse','HEAD');epoch=int(git('show','-s','--format=%ct','HEAD'))
 if git('status','--porcelain'):raise ValueError('Build requires a clean committed checkout')
 subprocess.run(['npm','run','build'],cwd=ROOT/'web-local-search-client',check=True,stdout=subprocess.DEVNULL)
 files={}
 for name in git('ls-files').splitlines():
  path=ROOT/name
  if path.is_symlink() or not path.is_file():raise ValueError('Unsupported source entry: '+name)
  if any(part in {'.git','vendor','node_modules','secrets','runtime','.venv'} for part in Path(name).parts) or name.endswith(('.key','.pem','.sqlite')):raise ValueError('Private source entry rejected')
  files[name]=path.read_bytes()
 for path in sorted((ROOT/'web-local-search-client/dist').rglob('*')):
  if path.is_file() and not path.is_symlink():files[str(path.relative_to(ROOT))]=path.read_bytes()
 files['release/SBOM.cdx.json']=encoded(sbom(commit,epoch))
 files['release/BUILD-METADATA.json']=encoded({'artifact_profile':'source-browser-v1','source_commit':commit,'source_date_epoch':epoch,'dependencies':'locked; PHP/Python runtime provisioning is separate','production_approved':False,'generator':'tools/build_release_candidate.py'})
 files['release/FILE-MANIFEST.json']=encoded({name:hashlib.sha256(body).hexdigest() for name,body in sorted(files.items())})
 destination.mkdir(parents=True,exist_ok=True);artifact=destination/'onion-service-discovery-source-browser.tar.gz'
 with artifact.open('wb') as raw,gzip.GzipFile(filename='',mode='wb',fileobj=raw,mtime=0,compresslevel=9) as compressed,tarfile.open(fileobj=compressed,mode='w',format=tarfile.USTAR_FORMAT) as archive:
  for name,body in sorted(files.items()):
   entry=tarfile.TarInfo('onion-service-discovery/'+name);entry.size=len(body);entry.mtime=epoch;entry.mode=0o755 if name=='node-php/bin/onion-service-discovery' else 0o644;entry.uid=entry.gid=0;entry.uname=entry.gname='';archive.addfile(entry,io.BytesIO(body))
 digest=hashlib.sha256(artifact.read_bytes()).hexdigest();(destination/'SHA256SUMS').write_text(digest+'  '+artifact.name+'\n')
 (destination/'candidate-provenance.json').write_bytes(encoded({'_type':'https://in-toto.io/Statement/v1','subject':[{'name':artifact.name,'digest':{'sha256':digest}}],'predicateType':'https://slsa.dev/provenance/v1','predicate':{'buildDefinition':{'buildType':'https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/source-browser-candidate/v1','externalParameters':{'commit':commit},'resolvedDependencies':[{'uri':'git+https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery','digest':{'gitCommit':commit}}]},'runDetails':{'builder':{'id':'urn:onion-service-discovery:local-unattested-development-builder'}}}}))
 return {'artifact':str(artifact),'sha256':digest,'source_commit':commit,'production_approved':False}
if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--output',type=Path,required=True);a=p.parse_args();print(json.dumps(build(a.output),sort_keys=True))
