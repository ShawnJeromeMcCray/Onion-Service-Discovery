"""Synthetic typical-profile workload; deterministic public TEST seeds only."""
import argparse,base64,copy,hashlib,json,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT/'validator-python'))
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from canonical_json import canonical_json
from record_format import message,content_hash,encode_bytes

def key(label):return Ed25519PrivateKey.from_private_bytes(hashlib.sha256(('OSD BENCHMARK PUBLIC TEST ONLY '+label).encode()).digest())
def public(k):return encode_bytes(k.public_key().public_bytes_raw(),'ed25519:')
lookup_prefix=None
def onion(label):
 if lookup_prefix is not None and label.startswith('service-'):
  for attempt in range(65536):
   k=key('tor-'+label+'-attempt-'+str(attempt)).public_key().public_bytes_raw();v=b'\x03'
   address=base64.b32encode(k+hashlib.sha3_256(b'.onion checksum'+k+v).digest()[:2]+v).decode().lower()+'.onion'
   if hashlib.sha256(address.encode()).hexdigest().startswith(lookup_prefix):return address
  raise ValueError('Bounded synthetic address-grind budget exhausted')
 k=key('tor-'+label).public_key().public_bytes_raw();v=b'\x03'
 return base64.b32encode(k+hashlib.sha3_256(b'.onion checksum'+k+v).digest()[:2]+v).decode().lower()+'.onion'
def base(kind):return {'protocol':'Onion Service Discovery','protocol_version':1,'record_type':kind}
def sign(record,k):return {**record,'signature':encode_bytes(k.sign(message(record)),'ed25519sig:')}
def generate(services,nodes):
 records=[];nodekeys=[key('node-'+str(i)) for i in range(nodes)]
 for i,k in enumerate(nodekeys):records.append(sign({**base('node_descriptor'),'node_identity_public_key':public(k),'node_onion':onion('node-'+str(i)),'descriptor_revision':1,'previous_descriptor_hash':None,'supported_protocol_versions':[1],'capabilities':['index.core.v1','sync.snapshots.v1'],'software':{'name':'Onion Service Discovery','version':'0.1.0-dev'}},k))
 for i in range(services):
  k=key('service-'+str(i));r=sign({**base('service_manifest'),'onion':onion('service-'+str(i)),'service_identity_public_key':public(k),'revision':1,'previous_manifest_hash':None,'discovery':{'publish_service':True,'mirror_directory':False,'discover_services':False,'sync_with_peers':False},'service':{'name':f'Synthetic Library {i}','description':('Public synthetic library with multilingual books and documentation. '*5).strip(),'languages':['de','en'],'categories':['books','documentation','education']},'links':{'related_onion_services':[],'clearnet':[]},'published_at':'2026-10-12T00:00:00Z'},k);records.append(r)
  successor=copy.deepcopy(r);successor.update(revision=2,previous_manifest_hash=content_hash(r));successor['service']['description']+=' Revised catalog.';successor=sign(successor,k);records.append(successor)
  for n in nodekeys:records.append(sign({**base('direct_observation'),'node_identity_public_key':public(n),'onion':successor['onion'],'service_identity_public_key':public(k),'revision':2,'manifest_hash':content_hash(successor),'result':'SUCCESS_CURRENT','observed_at':'2026-10-12T00:00:00Z'},n))
 return records
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('output',type=Path);p.add_argument('--services',type=int,default=1000);p.add_argument('--nodes',type=int,default=5);p.add_argument('--lookup-prefix');a=p.parse_args()
 if not 1<=a.services<=1000000 or not 1<=a.nodes<=100:raise SystemExit('Workload bounds invalid')
 if a.lookup_prefix is not None:
  import re
  if re.fullmatch('[0-9a-f]{2}',a.lookup_prefix)is None:raise SystemExit('Exactly two hexadecimal prefix characters required')
  lookup_prefix=a.lookup_prefix
 a.output.write_text('['+','.join(canonical_json(record) for record in generate(a.services,a.nodes))+']'+'\n');print(json.dumps({'services':a.services,'nodes':a.nodes,'bytes':a.output.stat().st_size}))
