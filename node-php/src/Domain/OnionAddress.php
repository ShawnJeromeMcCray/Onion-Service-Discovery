<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Domain;

/** A canonical Tor v3 hostname; construction never accesses Tor private keys. */
final readonly class OnionAddress
{
    private function __construct(public string $hostname) {}

    public static function parse(string $hostname): self
    {
        if (preg_match('/\A[a-z2-7]{56}\.onion\z/D', $hostname) !== 1) {
            throw new \InvalidArgumentException('ADDRESS_INVALID');
        }
        $alphabet = 'abcdefghijklmnopqrstuvwxyz234567';
        $buffer = 0;
        $bits = 0;
        $decoded = '';
        for ($index = 0; $index < 56; ++$index) {
            $value = strpos($alphabet, $hostname[$index]);
            if ($value === false) {
                throw new \InvalidArgumentException('ADDRESS_INVALID');
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }
        if (strlen($decoded) !== 35 || $bits !== 0 || ord($decoded[34]) !== 3) {
            throw new \InvalidArgumentException('ADDRESS_INVALID');
        }
        $checksum = substr(hash('sha3-256', '.onion checksum' . substr($decoded, 0, 32) . "\x03", true), 0, 2);
        if (!hash_equals($checksum, substr($decoded, 32, 2))) {
            throw new \InvalidArgumentException('ADDRESS_INVALID');
        }
        return new self($hostname);
    }
}
