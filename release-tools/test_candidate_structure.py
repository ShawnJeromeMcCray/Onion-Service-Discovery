"""Synthetic candidate structures; no production artifact authentication or execution."""
import hashlib,importlib.util,io,json,tarfile,tempfile,unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location('candidate_checker',Path(__file__).parents[1]/'tools/verify_candidate.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class CandidateStructureTests(unittest.TestCase):
 def setUp(self):self.temp=tempfile.TemporaryDirectory();self.path=Path(self.temp.name)/'candidate.tar.gz'
 def tearDown(self):self.temp.cleanup()
 def archive(self,tamper=False,traversal=False,bad_sbom=False,symlink=False):
  files={'app.txt':b'SYNTHETIC ONLY, never execute','release/BUILD-METADATA.json':json.dumps({'source_commit':'0'*40,'production_approved':False,'artifact_profile':'source-browser-v1'}).encode(),'release/SBOM.cdx.json':json.dumps({'bomFormat':'CycloneDX','specVersion':'1.6','version':1,'components':[]}if not bad_sbom else {'bad':'schema'}).encode()}
  files['release/FILE-MANIFEST.json']=json.dumps({name:hashlib.sha256(body).hexdigest()for name,body in files.items()}).encode()
  if tamper:files['app.txt']=b'Replaced after manifest construction'
  with tarfile.open(self.path,'w:gz')as archive:
   for name,body in files.items():
    info=tarfile.TarInfo('onion-service-discovery/'+name);info.size=len(body);archive.addfile(info,io.BytesIO(body))
   if traversal:
    info=tarfile.TarInfo('onion-service-discovery/../../escape');info.size=1;archive.addfile(info,io.BytesIO(b'x'))
   if symlink:
    info=tarfile.TarInfo('onion-service-discovery/link');info.type=tarfile.SYMTYPE;info.linkname='/etc/passwd';archive.addfile(info)
 def test_valid_structure_is_not_authentication(self):
  self.archive();result=module.verify(self.path);self.assertEqual('NOT_PERFORMED',result['authentication']);self.assertFalse(result['executed']);self.assertFalse(result['production_approved'])
 def test_file_substitution_rejected(self):
  self.archive(tamper=True)
  with self.assertRaisesRegex(ValueError,'manifest mismatch'):module.verify(self.path)
 def test_traversal_rejected_without_extraction(self):
  self.archive(traversal=True)
  with self.assertRaisesRegex(ValueError,'entry rejected'):module.verify(self.path)
 def test_symbolic_link_rejected(self):
  self.archive(symlink=True)
  with self.assertRaisesRegex(ValueError,'entry rejected'):module.verify(self.path)
 def test_invalid_sbom_rejected(self):
  self.archive(bad_sbom=True)
  with self.assertRaisesRegex(ValueError,'SBOM schema rejected'):module.verify(self.path)
if __name__=='__main__':unittest.main(verbosity=2)
