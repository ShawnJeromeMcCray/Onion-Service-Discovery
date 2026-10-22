"""Synthetic candidate structures; no production artifact authentication or execution."""
import hashlib,importlib.util,io,json,tarfile,tempfile,unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location('candidate_checker',Path(__file__).parents[1]/'tools/verify_candidate.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class CandidateStructureTests(unittest.TestCase):
 def setUp(self):self.temp=tempfile.TemporaryDirectory();self.path=Path(self.temp.name)/'candidate.tar.gz'
 def tearDown(self):self.temp.cleanup()
 def archive(self,tamper=False,traversal=False,bad_sbom=False,symlink=False,profile='source-browser-v1',vendor=(),preview=True,extra=()):
  metadata={'source_commit':'0'*40,'production_approved':False,'artifact_profile':profile}
  if profile in {'runtime-v1','static-client-v1'}:metadata['unsigned_development_preview']=preview
  files={} if profile=='static-client-v1' else {'app.txt':b'SYNTHETIC ONLY, never execute'}
  files|={'release/BUILD-METADATA.json':json.dumps(metadata).encode(),'release/SBOM.cdx.json':json.dumps({'bomFormat':'CycloneDX','specVersion':'1.6','version':1,'components':[]}if not bad_sbom else {'bad':'schema'}).encode()}
  for name in vendor:files['node-php/vendor/'+name]=b'<?php // SYNTHETIC ONLY'
  if profile=='static-client-v1':
   for name in ('index.html','client.js','verifier-worker.js','build-manifest.json'):files['web-local-search-client/dist/'+name]=b'SYNTHETIC ONLY'
  for name in extra:files[name]=b'SYNTHETIC ONLY'
  if profile=='runtime-v1':files['release/DEVELOPMENT-PREVIEW.txt']=b'UNSIGNED DEVELOPMENT PREVIEW'
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
 RUNTIME=('autoload.php','composer/installed.json','opis/json-schema/src/Validator.php')
 def test_runtime_profile_carries_runtime_dependencies(self):
  self.archive(profile='runtime-v1',vendor=self.RUNTIME);result=module.verify(self.path)
  self.assertEqual('runtime-v1',result['artifact_profile']);self.assertEqual('NOT_PERFORMED',result['authentication']);self.assertFalse(result['production_approved'])
 def test_runtime_profile_without_dependencies_rejected(self):
  self.archive(profile='runtime-v1')
  with self.assertRaisesRegex(ValueError,'profile rejected'):module.verify(self.path)
 def test_runtime_profile_with_development_packages_rejected(self):
  self.archive(profile='runtime-v1',vendor=self.RUNTIME+('phpstan/phpstan/phpstan.phar',))
  with self.assertRaisesRegex(ValueError,'profile rejected'):module.verify(self.path)
 def test_runtime_profile_must_be_marked_as_preview(self):
  self.archive(profile='runtime-v1',vendor=self.RUNTIME,preview=False)
  with self.assertRaisesRegex(ValueError,'profile rejected'):module.verify(self.path)
 def test_source_profile_with_dependencies_rejected(self):
  self.archive(vendor=self.RUNTIME)
  with self.assertRaisesRegex(ValueError,'profile rejected'):module.verify(self.path)
 def test_static_client_profile_holds_only_the_browser_bundle(self):
  self.archive(profile='static-client-v1');self.assertEqual('static-client-v1',module.verify(self.path)['artifact_profile'])
 def test_static_client_profile_with_server_code_rejected(self):
  self.archive(profile='static-client-v1',extra=('node-php/public/index.php',))
  with self.assertRaisesRegex(ValueError,'profile rejected'):module.verify(self.path)
 def test_static_client_profile_must_be_marked_as_preview(self):
  self.archive(profile='static-client-v1',preview=False)
  with self.assertRaisesRegex(ValueError,'profile rejected'):module.verify(self.path)
if __name__=='__main__':unittest.main(verbosity=2)
