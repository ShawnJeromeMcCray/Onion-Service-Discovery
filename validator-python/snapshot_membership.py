"""Independent offline exact-state membership in a complete signed draft snapshot."""
import json

from nacl.bindings import crypto_core_ed25519_is_valid_point
from identity_deriver import derive_identities
from record_format import decode_bytes
from record_verifier import verify_wire
from snapshot_verifier import verify_chunk

MAX_RECORDS = 10000
MAX_BYTES = 33554432


def tuple_state(identity, records):
    manifest = identity['current_manifest']
    epoch = None
    if manifest is not None:
        documents = {r['content_hash']: json.loads(r['canonical_wire']) for r in records if r['type'] == 'service_manifest'}
        epoch, key = 1, identity['genesis_key']
        for hash_value in identity['history_hashes']:
            next_key = documents[hash_value]['service_identity_public_key']
            if next_key != key:
                epoch, key = epoch + 1, next_key
    return {'logical_service_id': identity['genesis_key'], 'current_service_identity_public_key': manifest['service_identity_public_key'] if manifest else None,
            'key_epoch': epoch, 'onion': manifest['onion'] if manifest else None, 'revision': identity['highest_revision'],
            'manifest_hash': identity['current_hash'], 'protocol_version': 1, 'active': identity['discoverability'] == 'PUBLISHED'}


def verify_membership(checkpoint_wire, chunks, expected_node_key, local_history):
    if not crypto_core_ed25519_is_valid_point(decode_bytes(expected_node_key, 'ed25519:', 32, 'PUBLIC_KEY_INVALID')):
        raise ValueError('PUBLIC_KEY_INVALID')
    checkpoint = verify_wire(checkpoint_wire)
    descriptor = json.loads(checkpoint['canonical_wire'])
    if checkpoint['type'] != 'snapshot_descriptor' or descriptor['node_identity_public_key'] != expected_node_key:
        raise ValueError('CHECKPOINT_NODE_MISMATCH')
    if descriptor['record_count'] > MAX_RECORDS or sum(c['bytes'] for c in descriptor['chunks']) > MAX_BYTES:
        raise ValueError('WORK_BUDGET_EXHAUSTED')
    # Revalidate caller-supplied history: Python has no unforgeable validated-record type.
    if len(local_history) > MAX_RECORDS:
        raise ValueError('WORK_BUDGET_EXHAUSTED')
    local_records = [verify_wire(r['canonical_wire'].encode()) for r in local_history]
    local = derive_identities(local_records, MAX_RECORDS)
    if len(local['identities']) != 1 or local['pending_hashes'] or local['rejected']:
        raise ValueError('LOCAL_CONTINUITY_INCOMPLETE')
    own = local['identities'][0]
    if own['identity_state'] != 'VALID' or own['current_hash'] is None:
        raise ValueError('LOCAL_IDENTITY_CONFLICT')
    records, position, last_hash = [], 0, None
    for wire in chunks:
        if position >= len(descriptor['chunks']):
            raise ValueError('SNAPSHOT_INCOMPLETE')
        result = verify_chunk(wire, descriptor, position, last_hash)
        records.extend(result['records'])
        last_hash = result['last_hash']
        position += 1
    if position != len(descriptor['chunks']) or len(records) != descriptor['record_count']:
        raise ValueError('SNAPSHOT_INCOMPLETE')
    view = derive_identities(records, MAX_RECORDS)
    combined = {r['content_hash']: r for r in local_records + records}
    conflicting = any(i['genesis_key'] == own['genesis_key'] and i['identity_state'] == 'FORKED'
                      for i in derive_identities(list(combined.values()), 2 * MAX_RECORDS)['identities'])
    found = next((x for x in view['identities'] if x['genesis_key'] == own['genesis_key']), None)
    status = 'IDENTITY_NOT_PRESENT'
    if conflicting:
        status = 'CONFLICTING_SERVICE_STATE'
    elif found is not None:
        if found['identity_state'] != 'VALID':
            status = 'CONFLICTING_SERVICE_STATE'
        elif found['current_hash'] == own['current_hash']:
            status = 'CURRENT_STATE_INCLUDED' if found['discoverability'] == 'PUBLISHED' else 'WITHDRAWN_IN_SNAPSHOT'
        elif found['highest_revision'] < own['highest_revision']:
            status = 'STALE_REVISION_INCLUDED'
        else:
            status = 'DIFFERENT_SERVICE_STATE'
    observation = None
    for record in records:
        if record['type'] != 'direct_observation':
            continue
        doc = json.loads(record['canonical_wire'])
        manifest = own['current_manifest']
        if (doc['node_identity_public_key'] == expected_node_key and doc['manifest_hash'] == own['current_hash']
                and doc['onion'] == manifest['onion'] and doc['service_identity_public_key'] == manifest['service_identity_public_key']
                and doc['revision'] == manifest['revision']):
            observation = max(observation or '', doc['observed_at'])
    return {'status': status, 'expected_state': tuple_state(own, local_records), 'snapshot_state': tuple_state(found, records) if found else None,
            'checkpoint_hash': checkpoint['content_hash'], 'node_identity_public_key': expected_node_key, 'checkpoint_generation': descriptor['generation'],
            'checkpoint_created_at': descriptor['created_at'], 'checkpoint_root': descriptor['root_hash'], 'evidence_type': 'COMPLETE_SIGNED_DRAFT_SNAPSHOT',
            'node_claimed_direct_observation_at': observation, 'checkpoint_consistency': 'NOT_EVALUATED', 'freshness': 'NOT_EVALUATED', 'network_side_effects': False}
