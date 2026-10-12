import base64
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('strict_json', ROOT / 'validator-python/strict_json.py')
assert SPEC is not None and SPEC.loader is not None
VALIDATOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VALIDATOR)


class StrictJsonTests(unittest.TestCase):
    def test_shared_vectors(self):
        fixtures = json.loads((ROOT / 'protocol/test-vectors/strict-json.json').read_text())
        for fixture in fixtures['cases']:
            with self.subTest(case=fixture['id']):
                data = base64.b64decode(fixture['input_base64'], validate=True)
                if fixture.get('error'):
                    with self.assertRaises(VALIDATOR.ProtocolInputError) as caught:
                        VALIDATOR.parse_strict_json(data, fixture.get('max_bytes', 16384))
                    self.assertEqual(fixture['error'], str(caught.exception))
                else:
                    self.assertEqual(fixture['expected'], VALIDATOR.parse_strict_json(data, fixture.get('max_bytes', 16384)))
