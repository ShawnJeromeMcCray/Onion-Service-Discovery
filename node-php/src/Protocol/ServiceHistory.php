<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

use OnionServiceDiscovery\Validation\StrictJson;

/**
 * PCP-004 service history bundle: connects a directly fetched current Manifest to its
 * signed history. The bundle is unsigned and has no authority; every record proves itself.
 */
final readonly class ServiceHistory
{
    public const MAX_BYTES = 1048576;
    public const MAX_RECORDS = 256;
    private const TYPES = ['service_manifest', 'service_identity_key_rotation', 'onion_service_migration'];

    public function __construct(private RecordVerifier $verifier) {}

    /**
     * @param ValidatedRecord $current Manifest fetched from the same Onion, verified with exact Onion binding.
     * @param list<ValidatedRecord> $known Records the verifier already holds.
     * @return array{state:string,genesis_key:?string,admitted_hashes:list<string>,admitted:list<ValidatedRecord>}
     */
    public function verify(string $bundleBytes, ValidatedRecord $current, array $known): array
    {
        if (strlen($bundleBytes) > self::MAX_BYTES || $current->type !== 'service_manifest') { throw new ProtocolException('HISTORY_BUNDLE_INVALID'); }
        $bundle = StrictJson::parse($bundleBytes, self::MAX_BYTES);
        $members = get_object_vars($bundle); ksort($members);
        if (array_keys($members) !== ['bundle_type', 'protocol', 'protocol_version', 'records'] || $bundle->protocol !== 'Onion Service Discovery'
            || $bundle->protocol_version !== 1 || $bundle->bundle_type !== 'service_history' || !is_array($bundle->records)
            || count($bundle->records) < 1 || count($bundle->records) > self::MAX_RECORDS) {
            throw new ProtocolException('HISTORY_BUNDLE_INVALID');
        }
        $records = [];
        foreach ($bundle->records as $entry) {
            if (!$entry instanceof \stdClass) { throw new ProtocolException('HISTORY_BUNDLE_INVALID'); }
            $record = $this->verifier->verify($entry);
            if (!in_array($record->type, self::TYPES, true) || isset($records[$record->contentHash])) { throw new ProtocolException('HISTORY_BUNDLE_INVALID'); }
            $records[$record->contentHash] = $record;
        }
        $union = [];
        foreach ($known as $record) { $union[$record->contentHash] = $record; }
        $knownHashes = $union;
        foreach ($records as $hash => $record) { $union[$hash] ??= $record; }
        $union[$current->contentHash] ??= $current;
        $view = IdentityDeriver::derive(array_values($union), 10000);
        foreach ($view['identities'] as $identity) {
            if (!in_array($current->contentHash, $identity['history_hashes'], true)) { continue; }
            $state = $identity['identity_state'] === 'FORKED' ? 'FORKED' : ($identity['current_hash'] === $current->contentHash ? 'CONNECTED' : 'DOWNGRADE');
            $connected = array_merge($identity['history_hashes'], $identity['rotation_hashes'], $identity['migration_hashes']);
            foreach ($identity['conflicts'] as $conflict) { $connected = array_merge($connected, $conflict['hashes']); }
            foreach ($identity['transition_conflicts'] as $conflict) { $connected = array_merge($connected, $conflict['record_hashes']); }
            $admitted = [];
            foreach (array_unique($connected) as $hash) {
                if (isset($records[$hash]) && !isset($knownHashes[$hash]) && $hash !== $current->contentHash) { $admitted[$hash] = $records[$hash]; }
            }
            ksort($admitted, SORT_STRING);
            return ['state' => $state, 'genesis_key' => $identity['genesis_key'], 'admitted_hashes' => array_keys($admitted), 'admitted' => array_values($admitted)];
        }
        return ['state' => 'INCOMPLETE', 'genesis_key' => null, 'admitted_hashes' => [], 'admitted' => []];
    }
}
