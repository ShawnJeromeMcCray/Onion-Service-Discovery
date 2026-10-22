"""Every PHP test file must run in CI: a file that no runner names is silently skipped."""
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class PhpTestRegistration(unittest.TestCase):
    def test_every_php_test_is_run_by_ci(self):
        runners = (ROOT / 'tools/run_input_conformance.py').read_text(encoding='utf-8') + (ROOT / '.github/workflows/foundation.yml').read_text(encoding='utf-8')
        # Helpers that other tests start, not tests of their own.
        helpers = {path for path in (ROOT / 'node-php/tests').glob('*.php') if path.name.endswith(('-worker.php', '.inc.php'))}
        missing = sorted(path.relative_to(ROOT).as_posix() for path in (ROOT / 'node-php/tests').glob('*.php') if path not in helpers and path.relative_to(ROOT).as_posix() not in runners)
        self.assertEqual([], missing, 'add these to tools/run_input_conformance.py')


if __name__ == '__main__':
    unittest.main()
