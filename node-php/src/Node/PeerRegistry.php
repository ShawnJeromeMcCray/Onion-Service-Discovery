<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;
final class PeerRegistry
{
    public const MAX_PEERS = 256;
    public static function add(StorageBackend $store, OnionAddress $onion, string $expectedKey): bool
    {
        Encoding::publicKey($expectedKey);
        return $store->transaction(static function (StorageTransaction $tx) use ($onion, $expectedKey): bool {
            $existing = $tx->get('peers', $onion->hostname);
            if ($existing !== null) {
                if ($existing['node_identity_public_key'] !== $expectedKey) { throw new ProtocolException('PEER_KEY_CHANGE_REQUIRES_EXPLICIT_RESET'); }
                return false;
            }
            if (count($tx->state()['tables']['peers']) >= self::MAX_PEERS) { throw new ProtocolException('PEER_POOL_FULL'); }
            $tx->set('peers', $onion->hostname, ['state' => 'CANDIDATE', 'node_identity_public_key' => $expectedKey, 'failures' => 0, 'next_attempt' => 0, 'claim' => null, 'lease_until' => 0, 'descriptor_hash' => null, 'descriptor_revision' => 0, 'error' => null]);
            return true;
        });
    }
}
