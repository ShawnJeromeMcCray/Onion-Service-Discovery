"""Independent continuity graph built from already verified public records."""
import json


def derive_identities(records, max_records=100000):
    if type(max_records) is not int or not 1 <= max_records <= 1000000 or len(records) > max_records:
        raise ValueError('WORK_BUDGET_EXHAUSTED')
    documents = {record['content_hash']: json.loads(record['canonical_wire']) for record in records}
    manifests = {hash_value: record for hash_value, record in documents.items() if record['record_type'] == 'service_manifest'}
    children = {}
    rotation_proofs = {}
    migration_proofs = {}
    rotation_targets = {}
    migration_targets = {}
    for hash_value, record in documents.items():
        kind = record['record_type']
        if kind == 'service_manifest' and record['previous_manifest_hash'] is not None:
            children.setdefault(record['previous_manifest_hash'], set()).add(hash_value)
        elif kind == 'service_identity_key_rotation':
            index = (record['previous_state_hash'], record['effective_revision'], record['onion'], record['old_public_key'], record['new_public_key'])
            rotation_proofs.setdefault(index, set()).add(hash_value)
            context = index[:4]
            rotation_targets.setdefault(context, {}).setdefault(record['new_public_key'], set()).add(hash_value)
        elif kind == 'onion_service_migration':
            index = (record['previous_state_hash'], record['effective_revision'], record['from_onion'], record['to_onion'], record['service_identity_public_key'])
            migration_proofs.setdefault(index, set()).add(hash_value)
            context = (record['previous_state_hash'], record['effective_revision'], record['from_onion'], record['service_identity_public_key'])
            migration_targets.setdefault(context, {}).setdefault(record['to_onion'], set()).add(hash_value)
    roots = {}
    assigned = {}
    queue = []
    for hash_value, record in sorted(manifests.items()):
        if record['revision'] == 1:
            identity = record['service_identity_public_key']
            assigned[hash_value] = identity
            roots.setdefault(identity, {'revisions': {}, 'rotations': set(), 'migrations': set(), 'transition_conflicts': {}})
            queue.append(hash_value)
    visited = set()
    rejected = {}
    position = 0
    while position < len(queue):
        hash_value = queue[position]
        position += 1
        if hash_value in visited:
            continue
        visited.add(hash_value)
        previous = manifests[hash_value]
        root = roots[assigned[hash_value]]
        root['revisions'].setdefault(previous['revision'], set()).add(hash_value)
        if previous['revision'] < 9007199254740991:
            context = (hash_value, previous['revision'] + 1, previous['onion'], previous['service_identity_public_key'])
            for kind, targets in [('KEY_ROTATION_CONFLICT', rotation_targets.get(context, {})), ('MIGRATION_CONFLICT', migration_targets.get(context, {}))]:
                if len(targets) > 1:
                    proofs = sorted(proof for hashes in targets.values() for proof in hashes)
                    root['transition_conflicts'][kind + '|' + hash_value] = {'type': kind, 'previous_hash': hash_value, 'record_hashes': proofs}
        for next_hash in sorted(children.get(hash_value, ())):
            successor = manifests[next_hash]
            if previous['revision'] == 9007199254740991 or successor['revision'] != previous['revision'] + 1:
                rejected[next_hash] = 'REVISION_CHAIN_BROKEN'
                continue
            rotations = set()
            migrations = set()
            if successor['service_identity_public_key'] != previous['service_identity_public_key']:
                index = (hash_value, successor['revision'], previous['onion'], previous['service_identity_public_key'], successor['service_identity_public_key'])
                rotations = rotation_proofs.get(index, set())
                if not rotations:
                    rejected[next_hash] = 'KEY_ROTATION_INVALID'
                    continue
            if successor['onion'] != previous['onion']:
                index = (hash_value, successor['revision'], previous['onion'], successor['onion'], previous['service_identity_public_key'])
                migrations = migration_proofs.get(index, set())
                if not migrations:
                    rejected[next_hash] = 'MIGRATION_INVALID'
                    continue
            root['rotations'].update(rotations)
            root['migrations'].update(migrations)
            assigned[next_hash] = assigned[hash_value]
            queue.append(next_hash)
    identities = []
    for identity, root in sorted(roots.items()):
        revisions = root['revisions']
        highest = max(revisions)
        conflicts = [{'revision': revision, 'hashes': sorted(hashes)} for revision, hashes in sorted(revisions.items()) if len(hashes) > 1]
        transitions = [value for _, value in sorted(root['transition_conflicts'].items())]
        forked = bool(conflicts or transitions)
        current_hash = None if forked else next(iter(revisions[highest]))
        current = manifests.get(current_hash)
        identities.append({
            'genesis_key': identity, 'highest_revision': highest,
            'identity_state': 'FORKED' if forked else 'VALID', 'current_hash': current_hash,
            'current_manifest': current,
            'discoverability': 'UNKNOWN' if current is None else 'PUBLISHED' if current['discovery']['publish_service'] else 'WITHDRAWN',
            'history_hashes': [hash_value for _, hashes in sorted(revisions.items()) for hash_value in sorted(hashes)],
            'rotation_hashes': sorted(root['rotations']), 'migration_hashes': sorted(root['migrations']), 'conflicts': conflicts, 'transition_conflicts': transitions,
        })
    return {'identities': identities, 'pending_hashes': sorted(set(manifests) - visited - set(rejected)), 'rejected': dict(sorted(rejected.items()))}
