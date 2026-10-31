import { sha3_256 } from '@noble/hashes/sha3.js';
import { sha256 } from '@noble/hashes/sha2.js';
import { ed25519 } from '@noble/curves/ed25519.js';
import type { CryptoProviders } from './record-verifier.ts';

/** Strict Ed25519 public point check: canonical encoding, not small order, torsion-free. */
export function isStrictEd25519Point(bytes: Uint8Array): boolean {
  if (bytes.length !== 32) return false;
  try { const point = ed25519.Point.fromBytes(bytes, false); return !point.isSmallOrder() && point.isTorsionFree(); }
  catch { return false; }
}

export function browserCryptoProviders(): CryptoProviders {
  // Cache only exact public point encodings that passed strict subgroup checks.
  // Message signatures are still verified on every call; this is not a trust cache.
  const validPoints = new Set<string>();
  const validateEd25519Point = (bytes: Uint8Array): boolean => {
    if (bytes.length !== 32) return false;
    const encoding = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    if (validPoints.has(encoding)) return true;
    if (!isStrictEd25519Point(bytes)) return false;
    if (validPoints.size >= 10000) validPoints.delete(validPoints.values().next().value!);
    validPoints.add(encoding); return true;
  };
  return {
    subtle: globalThis.crypto?.subtle ?? {
      async digest(algorithm: AlgorithmIdentifier, data: BufferSource): Promise<ArrayBuffer> {
        const name = typeof algorithm === 'string' ? algorithm : algorithm.name;
        if (name !== 'SHA-256') throw new Error('CRYPTO_ALGORITHM_UNSUPPORTED');
        const bytes = data instanceof ArrayBuffer ? new Uint8Array(data) : new Uint8Array(data.buffer, data.byteOffset, data.byteLength);
        return Uint8Array.from(sha256(bytes)).buffer;
      },
    },
    sha3: input => sha3_256(input),
    validateEd25519Point,
    async verifyEd25519(publicKey, signature, message): Promise<boolean> {
      if (!validateEd25519Point(publicKey) || !validateEd25519Point(signature.subarray(0, 32))) return false;
      try { return ed25519.verify(signature, message, publicKey, { zip215: false }); }
      catch { return false; }
    },
  };
}
