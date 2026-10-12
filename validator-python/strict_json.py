"""Independent bounded JSON input validator, prior to record-specific schemas."""

import json

MAX_INTEGER = 9007199254740991


class ProtocolInputError(ValueError):
    pass


def parse_strict_json(data: bytes, max_bytes: int = 16384) -> dict:
    if type(max_bytes) is not int or not 1 <= max_bytes <= 1048576 or len(data) > max_bytes:
        raise ProtocolInputError('DOCUMENT_TOO_LARGE')
    if data.startswith(b'\xef\xbb\xbf'):
        raise ProtocolInputError('ENCODING_INVALID')
    try:
        source = data.decode('utf-8', errors='strict')
    except UnicodeError:
        raise ProtocolInputError('ENCODING_INVALID') from None

    def pairs(items):
        result = {}
        for key, value in items:
            if '\0' in key:
                raise ProtocolInputError('ENCODING_INVALID')
            if key in result:
                raise ProtocolInputError('JSON_DUPLICATE_KEY')
            if len(result) >= 128:
                raise ProtocolInputError('SCHEMA_INVALID')
            result[key] = value
        return result

    def integer(token):
        if len(token) > 17 or token == '-0':
            raise ProtocolInputError('SCHEMA_INVALID')
        value = int(token)
        if abs(value) > MAX_INTEGER:
            raise ProtocolInputError('SCHEMA_INVALID')
        return value

    def noninteger(_token):
        raise ProtocolInputError('SCHEMA_INVALID')

    def constant(_token):
        raise ProtocolInputError('JSON_INVALID')

    # Bound parser recursion before the native decoder allocates nested objects.
    depth = 0
    inside_string = False
    escaped = False
    for character in source:
        if inside_string:
            if escaped:
                escaped = False
            elif character == '\\':
                escaped = True
            elif character == '"':
                inside_string = False
        elif character == '"':
            inside_string = True
        elif character in '{[':
            depth += 1
            if depth > 16:
                raise ProtocolInputError('JSON_DEPTH_EXCEEDED')
        elif character in '}]':
            depth -= 1

    try:
        result = json.loads(source, object_pairs_hook=pairs, parse_int=integer,
                            parse_float=noninteger, parse_constant=constant)
    except ProtocolInputError:
        raise
    except (ValueError, RecursionError):
        raise ProtocolInputError('JSON_INVALID') from None

    def walk(value, depth):
        if isinstance(value, (dict, list)):
            if depth > 16:
                raise ProtocolInputError('JSON_DEPTH_EXCEEDED')
            if isinstance(value, list):
                if len(value) > 256:
                    raise ProtocolInputError('SCHEMA_INVALID')
                children = value
            else:
                for key in value:
                    walk(key, depth + 1)
                children = value.values()
            for child in children:
                walk(child, depth + 1)
        elif isinstance(value, str):
            if any(0xd800 <= ord(character) <= 0xdfff for character in value):
                raise ProtocolInputError('ENCODING_INVALID')
            if len(value) > 4096:
                raise ProtocolInputError('SCHEMA_INVALID')

    walk(result, 1)
    if not isinstance(result, dict):
        raise ProtocolInputError('SCHEMA_INVALID')
    return result
