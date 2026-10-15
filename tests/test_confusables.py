"""The committed confusable table matches its provenance, and the generator refuses other sources (D-024)."""
import importlib.util
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GENERATOR = ROOT / 'tools/generate_confusables.py'


def generator_module():
    spec = importlib.util.spec_from_file_location('generate_confusables', GENERATOR)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class ConfusablesTableTest(unittest.TestCase):
    def test_committed_table_matches_its_provenance(self) -> None:
        result = subprocess.run([sys.executable, str(GENERATOR), '--check'], capture_output=True, text=True, check=False)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_a_substituted_source_is_refused_before_writing(self) -> None:
        before = (ROOT / 'web-local-search-client/src/client/confusables-data.ts').read_bytes()
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'confusables.txt'
            source.write_text('# Version: 17.0.0\n0430 ;\t0061 ;\tMA\t# substituted\n')
            result = subprocess.run([sys.executable, str(GENERATOR), str(source)], capture_output=True, text=True, check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('hash mismatch', result.stderr)
        self.assertEqual((ROOT / 'web-local-search-client/src/client/confusables-data.ts').read_bytes(), before)

    def test_prototype_encoding_round_trips(self) -> None:
        module = generator_module()
        table = {0: (0x10FFFF,), 31: (32,), 0x1D400: (0x41,), 0x2F800: tuple(range(0x61, 0x61 + 18))}
        self.assertEqual(module.decode(module.encode(table)), table)


if __name__ == '__main__':
    unittest.main()
