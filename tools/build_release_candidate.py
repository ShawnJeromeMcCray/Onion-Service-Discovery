"""Deterministic source + built-browser candidate; no signing keys or auto-publish.

`--profile runtime` (decision D-029) also bundles the PHP runtime dependencies that
`composer install --no-dev --prefer-dist` placed in node-php/vendor from the committed lock,
so an operator needs no Composer: an unsigned development preview, never a release."""
from __future__ import annotations
import argparse,base64,gzip,hashlib,io,json,re,subprocess,tarfile,uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def git(*args):return subprocess.check_output(['git',*args],cwd=ROOT,text=True).strip()
def encoded(value):return (json.dumps(value,sort_keys=True,indent=2,ensure_ascii=False)+'\n').encode()
def sbom(commit,epoch,profile='source-browser'):
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
 unicode=json.loads((ROOT/'protocol/registries/search-unicode-v1.provenance.json').read_text())
 purl='pkg:generic/unicode/ucd@'+unicode['unicode_version']
 components[purl]={'type':'data','name':'Unicode search tables and official normalization fixtures','version':unicode['unicode_version'],'purl':purl,'bom-ref':purl,'scope':'required','licenses':[{'license':{'id':'Unicode-3.0'}}],'hashes':[{'alg':'SHA-256','content':unicode['table_sha256']}],'externalReferences':[{'type':'distribution','url':unicode['source_url'],'hashes':[{'alg':'SHA-256','content':unicode['archive_sha256']}]}],'properties':[{'name':'osd:derived-search-table-sha256','value':unicode['table_sha256']},{'name':'osd:license-notice','value':unicode['license_file']}]}
 confusables=json.loads((ROOT/'protocol/registries/confusables-v1.provenance.json').read_text())
 purl='pkg:generic/unicode/confusables@'+confusables['unicode_version']
 components[purl]={'type':'data','name':'Unicode confusable prototypes (UTS #39) for look-alike name warnings','version':confusables['unicode_version'],'purl':purl,'bom-ref':purl,'scope':'required','licenses':[{'license':{'id':'Unicode-3.0'}}],'hashes':[{'alg':'SHA-256','content':confusables['table_sha256']}],'externalReferences':[{'type':'distribution','url':confusables['source']['source_url'],'hashes':[{'alg':'SHA-256','content':confusables['source']['sha256']}]}],'properties':[{'name':'osd:derived-confusables-table-sha256','value':confusables['table_sha256']},{'name':'osd:license-notice','value':confusables['license_file']}]}
 return {'bomFormat':'CycloneDX','specVersion':'1.6','version':1,'serialNumber':'urn:uuid:'+str(uuid.uuid5(uuid.NAMESPACE_URL,'OSD-SBOM-'+('' if profile=='source-browser' else profile+'-')+commit)),'metadata':{'component':{'type':'application','name':'Onion Service Discovery','version':'development-'+commit[:12]}},'components':[components[key] for key in sorted(components)]}
def vendor_files():
 """Locked PHP runtime dependencies only: a dist install without development packages."""
 vendor=ROOT/'node-php/vendor'
 if vendor.is_symlink() or not (vendor/'autoload.php').is_file():raise ValueError('Runtime profile requires composer install --no-dev in node-php/vendor')
 installed=json.loads((vendor/'composer/installed.json').read_text())
 locked={item['name']:item['version'] for item in json.loads((ROOT/'node-php/composer.lock').read_text())['packages']}
 if installed.get('dev') is not False or installed.get('dev-package-names') or {item['name']:item['version'] for item in installed['packages']}!=locked or any(item.get('installation-source')!='dist' for item in installed['packages']):
  raise ValueError('node-php/vendor must be exactly the locked runtime packages, installed from dist without development packages')
 files={}
 for path in sorted(vendor.rglob('*')):
  if path.is_symlink():raise ValueError('Unsupported vendor entry: '+str(path.relative_to(ROOT)))
  if not path.is_file():continue
  name=str(path.relative_to(ROOT))
  if any(part in {'.git','.svn','.hg'} for part in Path(name).parts) or name.endswith(('.key','.pem','.sqlite')):raise ValueError('Vendor entry rejected: '+name)
  files[name]=path.read_bytes()
 return files
def build(destination,profile='source-browser'):
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
 runtime=profile=='runtime'
 if runtime:
  files.update(vendor_files())
  files['release/DEVELOPMENT-PREVIEW.txt']=('UNSIGNED DEVELOPMENT PREVIEW of commit '+commit+'.\nBuilt by CI for each merge to main (decision D-029). It is not a release and not production-approved.\nVerify its GitHub build provenance before running it; see docs/operations/release-candidate.md.\nPHP runtime dependencies from node-php/composer.lock are included; Python validators are not.\n').encode()
 files['release/SBOM.cdx.json']=encoded(sbom(commit,epoch,profile))
 files['release/BUILD-METADATA.json']=encoded({'artifact_profile':'runtime-v1' if runtime else 'source-browser-v1','source_commit':commit,'source_date_epoch':epoch,
  'dependencies':'PHP runtime dependencies from node-php/composer.lock included without development packages; Python provisioning is separate' if runtime else 'locked; PHP/Python runtime provisioning is separate',
  'unsigned_development_preview':True,'production_approved':False,'generator':'tools/build_release_candidate.py'} if runtime else {'artifact_profile':'source-browser-v1','source_commit':commit,'source_date_epoch':epoch,'dependencies':'locked; PHP/Python runtime provisioning is separate','production_approved':False,'generator':'tools/build_release_candidate.py'})
 files['release/FILE-MANIFEST.json']=encoded({name:hashlib.sha256(body).hexdigest() for name,body in sorted(files.items())})
 destination.mkdir(parents=True,exist_ok=True);artifact=destination/('onion-service-discovery-development-runtime.tar.gz' if runtime else 'onion-service-discovery-source-browser.tar.gz')
 with artifact.open('wb') as raw,gzip.GzipFile(filename='',mode='wb',fileobj=raw,mtime=0,compresslevel=9) as compressed,tarfile.open(fileobj=compressed,mode='w',format=tarfile.USTAR_FORMAT) as archive:
  for name,body in sorted(files.items()):
   entry=tarfile.TarInfo('onion-service-discovery/'+name);entry.size=len(body);entry.mtime=epoch;entry.mode=0o755 if name=='node-php/bin/onion-service-discovery' else 0o644;entry.uid=entry.gid=0;entry.uname=entry.gname='';archive.addfile(entry,io.BytesIO(body))
 (destination/'SBOM.cdx.json').write_bytes(files['release/SBOM.cdx.json'])
 digest=hashlib.sha256(artifact.read_bytes()).hexdigest();(destination/'SHA256SUMS').write_text(digest+'  '+artifact.name+'\n')
 (destination/'candidate-provenance.json').write_bytes(encoded({'_type':'https://in-toto.io/Statement/v1','subject':[{'name':artifact.name,'digest':{'sha256':digest}}],'predicateType':'https://slsa.dev/provenance/v1','predicate':{'buildDefinition':{'buildType':'https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/'+('development-runtime' if runtime else 'source-browser-candidate')+'/v1','externalParameters':{'commit':commit},'resolvedDependencies':[{'uri':'git+https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery','digest':{'gitCommit':commit}}]},'runDetails':{'builder':{'id':'urn:onion-service-discovery:local-unattested-development-builder'}}}}))
 return {'artifact':str(artifact),'artifact_profile':'runtime-v1' if runtime else 'source-browser-v1','sha256':digest,'source_commit':commit,'production_approved':False}
if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--output',type=Path,required=True);p.add_argument('--profile',choices=['source-browser','runtime'],default='source-browser');a=p.parse_args();print(json.dumps(build(a.output,a.profile),sort_keys=True))
