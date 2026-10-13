#!/usr/bin/env python3
"""Run finite draft models with a checksum-pinned development-only TLC tool."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile
import urllib.request

ROOT = Path(__file__).resolve().parents[1]

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--jar', type=Path)
    parser.add_argument('--report', type=Path)
    parser.add_argument('--membership-only', action='store_true', help='Check only the isolated offline membership model and its assertion mutation')
    args = parser.parse_args()
    pin = json.loads((ROOT / 'formal/toolchain.json').read_text())
    jar = args.jar or ROOT / '.tools/tla2tools.jar'
    if not jar.exists():
        jar.parent.mkdir(parents=True, exist_ok=True)
        with urllib.request.urlopen(pin['url'], timeout=60) as response:
            data = response.read(pin['bytes'] + 1)
        if len(data) != pin['bytes'] or hashlib.sha256(data).hexdigest() != pin['sha256']:
            raise SystemExit('Formal tool checksum mismatch')
        jar.write_bytes(data)
    if jar.stat().st_size != pin['bytes'] or hashlib.sha256(jar.read_bytes()).hexdigest() != pin['sha256']:
        raise SystemExit('Formal tool checksum mismatch')
    results = []
    (ROOT / '.tools').mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='osd-formal-', dir=ROOT / '.tools') as temp:
        names = ['SnapshotMembership', 'UnsafeAssertionMutation']
        if not args.membership_only:
            names = ['OnionServiceDiscovery', 'TransportFailure', 'UnsafeRollbackMutation'] + names
        for name in names:
            config = ROOT / 'formal' / (name + '.cfg')
            module = 'SnapshotMembership' if name in ['SnapshotMembership', 'UnsafeAssertionMutation'] else 'OnionServiceDiscovery'
            invariant = 'NoUnprovedInclusion' if name == 'UnsafeAssertionMutation' else 'NoRevisionDowngrade'
            if name == 'UnsafeRollbackMutation':
                config = Path(temp) / (name + '.cfg')
                config.write_text((ROOT / 'formal/OnionServiceDiscovery.cfg').read_text().replace('UnsafeRollback = FALSE', 'UnsafeRollback = TRUE'))
            if name == 'UnsafeAssertionMutation':
                config = Path(temp) / (name + '.cfg')
                config.write_text((ROOT / 'formal/SnapshotMembership.cfg').read_text().replace('UnsafeAssertion = FALSE', 'UnsafeAssertion = TRUE'))
            process = subprocess.run(['java', '-XX:+UseParallelGC', '-Xmx1536m', '-cp', str(jar.resolve()), 'tlc2.TLC', '-seed', '8785', '-fp', '0', '-workers', '2', '-metadir', str(Path(temp) / name), '-config', str(config), f'formal/{module}.tla'], cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, timeout=900)
            expected_failure = name in ['UnsafeRollbackMutation', 'UnsafeAssertionMutation']
            passed = (process.returncode == 12 and f'Invariant {invariant} is violated' in process.stdout) if expected_failure else (process.returncode == 0 and 'Model checking completed. No error has been found.' in process.stdout)
            results.append({'model': name, 'expected': f'{invariant} rejection' if expected_failure else 'all invariants hold', 'passed': passed, 'exit_code': process.returncode, 'output': process.stdout})
            print(name + ': ' + ('PASS' if passed else 'FAIL'), flush=True)
            if not passed:
                print(process.stdout)
                raise SystemExit(1)
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps({'status': 'FINITE_DRAFT_MODEL_PASS', 'human_review': 'OPEN', 'tool_sha256': pin['sha256'], 'results': results}, indent=2) + '\n')

if __name__ == '__main__':
    main()
