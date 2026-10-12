"""Deterministic TEST ONLY records. Never use these public seeds for real services."""
from pathlib import Path
import base64,copy,hashlib,json,sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'validator-python'))
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from canonical_json import canonical_json
from record_format import encode_bytes,message,payload,content_hash

def key(label):return Ed25519PrivateKey.from_private_bytes(hashlib.sha256(('Onion Service Discovery TEST ONLY '+label).encode()).digest())
def public(k):return encode_bytes(k.public_key().public_bytes_raw(),'ed25519:')
def onion(label):
 k=key('tor-address-'+label).public_key().public_bytes_raw();v=b'\x03';checksum=hashlib.sha3_256(b'.onion checksum'+k+v).digest()[:2]
 return base64.b32encode(k+checksum+v).decode().lower()+'.onion'
def base(kind):return {'protocol':'Onion Service Discovery','protocol_version':1,'record_type':kind}
def signed(rec,k,field='signature',next_key=None):
 rec=copy.deepcopy(rec);rec[field]=encode_bytes(k.sign(message(rec)),'ed25519sig:')
 if next_key:rec['new_key_confirmation']=encode_bytes(next_key.sign(message(rec)),'ed25519sig:')
 return rec
service=key('service-1');nextservice=key('service-2');node=key('node-1');a=onion('a');b=onion('b')
genesis=signed({**base('service_manifest'),'onion':a,'service_identity_public_key':public(service),'revision':1,'previous_manifest_hash':None,'discovery':{'publish_service':True,'mirror_directory':False,'discover_services':False,'sync_with_peers':False},'service':{'name':'Synthetic Library','description':'Fictional books and multilingual documentation.','languages':['de','en'],'categories':['books','documentation']},'links':{'related_onion_services':[],'clearnet':[]},'published_at':'2026-10-12T00:00:00Z'},service)
revision=copy.deepcopy(genesis);revision.update(revision=2,previous_manifest_hash=content_hash(genesis));revision['service']['description']='A revised fictional collection.';revision=signed(revision,service)
rotation=signed({**base('service_identity_key_rotation'),'onion':a,'old_public_key':public(service),'new_public_key':public(nextservice),'effective_revision':2,'previous_state_hash':content_hash(genesis)},service,'old_key_signature',nextservice)
rotated=copy.deepcopy(revision);rotated['service_identity_public_key']=public(nextservice);rotated=signed(rotated,nextservice)
migration=signed({**base('onion_service_migration'),'service_identity_public_key':public(service),'from_onion':a,'to_onion':b,'effective_revision':2,'previous_state_hash':content_hash(genesis)},service,'service_identity_signature')
migrated=copy.deepcopy(revision);migrated['onion']=b;migrated=signed(migrated,service)
node_record=signed({**base('node_descriptor'),'node_identity_public_key':public(node),'node_onion':onion('node'),'descriptor_revision':1,'previous_descriptor_hash':None,'supported_protocol_versions':[1],'capabilities':['index.core.v1','sync.changes.v1','sync.snapshots.v1'],'software':{'name':'Onion Service Discovery','version':'0.1.0-dev'}},node)
observation=signed({**base('direct_observation'),'node_identity_public_key':public(node),'onion':a,'service_identity_public_key':public(service),'revision':1,'manifest_hash':content_hash(genesis),'result':'SUCCESS_CURRENT','observed_at':'2026-10-12T00:00:00Z'},node)
snapshot=signed({**base('snapshot_descriptor'),'node_identity_public_key':public(node),'generation':1,'previous_checkpoint_hash':None,'created_at':'2026-10-12T00:00:00Z','root_hash':'sha256:'+hashlib.sha256(b'[]').hexdigest(),'record_count':0,'cursor':'Yzox','chunks':[]},node)
cases=[]
def add(ident,rec,error=None,expected_onion=None,raw=None):
 body=canonical_json(rec).encode() if raw is None else raw
 entry={'id':ident,'input_base64':base64.b64encode(body).decode()}
 if error:entry['error']=error
 else:entry.update(expected_hash=content_hash(rec),expected_payload_hex=canonical_json(payload(rec)).encode().hex(),expected_message_hex=message(rec).hex(),expected_wire=canonical_json(rec))
 if expected_onion is not None:entry['expected_onion']=expected_onion
 cases.append(entry)
for ident,rec in [('genesis',genesis),('revision-2',revision),('rotation',rotation),('rotated-manifest',rotated),('migration',migration),('migrated-manifest',migrated),('node-descriptor',node_record),('direct-observation',observation),('snapshot-descriptor',snapshot)]:add(ident,rec)
add('correct-host-binding',genesis,expected_onion=a)
add('incorrect-host-binding',genesis,'ONION_BINDING_MISMATCH',b)
mut=copy.deepcopy(genesis);mut['service']['name']='Changed by a peer';add('metadata-mutation',mut,'SIGNATURE_INVALID')
mut=copy.deepcopy(genesis);mut['unexpected']=True;add('unknown-top-level',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(genesis);mut['service']['unexpected']=True;add('unknown-nested',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(genesis);mut['protocol_version']=2;add('unknown-protocol',mut,'PROTOCOL_UNSUPPORTED')
mut=copy.deepcopy(genesis);mut['previous_manifest_hash']='sha256:'+'0'*64;add('genesis-not-null',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(revision);mut['previous_manifest_hash']=None;add('successor-null',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(genesis);mut['service']['languages']=['en','de'];add('unsorted-languages',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(genesis);mut['service']['languages']=['de','de'];add('duplicate-languages',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(genesis);mut['published_at']='2026-02-30T00:00:00Z';add('impossible-date',mut,'SCHEMA_INVALID')
mut=copy.deepcopy(genesis);mut['onion']='a'*56+'.onion';add('wrong-onion-checksum',mut,'ADDRESS_INVALID')
mut=copy.deepcopy(genesis);alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';mut['service_identity_public_key']=mut['service_identity_public_key'][:-1]+alphabet[alphabet.index(mut['service_identity_public_key'][-1])|1];add('noncanonical-public-key',mut,'PUBLIC_KEY_INVALID')
mut=copy.deepcopy(rotation);mut['new_key_confirmation']=encode_bytes(bytes(64),'ed25519sig:');add('bad-rotation-countersignature',mut,'KEY_ROTATION_INVALID')
mut=copy.deepcopy(migration);mut['to_onion']=a;add('same-address-migration',mut,'MIGRATION_INVALID')
mut=copy.deepcopy(genesis);mut['signature']=encode_bytes(service.sign(b'wrong domain'+message(genesis)),'ed25519sig:');add('wrong-signature-domain',mut,'SIGNATURE_INVALID')
mut=copy.deepcopy(genesis);mut['signature']=encode_bytes(node.sign(message(genesis)),'ed25519sig:');add('node-cannot-sign-service',mut,'SIGNATURE_INVALID')
add('raw-wire-limit',genesis,'DOCUMENT_TOO_LARGE',raw=canonical_json(genesis).encode()+b' '*16384)
add('reordered-wire',genesis,raw=json.dumps(dict(reversed(list(genesis.items()))),ensure_ascii=False).encode())
(ROOT/'protocol/test-vectors/signed-records.json').write_text(json.dumps({'format_version':1,'status':'DRAFT_CRYPTO_CORE','warning':'ALL SEEDS ARE PUBLIC TEST ONLY. Not a protocol freeze approval.','test_seed_labels':['service-1','service-2','node-1'],'cases':cases,'stories':{'genesis':genesis,'successor':revision,'rotation':rotation,'rotated':rotated,'migration':migration,'migrated':migrated}},indent=2,ensure_ascii=False)+'\n')
canonical_cases=[
 ('ordering','{"z":0,"a":1}','{"a":1,"z":0}'),
 ('integer-key-order','{"2":2,"10":10,"1":1}','{"1":1,"10":10,"2":2}'),
 ('nested-order','{"z":[{"b":2,"a":1}],"a":{}}','{"a":{},"z":[{"a":1,"b":2}]}'),
 ('line-separators',json.dumps({'s':'\u2028\u2029'},ensure_ascii=False),'{"s":"\u2028\u2029"}'),
 ('unicode-sort',json.dumps({'\ufb33':'Hebrew','😀':'Emoji','€':'Euro','ö':'Latin','\x80':'Control','1':'One','\r':'Return'},ensure_ascii=False),'{"\\r":"Return","1":"One","\u0080":"Control","ö":"Latin","€":"Euro","😀":"Emoji","דּ":"Hebrew"}'),
 ('escapes','{"s":"\\u000f\\b\\t\\n\\f\\r\\\"\\\\\\/"}','{"s":"\\u000f\\b\\t\\n\\f\\r\\\"\\\\/"}'),
 ('no-unicode-normalization','{"s":"e\\u0301"}','{"s":"é"}'),
 ('literals','{"z":null,"a":[true,false,0,9007199254740991]}','{"a":[true,false,0,9007199254740991],"z":null}')]
(ROOT/'protocol/test-vectors/canonical-json.json').write_text(json.dumps({'format_version':1,'status':'INTEGER_SUBSET','cases':[{'id':i,'input':body,'expected_hex':expected.encode().hex()} for i,body,expected in canonical_cases]},indent=2)+'\n')
print(len(cases),'signed-record cases and',len(canonical_cases),'canonical-byte cases generated')

from identity_deriver import derive_identities
from record_verifier import verify_record
third=copy.deepcopy(revision);third.update(revision=3,previous_manifest_hash=content_hash(revision));third['service']['name']='Third revision';third=signed(third,service)
fork=copy.deepcopy(revision);fork['service']['name']='Conflicting second revision';fork=signed(fork,service)
withdrawal=copy.deepcopy(revision);withdrawal['discovery']['publish_service']=False;withdrawal=signed(withdrawal,service)
combined=copy.deepcopy(rotated);combined['onion']=b;combined=signed(combined,nextservice)
otherkey=key('service-3')
rotation2=signed({**base('service_identity_key_rotation'),'onion':a,'old_public_key':public(service),'new_public_key':public(otherkey),'effective_revision':2,'previous_state_hash':content_hash(genesis)},service,'old_key_signature',otherkey)
migration2=signed({**base('onion_service_migration'),'service_identity_public_key':public(service),'from_onion':a,'to_onion':onion('c'),'effective_revision':2,'previous_state_hash':content_hash(genesis)},service,'service_identity_signature')
gap=copy.deepcopy(third);gap['previous_manifest_hash']=content_hash(genesis);gap=signed(gap,service)
othergenesis=copy.deepcopy(genesis);othergenesis['service_identity_public_key']=public(otherkey);othergenesis=signed(othergenesis,otherkey)
sequences=[]
def sequence(ident,records,highest=None,state='VALID',published='PUBLISHED',rotations=0,migrations=0,error=None,max_records=None,identities=1,pending=0,rejected=0):
 verified=[verify_record(record) for record in records]
 entry={'id':ident,'records':records,'requirements':['FORMAL-REV-001','FORMAL-REV-003','FORMAL-KEY-004','FORMAL-MIG-001']}
 if max_records is not None:entry['max_records']=max_records
 if error:entry['error']=error
 else:
  expected=derive_identities(verified)
  assert len(expected['identities'])==identities,ident
  assert len(expected['pending_hashes'])==pending,ident
  assert len(expected['rejected'])==rejected,ident
  if identities==1:
   subject=expected['identities'][0]
   assert subject['highest_revision']==highest and subject['identity_state']==state and subject['discoverability']==published,ident
   assert len(subject['rotation_hashes'])==rotations and len(subject['migration_hashes'])==migrations,ident
  entry['expected']=expected
 sequences.append(entry)
sequence('genesis',[genesis],1)
sequence('valid-successor',[genesis,revision],2)
sequence('stale-replay-and-duplicates',[revision,genesis,genesis],2)
sequence('fork-same-revision',[genesis,revision,fork],2,'FORKED','UNKNOWN')
sequence('later-revision-does-not-clear-fork',[genesis,revision,third,fork],3,'FORKED','UNKNOWN')
sequence('rotation-not-committed-before-new-manifest',[genesis,rotation],1)
sequence('new-key-needs-rotation',[genesis,rotated],1,rejected=1)
sequence('valid-rotation',[genesis,rotation,rotated],2,rotations=1)
sequence('new-onion-needs-migration',[genesis,migrated],1,rejected=1)
sequence('valid-migration',[genesis,migration,migrated],2,migrations=1)
sequence('combined-transition-atomic',[genesis,rotation,migration,combined],2,rotations=1,migrations=1)
sequence('partial-combined-transition-is-not-rotation',[genesis,rotation,combined],1,rejected=1)
sequence('competing-rotations-without-new-manifests',[genesis,rotation,rotation2],1,'FORKED','UNKNOWN')
sequence('competing-migrations-without-new-manifests',[genesis,migration,migration2],1,'FORKED','UNKNOWN')
sequence('withdrawal-retains-history',[withdrawal,genesis,genesis],2,published='WITHDRAWN')
sequence('orphan-is-not-continuity',[revision],identities=0,pending=1)
sequence('reverse-chain-order',[third,revision,genesis],3)
sequence('revision-gap-is-rejected',[genesis,gap],1,rejected=1)
sequence('same-onion-distinct-identities-remain-separate',[genesis,othergenesis],identities=2)
sequence('imported-observation-does-not-change-identity',[genesis,observation],1)
sequence('work-limit-is-finite',[genesis,revision],error='WORK_BUDGET_EXHAUSTED',max_records=1)
(ROOT/'protocol/test-vectors/identity-sequences.json').write_text(json.dumps({'format_version':1,'status':'DRAFT_IDENTITY_GRAPH','cases':sequences},indent=2,ensure_ascii=False)+'\n')
print(len(sequences),'identity sequences generated with independent expected-state assertions')
