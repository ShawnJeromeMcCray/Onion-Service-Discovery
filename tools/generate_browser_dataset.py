"""Deterministic public TEST ONLY browser dataset and three controlled source fixtures."""
import base64
import copy
import hashlib
import json
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'validator-python'))
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from canonical_json import canonical_json
from record_format import encode_bytes,message,content_hash
from snapshot_format import root_hash,chunk_hash

def key(label):return Ed25519PrivateKey.from_private_bytes(hashlib.sha256(('Onion Service Discovery TEST ONLY '+label).encode()).digest())
def public(k):return encode_bytes(k.public_key().public_bytes_raw(),'ed25519:')
def onion(label):
    value=key('tor-address-'+label).public_key().public_bytes_raw();version=b'\x03';checksum=hashlib.sha3_256(b'.onion checksum'+value+version).digest()[:2]
    return base64.b32encode(value+checksum+version).decode().lower()+'.onion'
def signed(record,k,field='signature'):
    record=copy.deepcopy(record);record[field]=encode_bytes(k.sign(message(record)),'ed25519sig:');return record
f=json.loads((ROOT/'protocol/test-vectors/signed-records.json').read_text())
records=[f['stories']['genesis'],f['stories']['successor']]
for label,name,description,categories,languages in [
    ('climate','Synthetic Climate Archive','Fictional climate research and public data.',['research'],['en']),
    ('html','<img src=x onerror=alert(1)>','<script>alert("fixture")</script> — text only.',['testing'],['en']),
    ('withdrawn','Synthetic Withdrawn Service','Fictional service withdrawal example.',['testing'],['de']),
]:
    record=copy.deepcopy(f['stories']['genesis']);record.update(onion=onion(label),service_identity_public_key=public(key('browser-service-'+label)),revision=1,previous_manifest_hash=None)
    record['service'].update(name=name,description=description,categories=categories,languages=languages)
    record=signed(record,key('browser-service-'+label));records.append(record)
    if label=='withdrawn':
        successor=copy.deepcopy(record);successor.update(revision=2,previous_manifest_hash=content_hash(record));successor['discovery']['publish_service']=False;records.append(signed(successor,key('browser-service-'+label)))
records=sorted(records,key=content_hash)
bundle={'bundle_version':1,'records':records}
(ROOT/'web-local-search-client/public/demo.json').write_text(json.dumps(bundle,ensure_ascii=False,indent=2)+'\n')
sources=[]
for index in range(1,4):
    node=key('browser-source-'+str(index));address=onion('browser-source-'+str(index))
    descriptor=signed({'protocol':'Onion Service Discovery','protocol_version':1,'record_type':'node_descriptor','node_identity_public_key':public(node),'node_onion':address,'descriptor_revision':1,'previous_descriptor_hash':None,'supported_protocol_versions':[1],'capabilities':['index.core.v1'],'software':{'name':'Onion Service Discovery','version':'0.1.0-test'}},node)
    source_records=sorted(records+[descriptor],key=content_hash);wire=canonical_json(source_records).encode();chunks=[{'hash':chunk_hash(wire),'bytes':len(wire),'record_count':len(source_records)}]
    checkpoint=signed({'protocol':'Onion Service Discovery','protocol_version':1,'record_type':'snapshot_descriptor','node_identity_public_key':public(node),'generation':1,'previous_checkpoint_hash':None,'created_at':'2026-10-12T00:00:00Z','root_hash':root_hash(chunks),'record_count':len(source_records),'cursor':'fixture1','chunks':chunks},node)
    state={'protocol':'Onion Service Discovery','protocol_version':1,'node_identity_public_key':public(node),'checkpoint_hash':content_hash(checkpoint),'generation':1,'cursor':'fixture1'}
    sources.append({'onion':address,'node_descriptor':descriptor,'checkpoint':checkpoint,'state':state,'chunk_base64':base64.b64encode(wire).decode()})
(ROOT/'protocol/test-vectors/browser-dataset.json').write_text(json.dumps({'fixture_version':1,'warning':'SYNTHETIC TEST ONLY. All private seeds are publicly derivable. Not production seeds or live services.','sources':sources,'bundle':bundle},ensure_ascii=False,indent=2)+'\n')
print('Generated',len(records),'synthetic public records and',len(sources),'controlled source fixtures')
