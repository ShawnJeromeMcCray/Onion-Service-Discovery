<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Signing;
interface SignerProvider
{
    public function role(): string;
    public function publicKey(): string;
    /** Signs only allowlisted structured records, with a fixed protocol domain. */
    public function signRecord(\stdClass $record, bool $rotationConfirmation = false): \stdClass;
}
