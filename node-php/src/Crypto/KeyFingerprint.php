<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Crypto;

/**
 * Human-comparable key fingerprint v1 (decision D-025): SHA-256 over the domain, one zero byte and the
 * raw 32-byte Ed25519 key; the first 150 bits as Base32 in six groups of five. It identifies a key,
 * never a person or the Tor key of an onion address.
 */
final class KeyFingerprint
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DOMAIN = "Onion Service Discovery/key-fingerprint/v1\0";

    public static function of(string $publicKey): string
    {
        $digest = hash('sha256', self::DOMAIN . Encoding::publicKey($publicKey), true);
        $text = ''; $buffer = 0; $bits = 0;
        foreach (str_split($digest) as $byte) {
            $buffer = (($buffer << 8) | ord($byte)) & 0xffff; $bits += 8;
            while ($bits >= 5 && strlen($text) < 30) { $bits -= 5; $text .= self::ALPHABET[($buffer >> $bits) & 31]; }
            if (strlen($text) === 30) { break; }
        }
        return implode('-', str_split($text, 5));
    }
}
