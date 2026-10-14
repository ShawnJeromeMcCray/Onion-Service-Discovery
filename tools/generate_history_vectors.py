"""Generate PCP-004 service history bundle vectors from the signed identity-sequence fixtures.

Expected results follow the PCP-004 rules directly (content hashes come from the protocol hash
function); they are not produced by any of the three implementations under test.
"""
import base64
import copy
import json
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'validator-python'))
from canonical_json import canonical_json  # noqa: E402
from record_format import content_hash  # noqa: E402

stories = {case['id']: case['records'] for case in json.loads((ROOT / 'protocol/test-vectors/identity-sequences.json').read_text())['cases']}
cases = []


def encode(value) -> str:
    # Bundles need not be canonical; plain compact JSON also allows over-limit negative cases.
    raw = value if isinstance(value, bytes) else json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()
    return base64.b64encode(raw).decode('ascii')


def bundle(records, **override):
    document = {'protocol': 'Onion Service Discovery', 'protocol_version': 1, 'bundle_type': 'service_history', 'records': records}
    document.update(override)
    return document


def add(ident, current, bundle_value, known=(), state=None, admitted=(), error=None, onion=None):
    entry = {'id': ident, 'input': {'known_records': list(known), 'current_wire': canonical_json(current),
                                    'onion': onion or current['onion'], 'bundle_base64': encode(bundle_value)}}
    if error is not None:
        entry['error'] = error
    else:
        genesis = None if state == 'INCOMPLETE' else stories[ident_story[ident]][0]['service_identity_public_key']
        entry['expected'] = {'state': state, 'genesis_key': genesis, 'admitted_hashes': sorted(content_hash(record) for record in admitted)}
    cases.append(entry)


ident_story = {}


def story(ident, name):
    ident_story[ident] = name
    return stories[name]


genesis = story('history-genesis-only', 'genesis')[0]
add('history-genesis-only', genesis, bundle([genesis]), state='CONNECTED')

r1, r2 = story('history-first-contact-after-revision', 'valid-successor')
add('history-first-contact-after-revision', r2, bundle([r1, r2]), state='CONNECTED', admitted=[r1])

m1, rotation, m2 = story('history-first-contact-after-rotation', 'valid-rotation')
add('history-first-contact-after-rotation', m2, bundle([m1, rotation, m2]), state='CONNECTED', admitted=[m1, rotation])

g, migration, moved = story('history-migration-with-known-genesis', 'valid-migration')
add('history-migration-with-known-genesis', moved, bundle([g, migration, moved]), known=[g], state='CONNECTED', admitted=[migration])

c1, c_rotation, c_migration, c2 = story('history-combined-transition', 'combined-transition-atomic')
add('history-combined-transition', c2, bundle([c1, c_rotation, c_migration, c2]), state='CONNECTED', admitted=[c1, c_rotation, c_migration])

k1, k2 = story('history-missing-rotation-proof', 'new-key-needs-rotation')
add('history-missing-rotation-proof', k2, bundle([k1, k2]), state='INCOMPLETE')

f1, f2a, f2b = story('history-fork-evidence', 'fork-same-revision')
add('history-fork-evidence', f2a, bundle([f1, f2a, f2b]), state='FORKED', admitted=[f1, f2b])

other = stories['same-onion-distinct-identities-remain-separate'][1]
story('history-unrelated-identity-ignored', 'valid-successor')
add('history-unrelated-identity-ignored', r2, bundle([r1, other, r2]), state='CONNECTED', admitted=[r1])

story('history-downgrade', 'valid-successor')
add('history-downgrade', r1, bundle([r1]), known=[r1, r2], state='DOWNGRADE')

story('history-orphan-bundle', 'valid-successor')
add('history-orphan-bundle', r2, bundle([r2]), state='INCOMPLETE')

# Malformed bundles and propagated record errors.
add('history-bundle-type-invalid', r2, bundle([r1, r2], bundle_type='checkpoint'), error='HISTORY_BUNDLE_INVALID')
add('history-extra-member', r2, bundle([r1, r2], signature='ed25519sig:AA'), error='HISTORY_BUNDLE_INVALID')
add('history-empty-records', genesis, bundle([]), error='HISTORY_BUNDLE_INVALID')
add('history-protocol-version-boolean', r2, bundle([r1, r2], protocol_version=True), error='HISTORY_BUNDLE_INVALID')
add('history-records-not-array', r2, bundle({'0': r1}), error='HISTORY_BUNDLE_INVALID')
add('history-non-object-entry', r2, bundle([1, r2]), error='HISTORY_BUNDLE_INVALID')
observation = stories['imported-observation-does-not-change-identity'][1]
add('history-foreign-record-type', genesis, bundle([genesis, observation]), error='HISTORY_BUNDLE_INVALID')
add('history-duplicate-record', r2, bundle([r1, r1, r2]), error='HISTORY_BUNDLE_INVALID')
tampered = copy.deepcopy(r1)
tampered['service']['description'] = 'Tampered after signing.'
add('history-tampered-signature', r2, bundle([tampered, r2]), error='SIGNATURE_INVALID')
foreign_onion = json.loads((ROOT / 'protocol/test-vectors/onion-addresses.json').read_text())['cases'][0]['input']
add('history-current-onion-mismatch', r2, bundle([r1, r2]), onion=foreign_onion, error='ONION_BINDING_MISMATCH')
add('history-invalid-json', r2, b'{"protocol":', error='JSON_INVALID')
add('history-too-many-elements', r2, bundle([{}] * 257), error='SCHEMA_INVALID')

(ROOT / 'protocol/test-vectors/service-history.json').write_text(json.dumps({
    'status': 'DRAFT_PCP_004', 'description': 'PCP-004 service history bundle vectors built from TEST ONLY identity-sequence fixtures.',
    'cases': cases}, indent=1, ensure_ascii=False) + '\n')
print(f'Wrote {len(cases)} service history cases')
