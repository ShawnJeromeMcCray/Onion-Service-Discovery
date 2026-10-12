#!/usr/bin/env python3
"""Offline independent CLI; no database, UI or Tor dependencies."""
import argparse
import json
from pathlib import Path

from canonical_json import canonical_json
from identity_deriver import derive_identities
from record_verifier import verify_wire, verify_record
from strict_json import parse_strict_json


def bounded_file(name, maximum):
    if '://' in name:
        raise ValueError('INPUT_FILE_UNAVAILABLE')
    try:
        with Path(name).open('rb') as stream:
            data = stream.read(maximum + 1)
    except OSError:
        raise ValueError('INPUT_FILE_UNAVAILABLE') from None
    if len(data) > maximum:
        raise ValueError('DOCUMENT_TOO_LARGE')
    return data


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['validate', 'validate-manifest', 'manifest:verify', 'protocol:inspect', 'protocol:hash', 'hash', 'canonicalize', 'protocol:canonicalize', 'apply-sequence', 'protocol:apply-sequence'])
    parser.add_argument('file')
    parser.add_argument('--json', action='store_true')
    parser.add_argument('--expected-onion')
    args = parser.parse_args()
    try:
        if args.command in ('canonicalize', 'protocol:canonicalize'):
            data = parse_strict_json(bounded_file(args.file, 65536), 65536)
            output = {'status': 'PASS', 'canonical_hex': canonical_json(data).encode().hex()}
        elif args.command in ('apply-sequence', 'protocol:apply-sequence'):
            data = parse_strict_json(bounded_file(args.file, 1048576), 1048576)
            if not isinstance(data.get('records'), list) or len(data['records']) > 256:
                raise ValueError('SCHEMA_INVALID')
            records = [verify_record(record) for record in data['records']]
            output = {'status': 'PASS', 'state': derive_identities(records, data.get('max_records', 100000))}
        else:
            record = verify_wire(bounded_file(args.file, 65536), args.expected_onion)
            if args.command in ('validate-manifest', 'manifest:verify') and record['type'] != 'service_manifest':
                raise ValueError('SCHEMA_INVALID')
            output = {'status': 'PASS', 'record_type': record['type'], 'content_hash': record['content_hash'], 'signature_valid': True, 'directly_observed': False}
        print(json.dumps(output, ensure_ascii=False, separators=(',', ':')))
        return 0
    except ValueError as error:
        # Errors are stable codes from parser/validator; raw input never appears.
        print(json.dumps({'status': 'FAIL', 'error': str(error)}))
        return 3


if __name__ == '__main__':
    raise SystemExit(main())
