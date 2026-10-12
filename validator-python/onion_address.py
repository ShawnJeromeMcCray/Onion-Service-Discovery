"""Independent, standard-library-only Tor v3 address validator.

This module implements the Tor encoding specification directly and does not
import the PHP or TypeScript implementation. It is not a complete Protocol Core.
"""

from __future__ import annotations

import base64
import hashlib
import hmac
import re


def validate_onion_address(hostname: object) -> str:
    if not isinstance(hostname, str) or re.fullmatch(r"[a-z2-7]{56}\.onion", hostname) is None:
        raise ValueError("ADDRESS_INVALID")
    decoded = base64.b32decode(hostname[:-6].upper(), casefold=False)
    if len(decoded) != 35 or decoded[-1] != 3:
        raise ValueError("ADDRESS_INVALID")
    checksum = hashlib.sha3_256(b".onion checksum" + decoded[:32] + b"\x03").digest()[:2]
    if not hmac.compare_digest(checksum, decoded[32:34]):
        raise ValueError("ADDRESS_INVALID")
    return hostname
