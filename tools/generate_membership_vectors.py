"""Generate synthetic offline snapshot-membership fixtures; all signing keys are TEST ONLY."""
import copy
import hashlib
import json
from pathlib import Path
import sys

from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'validator-python'))
from canonical_json import canonical_json
from record_format import content_hash, encode_bytes, message
from snapshot_format import chunk_hash, root_hash

stories = {c['id']: c for c in json.loads((ROOT / 'protocol/test-vectors/identity-sequences.json').read_text())['cases']}
key = Ed25519PrivateKey.from_private_bytes(hashlib.sha256(b'Onion Service Discovery TEST ONLY snapshot membership').digest())
node_key = encode_bytes(key.public_key().public_bytes_raw(), 'ed25519:')
cases = []


def sign(document):
    document = copy.deepcopy(document)
    document['signature'] = encode_bytes(key.sign(message(document)), 'ed25519sig:')
    return document


def checkpoint(documents, reverse=False):
    ordered = sorted({content_hash(d): d for d in documents}.items(), reverse=reverse)
    chunks = [canonical_json([d for _, d in ordered])] if ordered else []
    metadata = [{'hash': chunk_hash(w.encode()), 'bytes': len(w.encode()), 'record_count': len(ordered)} for w in chunks]
    descriptor = sign({'protocol': 'Onion Service Discovery', 'protocol_version': 1, 'record_type': 'snapshot_descriptor',
                       'node_identity_public_key': node_key, 'generation': 1, 'previous_checkpoint_hash': None,
                       'created_at': '2026-10-13T00:00:00Z', 'root_hash': root_hash(metadata), 'record_count': len(ordered), 'cursor': '0', 'chunks': metadata})
    return {'checkpoint_wire': canonical_json(descriptor), 'chunks': chunks, 'expected_node_key': node_key}


def expected_tuple(story):
    identity = story['expected']['identities'][0]
    manifest = identity['current_manifest']
    return {'logical_service_id': identity['genesis_key'], 'current_service_identity_public_key': manifest['service_identity_public_key'] if manifest else None,
            'key_epoch': (2 if story['id'] in ('valid-rotation', 'combined-transition-atomic') else 1) if manifest else None,
            'onion': manifest['onion'] if manifest else None, 'revision': identity['highest_revision'], 'manifest_hash': identity['current_hash'],
            'protocol_version': 1, 'active': identity['discoverability'] == 'PUBLISHED'}


def add(ident, local='genesis', remote='genesis', status='CURRENT_STATE_INCLUDED', error=None, mutate=None):
    own = stories[local]
    docs = copy.deepcopy(stories[remote]['records']) if remote else []
    fixture = {**checkpoint(docs), 'local_records': copy.deepcopy(own['records'])}
    if mutate:
        mutate(fixture)
    entry = {'id': ident, 'input': fixture}
    if error:
        entry['error'] = error
    else:
        cp = json.loads(fixture['checkpoint_wire'])
        entry['expected'] = {'status': status, 'expected_state': expected_tuple(own),
            'snapshot_state': expected_tuple(stories[remote]) if remote and status != 'IDENTITY_NOT_PRESENT' else None,
            'checkpoint_hash': content_hash(cp), 'node_identity_public_key': node_key, 'checkpoint_generation': 1,
            'checkpoint_created_at': cp['created_at'], 'checkpoint_root': cp['root_hash'], 'evidence_type': 'COMPLETE_SIGNED_DRAFT_SNAPSHOT',
            'node_claimed_direct_observation_at': None, 'checkpoint_consistency': 'NOT_EVALUATED', 'freshness': 'NOT_EVALUATED', 'network_side_effects': False}
    cases.append(entry)


for story in ('genesis', 'valid-successor', 'valid-rotation', 'valid-migration', 'combined-transition-atomic'):
    add('membership-' + story, story, story)
add('membership-withdrawal', 'withdrawal-retains-history', 'withdrawal-retains-history', 'WITHDRAWN_IN_SNAPSHOT')
add('membership-stale', 'valid-successor', 'genesis', 'STALE_REVISION_INCLUDED')
add('membership-historical-is-not-active', 'genesis', 'valid-successor', 'DIFFERENT_SERVICE_STATE')
add('membership-fork', remote='fork-same-revision', status='CONFLICTING_SERVICE_STATE')
fork_branch = [stories['fork-same-revision']['records'][0], stories['fork-same-revision']['records'][2]]
add('membership-split-view-fork', local='valid-successor', remote='fork-same-revision', status='CONFLICTING_SERVICE_STATE', mutate=lambda f: f.update(checkpoint(fork_branch)))
branch = fork_branch[1]
cases[-1]['expected']['snapshot_state'] = {'logical_service_id': fork_branch[0]['service_identity_public_key'],
    'current_service_identity_public_key': branch['service_identity_public_key'], 'key_epoch': 1, 'onion': branch['onion'],
    'revision': branch['revision'], 'manifest_hash': content_hash(branch), 'protocol_version': 1, 'active': True}
add('membership-omission', remote=None, status='IDENTITY_NOT_PRESENT')
add('membership-orphan-is-not-logical-identity', local='valid-successor', remote='orphan-is-not-continuity', status='IDENTITY_NOT_PRESENT')
add('membership-local-fork', local='fork-same-revision', error='LOCAL_IDENTITY_CONFLICT')
add('membership-local-orphan', local='orphan-is-not-continuity', error='LOCAL_CONTINUITY_INCOMPLETE')
add('membership-local-ambiguous', local='same-onion-distinct-identities-remain-separate', error='LOCAL_CONTINUITY_INCOMPLETE')
add('membership-missing-chunk', error='SNAPSHOT_INCOMPLETE', mutate=lambda f: f.update(chunks=[]))
add('membership-extra-chunk', error='SNAPSHOT_INCOMPLETE', mutate=lambda f: f['chunks'].append('[]'))
add('membership-corrupt-chunk', error='SNAPSHOT_CHUNK_HASH_INVALID', mutate=lambda f: f['chunks'].__setitem__(0, f['chunks'][0] + ' '))
add('membership-wrong-node', error='CHECKPOINT_NODE_MISMATCH', mutate=lambda f: f.update(expected_node_key=stories['genesis']['records'][0]['service_identity_public_key']))
add('membership-boolean-is-not-proof', error='PROTOCOL_UNSUPPORTED', mutate=lambda f: f.update(checkpoint_wire='{"listed":true}'))


def wrong_order(fixture):
    fixture.update(checkpoint(stories['valid-successor']['records'], reverse=True))


add('membership-record-order', remote='valid-successor', error='SNAPSHOT_RECORD_ORDER_INVALID', mutate=wrong_order)


def corrupt_signature(fixture):
    documents = copy.deepcopy(stories['genesis']['records'])
    documents[0]['service']['name'] = 'Unsigned attacker substitution'
    fixture.update(checkpoint(documents))


add('membership-service-signature', error='SIGNATURE_INVALID', mutate=corrupt_signature)


def budget(fixture, count):
    cp = json.loads(fixture['checkpoint_wire'])
    cp['chunks'] = [{'hash': 'sha256:' + hashlib.sha256(str(i).encode()).hexdigest(), 'bytes': 524288, 'record_count': count} for i in range(65)]
    cp['record_count'] = 65 * count
    cp['root_hash'] = root_hash(cp['chunks'])
    fixture.update(checkpoint_wire=canonical_json(sign(cp)), chunks=[])


add('membership-byte-budget-before-chunk-read', error='WORK_BUDGET_EXHAUSTED', mutate=lambda f: budget(f, 1))
add('membership-record-budget-before-chunk-read', error='WORK_BUDGET_EXHAUSTED', mutate=lambda f: budget(f, 256))

for entry in cases:
    wire = entry['input']['checkpoint_wire']
    entry['input']['checkpoint_wire'] = [wire[i:i + 3072] for i in range(0, len(wire), 3072)]

(ROOT / 'protocol/test-vectors/snapshot-membership.json').write_text(json.dumps({'status': 'DRAFT_OFFLINE_MEMBERSHIP',
    'scope': 'Existing complete draft snapshot bytes; no active-index checkpoint freeze, freshness, ancestry or production approval', 'cases': cases}, ensure_ascii=False, indent=2) + '\n')
print(f'Generated {len(cases)} draft membership cases')
