"""Independent draft snapshot byte construction."""
import hashlib
from canonical_json import canonical_json


def chunk_hash(canonical_bytes):
    if isinstance(canonical_bytes, str):
        canonical_bytes = canonical_bytes.encode('utf-8')
    return 'sha256:' + hashlib.sha256(b'Onion Service Discovery/snapshot-chunk/v1\0' + canonical_bytes).hexdigest()


def root_hash(chunks):
    return 'sha256:' + hashlib.sha256(b'Onion Service Discovery/snapshot-root/v1\0' + canonical_json(chunks).encode('utf-8')).hexdigest()
