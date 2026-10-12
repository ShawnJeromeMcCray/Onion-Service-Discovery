<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Protocol;
use OnionServiceDiscovery\Crypto\CanonicalJson;
final class SnapshotFormat
{
    public static function chunkHash(string $canonicalBytes): string { return 'sha256:' . hash('sha256', "Onion Service Discovery/snapshot-chunk/v1\0" . $canonicalBytes); }
    /**
     * @param array<array-key, mixed> $chunks
     */
    public static function rootHash(array $chunks): string { return 'sha256:' . hash('sha256', "Onion Service Discovery/snapshot-root/v1\0" . CanonicalJson::encode($chunks)); }
}
