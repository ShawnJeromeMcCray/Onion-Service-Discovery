"""Independent PCP-004 service history bundle verifier (unsigned bundle, self-proving records)."""
from identity_deriver import derive_identities
from record_verifier import verify_record
from strict_json import parse_strict_json

MAX_BYTES = 1048576
MAX_RECORDS = 256
TYPES = {'service_manifest', 'service_identity_key_rotation', 'onion_service_migration'}


def verify_service_history(bundle_bytes, current, known):
    """current: a Manifest verified with exact Onion binding; known: records already held."""
    if len(bundle_bytes) > MAX_BYTES or current['type'] != 'service_manifest':
        raise ValueError('HISTORY_BUNDLE_INVALID')
    bundle = parse_strict_json(bundle_bytes, MAX_BYTES)
    records_list = bundle.get('records')
    # Python treats True as 1: compare types explicitly so all implementations agree.
    if (sorted(bundle) != ['bundle_type', 'protocol', 'protocol_version', 'records'] or bundle['protocol'] != 'Onion Service Discovery'
            or type(bundle['protocol_version']) is not int or bundle['protocol_version'] != 1 or bundle['bundle_type'] != 'service_history'
            or type(records_list) is not list or not 1 <= len(records_list) <= MAX_RECORDS):
        raise ValueError('HISTORY_BUNDLE_INVALID')
    records = {}
    for entry in records_list:
        if type(entry) is not dict:
            raise ValueError('HISTORY_BUNDLE_INVALID')
        record = verify_record(entry)
        if record['type'] not in TYPES or record['content_hash'] in records:
            raise ValueError('HISTORY_BUNDLE_INVALID')
        records[record['content_hash']] = record
    union = {record['content_hash']: record for record in known}
    known_hashes = set(union)
    for hash_value, record in records.items():
        union.setdefault(hash_value, record)
    union.setdefault(current['content_hash'], current)
    for identity in derive_identities(list(union.values()), 10000)['identities']:
        if current['content_hash'] not in identity['history_hashes']:
            continue
        if identity['identity_state'] == 'FORKED':
            state = 'FORKED'
        else:
            state = 'CONNECTED' if identity['current_hash'] == current['content_hash'] else 'DOWNGRADE'
        connected = set(identity['history_hashes']) | set(identity['rotation_hashes']) | set(identity['migration_hashes'])
        for conflict in identity['conflicts']:
            connected |= set(conflict['hashes'])
        for conflict in identity['transition_conflicts']:
            connected |= set(conflict['record_hashes'])
        admitted = sorted(h for h in connected if h in records and h not in known_hashes and h != current['content_hash'])
        return {'state': state, 'genesis_key': identity['genesis_key'], 'admitted_hashes': admitted}
    return {'state': 'INCOMPLETE', 'genesis_key': None, 'admitted_hashes': []}
