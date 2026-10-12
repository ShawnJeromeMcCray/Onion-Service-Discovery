<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Crypto;

use OnionServiceDiscovery\Protocol\ProtocolException;

/** RFC 8785 serialization for the protocol's bounded integer-only subset. */
final class CanonicalJson
{
    public static function encode(mixed $value, int $depth = 1): string
    {
        if ($value instanceof \stdClass) {
            if ($depth > 16) {
                throw new ProtocolException('JSON_DEPTH_EXCEEDED');
            }
            $properties = get_object_vars($value);
            if (count($properties) > 128) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
            $keys = array_map(static fn (string|int $key): string => (string) $key, array_keys($properties));
            usort($keys, static fn (string $a, string $b): int => strcmp(self::utf16($a), self::utf16($b)));
            $parts = [];
            foreach ($keys as $key) {
                $parts[] = self::encode($key, $depth + 1) . ':' . self::encode($properties[$key], $depth + 1);
            }
            return '{' . implode(',', $parts) . '}';
        }
        if (is_array($value)) {
            if ($depth > 16) {
                throw new ProtocolException('JSON_DEPTH_EXCEEDED');
            }
            if (!array_is_list($value) || count($value) > 256) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
            return '[' . implode(',', array_map(static fn (mixed $item): string => self::encode($item, $depth + 1), $value)) . ']';
        }
        if (is_int($value)) {
            if ($value < -9007199254740991 || $value > 9007199254740991) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
            return (string) $value;
        }
        if (is_string($value)) {
            $length = preg_match_all('/./us', $value);
            if ($length === false) {
                throw new ProtocolException('ENCODING_INVALID');
            }
            if ($length > 4096) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
        } elseif (!is_bool($value) && $value !== null) {
            throw new ProtocolException('SCHEMA_INVALID');
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        } catch (\JsonException) {
            throw new ProtocolException('ENCODING_INVALID');
        }
    }

    private static function utf16(string $value): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new ProtocolException('ENCODING_INVALID');
        }
        $bytes = iconv('UTF-8', 'UTF-16BE', $value);
        if ($bytes === false) {
            throw new ProtocolException('ENCODING_INVALID');
        }
        return $bytes;
    }
}
