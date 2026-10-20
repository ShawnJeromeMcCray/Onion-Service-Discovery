"""Language-independent offline black-box suite. Imports no implementation code."""
from __future__ import annotations

import argparse
import base64
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
PHP_IMAGE = 'php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3'


def request(operation, data, **options):
    encoded = base64.b64encode(data).decode('ascii')
    return {'operation': operation, 'input_chunks': [encoded[index:index + 3072] for index in range(0, len(encoded), 3072)], **options}


def cases():
    result = []
    vectors = ROOT / 'protocol/test-vectors'
    for fixture in json.loads((vectors / 'onion-addresses.json').read_text())['cases']:
        expected = {'status': 'PASS', 'address': fixture['input']} if fixture['valid'] else {'status': 'FAIL', 'error': 'ADDRESS_INVALID'}
        result.append((fixture['id'], request('address', fixture['input'].encode()), expected))
    for fixture in json.loads((vectors / 'strict-json.json').read_text())['cases']:
        expected = {'status': 'FAIL', 'error': fixture['error']} if fixture.get('error') else {'status': 'PASS', 'value': fixture['expected']}
        result.append((fixture['id'], request('strict-json', base64.b64decode(fixture['input_base64']), max_bytes=fixture.get('max_bytes', 16384)), expected))
    for fixture in json.loads((vectors / 'signed-records.json').read_text())['cases']:
        options = {'expected_onion': fixture['expected_onion']} if 'expected_onion' in fixture else {}
        expected = {'status': 'FAIL', 'error': fixture['error']} if fixture.get('error') else {'status': 'PASS', 'hash': fixture['expected_hash'], 'wire': fixture['expected_wire'], 'payload_hex': fixture['expected_payload_hex'], 'message_hex': fixture['expected_message_hex']}
        result.append((fixture['id'], request('validate', base64.b64decode(fixture['input_base64']), **options), expected))
    for fixture in json.loads((vectors / 'canonical-json.json').read_text())['cases']:
        result.append((fixture['id'], request('canonicalize', fixture['input'].encode()), {'status': 'PASS', 'canonical_hex': fixture['expected_hex']}))
    for fixture in json.loads((vectors / 'identity-sequences.json').read_text())['cases']:
        sequence = {'records': fixture['records']}
        if 'max_records' in fixture:
            sequence['max_records'] = fixture['max_records']
        expected = {'status': 'FAIL', 'error': fixture['error']} if fixture.get('error') else {'status': 'PASS', 'state': fixture['expected']}
        result.append((fixture['id'], request('sequence', json.dumps(sequence, ensure_ascii=False, separators=(',', ':')).encode()), expected))
    for fixture in json.loads((vectors / 'snapshot-membership.json').read_text())['cases']:
        expected = {'status': 'FAIL', 'error': fixture['error']} if fixture.get('error') else {'status': 'PASS', 'result': fixture['expected']}
        result.append((fixture['id'], request('snapshot-membership', json.dumps(fixture['input'], ensure_ascii=False, separators=(',', ':')).encode()), expected))
    for fixture in json.loads((vectors / 'service-history.json').read_text())['cases']:
        expected = {'status': 'FAIL', 'error': fixture['error']} if fixture.get('error') else {'status': 'PASS', 'result': fixture['expected']}
        result.append((fixture['id'], request('service-history', json.dumps(fixture['input'], ensure_ascii=False, separators=(',', ':')).encode()), expected))
    return result


def batches(fixtures):
    """Each adapter run accepts at most 256 requests and 1 MiB of stdin; larger suites run in batches."""
    batch, size = [], 0
    for fixture in fixtures:
        line = json.dumps(fixture[1], separators=(',', ':')) + '\n'
        length = len(line.encode())
        if length > 1048576:
            raise RuntimeError('One fixture exceeds the adapter budget')
        if batch and (len(batch) == 256 or size + length > 1048576):
            yield batch
            batch, size = [], 0
        batch.append((fixture, line))
        size += length
    if batch:
        yield batch


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php-image', choices=['php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3', 'php@sha256:1d2cf2b7d715b5b3aa959f0150ccbcc7b71e0181b637e443abf40a7fb56bf79a', 'php@sha256:aafe21201943a8a6e497ddfb471e2ce68ada76adede38c7d61fede8918b24319'], default=PHP_IMAGE)
    parser.add_argument('--php-container', action='store_true')
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    fixtures = cases()
    runs = list(batches(fixtures))
    php = ['php', 'node-php/bin/conformance-adapter.php']
    if args.php_container:
        php = ['docker', 'run', '--rm', '-i', '--network', 'none', '--mount', f'type=bind,src={ROOT},dst=/app,readonly', '--workdir', '/app', args.php_image, 'php', 'node-php/bin/conformance-adapter.php']
    commands = {'PHP': php, 'TypeScript': ['node', 'web-local-search-client/tools/conformance-adapter.mjs'], 'Python': [sys.executable, 'validator-python/conformance_adapter.py']}
    results = []
    for implementation, command in commands.items():
        checks = []
        try:
            for batch in runs:
                completed = subprocess.run(command, input=''.join(line for _, line in batch), text=True, capture_output=True, cwd=ROOT, timeout=120)
                lines = completed.stdout.splitlines()
                if completed.returncode != 0 or len(lines) != len(batch):
                    raise RuntimeError('Adapter exited or produced incorrect response count')
                for ((ident, _, expected), _), line in zip(batch, lines):
                    actual = json.loads(line)
                    checks.append({'id': ident, 'status': 'PASS' if actual == expected else 'FAIL'})
        except (OSError, ValueError, RuntimeError, subprocess.TimeoutExpired) as error:
            checks.append({'id': 'ADAPTER_EXECUTION', 'status': 'FAIL', 'reason': type(error).__name__})
        passed = sum(check['status'] == 'PASS' for check in checks)
        failed = sum(check['status'] == 'FAIL' for check in checks)
        print(f'{implementation}: {passed} PASS, {failed} FAIL')
        for check in checks:
            if check['status'] == 'FAIL':
                print(f"FAIL {implementation}: {check['id']}", file=sys.stderr)
        results.append({'implementation': implementation, 'pass': passed, 'fail': failed, 'skip': 0, 'checks': checks})
    passed = all(result['fail'] == 0 and result['pass'] == len(fixtures) for result in results)
    report = {'profile': 'DRAFT_CORE', 'status': 'PASS' if passed else 'FAIL', 'generated_at': datetime.now(timezone.utc).isoformat(),
              'fixture_count_per_implementation': len(fixtures), 'adapter_batches': len(runs), 'fixture_hashes': {path.name: hashlib.sha256(path.read_bytes()).hexdigest() for path in sorted((ROOT / 'protocol/test-vectors').glob('*.json'))},
              'results': results, 'limits': ['Not Protocol Stable', 'No NODE/CLIENT network profile', 'Human crypto approval remains open']}
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + '\n')
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
