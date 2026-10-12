import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('onion_address', ROOT / 'validator-python/onion_address.py')
assert SPEC is not None and SPEC.loader is not None
VALIDATOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VALIDATOR)


class OnionAddressTests(unittest.TestCase):
    def test_shared_vectors(self):
        vectors = json.loads((ROOT / 'protocol/test-vectors/onion-addresses.json').read_text())
        for fixture in vectors['cases']:
            with self.subTest(case=fixture['id']):
                if fixture['valid']:
                    self.assertEqual(fixture['input'], VALIDATOR.validate_onion_address(fixture['input']))
                else:
                    with self.assertRaisesRegex(ValueError, '^ADDRESS_INVALID$'):
                        VALIDATOR.validate_onion_address(fixture['input'])

    def test_non_string_values_rejected(self):
        for value in (None, {}, [], 42, True):
            with self.assertRaisesRegex(ValueError, '^ADDRESS_INVALID$'):
                VALIDATOR.validate_onion_address(value)
