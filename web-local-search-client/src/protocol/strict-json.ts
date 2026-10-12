export type JsonValue = null | boolean | number | string | JsonValue[] | { [key: string]: JsonValue };

const MAX_SAFE_INTEGER = 9007199254740991;

/** Bounded parser that rejects duplicates before constructing protocol objects. */
export function parseStrictValue(bytes: Uint8Array, maxBytes = 16384): JsonValue {
  if (!Number.isSafeInteger(maxBytes) || maxBytes < 1 || maxBytes > 1048576) throw new Error('DOCUMENT_TOO_LARGE');
  if (bytes.byteLength > maxBytes) throw new Error('DOCUMENT_TOO_LARGE');
  if (bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) throw new Error('ENCODING_INVALID');
  let source: string;
  try { source = new TextDecoder('utf-8', { fatal: true }).decode(bytes); }
  catch { throw new Error('ENCODING_INVALID'); }
  let cursor = 0;
  const fail = (code = 'JSON_INVALID'): never => { throw new Error(code); };
  const whitespace = () => { while (/^[\t\n\r ]$/.test(source[cursor] ?? '')) cursor++; };
  const string = (): string => {
    const begin = cursor++;
    let escaped = false;
    while (cursor < source.length) {
      const character = source[cursor++];
      if (!escaped && character === '"') {
        let value: unknown;
        try { value = JSON.parse(source.slice(begin, cursor)); }
        catch { return fail(); }
        if (typeof value !== 'string') return fail();
        let length = 0;
        for (const scalar of value) {
          const code = scalar.codePointAt(0)!;
          if (code >= 0xd800 && code <= 0xdfff) return fail('ENCODING_INVALID');
          if (++length > 4096) return fail('SCHEMA_INVALID');
        }
        return value;
      }
      if (!escaped && character === '\\') escaped = true;
      else escaped = false;
    }
    return fail();
  };
  const value = (depth: number): JsonValue => {
    whitespace();
    const character = source[cursor];
    if (character === '"') return string();
    if (character === '{' || character === '[') {
      if (depth > 16) return fail('JSON_DEPTH_EXCEEDED');
      cursor++;
      whitespace();
      if (character === '{') {
        const object: { [key: string]: JsonValue } = Object.create(null) as { [key: string]: JsonValue };
        const keys = new Set<string>();
        if (source[cursor] === '}') { cursor++; return object; }
        while (true) {
          whitespace();
          if (source[cursor] !== '"') return fail();
          const key = string();
          if (key.includes('\0')) return fail('ENCODING_INVALID');
          if (keys.has(key)) return fail('JSON_DUPLICATE_KEY');
          if (keys.size >= 128) return fail('SCHEMA_INVALID');
          keys.add(key);
          whitespace();
          if (source[cursor++] !== ':') return fail();
          object[key] = value(depth + 1);
          whitespace();
          const separator = source[cursor++];
          if (separator === '}') return object;
          if (separator !== ',') return fail();
        }
      }
      const array: JsonValue[] = [];
      if (source[cursor] === ']') { cursor++; return array; }
      while (true) {
        if (array.length >= 256) return fail('SCHEMA_INVALID');
        array.push(value(depth + 1));
        whitespace();
        const separator = source[cursor++];
        if (separator === ']') return array;
        if (separator !== ',') return fail();
      }
    }
    for (const [token, result] of [['true', true], ['false', false], ['null', null]] as const) {
      if (source.startsWith(token, cursor)) { cursor += token.length; return result; }
    }
    const match = /^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/.exec(source.slice(cursor));
    if (match === null) return fail();
    const token = match[0];
    cursor += token.length;
    if (/[.eE]/.test(token)) return fail('SCHEMA_INVALID');
    const integer = Number(token);
    if (!Number.isSafeInteger(integer) || Math.abs(integer) > MAX_SAFE_INTEGER || Object.is(integer, -0)) return fail('SCHEMA_INVALID');
    return integer;
  };
  const parsed = value(1);
  whitespace();
  if (cursor !== source.length) return fail();
  return parsed;
}

export function parseStrictJson(bytes: Uint8Array, maxBytes = 16384): { [key: string]: JsonValue } {
  const result = parseStrictValue(bytes, maxBytes);
  if (result === null || typeof result !== 'object' || Array.isArray(result)) throw new Error('SCHEMA_INVALID');
  return result;
}
