"""Synthetic TUF roles only: no production trust root or release keys."""
from datetime import datetime,timedelta,timezone
import importlib.util
import json
from pathlib import Path
import shutil
import tempfile
import unittest
from securesystemslib.signer import CryptoSigner
from tuf.api import exceptions
from tuf.api.metadata import Metadata,Root,Targets,Snapshot,Timestamp,TargetFile,MetaFile

spec=importlib.util.spec_from_file_location('release_verifier',Path(__file__).with_name('verify_release.py'))
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class ReleaseVerifierTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.root=Path(self.temp.name);self.repo=self.root/'repository';(self.repo/'metadata').mkdir(parents=True);(self.repo/'targets').mkdir();self.state=self.root/'state';self.artifact=self.root/'artifact.tar.gz';self.artifact.write_bytes(b'SYNTHETIC TEST ONLY. Never execute this verification fixture.\n');self.target='onion-service-discovery-source.tar.gz';self.now=datetime.now(timezone.utc)
        self.signers={role:[CryptoSigner.generate_ed25519() for _ in range(3 if role in ('root','targets') else 1)] for role in ['root','targets','snapshot','timestamp']}
        root=Root(expires=self.now+timedelta(days=365))
        for role,signers in self.signers.items():
            for signer in signers:root.add_key(signer.public_key,role)
            root.roles[role].threshold=2 if role in ('root','targets') else 1
        self.root_metadata=Metadata(root);self.sign(self.root_metadata,'root');self.trusted=self.root/'trusted-root.json';self.trusted.write_bytes(self.root_metadata.to_bytes());self.write_repo(1,1)
    def tearDown(self):self.temp.cleanup()
    def sign(self,metadata,role,one=False):
        for index,signer in enumerate(self.signers[role][:1 if one else metadata.signed.roles[role].threshold if isinstance(metadata.signed,Root) else 2 if role=='targets' else 1]):metadata.sign(signer,append=index>0)
    def write_repo(self,version,sequence,expired=False,one_target_signature=False):
        info=TargetFile.from_file(self.target,str(self.artifact),['sha256']);info.unrecognized_fields['custom']={'release_sequence':sequence,'artifact_profile':'source-v1','build_epoch':int(self.now.timestamp())}
        targets=Metadata(Targets(version=version,expires=self.now+timedelta(days=7),targets={self.target:info}));self.sign(targets,'targets',one_target_signature);target_bytes=targets.to_bytes();(self.repo/'metadata'/f'{version}.targets.json').write_bytes(target_bytes)
        snapshot=Metadata(Snapshot(version=version,expires=self.now+timedelta(days=2),meta={'targets.json':MetaFile.from_data(version,target_bytes,['sha256'])}));self.sign(snapshot,'snapshot');snapshot_bytes=snapshot.to_bytes();(self.repo/'metadata'/f'{version}.snapshot.json').write_bytes(snapshot_bytes)
        timestamp=Metadata(Timestamp(version=version,expires=self.now+timedelta(hours=-1 if expired else 6),snapshot_meta=MetaFile.from_data(version,snapshot_bytes,['sha256'])));self.sign(timestamp,'timestamp');(self.repo/'metadata'/'timestamp.json').write_bytes(timestamp.to_bytes())
    def verify(self):return module.verify_release(self.artifact,self.repo,self.trusted,self.state,self.target)
    def test_valid_artifact_and_no_execution(self):
        result=self.verify();self.assertEqual('PASS',result['status']);self.assertFalse(result['executed']);self.assertEqual(1,result['release_sequence'])
    def test_artifact_replacement_rejected(self):
        self.artifact.write_bytes(b'Attacker artifact replacement')
        with self.assertRaises(exceptions.LengthOrHashMismatchError):self.verify()
    def test_insufficient_targets_threshold_rejected(self):
        self.write_repo(2,2,one_target_signature=True)
        with self.assertRaises(exceptions.UnsignedMetadataError):self.verify()
    def test_expired_freshness_metadata_rejected(self):
        self.write_repo(2,2,expired=True)
        with self.assertRaises(exceptions.ExpiredMetadataError):self.verify()
    def test_tuf_metadata_rollback_rejected(self):
        old=(self.repo/'metadata'/'timestamp.json').read_bytes();self.write_repo(2,2);self.verify();(self.repo/'metadata'/'timestamp.json').write_bytes(old)
        with self.assertRaises(exceptions.BadVersionNumberError):self.verify()
    def test_software_sequence_rollback_rejected_even_with_new_valid_metadata(self):
        self.write_repo(2,2);self.verify();self.write_repo(3,1)
        with self.assertRaisesRegex(module.ReleaseVerificationError,'SOFTWARE_ROLLBACK_OR_REPLACEMENT'):self.verify()
    def test_same_release_sequence_cannot_replace_bytes(self):
        self.verify();self.artifact.write_bytes(b'Different bytes under unchanged release sequence');self.write_repo(2,1)
        with self.assertRaisesRegex(module.ReleaseVerificationError,'SOFTWARE_ROLLBACK_OR_REPLACEMENT'):self.verify()
    def test_bootstrap_anchor_cannot_silently_change(self):
        self.verify();self.trusted.write_bytes(self.trusted.read_bytes()+b' ')
        with self.assertRaisesRegex(module.ReleaseVerificationError,'BOOTSTRAP_ROOT_CHANGED'):self.verify()
    def test_untrusted_cache_symlink_rejected(self):
        self.verify();link=self.state/'metadata'/'targets.json';link.unlink();link.symlink_to(self.trusted)
        with self.assertRaisesRegex(module.ReleaseVerificationError,'STATE_DIRECTORY_UNSAFE'):self.verify()
    def test_local_clock_rollback_rejected(self):
        self.verify();path=self.state/'policy.json';policy=json.loads(path.read_text());policy['last_verified_at']=int(self.now.timestamp())+3600;path.write_text(json.dumps(policy))
        with self.assertRaisesRegex(module.ReleaseVerificationError,'CLOCK_ROLLBACK'):self.verify()
    def test_root_rotation_with_insufficient_old_threshold_rejected(self):
        self.verify();root=Root.from_dict(self.root_metadata.signed.to_dict());root.version=2;rotated=Metadata(root);rotated.sign(self.signers['root'][0]);(self.repo/'metadata'/'2.root.json').write_bytes(rotated.to_bytes())
        with self.assertRaises(exceptions.UnsignedMetadataError):self.verify()
    def test_tor_private_filename_is_never_an_artifact(self):
        self.artifact=self.root/'hs_ed25519_secret_key';self.artifact.write_bytes(b'SYNTHETIC filename-boundary fixture, not a Tor key')
        with self.assertRaisesRegex(module.ReleaseVerificationError,'TOR_KEY_ACCESS_FORBIDDEN'):self.verify()
        self.assertFalse(self.state.exists())
    def test_target_path_traversal_rejected(self):
        self.target='../artifact.tar.gz'
        with self.assertRaisesRegex(module.ReleaseVerificationError,'TARGET_PATH_INVALID'):self.verify()
    def test_offline_root_rotation_requires_old_and_new_thresholds(self):
        self.verify();new_signers=[CryptoSigner.generate_ed25519() for _ in range(3)];root=Root.from_dict(self.root_metadata.signed.to_dict());root.version=2
        for keyid in list(root.roles['root'].keyids):root.revoke_key(keyid,'root')
        for signer in new_signers:root.add_key(signer.public_key,'root')
        rotated=Metadata(root)
        for index,signer in enumerate(self.signers['root'][:2]+new_signers[:2]):rotated.sign(signer,append=index>0)
        (self.repo/'metadata'/'2.root.json').write_bytes(rotated.to_bytes());self.write_repo(2,2);self.assertEqual('PASS',self.verify()['status'])

if __name__=='__main__':unittest.main(verbosity=2)
