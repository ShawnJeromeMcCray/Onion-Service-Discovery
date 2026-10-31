<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\StorageException;

/** Read-only local diagnostics. No Tor probing, key decryption, recovery or network requests. */
final class Diagnostics
{
    private const EXTENSIONS = ['json', 'sodium', 'iconv', 'curl'];
    /** Hard limits of the public export directory (PublicBlobStore). */
    private const EXPORT_MAX_FILES = 4096;
    private const EXPORT_MAX_BYTES = 268435456;
    /** Timer templates refresh snapshots daily and publisher evidence every six hours. */
    private const SNAPSHOT_STALE_AFTER_SECONDS = 172800;
    private const PUBLISHER_EVIDENCE_STALE_AFTER_SECONDS = 129600;

    /**
     * Supported PHP minor versions (D-002): 8.3 and newer, 8.5 preferred; each one runs in CI. Composer's
     * platform check (composer.json requires ^8.3) refuses an older PHP before any OSD code runs.
     */
    private const TESTED_MINORS = ['8.3', '8.4', '8.5'];

    /** PASS on a tested 64-bit runtime with every required extension, WARN on a newer untested minor version. */
    public static function runtimeState(): string
    {
        if (PHP_INT_SIZE !== 8) { return 'FAIL'; }
        foreach (self::EXTENSIONS as $extension) { if (!extension_loaded($extension)) { return 'FAIL'; } }
        return in_array(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, self::TESTED_MINORS, true) ? 'PASS' : 'WARN';
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
        // Every FAIL names its own registered failure code (docs/operations/error-codes.md).
        $checks['runtime'] = PHP_INT_SIZE === 8 ? 'PASS' : 'FAIL';
        if ($checks['runtime'] === 'FAIL') { $failures[] = 'RUNTIME_NOT_64_BIT'; }
        foreach (self::EXTENSIONS as $extension) { $checks['extension_' . $extension] = extension_loaded($extension) ? 'PASS' : 'FAIL'; if (!extension_loaded($extension)) { $failures[] = 'PHP_EXTENSION_MISSING'; } }
        if (!in_array(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, self::TESTED_MINORS, true)) { $warnings[] = 'RUNTIME_OUTSIDE_EXECUTED_MINOR_MATRIX'; }
        $publication = null; $scheduler = []; $exportUsage = null; $storageUsage = null;
        if ($manifestPath !== null && $config->nodeOnion !== null && $config->features['publish_service']) {
            $facts = PublisherStatus::inspect($config, $verifier, $manifestPath, $configPath);
            $publication = ['overall' => $facts['overall'], 'manifest_state' => $facts['manifest']['state'], 'revision' => $facts['manifest']['revision'] ?? null,
                'manifest_hash' => $facts['manifest']['hash'] ?? null, 'self_verification' => $facts['self_verification'], 'announcement' => $facts['announcement'],
                'active_index_membership' => $facts['active_index_membership'], 'source_integrity' => $facts['source_integrity'] ?? 'NOT_CHECKED'];
            $state = $facts['manifest']['state'];
            $checks['publisher_manifest'] = $state === 'SIGNATURE_VALID' ? 'PASS' : 'FAIL';
            if ($state !== 'SIGNATURE_VALID') { $failures[] = 'PUBLISHER_MANIFEST_NOT_VALID'; }
            if (($facts['source_integrity'] ?? null) !== null && $facts['source_integrity'] !== 'NO_CONFLICT_DETECTED') { $warnings[] = 'PUBLISHER_CHECKED_NODE_CHECKPOINT_CONFLICT'; }
            // Without chosen Nodes neither the timer nor a plain command can check or announce anything.
            $checks['publisher_nodes'] = $config->publisherNodes === [] ? 'WARN' : 'PASS';
            if ($config->publisherNodes === []) { $warnings[] = 'PUBLISHER_NODES_NOT_CONFIGURED'; }
            // The last saved self-check through the own Onion failed or saw another Manifest: the page answers NOT YET, so doctor must not say PASS.
            if (str_starts_with($facts['self_verification'], 'FAIL_') || $facts['self_verification'] === 'SERVED_MANIFEST_DIFFERS') { $warnings[] = 'PUBLISHER_SELF_VERIFICATION_FAILED'; }
            // Scheduler freshness from saved timestamps (spec 32 section 8): the publisher timer runs every six hours.
            $checkedAt = is_string($facts['self_verification_checked_at'] ?? null) ? (int) strtotime($facts['self_verification_checked_at']) : null;
            $scheduler['publisher_checks'] = $checkedAt === null ? 'NOT_OBSERVED' : (time() - $checkedAt > self::PUBLISHER_EVIDENCE_STALE_AFTER_SECONDS ? 'STALE' : 'RECENT');
            if ($scheduler['publisher_checks'] === 'STALE') { $warnings[] = 'PUBLISHER_TIMER_STALE'; }
        }
        if (!in_array($config->role, ['client-host','publisher'], true) && $config->backend === 'sqlite') {
            $checks['sqlite_driver'] = class_exists(\PDO::class) && in_array('sqlite', \PDO::getAvailableDrivers(), true) ? 'PASS' : 'FAIL';
            if ($checks['sqlite_driver'] === 'FAIL') { $failures[] = 'SQLITE_DRIVER_UNAVAILABLE'; }
        }
        $checks['tor_transport_configuration'] = 'PASS'; // NodeConfig already restricts loopback SOCKS and finite timeout.
        $checks['server_search_disabled'] = $config->features['server_search'] ? 'WARN' : 'PASS';
        if ($config->features['server_search']) { $warnings[] = 'SERVER_SEARCH_ENABLED_QUERIES_SENT_TO_NODE'; }
        if (in_array($config->role, ['client-host', 'combined'], true)) {
            // D-028: the browser files this host serves must be exactly those its build manifest names.
            $checks['client_assets'] = self::clientAssets(dirname(__DIR__, 3) . '/web-local-search-client/dist');
            if ($checks['client_assets'] === 'WARN') { $warnings[] = 'CLIENT_NOT_BUILT'; } elseif ($checks['client_assets'] === 'FAIL') { $failures[] = 'CLIENT_ASSETS_MODIFIED'; }
        }
        if ($config->features['visitor_sources'] ?? false) { $checks['visitor_source_relay'] = 'WARN'; $warnings[] = 'VISITOR_SOURCE_RELAY_ENABLED_OUTBOUND_TO_VISITOR_CHOSEN_NODES'; }
        // Before node:init nothing exists yet: setup in progress, not a fault (node:init creates the store and the export directory).
        $uninitialized = in_array($config->role, ['node', 'combined'], true) && !file_exists($config->storagePath) && !is_link($config->storagePath);
        if ($config->role !== 'publisher' && !($uninitialized && !file_exists($config->exportPath) && !is_link($config->exportPath))) {
            $checks['export_directory'] = !is_link($config->exportPath) && is_dir($config->exportPath) && (fileperms($config->exportPath) & 0077) === 0 ? 'PASS' : 'FAIL';
            if ($checks['export_directory'] === 'FAIL') { $failures[] = 'EXPORT_DIRECTORY_UNSAFE'; }
        }
        if ($config->role !== 'publisher' && ($checks['export_directory'] ?? null) === 'PASS') {
            // Export budget (spec 18 section 17): snapshots fail with EXPORT_BUDGET_EXHAUSTED at the limit.
            $files = 0; $bytes = 0;
            foreach (new \DirectoryIterator($config->exportPath) as $file) {
                if ($file->isDot() || $file->isLink() || !$file->isFile() || preg_match('/\A[0-9a-f]{64}\.json\z/D', $file->getFilename()) !== 1) { continue; }
                ++$files; $bytes += $file->getSize();
                if ($files > self::EXPORT_MAX_FILES) { break; }
            }
            $usage = max($files / self::EXPORT_MAX_FILES, $bytes / self::EXPORT_MAX_BYTES);
            $exportUsage = ['blob_files' => $files, 'blob_bytes' => $bytes, 'max_files' => self::EXPORT_MAX_FILES, 'max_bytes' => self::EXPORT_MAX_BYTES];
            $checks['export_budget'] = $usage >= 1 ? 'FAIL' : ($usage >= 0.8 ? 'WARN' : 'PASS');
            if ($checks['export_budget'] === 'WARN') { $warnings[] = 'EXPORT_BUDGET_NEARLY_EXHAUSTED'; } elseif ($checks['export_budget'] === 'FAIL') { $failures[] = 'EXPORT_BUDGET_EXHAUSTED'; }
            // The front controller's request budgets: an unusable file answers 503, a damaged one is refilled by the next request.
            $budgets = array_map(static fn (string $name): string => \OnionServiceDiscovery\Http\PublicRequestBudget::inspect($config->exportPath . '/' . $name), \OnionServiceDiscovery\Http\PublicRequestBudget::FILES);
            $checks['request_budget_files'] = array_intersect($budgets, ['UNAVAILABLE', 'CORRUPT']) === [] ? 'PASS' : 'WARN';
            if (in_array('UNAVAILABLE', $budgets, true)) { $warnings[] = 'RATE_LIMIT_UNAVAILABLE'; }
            if (in_array('CORRUPT', $budgets, true)) { $warnings[] = 'RATE_LIMIT_CORRUPT'; }
        }
        if ($config->nodeKeyPath !== null) {
            $checks['node_key_permissions'] = !is_link($config->nodeKeyPath) && is_file($config->nodeKeyPath) && (fileperms($config->nodeKeyPath) & 0077) === 0 ? 'PASS' : 'WARN';
            if ($checks['node_key_permissions'] === 'WARN') { $warnings[] = 'NODE_KEY_MISSING_OR_UNSAFE'; }
        }
        $envelope = null;
        if ($config->nodeKeyPath !== null && $checks['node_key_permissions'] === 'PASS') {
            try { $envelope = \OnionServiceDiscovery\Signing\ProtectedFileSigner::inspectEnvelope($config->nodeKeyPath, 'node'); $checks['node_key_envelope_header'] = 'PASS'; }
            catch (\OnionServiceDiscovery\Signing\SignerException) { $checks['node_key_envelope_header'] = 'FAIL'; $failures[] = 'NODE_KEY_ENVELOPE_INVALID'; }
        }
        $state = null; $ledger = $config->nodeKeyPath === null || in_array($config->role, ['client-host','publisher'], true) ? 'NOT_APPLICABLE' : 'NOT_CHECKED';
        if ($uninitialized) { $checks['node_descriptor'] = 'WARN'; $warnings[] = 'NODE_NOT_INITIALIZED'; }
        elseif (!in_array($config->role, ['client-host','publisher'], true)) {
            try {
                $store = $config->store(readOnly: true); $state = $store->snapshot(); $store->integrityCheck(); $checks['storage_integrity'] = 'PASS';
                if ($store instanceof \OnionServiceDiscovery\Storage\FilesystemStore && $store->formatVersion() === 1) { $warnings[] = 'FILESYSTEM_FORMAT_MIGRATION_REQUIRED'; }
                // Record and state budgets: at the limit admission stops with STORAGE_BUDGET_EXHAUSTED.
                $storageUsage = \OnionServiceDiscovery\Storage\StorageTransaction::usage($state, new \OnionServiceDiscovery\Storage\StorageLimits()) + ['record_pruning' => $store->supportsPruning() ? 'ENABLED' : 'MIGRATION_REQUIRED'];
                $checks['storage_budget'] = $storageUsage['state'];
                if ($storageUsage['state'] === 'WARN') { $warnings[] = 'STORAGE_BUDGET_NEARLY_EXHAUSTED'; }
                if (!$store->supportsPruning()) { $warnings[] = 'STORAGE_PRUNING_MIGRATION_REQUIRED'; }
                $descriptor = $state['tables']['node']['key:descriptor'] ?? null;
                if ($descriptor === null) { $checks['node_descriptor'] = 'WARN'; $warnings[] = 'NODE_NOT_INITIALIZED'; }
                else {
                    $wire = $store->readRecord($descriptor['hash']);
                    if ($wire === null) { throw new StorageException('STORAGE_CORRUPT'); }
                    $record = $verifier->verifyWire($wire); $doc = $record->document();
                    $checks['node_descriptor'] = $record->type === 'node_descriptor' && $record->contentHash === $descriptor['hash'] && $doc->node_onion === $config->nodeOnion && ($envelope === null || $envelope['public_key'] === $doc->node_identity_public_key) ? 'PASS' : 'FAIL';
                    if ($checks['node_descriptor'] === 'FAIL') { $failures[] = 'NODE_DESCRIPTOR_MISMATCH'; }
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
                    // The snapshot timer republishes at least daily, so an older checkpoint means it stopped.
                    $created = (int) strtotime((string) $checkpoint->document()->created_at);
                    $scheduler['snapshot'] = time() - $created > self::SNAPSHOT_STALE_AFTER_SECONDS ? 'STALE' : 'RECENT';
                    if ($scheduler['snapshot'] === 'STALE') { $warnings[] = 'SNAPSHOT_TIMER_STALE'; }
                    if ($config->features['server_search']) {
                        try {
                            \OnionServiceDiscovery\Search\SearchIndex::load($store, $blobs, $verifier, static function (): void {});
                            $checks['server_search_index'] = 'PASS';
                        } catch (\Throwable) { $checks['server_search_index'] = 'FAIL'; $failures[] = 'SERVER_SEARCH_INDEX_UNAVAILABLE_OR_CORRUPT'; }
                    }
                }
                if ($ledger === 'NOT_CHECKED') {
                    $ledger = self::signingLedger($config, $verifier, $store, $state, $envelope['public_key'] ?? null);
                    if ($ledger === 'AHEAD_OF_STORE') { $failures[] = 'SIGNING_LEDGER_AHEAD_OF_STORE'; } elseif ($ledger === 'INVALID') { $failures[] = 'SIGNING_LEDGER_INVALID'; }
                }
                $copy = ($checks['export_directory'] ?? null) === 'PASS' ? self::publishedStateCopy($config, $verifier, $state) : ($published === null ? null : 'STALE');
                if ($copy === 'STALE') { $checks['published_state_copy'] = 'WARN'; $warnings[] = 'PUBLISHED_STATE_STALE'; } elseif ($copy === 'PASS') { $checks['published_state_copy'] = 'PASS'; }
            } catch (StorageException $error) { $checks[str_starts_with($error->getMessage(), 'EXPORT_') ? 'snapshot_exports' : 'storage_integrity'] = 'FAIL'; $failures[] = $error->getMessage(); }
            catch (\InvalidArgumentException) { $checks['node_descriptor'] = 'FAIL'; $failures[] = 'NODE_DESCRIPTOR_INVALID'; }
        }
        $latestSync = 0; $quarantined = 0;
        foreach ($state['tables']['sync'] ?? [] as $row) { $latestSync = max($latestSync, $row['last_sync'] ?? 0); }
        foreach ($state['tables']['peers'] ?? [] as $row) { if (($row['state'] ?? null) === 'QUARANTINED') { ++$quarantined; } }
        $concentration = \OnionServiceDiscovery\Node\PeerRegistry::concentration($state['tables']['peers'] ?? []);
        if ($concentration['concentrated']) { $warnings[] = 'SOURCE_SET_CONCENTRATED'; }
        if ($concentration['grouping_inconsistent']) { $warnings[] = 'SOURCE_GROUP_ASSIGNMENTS_INCONSISTENT'; }
        $checks['source_concentration'] = $concentration['concentrated'] || $concentration['grouping_inconsistent'] ? 'WARN' : 'PASS';
        // What the public sees of this Node (the status page's storage state) and, if needed, the next step.
        $nodeHealth = NodeStatus::inspect($config, $verifier, time())['storage_state'] ?? null;
        $health = ['node_health' => $nodeHealth ?? 'NOT_APPLICABLE', 'next_action' => ($nodeHealth === null ? null : NodeStatus::nextAction($nodeHealth)) ?? \OnionServiceDiscovery\Node\SigningLedger::nextAction($ledger)
            ?? (in_array('NODE_NOT_INITIALIZED', $warnings, true) ? NodeStatus::nextAction('NOT_INITIALIZED') : null), 'signing_ledger' => $ledger];
        return ['status' => $failures !== [] ? 'FAIL' : ($warnings !== [] ? 'WARN' : 'PASS'), 'software_version' => '0.1.0-dev', 'php_version' => PHP_VERSION, 'protocol_versions' => [1], 'db_schema' => $state['storage_schema'] ?? null, 'role' => $config->role, 'config_state' => 'PASS', 'crypto_state' => 'RUNTIME_EXTENSIONS_ONLY', 'key_authentication_state' => 'NOT_UNLOCKED_UNAUTHENTICATED_HEADER', 'tor_transport_state' => 'CONFIGURED_NOT_PROBED', 'manifest_state' => $publication['manifest_state'] ?? 'NOT_APPLICABLE', 'publication' => $publication, 'sync_state' => ['latest_sync_epoch' => $latestSync, 'quarantined_peers' => $quarantined], 'source_concentration' => $concentration, 'scheduler_state' => $scheduler === [] ? 'NOT_OBSERVED' : $scheduler, 'export_usage' => $exportUsage, 'storage_usage' => $storageUsage, 'backup_state' => 'NOT_OBSERVED', 'disk_state' => ['free_bytes' => is_dir($config->exportPath) ? disk_free_space($config->exportPath) : null], 'security_check_summary' => $checks, 'warnings' => $warnings, 'failures' => array_values(array_unique($failures)), 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'), 'network_side_effects' => false, 'production_approval' => 'OPEN'] + $health;
    }

    /**
     * D-045: degraded serving uses a copy of exactly the store's published snapshot and Node descriptor.
     * PASS when the export directory's copy matches the store, STALE when it is missing or differs, null
     * when there is nothing to compare (no published snapshot and no copy).
     * @param array<array-key,mixed> $state
     */
    public static function publishedStateCopy(NodeConfig $config, RecordVerifier $verifier, array $state): ?string
    {
        $published = $state['tables']['node']['key:published-snapshot'] ?? null; $descriptor = $state['tables']['node']['key:descriptor'] ?? null;
        $copy = \OnionServiceDiscovery\Node\PublishedState::load(new \OnionServiceDiscovery\Storage\PublicBlobStore($config->exportPath), $verifier, $config->nodeOnion);
        if (($copy === null ? null : [$copy->checkpoint->contentHash, $copy->nodeDescriptor?->contentHash]) !== ($published === null ? null : [$published['hash'], $descriptor['hash'] ?? null])) { return 'STALE'; }
        return $copy === null ? null : 'PASS';
    }

    /**
     * The signing ledger beside the Node key compared with the store (SigningLedger::inspect): NOT_APPLICABLE
     * without a Node key. The key comes from the unauthenticated envelope header, or else from the store.
     * @param array<array-key,mixed> $state
     */
    public static function signingLedger(NodeConfig $config, RecordVerifier $verifier, \OnionServiceDiscovery\Storage\StorageBackend $store, array $state, ?string $publicKey = null): string
    {
        if ($config->nodeKeyPath === null || in_array($config->role, ['client-host','publisher'], true)) { return 'NOT_APPLICABLE'; }
        try {
            if ($publicKey === null) {
                try { $publicKey = \OnionServiceDiscovery\Signing\ProtectedFileSigner::inspectEnvelope($config->nodeKeyPath, 'node')['public_key']; }
                catch (\Throwable) { $publicKey = NodeStatus::identity($config, $verifier, $store, $state)['node_identity_public_key'] ?? null; }
            }
            return \OnionServiceDiscovery\Node\SigningLedger::forKey($config->nodeKeyPath)->inspect(is_string($publicKey) ? $publicKey : null, $store, $verifier, $state);
        } catch (\Throwable) { return 'NOT_CHECKED'; } // A store that cannot be compared is reported by the storage checks.
    }

    /**
     * Focused security checks for security:check (spec 32 section 9, spec 18 section 53), each with a
     * remediation. They read local files only; nothing is changed and no request is made.
     * @return list<array{check:string,status:string,remediation:string}>
     */
    public static function security(NodeConfig $config, string $configPath, string $root): array
    {
        $findings = [];
        $add = static function (string $check, string $status, string $remediation) use (&$findings): void { $findings[] = ['check' => $check, 'status' => $status, 'remediation' => $remediation]; };
        $mode = @fileperms($configPath);
        // The file holds no keys, but others must not change it, and paths in it describe the installation.
        $add('configuration_file_private', $mode === false || ($mode & 0022) !== 0 ? 'FAIL' : (($mode & 0044) !== 0 ? 'WARN' : 'PASS'),
            'chmod 600 the configuration file and own it by the service user that runs the web server and the timers.');
        $directoryMode = @fileperms(dirname($configPath));
        $add('configuration_directory_private', $directoryMode !== false && ($directoryMode & 0077) === 0 ? 'PASS' : 'WARN', 'chmod 700 the directory that holds the configuration.');
        // Tor's own secret key never belongs near OSD files: OSD neither needs nor reads it.
        $torKeys = 0;
        foreach (array_unique([dirname($configPath), $config->storagePath, dirname($config->storagePath), $config->exportPath]) as $directory) {
            foreach (['hs_ed25519_secret_key', 'private_key'] as $name) { if (file_exists($directory . '/' . $name)) { ++$torKeys; } }
        }
        $add('tor_secret_key_absent', $torKeys === 0 ? 'PASS' : 'FAIL', 'Move Tor’s hs_ed25519_secret_key out of every OSD directory; keep it only in the HiddenServiceDir that Tor reads.');
        // Only the front controller and the installed public records belong in the web root.
        $public = $root . '/node-php/public'; $unexpected = [];
        foreach (is_dir($public) ? scandir($public) ?: [] : [] as $name) {
            if (in_array($name, ['.', '..', 'index.php', '.well-known', '.gitkeep'], true)) { continue; }
            $unexpected[] = $name;
        }
        foreach (is_dir($public . '/.well-known') ? scandir($public . '/.well-known') ?: [] : [] as $name) {
            if (in_array($name, ['.', '..', 'onion-service-discovery.json', 'onion-service-discovery-history.json', '.publisher.lock'], true)) { continue; }
            $unexpected[] = '.well-known/' . $name;
        }
        $add('web_root_contents', $unexpected === [] ? 'PASS' : 'WARN', $unexpected === [] ? 'node-php/public holds only the front controller and the installed public records.'
            : 'Remove from node-php/public: ' . implode(', ', array_slice($unexpected, 0, 16)) . '. Keep configuration, keys, backups and stores outside the web root.');
        // A release package lists the hash of every file it shipped.
        $manifest = $root . '/release/FILE-MANIFEST.json';
        if (is_file($manifest) && !is_link($manifest) && filesize($manifest) <= 8388608) {
            $listed = json_decode((string) file_get_contents($manifest), true, 4, JSON_THROW_ON_ERROR); $changed = 0; $missing = 0;
            foreach (is_array($listed) ? $listed : [] as $name => $hash) {
                if (!is_string($name) || !is_string($hash) || str_contains($name, '..') || str_starts_with($name, 'release/')) { continue; }
                $path = $root . '/' . $name;
                if (!is_file($path)) { ++$missing; } elseif (hash_file('sha256', $path) !== $hash) { ++$changed; }
            }
            $add('release_files_unchanged', $changed === 0 && $missing === 0 ? 'PASS' : 'FAIL', $changed === 0 && $missing === 0 ? 'Every packaged file matches release/FILE-MANIFEST.json.'
                : $changed . ' packaged file(s) changed and ' . $missing . ' missing since packaging. Reinstall the package from a verified download.');
        } else {
            $add('release_files_unchanged', 'NOT_APPLICABLE', 'A source checkout has no release/FILE-MANIFEST.json; verify the checkout with git instead.');
        }
        $add('remote_administration', 'PASS', 'OSD has no remote administration interface; every change is a local command.');
        return $findings;
    }
    /** WARN when the client is not built, FAIL when a served file is missing or differs from build-manifest.json. */
    private static function clientAssets(string $dist): string
    {
        $manifestPath = $dist . '/build-manifest.json';
        if (is_link($dist) || !is_file($manifestPath)) { return 'WARN'; }
        try {
            $raw = file_get_contents($manifestPath, false, null, 0, 65537);
            if ($raw === false || strlen($raw) > 65536) { return 'FAIL'; }
            $manifest = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) { return 'FAIL'; }
        if (!is_array($manifest) || !is_array($manifest['files'] ?? null) || $manifest['files'] === [] || count($manifest['files']) > 64) { return 'FAIL'; }
        foreach ($manifest['files'] as $file) {
            if (!is_array($file) || !is_string($file['file'] ?? null) || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]{0,63}\z/D', $file['file']) !== 1 || !is_string($file['sha256'] ?? null) || !is_int($file['bytes'] ?? null)) { return 'FAIL'; }
            $path = $dist . '/' . $file['file'];
            if (is_link($path) || !is_file($path) || filesize($path) !== $file['bytes'] || hash_file('sha256', $path) !== $file['sha256']) { return 'FAIL'; }
        }
        return 'PASS';
    }
}
