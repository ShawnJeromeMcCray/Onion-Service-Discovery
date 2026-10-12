"""Run the three-language draft input subset; missing tools are failures."""

from __future__ import annotations

import argparse
from datetime import datetime, timezone
import json
from pathlib import Path
import shutil
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[1]
PHP_IMAGE = 'php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3'


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php-container', action='store_true', help='Use the pinned PHP CLI test image; Docker is required')
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    php = ['php']
    if args.php_container:
        php = ['docker', 'run', '--rm', '--network', 'none', '--mount',
               f'type=bind,src={ROOT},dst=/app,readonly', '--workdir', '/app', PHP_IMAGE, 'php']
    jobs = [
        ('repository-integrity', [sys.executable, 'tools/check_repository.py'], ROOT),
        ('python-vectors', [sys.executable, '-m', 'unittest', 'discover', '-s', 'tests', '-v'], ROOT),
        ('php-address-vectors', php + ['node-php/tests/onion-address.php'], ROOT),
        ('php-json-vectors', php + ['node-php/tests/strict-json.php'], ROOT),
        ('typescript-typecheck', ['npm', 'run', 'check'], ROOT / 'web-local-search-client'),
        ('typescript-vectors', ['npm', 'test'], ROOT / 'web-local-search-client'),
    ]
    results = []
    for name, command, cwd in jobs:
        if shutil.which(command[0]) is None:
            results.append({'check': name, 'status': 'FAIL', 'reason': 'Required executable unavailable'})
            print(f'FAIL: {name}: required executable unavailable')
            continue
        try:
            completed = subprocess.run(command, cwd=cwd, capture_output=True, text=True, timeout=120)
            status = 'PASS' if completed.returncode == 0 else 'FAIL'
            # Test output is deliberately summarized: never include raw protocol inputs.
            results.append({'check': name, 'status': status, 'exit_code': completed.returncode})
            print(f'{status}: {name}')
            if completed.returncode:
                print('Run this named check directly for diagnostic output.', file=sys.stderr)
        except (OSError, subprocess.TimeoutExpired):
            results.append({'check': name, 'status': 'FAIL', 'reason': 'Execution failed or timed out'})
            print(f'FAIL: {name}: execution failed or timed out')
    passed = all(result['status'] == 'PASS' for result in results)
    report = {
        'profile': 'DRAFT_ADDRESS_JSON_INPUT_SUBSET',
        'status': 'PASS' if passed else 'FAIL',
        'generated_at': datetime.now(timezone.utc).isoformat(),
        'shared_fixture_count': sum(len(json.loads(path.read_text())['cases']) for path in
                                    (ROOT / 'protocol/test-vectors').glob('*.json')),
        'php_container': PHP_IMAGE if args.php_container else None,
        'checks': results,
        'limitations': ['Not CORE-V1 conformance', 'Not browser compatibility evidence',
                        'No signatures, identity state, transport or storage tested',
                        'No production release approval'],
    }
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + '\n')
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
