/** SHA3-256 provider boundary. The browser adapter uses the pinned mature SHA3 implementation. */
export type Sha3Provider = (input: Uint8Array) => Uint8Array;

/** Strict canonical wire hostname validation; never normalizes signed input. */
export function validateOnionAddress(hostname: unknown, sha3: Sha3Provider): string {
  if (typeof hostname !== 'string' || hostname.length !== 62 || !/^[a-z2-7]{56}\.onion$/.test(hostname)) {
    throw new Error('ADDRESS_INVALID');
  }
  const alphabet = 'abcdefghijklmnopqrstuvwxyz234567';
  const decoded = new Uint8Array(35);
  let buffer = 0;
  let bits = 0;
  let cursor = 0;
  for (const character of hostname.slice(0, 56)) {
    const value = alphabet.indexOf(character);
    if (value < 0) throw new Error('ADDRESS_INVALID');
    buffer = (buffer << 5) | value;
    bits += 5;
    if (bits >= 8) {
      bits -= 8;
      decoded[cursor++] = (buffer >>> bits) & 255;
      buffer &= (1 << bits) - 1;
    }
  }
  if (cursor !== 35 || bits !== 0 || decoded[34] !== 3) throw new Error('ADDRESS_INVALID');
  const prefix = new TextEncoder().encode('.onion checksum');
  const message = new Uint8Array(prefix.length + 33);
  message.set(prefix);
  message.set(decoded.subarray(0, 32), prefix.length);
  message[message.length - 1] = 3;
  const digest = sha3(message);
  if (!(digest instanceof Uint8Array) || digest.length !== 32) throw new Error('HASH_INVALID');
  if (digest[0] !== decoded[32] || digest[1] !== decoded[33]) throw new Error('ADDRESS_INVALID');
  return hostname;
}
