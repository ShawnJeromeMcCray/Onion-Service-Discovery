<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Domain;
use OnionServiceDiscovery\Protocol\ProtocolException;

final readonly class LanguageTag
{
    private function __construct(public string $value) {}
    public static function parse(string $tag): self
    {
        if (strlen($tag) > 35 || preg_match('~\A(?:' . LanguageGrammar::PATTERN . ')\z~D', $tag) !== 1) { throw new ProtocolException('SCHEMA_INVALID'); }
        if (in_array($tag, LanguageGrammar::GRANDFATHERED, true) || str_starts_with($tag, 'x-')) { return new self($tag); }
        $parts = explode('-', $tag); $index = 1;
        if (strlen($parts[0]) <= 3) {
            for ($n = 0; $n < 3 && preg_match('/\A[a-z]{3}\z/D', $parts[$index] ?? '') === 1; ++$n) { ++$index; }
        }
        if (preg_match('/\A[a-z]{4}\z/D', $parts[$index] ?? '') === 1) { ++$index; }
        if (preg_match('/\A(?:[a-z]{2}|[0-9]{3})\z/D', $parts[$index] ?? '') === 1) { ++$index; }
        $variants = [];
        while (preg_match('/\A(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3})\z/D', $parts[$index] ?? '') === 1) {
            if (isset($variants[$parts[$index]])) { throw new ProtocolException('SCHEMA_INVALID'); }
            $variants[$parts[$index]] = true; ++$index;
        }
        $extensions = [];
        while (isset($parts[$index]) && $parts[$index] !== 'x') {
            if (isset($extensions[$parts[$index]])) { throw new ProtocolException('SCHEMA_INVALID'); }
            $extensions[$parts[$index]] = true; ++$index;
            while (isset($parts[$index]) && strlen($parts[$index]) > 1) { ++$index; }
        }
        return new self($tag);
    }
}
