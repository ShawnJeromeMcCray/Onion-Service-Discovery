<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Protocol\RecordVerifier;

/**
 * Public, read-only Discovery Node summary (spec 47 sections 36-40, 52-53): identity, snapshot,
 * storage and aggregate queue/sync facts. Never peer lists, paths, counters of rate limits or errors.
 */
final class NodeStatus
{
    /** @return array<string,mixed>|null Null for roles without a Node identity. */
    public static function inspect(NodeConfig $config, RecordVerifier $verifier, int $now): ?array
    {
        if (!in_array($config->role, ['node', 'combined'], true)) { return null; }
        $status = ['role' => $config->role, 'storage_backend' => $config->backend, 'storage_state' => 'ERROR', 'identity' => 'NOT_INITIALIZED'];
        // Before node:init there is nothing to read yet: setup in progress, not a fault.
        if (!file_exists($config->storagePath)) { $status['storage_state'] = 'NOT_INITIALIZED'; return $status; }
        try {
            $store = $config->store(readOnly: true); $state = $store->snapshot();
            $status['storage_state'] = 'READABLE';
            $status['signed_records'] = count($state['records']);
            $identity = self::identity($config, $verifier, $store, $state);
            if ($identity !== null) { $status = $identity + $status; $status['identity'] = 'INITIALIZED'; }
            if ($config->features['mirror_directory']) {
                $published = $state['tables']['node']['key:published-snapshot'] ?? null;
                $status['snapshot'] = 'NOT_PUBLISHED';
                if ($published !== null) {
                    $wire = $store->readRecord($published['hash']);
                    $checkpoint = $wire === null ? null : $verifier->verifyWire($wire);
                    if ($checkpoint === null || $checkpoint->type !== 'snapshot_descriptor' || $checkpoint->contentHash !== $published['hash']) { throw new \InvalidArgumentException('STORAGE_CORRUPT'); }
                    $created = $checkpoint->document()->created_at;
                    $status['snapshot'] = 'PUBLISHED'; $status['snapshot_generation'] = $checkpoint->document()->generation; $status['snapshot_created_at'] = $created;
                    $status['snapshot_age_seconds'] = max(0, $now - (int) strtotime($created));
                }
            }
            if ($config->features['discover_services']) {
                $states = [];
                foreach ($state['tables']['candidates'] as $row) { $key = is_string($row['state'] ?? null) ? $row['state'] : 'UNKNOWN'; $states[$key] = ($states[$key] ?? 0) + 1; }
                ksort($states); $status['candidates'] = $states;
                $last = 0; foreach ($state['tables']['local_observations'] as $row) { $last = max($last, (int) ($row['observed_at'] ?? 0)); }
                $status['last_direct_verification_at'] = $last === 0 ? null : gmdate('Y-m-d\TH:i:s\Z', $last);
            }
            if ($config->features['sync_with_peers']) {
                $active = 0; $quarantined = 0;
                foreach ($state['tables']['peers'] as $row) { $active += ($row['state'] ?? null) === 'ACTIVE' ? 1 : 0; $quarantined += ($row['state'] ?? null) === 'QUARANTINED' ? 1 : 0; }
                $last = 0; foreach ($state['tables']['sync'] as $row) { $last = max($last, (int) ($row['last_sync'] ?? 0)); }
                $status['peers'] = ['configured' => count($state['tables']['peers']), 'active' => $active, 'quarantined' => $quarantined];
                $status['last_successful_sync_at'] = $last === 0 ? null : gmdate('Y-m-d\TH:i:s\Z', $last);
            }
        } catch (\Throwable) {
            // A failing store degrades only this card; no detail or path is exposed publicly.
            $status['storage_state'] = 'ERROR';
        }
        return $status;
    }

    /**
     * The verified Node descriptor binding: the two values others must pin for this Node.
     * @param array<string,mixed> $state
     * @return array{node_onion:string,node_identity_public_key:string}|null
     */
    public static function identity(NodeConfig $config, RecordVerifier $verifier, \OnionServiceDiscovery\Storage\StorageBackend $store, array $state): ?array
    {
        $descriptor = $state['tables']['node']['key:descriptor'] ?? null;
        if ($descriptor === null) { return null; }
        $wire = $store->readRecord($descriptor['hash']);
        $record = $wire === null ? null : $verifier->verifyWire($wire);
        $document = $record?->document();
        $onion = $document?->node_onion; $key = $document?->node_identity_public_key;
        if ($record === null || $record->type !== 'node_descriptor' || $record->contentHash !== $descriptor['hash'] || !is_string($onion) || !is_string($key) || $onion !== $config->nodeOnion) {
            throw new \InvalidArgumentException('NODE_DESCRIPTOR_INVALID');
        }
        return ['node_onion' => $onion, 'node_identity_public_key' => $key];
    }
}
