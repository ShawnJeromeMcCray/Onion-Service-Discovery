<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Validation;

/** Integer-only, bounded protocol JSON. Record-specific schemas follow parsing. */
final class StrictJson
{
    private int $cursor = 0;

    private function __construct(private readonly string $source) {}

    public static function parse(string $bytes, int $maxBytes = 16384): \stdClass
    {
        $result = self::parseValue($bytes, $maxBytes);
        if (!$result instanceof \stdClass) { self::fail('SCHEMA_INVALID'); }
        return $result;
    }

    /** Bounded generic JSON for canonical snapshot array documents. */
    public static function parseValue(string $bytes, int $maxBytes = 16384): mixed
    {
        if ($maxBytes < 1 || $maxBytes > 1048576 || strlen($bytes) > $maxBytes) {
            throw new \InvalidArgumentException('DOCUMENT_TOO_LARGE');
        }
        if (str_starts_with($bytes, "\xef\xbb\xbf") || preg_match('//u', $bytes) !== 1) {
            throw new \InvalidArgumentException('ENCODING_INVALID');
        }
        $parser = new self($bytes);
        $result = $parser->value(1);
        $parser->whitespace();
        if ($parser->cursor !== strlen($bytes)) {
            self::fail();
        }
        return $result;
    }

    private static function fail(string $code = 'JSON_INVALID'): never
    {
        throw new \InvalidArgumentException($code);
    }

    private function current(): string
    {
        return $this->source[$this->cursor] ?? '';
    }

    private function whitespace(): void
    {
        while ($this->current() !== '' && str_contains("\t\n\r ", $this->current())) {
            ++$this->cursor;
        }
    }

    private function string(): string
    {
        $begin = $this->cursor++;
        $escaped = false;
        while ($this->cursor < strlen($this->source)) {
            $character = $this->source[$this->cursor++];
            if (!$escaped && $character === '"') {
                try {
                    $value = json_decode(substr($this->source, $begin, $this->cursor - $begin), false, 2, JSON_THROW_ON_ERROR);
                } catch (\JsonException $error) {
                    self::fail($error->getCode() === JSON_ERROR_UTF16 ? 'ENCODING_INVALID' : 'JSON_INVALID');
                }
                if (!is_string($value)) {
                    self::fail();
                }
                $length = preg_match_all('/./us', $value);
                if ($length === false) {
                    self::fail('ENCODING_INVALID');
                }
                if ($length > 4096) {
                    self::fail('SCHEMA_INVALID');
                }
                return $value;
            }
            if (!$escaped && $character === '\\') {
                $escaped = true;
            } else {
                $escaped = false;
            }
        }
        self::fail();
    }

    private function value(int $depth): mixed
    {
        $this->whitespace();
        $character = $this->current();
        if ($character === '"') {
            return $this->string();
        }
        if ($character === '{' || $character === '[') {
            if ($depth > 16) {
                self::fail('JSON_DEPTH_EXCEEDED');
            }
            ++$this->cursor;
            $this->whitespace();
            if ($character === '{') {
                $object = new \stdClass();
                $keys = [];
                if ($this->current() === '}') {
                    ++$this->cursor;
                    return $object;
                }
                while (true) {
                    $this->whitespace();
                    if ($this->current() !== '"') {
                        self::fail();
                    }
                    $key = $this->string();
                    if (array_key_exists('key:' . $key, $keys)) {
                        self::fail('JSON_DUPLICATE_KEY');
                    }
                    if (count($keys) >= 128) {
                        self::fail('SCHEMA_INVALID');
                    }
                    $keys['key:' . $key] = true;
                    $this->whitespace();
                    if ($this->current() !== ':') {
                        self::fail();
                    }
                    ++$this->cursor;
                    // Reject NUL property names consistently across implementations.
                    if (str_contains($key, "\0")) {
                        self::fail('ENCODING_INVALID');
                    }
                    $object->{$key} = $this->value($depth + 1);
                    $this->whitespace();
                    $separator = $this->current();
                    ++$this->cursor;
                    if ($separator === '}') {
                        return $object;
                    }
                    if ($separator !== ',') {
                        self::fail();
                    }
                }
            }
            $array = [];
            if ($this->current() === ']') {
                ++$this->cursor;
                return $array;
            }
            while (true) {
                if (count($array) >= 256) {
                    self::fail('SCHEMA_INVALID');
                }
                $array[] = $this->value($depth + 1);
                $this->whitespace();
                $separator = $this->current();
                ++$this->cursor;
                if ($separator === ']') {
                    return $array;
                }
                if ($separator !== ',') {
                    self::fail();
                }
            }
        }
        foreach (['true' => true, 'false' => false, 'null' => null] as $token => $result) {
            if (substr($this->source, $this->cursor, strlen($token)) === $token) {
                $this->cursor += strlen($token);
                return $result;
            }
        }
        if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A', $this->source, $matches, 0, $this->cursor) !== 1) {
            self::fail();
        }
        $token = $matches[0];
        $this->cursor += strlen($token);
        if (strpbrk($token, '.eE') !== false || $token === '-0' || strlen(ltrim($token, '-')) > 16) {
            self::fail('SCHEMA_INVALID');
        }
        $integer = (int) $token;
        if (abs($integer) > 9007199254740991) {
            self::fail('SCHEMA_INVALID');
        }
        return $integer;
    }
}
