import type { JsonValue } from './strict-json.ts';

/** RFC 8785 output for safe integers; floating-point values are outside the wire schemas. */
export function canonicalJson(value: JsonValue, depth = 1): string {
  if (value !== null && typeof value === 'object') {
    if (depth > 16) throw new Error('JSON_DEPTH_EXCEEDED');
    if (Array.isArray(value)) {
      if (value.length > 256) throw new Error('SCHEMA_INVALID');
      return '[' + value.map(item => canonicalJson(item, depth + 1)).join(',') + ']';
    }
    const prototype: unknown = Object.getPrototypeOf(value);
    if (prototype !== null && prototype !== Object.prototype) throw new Error('SCHEMA_INVALID');
    const keys = Object.keys(value).sort(); // ECMAScript compares UTF-16 code units.
    if (keys.length > 128) throw new Error('SCHEMA_INVALID');
    return '{' + keys.map(key => canonicalJson(key, depth + 1) + ':' + canonicalJson(value[key]!, depth + 1)).join(',') + '}';
  }
  if (typeof value === 'number' && (!Number.isSafeInteger(value) || Object.is(value, -0))) throw new Error('SCHEMA_INVALID');
  if (typeof value === 'string') {
    let length = 0;
    for (const scalar of value) {
      const point = scalar.codePointAt(0)!;
      if (point >= 0xd800 && point <= 0xdfff) throw new Error('ENCODING_INVALID');
      if (++length > 4096) throw new Error('SCHEMA_INVALID');
    }
  }
  if (value !== null && !['string', 'number', 'boolean'].includes(typeof value)) throw new Error('SCHEMA_INVALID');
  return JSON.stringify(value);
}
