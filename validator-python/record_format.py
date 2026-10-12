"""Independent fixed domain/encoding implementation; no reference-code imports."""
import base64
import hashlib
import re
from canonical_json import canonical_json

FORMATS = {
    'service_manifest': ('service-manifest', ('signature',), 'service_identity_public_key', 16384),
    'service_identity_key_rotation': ('service-key-rotation', ('old_key_signature', 'new_key_confirmation'), 'old_public_key', 8192),
    'onion_service_migration': ('onion-migration', ('service_identity_signature',), 'service_identity_public_key', 8192),
    'node_descriptor': ('node-descriptor', ('signature',), 'node_identity_public_key', 16384),
    'direct_observation': ('direct-observation', ('signature',), 'node_identity_public_key', 8192),
    'snapshot_descriptor': ('snapshot-descriptor', ('signature',), 'node_identity_public_key', 65536),
}


def record_type(record):
    if type(record) is not dict:
        raise ValueError('SCHEMA_INVALID')
    if record.get('protocol') != 'Onion Service Discovery' or type(record.get('protocol_version')) is not int or record.get('protocol_version') != 1:
        raise ValueError('PROTOCOL_UNSUPPORTED')
    kind = record.get('record_type')
    if type(kind) is not str or kind not in FORMATS:
        raise ValueError('SCHEMA_INVALID')
    return kind


def payload(record):
    excluded = FORMATS[record_type(record)][1]
    return {key: value for key, value in record.items() if key not in excluded}


def message(record):
    domain = FORMATS[record_type(record)][0]
    return ('Onion Service Discovery/' + domain + '/v1\0' + canonical_json(payload(record))).encode('utf-8')


def content_hash(record):
    kind = record_type(record)
    domain = 'service-manifest-hash' if kind == 'service_manifest' else FORMATS[kind][0] + '-hash'
    return 'sha256:' + hashlib.sha256(('Onion Service Discovery/' + domain + '/v1\0' + canonical_json(payload(record))).encode('utf-8')).hexdigest()


def encode_bytes(data, prefix):
    return prefix + base64.urlsafe_b64encode(data).decode('ascii').rstrip('=')


def decode_bytes(value, prefix, length, error):
    if type(value) is not str or not value.startswith(prefix) or len(value) != len(prefix) + (length * 8 + 5) // 6:
        raise ValueError(error)
    encoded = value[len(prefix):]
    if re.fullmatch('[A-Za-z0-9_-]+', encoded) is None:
        raise ValueError(error)
    decoded = base64.urlsafe_b64decode(encoded + '=' * ((4 - len(encoded) % 4) % 4))
    if len(decoded) != length or encode_bytes(decoded, prefix) != value:
        raise ValueError(error)
    return decoded
