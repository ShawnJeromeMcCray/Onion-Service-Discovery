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
 manifest=json.loads(documents['release/FILE-MANIFEST.json']);expected={'onion-service-discovery/'+name:digest for name,digest in manifest.items()}
 actual={name:digest for name,digest in hashes.items()if name!='onion-service-discovery/release/FILE-MANIFEST.json'}
 if expected!=actual:raise ValueError('File manifest mismatch')
 metadata=json.loads(documents['release/BUILD-METADATA.json'])
 if metadata.get('production_approved')is not False or metadata.get('artifact_profile')!='source-browser-v1' or re.fullmatch('[0-9a-f]{40}',metadata.get('source_commit',''))is None:raise ValueError('Development profile rejected')
 error=JsonStrictValidator(SchemaVersion.V1_6).validate_str(documents['release/SBOM.cdx.json'].decode())
 if error:raise ValueError('SBOM schema rejected')
 return {'status':'PASS','files':count,'uncompressed_bytes':total,'source_commit':metadata['source_commit'],'authentication':'NOT_PERFORMED','executed':False,'production_approved':False}
if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('artifact',type=Path);a=p.parse_args()
 try:result=verify(a.artifact)
 except Exception:result={'status':'FAIL','error':'CANDIDATE_STRUCTURE_OR_HASH_REJECTED'}
 print(json.dumps(result,sort_keys=True));raise SystemExit(0 if result['status']=='PASS'else 4)
