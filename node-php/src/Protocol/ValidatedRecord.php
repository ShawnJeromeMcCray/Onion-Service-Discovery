<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

/** Validated immutable bytes; callers receive fresh decoded copies. */
final readonly class ValidatedRecord
{
    private function __construct(public string $type, public string $contentHash, public string $canonicalWire) {}

    public static function verify(\stdClass $record, RecordVerifier $verifier, ?\OnionServiceDiscovery\Domain\OnionAddress $expectedOnion = null): self
    {
        $verifier->assertValid($record, $expectedOnion);
        return new self(RecordFormat::type($record), RecordFormat::contentHash($record), \OnionServiceDiscovery\Crypto\CanonicalJson::encode($record));
    }

    public function document(): \stdClass
    {
        $decoded = json_decode($this->canonicalWire, false, 32, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof \stdClass) {
            throw new \LogicException('Verified record is not an object');
        }
        return $decoded;
    }
}
