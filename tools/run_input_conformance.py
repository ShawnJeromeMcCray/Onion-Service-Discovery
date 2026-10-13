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
    parser.add_argument('--php-image', choices=['php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3', 'php@sha256:1d2cf2b7d715b5b3aa959f0150ccbcc7b71e0181b637e443abf40a7fb56bf79a'], default=PHP_IMAGE)
    parser.add_argument('--php-container', action='store_true', help='Use the pinned PHP CLI test image; Docker is required')
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    php = ['php']
    if args.php_container:
        php = ['docker', 'run', '--rm', '--network', 'none', '--mount',
               f'type=bind,src={ROOT},dst=/app,readonly', '--workdir', '/app', args.php_image, 'php']
    jobs = [
        ('browser-client-build', ['npm','run','build'], ROOT / 'web-local-search-client'),
        ('repository-integrity', [sys.executable, 'tools/check_repository.py'], ROOT),
        ('python-vectors', [sys.executable, '-m', 'unittest', 'discover', '-s', 'tests', '-v'], ROOT),
        ('php-address-vectors', php + ['node-php/tests/onion-address.php'], ROOT),
        ('php-json-vectors', php + ['node-php/tests/strict-json.php'], ROOT),
        ('php-record-vectors', php + ['node-php/tests/protocol-core.php'], ROOT),
        ('php-public-request-budget', php + ['node-php/tests/request-budget.php'], ROOT),
        ('php-node-initializer', php + ['node-php/tests/node-initializer.php'], ROOT),
        ('php-client-relay-controlled', php + ['node-php/tests/client-relay.php'], ROOT),
        ('php-client-host', php + ['node-php/tests/client-host.php'], ROOT),
        ('php-public-blobs', php + ['node-php/tests/public-blobs.php'], ROOT),
        ('php-public-evidence', php + ['node-php/tests/public-evidence.php'], ROOT),
        ('php-operator-cli', php + ['node-php/tests/operator-cli.php'], ROOT),
        ('php-read-only-diagnostics', php + ['node-php/tests/read-only-diagnostics.php'], ROOT),
        ('php-configuration', php + ['node-php/tests/configuration.php'], ROOT),
        ('php-publisher-status', php + ['node-php/tests/publisher-status.php'], ROOT),
        ('php-snapshot-chunks', php + ['node-php/tests/snapshot-chunks.php'], ROOT),
        ('php-snapshot-producer', php + ['node-php/tests/snapshot-producer.php'], ROOT),
        ('php-data-api', php + ['node-php/tests/data-api.php'], ROOT),
        ('php-search-unicode', php + ['node-php/tests/search-unicode.php'], ROOT),
        ('php-search-ranking', php + ['node-php/tests/search-ranking.php'], ROOT),
        ('php-server-search', php + ['node-php/tests/server-search.php'], ROOT),
        ('php-server-search-http', php + ['node-php/tests/server-search-http.php'], ROOT),
        ('php-service-id-api', php + ['node-php/tests/service-id-api.php'], ROOT),
        ('php-snapshot-membership-cli', php + ['node-php/tests/snapshot-membership-cli.php'], ROOT),
        ('php-static-analysis', php + ['node-php/vendor/bin/phpstan', 'analyse', 'node-php/src', '--level=8', '--no-progress'], ROOT),
        ('php-announcement-http', php + ['node-php/tests/announcement-http.php'], ROOT),
        ('php-candidate-announcement', php + ['node-php/tests/candidate-announcement.php'], ROOT),
        ('php-candidate-queue', php + ['node-php/tests/candidate-queue.php'], ROOT),
        ('php-candidate-budgets', php + ['node-php/tests/candidate-budgets.php'], ROOT),
        ('php-peer-source-groups', php + ['node-php/tests/peer-source-groups.php'], ROOT),
        ('php-direct-verification-controlled', php + ['node-php/tests/direct-verifier.php'], ROOT),
        ('php-storage-contract', php + ['node-php/tests/storage-conformance.php'], ROOT),
        ('php-storage-backup', php + ['node-php/tests/storage-backup.php'], ROOT),
        ('php-storage-archive', php + ['node-php/tests/storage-archive.php'], ROOT),
        ('php-storage-crash-recovery', php + ['node-php/tests/storage-crash.php'], ROOT),
        ('php-storage-format-migration', php + ['node-php/tests/storage-format-migration.php'], ROOT),
        ('php-tor-transport-controlled', php + ['node-php/tests/tor-transport.php'], ROOT),
        ('php-manifest-installer', php + ['node-php/tests/manifest-installer.php'], ROOT),
        ('php-manifest-drafts', php + ['node-php/tests/manifest-drafts.php'], ROOT),
        ('php-peer-sync-controlled', php + ['node-php/tests/peer-sync.php'], ROOT),
        ('php-protected-signer', php + ['node-php/tests/protected-signer.php'], ROOT),
        ('php-identity-sequences', php + ['node-php/tests/identity-sequences.php'], ROOT),
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
        'profile': 'DRAFT_CORE_UNIT_SUITES',
        'status': 'PASS' if passed else 'FAIL',
        'generated_at': datetime.now(timezone.utc).isoformat(),
        'shared_fixture_count': sum(len(json.loads(path.read_text()).get('cases',[])) for path in
                                    (ROOT / 'protocol/test-vectors').glob('*.json')),
        'php_container': args.php_image if args.php_container else None,
        'checks': results,
        'limitations': ['Not CORE-V1 conformance', 'Not browser compatibility evidence',
                        'Tor transport tested only through controlled loopback SOCKS; not live Tor or actual Tor Browser/Android; physical power-loss not established',
                        'No production release approval'],
    }
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + '\n')
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
