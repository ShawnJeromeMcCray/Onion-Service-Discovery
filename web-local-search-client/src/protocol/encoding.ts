export function encodeBytes(bytes: Uint8Array, prefix: string): string {
  let binary = '';
  for (const value of bytes) binary += String.fromCharCode(value);
  return prefix + btoa(binary).replaceAll('+', '-').replaceAll('/', '_').replace(/=+$/, '');
}

function decode(encoded: string, prefix: string, length: number, code: string): Uint8Array {
  if (!encoded.startsWith(prefix) || encoded.length !== prefix.length + Math.ceil(length * 8 / 6)) throw new Error(code);
  const value = encoded.slice(prefix.length);
  if (!/^[A-Za-z0-9_-]+$/.test(value)) throw new Error(code);
  let binary: string;
  try { binary = atob(value.replaceAll('-', '+').replaceAll('_', '/') + '='.repeat((4 - value.length % 4) % 4)); }
  catch { throw new Error(code); }
  const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
  if (bytes.length !== length || encodeBytes(bytes, prefix) !== encoded) throw new Error(code);
  return bytes;
}

export const publicKeyBytes = (key: string): Uint8Array => decode(key, 'ed25519:', 32, 'PUBLIC_KEY_INVALID');
export const signatureBytes = (signature: string): Uint8Array => decode(signature, 'ed25519sig:', 64, 'SIGNATURE_INVALID');
export function parseHash(value: string): string {
  if (value.length !== 71 || !/^sha256:[0-9a-f]{64}$/.test(value)) throw new Error('HASH_INVALID');
  return value;
}
