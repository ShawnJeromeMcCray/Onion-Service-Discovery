"""Bounded offline JSON-lines adapter for an external conformance runner."""
import base64
import json
import sys

from canonical_json import canonical_json
from identity_deriver import derive_identities
from onion_address import validate_onion_address
from record_format import message, payload
from record_verifier import verify_wire, verify_record
from strict_json import parse_strict_json


def operation(request):
    if not isinstance(request, dict) or set(request) - {'operation', 'input_chunks', 'expected_onion', 'max_bytes'}:
        raise ValueError('SCHEMA_INVALID')
    try:
        chunks = request['input_chunks']
        if type(chunks) is not list or any(type(chunk) is not str for chunk in chunks):
            raise ValueError('ENCODING_INVALID')
        encoded = ''.join(chunks)
        data = base64.b64decode(encoded, validate=True)
        if base64.b64encode(data).decode() != encoded:
            raise ValueError('ENCODING_INVALID')
    except (KeyError, ValueError, TypeError):
        raise ValueError('ENCODING_INVALID') from None
    if request.get('operation') == 'validate':
        verified = verify_wire(data, request.get('expected_onion'))
        record = parse_strict_json(data, 65536)
        return {'status': 'PASS', 'hash': verified['content_hash'], 'wire': verified['canonical_wire'],
                'payload_hex': canonical_json(payload(record)).encode().hex(), 'message_hex': message(record).hex()}
    if request.get('operation') == 'address':
        return {'status': 'PASS', 'address': validate_onion_address(data.decode('utf-8'))}
    if request.get('operation') == 'strict-json':
        return {'status': 'PASS', 'value': parse_strict_json(data, request.get('max_bytes', 16384))}
    if request.get('operation') == 'canonicalize':
        return {'status': 'PASS', 'canonical_hex': canonical_json(parse_strict_json(data, 65536)).encode().hex()}
    if request.get('operation') == 'sequence':
        fixture = parse_strict_json(data, 1048576)
        if type(fixture.get('records')) is not list:
            raise ValueError('SCHEMA_INVALID')
        records = [verify_record(record) for record in fixture['records']]
        return {'status': 'PASS', 'state': derive_identities(records, fixture.get('max_records', 100000))}
    raise ValueError('USAGE_ERROR')


total = 0
for index, line in enumerate(sys.stdin.buffer):
    total += len(line)
    if index >= 256 or total > 1048576 or len(line) > 262144:
        print(json.dumps({'status': 'FAIL', 'error': 'WORK_BUDGET_EXHAUSTED'}))
        raise SystemExit(3)
    try:
        output = operation(parse_strict_json(line, 262144))
    except ValueError as error:
        output = {'status': 'FAIL', 'error': str(error)}
    print(json.dumps(output, ensure_ascii=False, separators=(',', ':')))
