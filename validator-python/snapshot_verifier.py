"""Independent read-only snapshot chunk verifier."""
from canonical_json import canonical_json
from strict_json import parse_strict_value
from snapshot_format import chunk_hash
from record_verifier import verify_record


def verify_chunk(wire, checkpoint, index, previous_hash=None):
    if checkpoint.get('record_type') != 'snapshot_descriptor' or type(index) is not int or index < 0 or index >= len(checkpoint['chunks']):
        raise ValueError('SCHEMA_INVALID')
    verify_record(checkpoint)
    metadata = checkpoint['chunks'][index]
    if len(wire) != metadata['bytes'] or chunk_hash(wire) != metadata['hash']:
        raise ValueError('SNAPSHOT_CHUNK_HASH_INVALID')
    array = parse_strict_value(wire, 524288)
    if not isinstance(array, list) or len(array) != metadata['record_count'] or canonical_json(array).encode('utf-8') != wire:
        raise ValueError('SCHEMA_INVALID')
    result = []
    for document in array:
        verified = verify_record(document)
        if verified['type'] == 'snapshot_descriptor' or (previous_hash is not None and previous_hash >= verified['content_hash']):
            raise ValueError('SNAPSHOT_RECORD_ORDER_INVALID')
        previous_hash = verified['content_hash']
        result.append(verified)
    return {'records': result, 'last_hash': previous_hash}
