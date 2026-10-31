<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Node\PublishedState;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageException;

/**
 * Public, read-only Discovery Node summary (spec 47 sections 36-40, 52-53): identity, snapshot,
 * storage and aggregate queue/sync facts. Never peer lists, paths, counters of rate limits or errors.
 * Storage states (spec 47 section 39): HEALTHY; READ_ONLY (a format-1 filesystem store that every
 * writer refuses); DEGRADED (the store cannot be read, the last published snapshot is still served,
 * D-045); ERROR (nothing can be served); NOT_INITIALIZED before node:init.
 */
final class NodeStatus
{
    /** @return array<string,mixed>|null Null for roles without a Node identity. */
    public static function inspect(NodeConfig $config, RecordVerifier $verifier, int $now): ?array
    {
        if (!in_array($config->role, ['node', 'combined'], true)) { return null; }
        $status = $base = ['role' => $config->role, 'storage_backend' => $config->backend, 'storage_state' => 'ERROR', 'identity' => 'NOT_INITIALIZED'];
        try {
            $store = $config->store(readOnly: true); $state = $store->snapshot();
            $status['storage_state'] = $store instanceof FilesystemStore && $store->formatVersion() === 1 ? 'READ_ONLY' : 'HEALTHY';
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
        } catch (StorageException) {
            // The store cannot be read: DEGRADED while its last published snapshot is still served (D-045).
            // Before node:init there is nothing to read yet: setup in progress, not a fault.
            return self::degraded($config, $verifier, $now, $base) ?? ['storage_state' => file_exists($config->storagePath) ? 'ERROR' : 'NOT_INITIALIZED'] + $base;
        } catch (\Throwable) {
            // A failing store degrades only this card; no detail or path is exposed publicly.
            $status['storage_state'] = 'ERROR';
        }
        return $status;
    }

    /** The operator's next step for a storage state that needs one (CLI only; the public card never shows it). */
    public static function nextAction(string $storageState): ?string
    {
        return match ($storageState) {
            'DEGRADED' => 'The store cannot be read. The web routes keep serving the last published snapshot and refuse announcements, server search and lookups that need the store. Follow docs/operations/incident-runbook.md section 4: storage:verify BACKEND PATH names the cause without changing anything; stop the timers, back up the store and run storage:check BACKEND PATH, or restore a private backup if it reports STORAGE_CORRUPT; then run doctor and start the timers.',
            'ERROR' => 'The store cannot be read and no published snapshot can be served in its place, so the data routes answer 503. Follow docs/operations/incident-runbook.md section 4, starting with storage:verify BACKEND PATH, which changes nothing.',
            'READ_ONLY' => 'The filesystem store uses format 1: it can be read, but every writer refuses it. Stop the workers, back up the store and run storage:migrate filesystem PATH.',
            'NOT_INITIALIZED' => 'Setup is not finished: create the Node key with key:create node KEY_PATH --passphrase-file PRIVATE_FILE (KEY_PATH as in node.key_path), then run node:init CONFIG --passphrase-file PRIVATE_FILE, which creates the store and the export directory.',
            default => null,
        };
    }

    /**
     * The card while the store cannot be read but its last published snapshot is still served:
     * identity and snapshot from the verified published state only, nothing that needs the store.
     * @param array<string,mixed> $base
     * @return array<string,mixed>|null Null when no published state can be served.
     */
    private static function degraded(NodeConfig $config, RecordVerifier $verifier, int $now, array $base): ?array
    {
        try { $published = PublishedState::load(new PublicBlobStore($config->exportPath), $verifier, $config->nodeOnion); }
        catch (\Throwable) { return null; }
        if ($published === null) { return null; }
        $status = ['storage_state' => 'DEGRADED'] + $base;
        $node = $published->nodeDescriptor?->document();
        if ($node !== null) { $status = ['node_onion' => $node->node_onion, 'node_identity_public_key' => $node->node_identity_public_key] + $status; $status['identity'] = 'INITIALIZED'; }
        if ($config->features['mirror_directory']) {
            $created = $published->checkpoint->document()->created_at;
            $status['snapshot'] = 'PUBLISHED'; $status['snapshot_generation'] = $published->checkpoint->document()->generation; $status['snapshot_created_at'] = $created;
            $status['snapshot_age_seconds'] = max(0, $now - (int) strtotime($created));
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
