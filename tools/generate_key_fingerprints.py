"""Generate the D-025 key fingerprint vectors with an implementation independent of the PHP and browser code.

Fingerprint v1: SHA-256 over the ASCII bytes "Onion Service Discovery/key-fingerprint/v1", one zero
byte and the 32 raw Ed25519 public key bytes; the first 150 bits as RFC 4648 Base32 (uppercase, no
padding), written as six groups of five characters joined by "-". `--check` fails when the committed
vector file differs.
"""
from pathlib import Path
import base64
import hashlib
import json
import sys

ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / 'protocol/test-vectors/key-fingerprint-v1.json'
DOMAIN = b'Onion Service Discovery/key-fingerprint/v1\x00'
DEFINITION = ('SHA-256 over the ASCII bytes "Onion Service Discovery/key-fingerprint/v1", one zero byte and the '
              '32 raw Ed25519 public key bytes; the first 150 bits as RFC 4648 Base32 (uppercase, no padding), '
              'written as six groups of five characters joined by "-" (decision D-025).')


def public_key_bytes(encoded: str) -> bytes:
    if not encoded.startswith('ed25519:') or len(encoded) != 51:
        raise ValueError('PUBLIC_KEY_INVALID')
    raw = base64.urlsafe_b64decode(encoded[8:] + '=')
    if len(raw) != 32 or base64.urlsafe_b64encode(raw).decode().rstrip('=') != encoded[8:]:
        raise ValueError('PUBLIC_KEY_INVALID')
    return raw


def fingerprint(encoded: str) -> str:
    text = base64.b32encode(hashlib.sha256(DOMAIN + public_key_bytes(encoded)).digest()).decode()[:30]
    return '-'.join(text[index:index + 5] for index in range(0, 30, 5))


def keys() -> list[str]:
    # Public keys already used by the shared signed-record vectors, so every case is a valid curve point.
    found: set[str] = set()
    def walk(value: object) -> None:
        if isinstance(value, dict):
            for item in value.values(): walk(item)
        elif isinstance(value, list):
            for item in value: walk(item)
        elif isinstance(value, str) and value.startswith('ed25519:') and len(value) == 51:
            found.add(value)
    walk(json.loads((ROOT / 'protocol/test-vectors/signed-records.json').read_text(encoding='utf-8')))
    walk(json.loads((ROOT / 'protocol/test-vectors/browser-dataset.json').read_text(encoding='utf-8')))
    return sorted(found)[:12]


def rejected() -> list[dict[str, str]]:
    # Encodings that are not strict Ed25519 public points have no fingerprint (PUBLIC_KEY_INVALID), as in record verification.
    weak = [('identity', b'\x01' + bytes(31)), ('order-four', bytes(32)), ('order-two', b'\xec' + b'\xff' * 30 + b'\x7f'), ('noncanonical-y', b'\xed' + b'\xff' * 30 + b'\x7f')]
    return [{'id': ident, 'public_key': 'ed25519:' + base64.urlsafe_b64encode(raw).decode().rstrip('='), 'error': 'PUBLIC_KEY_INVALID'} for ident, raw in weak]


def render() -> str:
    cases = [{'public_key': key, 'fingerprint': fingerprint(key)} for key in keys()]
    return json.dumps({'format_version': 1, 'definition': DEFINITION, 'cases': cases, 'rejected': rejected()}, indent=1, ensure_ascii=False) + '\n'


def main() -> int:
    text = render()
    if sys.argv[1:] == ['--check']:
        if not TARGET.is_file() or TARGET.read_text(encoding='utf-8') != text:
            print('Stale key fingerprint vectors: run python3 tools/generate_key_fingerprints.py')
            return 1
        print('PASS: key fingerprint vectors match ' + str(len(json.loads(text)['cases'])) + ' cases')
        return 0
    TARGET.write_text(text, encoding='utf-8')
    return 0


if __name__ == '__main__':
    sys.exit(main())
