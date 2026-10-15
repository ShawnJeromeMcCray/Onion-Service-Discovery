import json
import re
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class KeyFingerprintVectors(unittest.TestCase):
    def test_vectors_are_current_and_well_formed(self) -> None:
        result = subprocess.run([sys.executable, str(ROOT / 'tools/generate_key_fingerprints.py'), '--check'], capture_output=True, text=True, check=False)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        vectors = json.loads((ROOT / 'protocol/test-vectors/key-fingerprint-v1.json').read_text(encoding='utf-8'))
        self.assertGreaterEqual(len(vectors['cases']), 5)
        fingerprints = [case['fingerprint'] for case in vectors['cases']]
        self.assertEqual(len(set(fingerprints)), len(fingerprints))
        for fingerprint in fingerprints:
            self.assertRegex(fingerprint, re.compile(r'\A[A-Z2-7]{5}(?:-[A-Z2-7]{5}){5}\Z'))


if __name__ == '__main__':
    unittest.main()
