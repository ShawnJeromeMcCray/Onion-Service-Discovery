"""The committed error-code registry matches the codes the sources raise (spec 32 section 35)."""
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class ErrorRegistryTest(unittest.TestCase):
    def test_registry_is_current(self) -> None:
        result = subprocess.run([sys.executable, str(ROOT / 'tools/generate_error_registry.py'), '--check'], capture_output=True, text=True, check=False)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()
