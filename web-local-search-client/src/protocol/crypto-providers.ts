import { sha3_256 } from '@noble/hashes/sha3.js';
import type { CryptoProviders } from './record-verifier.ts';

export function browserCryptoProviders(): CryptoProviders {
  if (globalThis.crypto?.subtle === undefined) throw new Error('CRYPTO_UNAVAILABLE');
  return { subtle: globalThis.crypto.subtle, sha3: input => sha3_256(input) };
}
