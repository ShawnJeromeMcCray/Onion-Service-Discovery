import base64
import json
from pathlib import Path
import sys
import unittest
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'validator-python'))
from canonical_json import canonical_json
from record_format import content_hash
from snapshot_verifier import verify_chunk

class SnapshotTests(unittest.TestCase):
    def test_chunk(self):
        f = json.loads((ROOT / 'protocol/test-vectors/signed-records.json').read_text())
        case = next(item for item in f['cases'] if item['id'] == 'snapshot-with-chunk')
        checkpoint = json.loads(base64.b64decode(case['input_base64']))
        records = sorted([f['stories']['genesis'], f['stories']['successor']], key=content_hash)
        wire = canonical_json(records).encode()
        self.assertEqual(2, len(verify_chunk(wire, checkpoint, 0)['records']))
        with self.assertRaisesRegex(ValueError, 'SNAPSHOT_CHUNK_HASH_INVALID'):
            verify_chunk(wire + b' ', checkpoint, 0)
        with self.assertRaisesRegex(ValueError, 'SNAPSHOT_RECORD_ORDER_INVALID'):
            verify_chunk(wire, checkpoint, 0, 'sha256:' + 'f' * 64)
