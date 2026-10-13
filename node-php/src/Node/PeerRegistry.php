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

    /** Local operator grouping can reduce diversity credit, never grant identity authority. */
    public static function group(StorageBackend $store, OnionAddress $onion, ?string $group): void
    {
        if ($group !== null && preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?\z/D', $group) !== 1) {
            throw new ProtocolException('SOURCE_GROUP_INVALID');
        }
        $store->transaction(static function (StorageTransaction $tx) use ($onion, $group): void {
            $peer = $tx->get('peers', $onion->hostname);
            if ($peer === null) { throw new ProtocolException('PEER_NOT_CONFIGURED'); }
            // Do not race an in-flight sync that owns a copy of this operational row.
            if ($peer['claim'] !== null) { throw new ProtocolException('PEER_BUSY'); }
            if ($group === null) { unset($peer['source_group']); }
            else { $peer['source_group'] = $group; }
            $tx->set('peers', $onion->hostname, $peer);
        });
    }

    /** @param array<array-key,mixed> $peers
     * @return array<string,mixed>
     */
    public static function concentration(array $peers): array
    {
        if (count($peers) > self::MAX_PEERS) { throw new ProtocolException('PEER_POOL_FULL'); }
        $keys = []; $groups = []; $unknown = []; $active = 0;
        foreach ($peers as $peer) {
            if (($peer['state'] ?? null) !== 'ACTIVE') { continue; }
            $key = $peer['node_identity_public_key'] ?? null;
            if (!is_string($key)) { throw new ProtocolException('SOURCE_GROUP_INVALID'); }
            Encoding::publicKey($key);
            ++$active; $keys[$key] = true;
            $group = $peer['source_group'] ?? null;
            if ($group === null) { $unknown[$key] = true; continue; }
            if (!is_string($group) || preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?\z/D', $group) !== 1) { throw new ProtocolException('SOURCE_GROUP_INVALID'); }
            // One Node key in multiple local groups cannot count as independent groups.
            $groups[$key][$group] = true;
        }
        $families = []; $inconsistent = false;
        foreach ($groups as $key => $assignments) {
            if (count($assignments) !== 1 || isset($unknown[$key])) { $inconsistent = true; continue; }
            foreach ($assignments as $name => $_) { $families[$name] = true; }
        }
        return ['active_peer_addresses' => $active, 'distinct_node_identities' => count($keys),
            'configured_source_groups' => count($families), 'ungrouped_node_identities' => count($unknown),
            'grouping_inconsistent' => $inconsistent,
            'concentrated' => count($keys) > 0 && (count($keys) === 1 || (count($families) === 1 && $unknown === [] && !$inconsistent)),
            'operator_independence' => 'NOT_PROVEN', 'trust_authority_from_count' => false];
    }
}
