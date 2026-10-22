"""Validate development artifact structure, file hashes and SBOM; no execution/authentication."""
import argparse,hashlib,json,re,tarfile
from pathlib import Path,PurePosixPath
from cyclonedx.schema import SchemaVersion
from cyclonedx.validation.json import JsonStrictValidator

def verify(path):
 if path.name in {'hs_ed25519_secret_key','private_key'} or path.is_symlink() or not path.is_file() or path.stat().st_size>134217728:raise ValueError('Artifact boundary rejected')
 hashes={};documents={};total=0;count=0
 with tarfile.open(path,mode='r|gz') as archive:
  for entry in archive:
   name=PurePosixPath(entry.name)
   if str(name) != entry.name or not entry.isfile() or name.is_absolute() or '..' in name.parts or '\\' in entry.name or not entry.name.startswith('onion-service-discovery/') or entry.name in hashes or entry.size>33554432 or entry.size<0:raise ValueError('Archive entry rejected')
   relative=entry.name[len('onion-service-discovery/'):]
   if name.name in {'hs_ed25519_secret_key','private_key'} or relative.endswith(('.key','.pem','.sqlite')):raise ValueError('Private file rejected')
   count+=1;total+=entry.size
   if count>4096 or total>134217728:raise ValueError('Archive budget exhausted')
   stream=archive.extractfile(entry);digest=hashlib.sha256();body=[];received=0
   if stream is None:raise ValueError('Archive entry unavailable')
   retain=relative in {'release/FILE-MANIFEST.json','release/BUILD-METADATA.json','release/SBOM.cdx.json'}
   while chunk:=stream.read(65536):
    received+=len(chunk);digest.update(chunk)
    if received>entry.size:raise ValueError('Entry size mismatch')
    if retain:
     if received>2097152:raise ValueError('Metadata budget exhausted')
     body.append(chunk)
   if received!=entry.size:raise ValueError('Entry truncated')
   hashes[entry.name]=digest.hexdigest()
   if retain:documents[relative]=b''.join(body)
 if set(documents)!={'release/FILE-MANIFEST.json','release/BUILD-METADATA.json','release/SBOM.cdx.json'}:raise ValueError('Release metadata absent')
 vendor={name for name in hashes if name.startswith('onion-service-discovery/node-php/vendor/')}
 manifest=json.loads(documents['release/FILE-MANIFEST.json']);expected={'onion-service-discovery/'+name:digest for name,digest in manifest.items()}
 actual={name:digest for name,digest in hashes.items()if name!='onion-service-discovery/release/FILE-MANIFEST.json'}
 if expected!=actual:raise ValueError('File manifest mismatch')
 metadata=json.loads(documents['release/BUILD-METADATA.json'])
 profile=metadata.get('artifact_profile')
 if metadata.get('production_approved')is not False or profile not in {'source-browser-v1','runtime-v1','static-client-v1'} or re.fullmatch('[0-9a-f]{40}',metadata.get('source_commit',''))is None:raise ValueError('Development profile rejected')
 # Only the runtime profile (D-029) carries PHP dependencies, and never development packages.
 if profile=='source-browser-v1' and vendor:raise ValueError('Development profile rejected')
 if profile=='runtime-v1' and (metadata.get('unsigned_development_preview')is not True or not {'onion-service-discovery/node-php/vendor/autoload.php','onion-service-discovery/node-php/vendor/composer/installed.json','onion-service-discovery/release/DEVELOPMENT-PREVIEW.txt'}<=set(hashes)
  or any(name.startswith(('onion-service-discovery/node-php/vendor/phpstan/','onion-service-discovery/node-php/vendor/bin/'))for name in vendor)):raise ValueError('Development profile rejected')
 # The static client bundle (D-028) is the built browser client and its manifest, nothing executable on a server.
 if profile=='static-client-v1' and (metadata.get('unsigned_development_preview')is not True or not {'onion-service-discovery/web-local-search-client/dist/'+name for name in ('index.html','client.js','verifier-worker.js','build-manifest.json')}<=set(hashes)
  or any(not name.startswith(('onion-service-discovery/web-local-search-client/dist/','onion-service-discovery/release/'))and name not in {'onion-service-discovery/LICENSE','onion-service-discovery/THIRD_PARTY_NOTICES.md','onion-service-discovery/docs/browser/client-contract.md'} for name in hashes)):raise ValueError('Development profile rejected')
 error=JsonStrictValidator(SchemaVersion.V1_6).validate_str(documents['release/SBOM.cdx.json'].decode())
 if error:raise ValueError('SBOM schema rejected')
 return {'status':'PASS','artifact_profile':profile,'files':count,'uncompressed_bytes':total,'source_commit':metadata['source_commit'],'authentication':'NOT_PERFORMED','executed':False,'production_approved':False}
if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('artifact',type=Path);a=p.parse_args()
 try:result=verify(a.artifact)
 except Exception:result={'status':'FAIL','error':'CANDIDATE_STRUCTURE_OR_HASH_REJECTED'}
 print(json.dumps(result,sort_keys=True));raise SystemExit(0 if result['status']=='PASS'else 4)
