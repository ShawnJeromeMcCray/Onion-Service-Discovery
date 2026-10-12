"""Offline operator verifier built on python-tuf. Never executes an artifact."""
from __future__ import annotations
import argparse
from datetime import datetime, timezone
import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import tempfile
from urllib.parse import urlsplit, unquote

from tuf.api import exceptions
from tuf.ngclient import Updater, UpdaterConfig
from tuf.ngclient.fetcher import FetcherInterface

class ReleaseVerificationError(ValueError):
    pass

class LocalRepositoryFetcher(FetcherInterface):
    """Read a supplied offline mirror; no DNS/network fallback or arbitrary paths."""
    def __init__(self, repository: Path):
        self.repository=repository.resolve()
    def _fetch(self,url):
        parsed=urlsplit(url)
        if parsed.scheme!='https' or parsed.netloc!='onion-service-discovery-release.invalid' or parsed.query or parsed.fragment:
            raise exceptions.DownloadError('Repository boundary rejected')
        path=PurePosixPath(unquote(parsed.path))
        if '..' in path.parts or not path.is_absolute() or '\\' in str(path):
            raise exceptions.DownloadError('Repository path rejected')
        candidate=self.repository.joinpath(*path.parts[1:])
        if candidate.is_symlink() or not candidate.is_file():
            raise exceptions.DownloadHTTPError('Offline metadata unavailable',404)
        resolved=candidate.resolve()
        if not resolved.is_relative_to(self.repository) or resolved.stat().st_size>134217728:
            raise exceptions.DownloadError('Repository budget rejected')
        def chunks():
            with resolved.open('rb') as stream:
                while chunk:=stream.read(65536):
                    yield chunk
        return chunks()

def atomic_json(path:Path,value):
    descriptor,name=tempfile.mkstemp(prefix='.state-',dir=path.parent)
    try:
        os.fchmod(descriptor,0o600)
        with os.fdopen(descriptor,'w') as stream:
            json.dump(value,stream,sort_keys=True);stream.write('\n');stream.flush();os.fsync(stream.fileno())
        os.replace(name,path)
        directory=os.open(path.parent,os.O_RDONLY)
        try:os.fsync(directory)
        finally:os.close(directory)
    finally:
        if os.path.exists(name):os.unlink(name)

def verify_release(artifact:Path,repository:Path,trusted_root:Path,state_dir:Path,target_path:str):
    if trusted_root.name in {'hs_ed25519_secret_key','private_key'} or trusted_root.is_symlink() or not trusted_root.is_file() or trusted_root.stat().st_size>512000:
        raise ReleaseVerificationError('TRUSTED_ROOT_UNAVAILABLE')
    if artifact.name in {'hs_ed25519_secret_key','private_key'}:raise ReleaseVerificationError('TOR_KEY_ACCESS_FORBIDDEN')
    if artifact.is_symlink() or not artifact.is_file() or artifact.stat().st_size>134217728:
        raise ReleaseVerificationError('ARTIFACT_UNAVAILABLE')
    target=PurePosixPath(target_path)
    if target.is_absolute() or '..' in target.parts or not target.parts or '\\' in target_path or len(target_path)>256:
        raise ReleaseVerificationError('TARGET_PATH_INVALID')
    if state_dir.is_symlink():raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
    state_dir.mkdir(mode=0o700,parents=True,exist_ok=True)
    if state_dir.stat().st_mode & 0o077:raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
    root=trusted_root.read_bytes();anchor_hash=hashlib.sha256(root).hexdigest()
    lock_path=state_dir/'verify.lock'
    if lock_path.is_symlink():raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
    descriptor=os.open(lock_path,os.O_RDWR|os.O_CREAT,0o600)
    with os.fdopen(descriptor,'a+') as lock:
        try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:raise ReleaseVerificationError('VERIFIER_BUSY') from None
        policy_path=state_dir/'policy.json'
        if policy_path.is_symlink():raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
        if policy_path.exists():
            if policy_path.stat().st_size>65536:raise ReleaseVerificationError('VERIFIER_STATE_INVALID')
            policy=json.loads(policy_path.read_text())
            if not isinstance(policy,dict) or set(policy)!={'anchor_sha256','highest','last_verified_at'} or not isinstance(policy['highest'],dict) or len(policy['highest'])>16 or type(policy['last_verified_at'])is not int or policy['last_verified_at']<0:
                raise ReleaseVerificationError('VERIFIER_STATE_INVALID')
            for profile,row in policy['highest'].items():
                if not isinstance(profile,str) or re.fullmatch('[a-z0-9_-]{1,64}',profile)is None or not isinstance(row,dict) or set(row)!={'sequence','sha256'} or type(row['sequence'])is not int or not 1<=row['sequence']<=9007199254740991 or not isinstance(row['sha256'],str) or re.fullmatch('[0-9a-f]{64}',row['sha256'])is None:
                    raise ReleaseVerificationError('VERIFIER_STATE_INVALID')
            if policy.get('anchor_sha256')!=anchor_hash:raise ReleaseVerificationError('BOOTSTRAP_ROOT_CHANGED')
        else:
            if (state_dir/'metadata'/'root.json').exists():raise ReleaseVerificationError('BOOTSTRAP_STATE_MISSING')
            policy={'anchor_sha256':anchor_hash,'highest':{},'last_verified_at':0}
            atomic_json(policy_path,policy)
        now=int(datetime.now(timezone.utc).timestamp())
        if now+300<policy['last_verified_at']:raise ReleaseVerificationError('CLOCK_ROLLBACK')
        metadata_dir=state_dir/'metadata';metadata_dir.mkdir(mode=0o700,exist_ok=True)
        if metadata_dir.is_symlink() or metadata_dir.stat().st_mode&0o077:raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
        cached_count=0;cached_bytes=0
        for cached in metadata_dir.rglob('*'):
            if cached.is_symlink():
                # python-tuf maintains precisely this versioned local root link.
                link=str(cached.readlink())
                if cached!=metadata_dir/'root.json' or re.fullmatch(r'root_history/[1-9][0-9]*\.root\.json',link)is None or not cached.resolve().is_relative_to(metadata_dir.resolve()) or not cached.is_file():
                    raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
            if cached.is_file():cached_count+=1;cached_bytes+=cached.stat().st_size
            elif not cached.is_dir():raise ReleaseVerificationError('STATE_DIRECTORY_UNSAFE')
            if cached_count>512 or cached_bytes>134217728:raise ReleaseVerificationError('VERIFIER_CACHE_BUDGET_EXHAUSTED')
        config=UpdaterConfig(max_root_rotations=32,max_delegations=16,root_max_length=512000,timestamp_max_length=16384,snapshot_max_length=2000000,targets_max_length=5000000)
        updater=Updater(str(metadata_dir),'https://onion-service-discovery-release.invalid/metadata/',fetcher=LocalRepositoryFetcher(repository),config=config,bootstrap=root if not (metadata_dir/'root.json').exists() else None)
        updater.refresh()
        info=updater.get_targetinfo(target_path)
        if info is None:raise ReleaseVerificationError('TARGET_NOT_AUTHORIZED')
        custom=info.custom or {};sequence=custom.get('release_sequence');profile=custom.get('artifact_profile');build_epoch=custom.get('build_epoch')
        if type(sequence)is not int or not 1<=sequence<=9007199254740991 or not isinstance(profile,str) or re.fullmatch('[a-z0-9_-]{1,64}',profile)is None or type(build_epoch)is not int or not 0<=build_epoch<=253402300799:
            raise ReleaseVerificationError('RELEASE_POLICY_METADATA_MISSING')
        if now+300<build_epoch:raise ReleaseVerificationError('CLOCK_BEFORE_RELEASE')
        if info.length>134217728:raise ReleaseVerificationError('ARTIFACT_BUDGET_EXHAUSTED')
        previous=policy['highest'].get(profile)
        expected_hash=info.hashes.get('sha256')
        if not isinstance(expected_hash,str) or re.fullmatch('[0-9a-f]{64}',expected_hash)is None:raise ReleaseVerificationError('ARTIFACT_SHA256_REQUIRED')
        if previous is not None and (sequence<previous['sequence'] or (sequence==previous['sequence'] and expected_hash!=previous['sha256'])):
            raise ReleaseVerificationError('SOFTWARE_ROLLBACK_OR_REPLACEMENT')
        with artifact.open('rb') as stream:info.verify_length_and_hashes(stream)
        if profile not in policy['highest'] and len(policy['highest'])>=16:raise ReleaseVerificationError('RELEASE_PROFILE_BUDGET_EXHAUSTED')
        policy['highest'][profile]={'sequence':sequence,'sha256':expected_hash};policy['last_verified_at']=now;atomic_json(policy_path,policy)
        return {'status':'PASS','target':target_path,'sha256':expected_hash,'bytes':info.length,'release_sequence':sequence,'artifact_profile':profile,'framework':'python-tuf 7.0.1','executed':False}

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('artifact',type=Path);parser.add_argument('--repository',type=Path,required=True);parser.add_argument('--trusted-root',type=Path,required=True);parser.add_argument('--state',type=Path,required=True);parser.add_argument('--target',required=True);args=parser.parse_args()
    try:result=verify_release(args.artifact,args.repository,args.trusted_root,args.state,args.target)
    except ReleaseVerificationError as error:result={'status':'FAIL','error':str(error)}
    except Exception:result={'status':'FAIL','error':'TUF_OR_ARTIFACT_VERIFICATION_FAILED'}
    print(json.dumps(result,sort_keys=True));return 0 if result['status']=='PASS' else 4
if __name__=='__main__':raise SystemExit(main())
