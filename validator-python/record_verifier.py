from language_tag import validate_language_tag
"""Independent read-only record verifier using local schemas and mature Ed25519."""
from datetime import datetime
import json
from pathlib import Path

from nacl.bindings import crypto_core_ed25519_is_valid_point
from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from jsonschema import Draft202012Validator

from canonical_json import canonical_json
from snapshot_format import root_hash
from onion_address import validate_onion_address
from strict_json import parse_strict_json
from record_format import FORMATS, record_type, message, content_hash, decode_bytes

ROOT = Path(__file__).resolve().parents[1]
SCHEMAS = {}


def verify_wire(wire: bytes, expected_onion=None):
    record = parse_strict_json(wire, 65536)
    kind = record_type(record)
    if len(wire) > FORMATS[kind][3]:
        raise ValueError('DOCUMENT_TOO_LARGE')
    return verify_record(record, expected_onion)


def verify_record(record, expected_onion=None):
    kind = record_type(record)
    canonical = canonical_json(record)
    if len(canonical.encode('utf-8')) > FORMATS[kind][3]:
        raise ValueError('DOCUMENT_TOO_LARGE')
    if kind not in SCHEMAS:
        schema = json.loads((ROOT / 'protocol/schemas' / (kind + '.json')).read_text())
        Draft202012Validator.check_schema(schema)
        SCHEMAS[kind] = Draft202012Validator(schema)
    if not SCHEMAS[kind].is_valid(record):
        raise ValueError('SCHEMA_INVALID')
    for field in ('onion', 'node_onion', 'from_onion', 'to_onion'):
        if field in record:
            validate_onion_address(record[field])
    for field in ('service_identity_public_key', 'node_identity_public_key', 'old_public_key', 'new_public_key'):
        if field in record:
            if not crypto_core_ed25519_is_valid_point(decode_bytes(record[field], 'ed25519:', 32, 'PUBLIC_KEY_INVALID')):
                raise ValueError('PUBLIC_KEY_INVALID')
    for field in FORMATS[kind][1]:
        decode_bytes(record[field], 'ed25519sig:', 64, 'SIGNATURE_INVALID')
    for field in ('published_at', 'observed_at', 'created_at'):
        if field in record:
            try:
                parsed = datetime.strptime(record[field], '%Y-%m-%dT%H:%M:%SZ')
            except ValueError:
                raise ValueError('SCHEMA_INVALID') from None
            if parsed.strftime('%Y-%m-%dT%H:%M:%SZ') != record[field]:
                raise ValueError('SCHEMA_INVALID')
    sets = []
    if 'service' in record:
        for language in record['service']['languages']:
            validate_language_tag(language)
        sets += [record['service']['languages'], record['service']['categories']]
    if 'links' in record and 'related_onion_services' in record['links']:
        related = record['links']['related_onion_services']
        sets.append(related)
        for onion in related:
            validate_onion_address(onion)
    for field in ('capabilities', 'supported_protocol_versions'):
        if field in record:
            sets.append(record[field])
    if any(values != sorted(values) or len(values) != len(set(values)) for values in sets):
        raise ValueError('SCHEMA_INVALID')
    if kind == 'snapshot_descriptor':
        hashes = [chunk['hash'] for chunk in record['chunks']]
        if len(set(hashes)) != len(hashes) or sum(chunk['record_count'] for chunk in record['chunks']) != record['record_count'] or root_hash(record['chunks']) != record['root_hash']:
            raise ValueError('SCHEMA_INVALID')
    if kind == 'service_identity_key_rotation' and (record['old_public_key'] == record['new_public_key'] or record['effective_revision'] < 2):
        raise ValueError('KEY_ROTATION_INVALID')
    if kind == 'onion_service_migration' and (record['from_onion'] == record['to_onion'] or record['effective_revision'] < 2):
        raise ValueError('MIGRATION_INVALID')
    if expected_onion is not None:
        validate_onion_address(expected_onion)
        if kind == 'service_manifest' and record['onion'] != expected_onion:
            raise ValueError('ONION_BINDING_MISMATCH')
    key_field = FORMATS[kind][2]
    signature_field = FORMATS[kind][1][0]
    key = Ed25519PublicKey.from_public_bytes(decode_bytes(record[key_field], 'ed25519:', 32, 'PUBLIC_KEY_INVALID'))
    try:
        if not crypto_core_ed25519_is_valid_point(decode_bytes(record[signature_field], 'ed25519sig:', 64, 'SIGNATURE_INVALID')[:32]):
            raise InvalidSignature
        key.verify(decode_bytes(record[signature_field], 'ed25519sig:', 64, 'SIGNATURE_INVALID'), message(record))
    except InvalidSignature:
        raise ValueError('SIGNATURE_INVALID') from None
    if kind == 'service_identity_key_rotation':
        key = Ed25519PublicKey.from_public_bytes(decode_bytes(record['new_public_key'], 'ed25519:', 32, 'PUBLIC_KEY_INVALID'))
        try:
            if not crypto_core_ed25519_is_valid_point(decode_bytes(record['new_key_confirmation'], 'ed25519sig:', 64, 'SIGNATURE_INVALID')[:32]):
                raise InvalidSignature
            key.verify(decode_bytes(record['new_key_confirmation'], 'ed25519sig:', 64, 'SIGNATURE_INVALID'), message(record))
        except InvalidSignature:
            raise ValueError('KEY_ROTATION_INVALID') from None
    return {'type': kind, 'content_hash': content_hash(record), 'canonical_wire': canonical}
