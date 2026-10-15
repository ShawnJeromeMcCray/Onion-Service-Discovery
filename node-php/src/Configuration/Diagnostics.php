<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\StorageException;

/** Read-only local diagnostics. No Tor probing, key decryption, recovery or network requests. */
final class Diagnostics
{
    private const EXTENSIONS = ['json', 'sodium', 'iconv', 'curl'];

    /** PASS on a tested 64-bit runtime with every required extension, WARN outside the tested minor versions. */
    public static function runtimeState(): string
    {
        if (PHP_INT_SIZE !== 8) { return 'FAIL'; }
        foreach (self::EXTENSIONS as $extension) { if (!extension_loaded($extension)) { return 'FAIL'; } }
        return str_starts_with(PHP_VERSION, '8.4.') || str_starts_with(PHP_VERSION, '8.5.') ? 'PASS' : 'WARN';
    }

    /**
     * @param string|null $manifestPath The installed public Manifest; with it a publishing installation
     *                                  also reports its manifest state (spec 32 sections 8 and 10).
     * @param string|null $configPath   Locates the saved publisher evidence beside the configuration.
     * @return array<string,mixed>
     */
    public static function inspect(NodeConfig $config, RecordVerifier $verifier, ?string $manifestPath = null, ?string $configPath = null): array
    {
        $checks = []; $warnings = []; $failures = [];
        $checks['runtime'] = PHP_INT_SIZE === 8 ? 'PASS' : 'FAIL';
        foreach (self::EXTENSIONS as $extension) { $checks['extension_' . $extension] = extension_loaded($extension) ? 'PASS' : 'FAIL'; }
        if (!str_starts_with(PHP_VERSION, '8.4.') && !str_starts_with(PHP_VERSION, '8.5.')) { $warnings[] = 'RUNTIME_OUTSIDE_EXECUTED_MINOR_MATRIX'; }
        $publication = null;
        if ($manifestPath !== null && $config->nodeOnion !== null && $config->features['publish_service']) {
            $facts = PublisherStatus::inspect($config, $verifier, $manifestPath, $configPath);
            $publication = ['overall' => $facts['overall'], 'manifest_state' => $facts['manifest']['state'], 'revision' => $facts['manifest']['revision'] ?? null,
                'manifest_hash' => $facts['manifest']['hash'] ?? null, 'self_verification' => $facts['self_verification'], 'announcement' => $facts['announcement'],
                'active_index_membership' => $facts['active_index_membership'], 'source_integrity' => $facts['source_integrity'] ?? 'NOT_CHECKED'];
            $state = $facts['manifest']['state'];
            $checks['publisher_manifest'] = $state === 'SIGNATURE_VALID' ? 'PASS' : 'FAIL';
            if (($facts['source_integrity'] ?? null) !== null && $facts['source_integrity'] !== 'NO_CONFLICT_DETECTED') { $warnings[] = 'PUBLISHER_CHECKED_NODE_CHECKPOINT_CONFLICT'; }
            // Without chosen Nodes neither the timer nor a plain command can check or announce anything.
            $checks['publisher_nodes'] = $config->publisherNodes === [] ? 'WARN' : 'PASS';
            if ($config->publisherNodes === []) { $warnings[] = 'PUBLISHER_NODES_NOT_CONFIGURED'; }
        }
        if (!in_array($config->role, ['client-host','publisher'], true) && $config->backend === 'sqlite') {
            $checks['sqlite_driver'] = class_exists(\PDO::class) && in_array('sqlite', \PDO::getAvailableDrivers(), true) ? 'PASS' : 'FAIL';
        }
        $checks['tor_transport_configuration'] = 'PASS'; // NodeConfig already restricts loopback SOCKS and finite timeout.
        $checks['server_search_disabled'] = $config->features['server_search'] ? 'WARN' : 'PASS';
        if ($config->features['server_search']) { $warnings[] = 'SERVER_SEARCH_ENABLED_QUERIES_SENT_TO_NODE'; }
        if ($config->features['visitor_sources'] ?? false) { $checks['visitor_source_relay'] = 'WARN'; $warnings[] = 'VISITOR_SOURCE_RELAY_ENABLED_OUTBOUND_TO_VISITOR_CHOSEN_NODES'; }
        if ($config->role !== 'publisher') { $checks['export_directory'] = !is_link($config->exportPath) && is_dir($config->exportPath) && (fileperms($config->exportPath) & 0077) === 0 ? 'PASS' : 'FAIL'; }
        if ($config->nodeKeyPath !== null) {
            $checks['node_key_permissions'] = !is_link($config->nodeKeyPath) && is_file($config->nodeKeyPath) && (fileperms($config->nodeKeyPath) & 0077) === 0 ? 'PASS' : 'WARN';
            if ($checks['node_key_permissions'] === 'WARN') { $warnings[] = 'NODE_KEY_MISSING_OR_UNSAFE'; }
        }
        $envelope = null;
        if ($config->nodeKeyPath !== null && $checks['node_key_permissions'] === 'PASS') {
            try { $envelope = \OnionServiceDiscovery\Signing\ProtectedFileSigner::inspectEnvelope($config->nodeKeyPath, 'node'); $checks['node_key_envelope_header'] = 'PASS'; }
            catch (\OnionServiceDiscovery\Signing\SignerException) { $checks['node_key_envelope_header'] = 'FAIL'; $failures[] = 'NODE_KEY_ENVELOPE_INVALID'; }
        }
        $state = null;
        if (!in_array($config->role, ['client-host','publisher'], true)) {
            try {
                $store = $config->store(readOnly: true); $state = $store->snapshot(); $store->integrityCheck(); $checks['storage_integrity'] = 'PASS';
                if ($store instanceof \OnionServiceDiscovery\Storage\FilesystemStore && $store->formatVersion() === 1) { $warnings[] = 'FILESYSTEM_FORMAT_MIGRATION_REQUIRED'; }
                $descriptor = $state['tables']['node']['key:descriptor'] ?? null;
                if ($descriptor === null) { $checks['node_descriptor'] = 'WARN'; $warnings[] = 'NODE_NOT_INITIALIZED'; }
                else {
                    $wire = $store->readRecord($descriptor['hash']);
                    if ($wire === null) { throw new StorageException('STORAGE_CORRUPT'); }
                    $record = $verifier->verifyWire($wire); $doc = $record->document();
                    $checks['node_descriptor'] = $record->type === 'node_descriptor' && $record->contentHash === $descriptor['hash'] && $doc->node_onion === $config->nodeOnion && ($envelope === null || $envelope['public_key'] === $doc->node_identity_public_key) ? 'PASS' : 'FAIL';
                }
                $published = $state['tables']['node']['key:published-snapshot'] ?? null;
                if ($config->features['mirror_directory'] && $published === null) { $checks['snapshot_exports'] = 'WARN'; $warnings[] = 'SNAPSHOT_NOT_PUBLISHED'; }
                elseif ($published !== null) {
                    $wire = $store->readRecord($published['hash']);
                    if ($wire === null) { throw new StorageException('STORAGE_CORRUPT'); }
                    $checkpoint = $verifier->verifyWire($wire);
                    if ($checkpoint->type !== 'snapshot_descriptor' || $checkpoint->contentHash !== $published['hash']) { throw new StorageException('STORAGE_CORRUPT'); }
                    $blobs = new \OnionServiceDiscovery\Storage\PublicBlobStore($config->exportPath);
                    if ($blobs->get($checkpoint->contentHash) !== $checkpoint->canonicalWire) { throw new StorageException('EXPORT_CORRUPT'); }
                    foreach ($checkpoint->document()->chunks as $chunk) {
                        $body = $blobs->get($chunk->hash);
                        if ($body === null || strlen($body) !== $chunk->bytes || \OnionServiceDiscovery\Protocol\SnapshotFormat::chunkHash($body) !== $chunk->hash) { throw new StorageException('EXPORT_CORRUPT'); }
                    }
                    foreach ($state['tables']['node'] as $key => $row) {
                        if (!str_starts_with($key, 'key:service-index-')) { continue; }
                        $body = $blobs->get($row['hash']);
                        if ($body === null || 'sha256:' . hash('sha256', "Onion Service Discovery/service-lookup/v1\0" . $body) !== $row['hash']) { throw new StorageException('EXPORT_CORRUPT'); }
                    }
                    $checks['snapshot_exports'] = 'PASS';
                    if ($config->features['server_search']) {
                        try {
                            \OnionServiceDiscovery\Search\SearchIndex::load($store, $blobs, $verifier, static function (): void {});
                            $checks['server_search_index'] = 'PASS';
                        } catch (\Throwable) { $checks['server_search_index'] = 'FAIL'; $failures[] = 'SERVER_SEARCH_INDEX_UNAVAILABLE_OR_CORRUPT'; }
                    }
                }
            } catch (StorageException $error) { $checks[str_starts_with($error->getMessage(), 'EXPORT_') ? 'snapshot_exports' : 'storage_integrity'] = 'FAIL'; $failures[] = $error->getMessage(); }
            catch (\InvalidArgumentException) { $checks['node_descriptor'] = 'FAIL'; $failures[] = 'NODE_DESCRIPTOR_INVALID'; }
        }
        foreach ($checks as $name => $result) { if ($result === 'FAIL') { $failures[] = strtoupper($name); } }
        $latestSync = 0; $quarantined = 0;
        foreach ($state['tables']['sync'] ?? [] as $row) { $latestSync = max($latestSync, $row['last_sync'] ?? 0); }
        foreach ($state['tables']['peers'] ?? [] as $row) { if (($row['state'] ?? null) === 'QUARANTINED') { ++$quarantined; } }
        $concentration = \OnionServiceDiscovery\Node\PeerRegistry::concentration($state['tables']['peers'] ?? []);
        if ($concentration['concentrated']) { $warnings[] = 'SOURCE_SET_CONCENTRATED'; }
        if ($concentration['grouping_inconsistent']) { $warnings[] = 'SOURCE_GROUP_ASSIGNMENTS_INCONSISTENT'; }
        $checks['source_concentration'] = $concentration['concentrated'] || $concentration['grouping_inconsistent'] ? 'WARN' : 'PASS';
        return ['status' => $failures !== [] ? 'FAIL' : ($warnings !== [] ? 'WARN' : 'PASS'), 'software_version' => '0.1.0-dev', 'php_version' => PHP_VERSION, 'protocol_versions' => [1], 'db_schema' => $state['storage_schema'] ?? null, 'role' => $config->role, 'config_state' => 'PASS', 'crypto_state' => 'RUNTIME_EXTENSIONS_ONLY', 'key_authentication_state' => 'NOT_UNLOCKED_UNAUTHENTICATED_HEADER', 'tor_transport_state' => 'CONFIGURED_NOT_PROBED', 'manifest_state' => $publication['manifest_state'] ?? 'NOT_APPLICABLE', 'publication' => $publication, 'sync_state' => ['latest_sync_epoch' => $latestSync, 'quarantined_peers' => $quarantined], 'source_concentration' => $concentration, 'scheduler_state' => 'NOT_OBSERVED', 'backup_state' => 'NOT_OBSERVED', 'disk_state' => ['free_bytes' => is_dir($config->exportPath) ? disk_free_space($config->exportPath) : null], 'security_check_summary' => $checks, 'warnings' => $warnings, 'failures' => array_values(array_unique($failures)), 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'), 'network_side_effects' => false, 'production_approval' => 'OPEN'];
    }
}
