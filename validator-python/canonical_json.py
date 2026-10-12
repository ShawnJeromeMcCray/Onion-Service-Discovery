"""Independent RFC 8785 serializer for the schema's bounded integer subset."""
import json


def canonical_json(value, depth=1):
    if isinstance(value, (dict, list)):
        if depth > 16:
            raise ValueError('JSON_DEPTH_EXCEEDED')
        if isinstance(value, list):
            if len(value) > 256:
                raise ValueError('SCHEMA_INVALID')
            return '[' + ','.join(canonical_json(item, depth + 1) for item in value) + ']'
        if len(value) > 128 or any(type(key) is not str for key in value):
            raise ValueError('SCHEMA_INVALID')
        try:
            keys = sorted(value, key=lambda key: key.encode('utf-16be'))
        except UnicodeError:
            raise ValueError('ENCODING_INVALID') from None
        return '{' + ','.join(canonical_json(key, depth + 1) + ':' + canonical_json(value[key], depth + 1) for key in keys) + '}'
    if type(value) is int and abs(value) > 9007199254740991:
        raise ValueError('SCHEMA_INVALID')
    if isinstance(value, str):
        if any(0xd800 <= ord(character) <= 0xdfff for character in value):
            raise ValueError('ENCODING_INVALID')
        if len(value) > 4096:
            raise ValueError('SCHEMA_INVALID')
    if value is not None and type(value) not in (str, int, bool):
        raise ValueError('SCHEMA_INVALID')
    return json.dumps(value, ensure_ascii=False, separators=(',', ':'))
