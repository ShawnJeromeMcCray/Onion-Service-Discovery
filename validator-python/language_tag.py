"""Independent semantic language validation over the shared draft RFC grammar."""
import re
from language_grammar import LANGUAGE_GRAMMAR, GRANDFATHERED_TAGS


def validate_language_tag(tag: str) -> None:
    if len(tag) > 35 or re.fullmatch(LANGUAGE_GRAMMAR, tag) is None:
        raise ValueError('SCHEMA_INVALID')
    if tag in GRANDFATHERED_TAGS or tag.startswith('x-'):
        return
    parts = tag.split('-')
    index = 1
    if len(parts[0]) <= 3:
        for _ in range(3):
            if index >= len(parts) or re.fullmatch('[a-z]{3}', parts[index]) is None:
                break
            index += 1
    if index < len(parts) and re.fullmatch('[a-z]{4}', parts[index]):
        index += 1
    if index < len(parts) and re.fullmatch('[a-z]{2}|[0-9]{3}', parts[index]):
        index += 1
    seen_variants = set()
    while index < len(parts) and re.fullmatch('[a-z0-9]{5,8}|[0-9][a-z0-9]{3}', parts[index]):
        if parts[index] in seen_variants:
            raise ValueError('SCHEMA_INVALID')
        seen_variants.add(parts[index])
        index += 1
    seen_extensions = set()
    while index < len(parts) and parts[index] != 'x':
        if parts[index] in seen_extensions:
            raise ValueError('SCHEMA_INVALID')
        seen_extensions.add(parts[index])
        index += 1
        while index < len(parts) and len(parts[index]) > 1:
            index += 1
