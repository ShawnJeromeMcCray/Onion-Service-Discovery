<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Crypto;

use OnionServiceDiscovery\Protocol\ProtocolException;

final class Encoding
{
    public static function encode(string $bytes, string $prefix): string
    {
        return $prefix . rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function publicKey(string $encoded): string
    {
        return self::decode($encoded, 'ed25519:', 32, 'PUBLIC_KEY_INVALID');
    }

    public static function signature(string $encoded): string
    {
        return self::decode($encoded, 'ed25519sig:', 64, 'SIGNATURE_INVALID');
    }

    public static function hash(string $encoded): string
    {
        if (preg_match('/\Asha256:[0-9a-f]{64}\z/D', $encoded) !== 1) {
            throw new ProtocolException('HASH_INVALID');
        }
        return $encoded;
    }

    private static function decode(string $encoded, string $prefix, int $length, string $error): string
    {
        $expected = (int) ceil($length * 8 / 6);
        if (!str_starts_with($encoded, $prefix) || strlen($encoded) !== strlen($prefix) + $expected) {
            throw new ProtocolException($error);
        }
        $value = substr($encoded, strlen($prefix));
        if (preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
            throw new ProtocolException($error);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false || strlen($decoded) !== $length || self::encode($decoded, $prefix) !== $encoded) {
            throw new ProtocolException($error);
        }
        return $decoded;
    }
}
