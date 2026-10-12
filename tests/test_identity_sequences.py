import json
from pathlib import Path
import random
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'validator-python'))
from identity_deriver import derive_identities
from record_verifier import verify_record


class IdentitySequenceTests(unittest.TestCase):
    def test_shared_sequences_and_properties(self):
        fixtures = json.loads((ROOT / 'protocol/test-vectors/identity-sequences.json').read_text())
        randomizer = random.Random(8785)
        for fixture in fixtures['cases']:
            with self.subTest(case=fixture['id']):
                records = [verify_record(record) for record in fixture['records']]
                if fixture.get('error'):
                    with self.assertRaisesRegex(ValueError, '^' + fixture['error'] + '$'):
                        derive_identities(records, fixture['max_records'])
                else:
                    self.assertEqual(fixture['expected'], derive_identities(records))
                    for _ in range(10):
                        shuffled = records + records
                        randomizer.shuffle(shuffled)
                        self.assertEqual(fixture['expected'], derive_identities(shuffled))
