import base64
import json
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'validator-python'))
from canonical_json import canonical_json
from record_format import message, payload
from record_verifier import _ecma_pattern, verify_wire
from strict_json import parse_strict_json


class ProtocolCoreTests(unittest.TestCase):
    def test_signed_records(self):
        fixtures = json.loads((ROOT / 'protocol/test-vectors/signed-records.json').read_text())
        for fixture in fixtures['cases']:
            with self.subTest(case=fixture['id']):
                data = base64.b64decode(fixture['input_base64'], validate=True)
                if fixture.get('error'):
                    with self.assertRaises(ValueError) as caught:
                        verify_wire(data, fixture.get('expected_onion'))
                    self.assertEqual(fixture['error'], str(caught.exception))
                else:
                    result = verify_wire(data, fixture.get('expected_onion'))
                    self.assertEqual(fixture['expected_hash'], result['content_hash'])
                    self.assertEqual(fixture['expected_wire'], result['canonical_wire'])
                    record = parse_strict_json(data, 65536)
                    self.assertEqual(fixture['expected_message_hex'], message(record).hex())
                    self.assertEqual(fixture['expected_payload_hex'], canonical_json(payload(record)).encode().hex())

    def test_schema_pattern_end_anchor_rejects_final_line_feed(self):
        self.assertIsNone(_ecma_pattern('^[a-z]+$').search('abc\n'))
        self.assertIsNotNone(_ecma_pattern('^[a-z]+$').search('abc'))
        self.assertIsNotNone(_ecma_pattern(r'^a\$[$]$').search('a$$'))
        self.assertIsNone(_ecma_pattern('^(?:a|b$)$').search('b\n'))

    def test_canonical_bytes(self):
        fixtures = json.loads((ROOT / 'protocol/test-vectors/canonical-json.json').read_text())
        for fixture in fixtures['cases']:
            with self.subTest(case=fixture['id']):
                self.assertEqual(fixture['expected_hex'], canonical_json(parse_strict_json(fixture['input'].encode())).encode().hex())
