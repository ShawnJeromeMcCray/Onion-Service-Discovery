"""Generate offline draft signed-record schemas from the shared limits registry."""
from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
LIMITS=json.loads((ROOT/'protocol/limits.json').read_text())
F=LIMITS['fields']
MAX=LIMITS['max_safe_integer']

def obj(properties,required=None):
    return {'type':'object','properties':properties,'required':list(properties) if required is None else required,'additionalProperties':False}
def string(maximum,minimum=0,pattern=None):
    value={'type':'string','minLength':minimum,'maxLength':maximum}
    if pattern is not None:value['pattern']=pattern
    return value
def array(item,maximum):return {'type':'array','items':item,'maxItems':maximum,'uniqueItems':True}
KEY=string(51,51,r'^ed25519:[A-Za-z0-9_-]{43}$')
SIG=string(97,97,r'^ed25519sig:[A-Za-z0-9_-]{86}$')
HASH=string(71,71,r'^sha256:[0-9a-f]{64}$')
ONION=string(62,62,r'^[a-z2-7]{56}\.onion$')
REV={'type':'integer','minimum':1,'maximum':MAX}
TIME=string(20,20,r'^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$')
FLAGS=obj({name:{'type':'boolean'} for name in ['publish_service','mirror_directory','discover_services','sync_with_peers']})
TEXT='^[^\u0000-\u001f\u007f-\u009f]*$'
SERVICE=obj({'name':string(F['service_name_scalars'],0,TEXT),'description':string(F['description_scalars'],0,TEXT),'languages':array(string(F['language_tag_ascii'],2,r'^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$'),F['languages']),'categories':array(string(F['category_ascii'],1,r'^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$'),F['categories'])},['name','languages','categories'])
LINKS=obj({'related_onion_services':array(ONION,F['related_onions']),'clearnet':array(string(F['clearnet_url_bytes'],8,r'^https://[^/@\s]+(?:/[^\s]*)?$'),F['clearnet_links'])},[])
NULL_HASH={'anyOf':[HASH,{'type':'null'}]}

def record(name,fields,signature='signature'):
    props={'protocol':{'const':'Onion Service Discovery'},'protocol_version':{'const':1},'record_type':{'const':name},**fields,signature:SIG}
    schema=obj(props)
    schema.update({'$schema':'https://json-schema.org/draft/2020-12/schema','$id':'https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/'+name+'.json','title':'Onion Service Discovery '+name,'$comment':'Implementation draft; protocol freeze and human crypto approval are outstanding.'})
    return schema
schemas={}
manifest=record('service_manifest',{'onion':ONION,'service_identity_public_key':KEY,'revision':REV,'previous_manifest_hash':NULL_HASH,'discovery':FLAGS,'service':SERVICE,'links':LINKS,'published_at':TIME})
manifest['required'].remove('links');manifest['required'].remove('published_at')
manifest['allOf']=[{'if':{'properties':{'revision':{'const':1}}},'then':{'properties':{'previous_manifest_hash':{'type':'null'}}},'else':{'properties':{'previous_manifest_hash':HASH}}},{'if':{'properties':{'discovery':{'properties':{'publish_service':{'const':True}}}}},'then':{'properties':{'service':{'properties':{'name':{'minLength':1}}}}}}]
schemas['service_manifest']=manifest
rotation=record('service_identity_key_rotation',{'onion':ONION,'old_public_key':KEY,'new_public_key':KEY,'effective_revision':REV,'previous_state_hash':HASH,'new_key_confirmation':SIG},'old_key_signature')
schemas['service_identity_key_rotation']=rotation
schemas['onion_service_migration']=record('onion_service_migration',{'service_identity_public_key':KEY,'from_onion':ONION,'to_onion':ONION,'effective_revision':REV,'previous_state_hash':HASH},'service_identity_signature')
node=record('node_descriptor',{'node_identity_public_key':KEY,'node_onion':ONION,'descriptor_revision':REV,'previous_descriptor_hash':NULL_HASH,'supported_protocol_versions':{'type':'array','items':REV,'minItems':1,'maxItems':16,'uniqueItems':True,'contains':{'const':1}},'capabilities':array(string(F['capability_ascii'],1,r'^[a-z0-9][a-z0-9.-]*$'),F['capabilities']),'software':obj({'name':string(64,1,TEXT),'version':string(64,1,TEXT)})})
node['allOf']=[{'if':{'properties':{'descriptor_revision':{'const':1}}},'then':{'properties':{'previous_descriptor_hash':{'type':'null'}}},'else':{'properties':{'previous_descriptor_hash':HASH}}}]
schemas['node_descriptor']=node
schemas['direct_observation']=record('direct_observation',{'node_identity_public_key':KEY,'onion':ONION,'service_identity_public_key':KEY,'revision':REV,'manifest_hash':HASH,'result':{'enum':['SUCCESS_CURRENT','SUCCESS_UNCHANGED','SUCCESS_NEW_REVISION','SUCCESS_VALID_ROTATION','SUCCESS_VALID_MIGRATION']},'observed_at':TIME})
chunk=obj({'hash':HASH,'bytes':{'type':'integer','minimum':2,'maximum':LIMITS['document_bytes']['chunk']},'record_count':{'type':'integer','minimum':0,'maximum':256}})
snapshot=record('snapshot_descriptor',{'node_identity_public_key':KEY,'generation':REV,'previous_checkpoint_hash':NULL_HASH,'created_at':TIME,'root_hash':HASH,'record_count':{'type':'integer','minimum':0,'maximum':MAX},'cursor':string(128,1,r'^[A-Za-z0-9_-]+$'),'chunks':{'type':'array','items':chunk,'maxItems':256}})
snapshot['allOf']=[{'if':{'properties':{'generation':{'const':1}}},'then':{'properties':{'previous_checkpoint_hash':{'type':'null'}}},'else':{'properties':{'previous_checkpoint_hash':HASH}}}]
schemas['snapshot_descriptor']=snapshot
for name,schema in schemas.items():(ROOT/'protocol/schemas'/f'{name}.json').write_text(json.dumps(schema,indent=2)+'\n')
(ROOT/'protocol/schemas/manifest.json').write_text(json.dumps({'status':'DRAFT','dialect':'https://json-schema.org/draft/2020-12/schema','record_types':sorted(schemas)},indent=2)+'\n')
print(f'Generated {len(schemas)} signed-record draft schemas')
