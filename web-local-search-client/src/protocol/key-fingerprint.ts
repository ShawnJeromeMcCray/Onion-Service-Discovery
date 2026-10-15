import { sha256 } from '@noble/hashes/sha2.js';
import { publicKeyBytes } from './encoding.ts';

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const DOMAIN = new TextEncoder().encode('Onion Service Discovery/key-fingerprint/v1\0');

/**
 * Human-comparable key fingerprint v1 (decision D-025): SHA-256 over the domain, one zero byte and the
 * raw 32-byte Ed25519 key; the first 150 bits as Base32 in six groups of five. Long enough that no one
 * can make another key match; it identifies a key, never a person or the Tor key of an onion address.
 */
export function keyFingerprint(publicKey: string): string {
  const key = publicKeyBytes(publicKey);
  const input = new Uint8Array(DOMAIN.length + key.length); input.set(DOMAIN); input.set(key, DOMAIN.length);
  const digest = sha256(input); let text = ''; let buffer = 0; let bits = 0;
  for (const byte of digest) {
    buffer = ((buffer << 8) | byte) & 0xffff; bits += 8;
    while (bits >= 5 && text.length < 30) { bits -= 5; text += ALPHABET[(buffer >> bits) & 31]; }
    if (text.length === 30) break;
  }
  return text.match(/.{5}/g)!.join('-');
}
