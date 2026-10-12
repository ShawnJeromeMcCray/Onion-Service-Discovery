<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

/** Internal/export codec, distinct from the much smaller protocol wire limits. */
final class StorageJson
{
    /**
     * @param array<array-key, mixed> $value
     */
    public static function encode(array $value): string
    {
        $sorted = self::sort($value, 1);
        try {
            return json_encode($sorted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        } catch (\JsonException) {
            throw new StorageException('STORAGE_ENCODING_INVALID');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function decode(string $bytes, int $maximum): array
    {
        if (strlen($bytes) > $maximum) {
            throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
        }
        try {
            $value = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        if (!is_array($value)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        // Internal files must equal the canonical representation that we write.
        if (self::encode($value) !== $bytes) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        return $value;
    }

    private static function sort(mixed $value, int $depth): mixed
    {
        if ($depth > 32) {
            throw new StorageException('STORAGE_ENCODING_INVALID');
        }
        if (is_array($value)) {
            if (count($value) > 1000000) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            if (!array_is_list($value)) {
                foreach (array_keys($value) as $key) {
                    if (!is_string($key) || preg_match('/\A[\x20-\x7e]{1,256}\z/D', $key) !== 1) {
                        throw new StorageException('STORAGE_ENCODING_INVALID');
                    }
                }
                ksort($value, SORT_STRING);
            }
            foreach ($value as &$child) {
                $child = self::sort($child, $depth + 1);
            }
            unset($child);
            return $value;
        }
        if (is_string($value)) {
            if (strlen($value) > 65536 || preg_match('//u', $value) !== 1) {
                throw new StorageException('STORAGE_ENCODING_INVALID');
            }
        } elseif (is_int($value)) {
            if ($value < -9007199254740991 || $value > 9007199254740991) {
                throw new StorageException('STORAGE_ENCODING_INVALID');
            }
        } elseif (!is_bool($value) && $value !== null) {
            throw new StorageException('STORAGE_ENCODING_INVALID');
        }
        return $value;
    }
}
