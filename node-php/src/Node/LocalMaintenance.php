<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageTransaction;

/**
 * Bounded housekeeping: replaceable remote assertions, and signed checkpoints nothing needs any
 * more. Never identity, history or fork evidence: service records, Node descriptors and every
 * checkpoint named by conflict evidence or by a peer's anchor or pending sync stay.
 */
final readonly class LocalMaintenance
{
    /** The Node's own newest checkpoints kept for D-052 ancestry walks by publishers and peers (they walk at most 32). */
    public const OWN_CHECKPOINT_RETENTION = 256;
    public function __construct(private StorageBackend $store, private int $ownCheckpointRetention = self::OWN_CHECKPOINT_RETENTION)
    { if ($ownCheckpointRetention < 64 || $ownCheckpointRetention > 4096) { throw new ProtocolException('CONFIGURATION_INVALID'); } }
    /** @return array<string,int|string> */
    public function run(int $now): array
    {
        if ($now < 0 || $now > 9007199254740991) { throw new ProtocolException('CLOCK_INVALID'); }
        $assertions = $this->store->transaction(static function (StorageTransaction $tx) use ($now): array {
            $state = $tx->state(); $keys = array_keys($state['tables']['assertions']); sort($keys, SORT_STRING);
            $count = count($keys); $offset = ($tx->get('maintenance', 'assertion-scan')['offset'] ?? 0) % max(1, $count);
            $scanned = min(256, $count); $removed = 0;
            for ($index = 0; $index < $scanned; ++$index) {
                $key = substr((string)$keys[($offset + $index) % $count], 4); $row = $tx->get('assertions', $key);
                if ($row === null || ($row['kind'] ?? null) !== 'REMOTE_PROVENANCE' || !is_array($row['sources'] ?? null)) { throw new ProtocolException('ASSERTION_STATE_INVALID'); }
                $sources = array_values(array_filter($row['sources'], static fn (array $source): bool => $source['expires_at'] > $now));
                if ($sources === []) { $tx->deleteOperational('assertions', $key); ++$removed; }
                elseif ($sources !== $row['sources']) { $row['sources'] = $sources; $tx->set('assertions', $key, $row); }
            }
            $tx->set('maintenance', 'assertion-scan', ['offset' => ($offset + $scanned) % max(1, $count - $removed)]);
            return ['assertions_scanned' => $scanned, 'assertions_removed' => $removed];
        });
        return $assertions + $this->prune();
    }

    /**
     * Removes at most 256 superseded checkpoints and 512 stale checkpoint witnesses per run, oldest
     * first, in one transaction over exactly the state they were chosen from (three attempts).
     * @return array{signed_records_removed:int,checkpoint_witnesses_removed:int,record_pruning:string}
     */
    private function prune(): array
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            [$state, $superseded, $stale] = $this->candidates();
            if ($superseded === [] && $stale === []) { return ['signed_records_removed' => 0, 'checkpoint_witnesses_removed' => 0, 'record_pruning' => $this->store->supportsPruning() ? 'ENABLED' : 'MIGRATION_REQUIRED']; }
            $work = static function (array $records) use ($state, $stale): \Closure {
                return static function (StorageTransaction $tx) use ($records, $state, $stale): void {
                    if ($tx->state()['generation'] !== $state['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
                    foreach ($stale as $key) { $tx->deleteOperational('checkpoint_evidence', $key); }
                    foreach ($records as $hash) { $tx->removeRecord($hash); }
                };
            };
            try {
                try { $this->store->transaction($work($superseded)); $pruning = 'ENABLED'; }
                catch (StorageException $error) {
                    // A store not yet migrated to schema 2 keeps every signed record; witnesses are still bounded.
                    if ($error->getMessage() !== 'STORAGE_MIGRATION_REQUIRED') { throw $error; }
                    $this->store->transaction($work([])); $pruning = 'MIGRATION_REQUIRED'; $superseded = [];
                }
                return ['signed_records_removed' => count($superseded), 'checkpoint_witnesses_removed' => count($stale), 'record_pruning' => $pruning];
            } catch (ProtocolException $error) { if ($error->getMessage() !== 'EVIDENCE_CHANGED_RETRY') { throw $error; } }
        }
        return ['signed_records_removed' => 0, 'checkpoint_witnesses_removed' => 0, 'record_pruning' => 'RETRY_NEXT_RUN'];
    }

    /** @return array{0:array<array-key,mixed>,1:list<string>,2:list<string>} */
    private function candidates(): array
    {
        $state = $this->store->snapshot(); $keep = []; $parents = [];
        // Checkpoints are read in batches (one state read each), then the own chain is walked back from the published one.
        $checkpoints = array_keys(array_filter($state['records'], static fn (array $metadata): bool => $metadata['record_type'] === 'snapshot_descriptor'));
        foreach (array_chunk($checkpoints, 256) as $batch) {
            foreach ($this->store->readRecords($batch) as $hash => $wire) { $parents[$hash] = json_decode($wire, false, 64, JSON_THROW_ON_ERROR)->previous_checkpoint_hash ?? null; }
        }
        $hash = $state['tables']['node']['key:published-snapshot']['hash'] ?? null;
        for ($kept = 0; is_string($hash) && array_key_exists($hash, $parents) && $kept < $this->ownCheckpointRetention; ++$kept) { $keep[$hash] = true; $hash = $parents[$hash]; }
        $anchors = [];
        foreach ($state['tables']['peers'] as $onion => $peer) {
            if (!is_string($peer['node_identity_public_key'] ?? null)) { continue; }
            $node = hash('sha256', $peer['node_identity_public_key']);
            $anchors[$node] = max($anchors[$node] ?? 0, (int) ($state['tables']['sync'][$onion]['generation'] ?? 0));
        }
        foreach ($state['tables']['sync'] as $sync) {
            foreach (['checkpoint_hash', 'pending_hash'] as $field) { if (is_string($sync[$field] ?? null)) { $keep[$sync[$field]] = true; } }
        }
        $stale = [];
        foreach ($state['tables']['checkpoint_evidence'] as $rowKey => $row) {
            if (!str_starts_with((string) $rowKey, 'key:checkpoint:')) { continue; }
            // Witnesses below a peer's verified anchor (or of no configured peer) no longer detect anything; conflicts stay.
            [, , $node, $generation] = explode(':', (string) $rowKey);
            if ($row['equivocated'] !== true && (!isset($anchors[$node]) || (int) $generation < $anchors[$node]) && count($stale) < 512) { $stale[] = substr((string) $rowKey, 4); continue; }
            foreach ($row['hashes'] as $witness) { $keep[$witness] = true; }
        }
        $superseded = array_filter($state['records'], static fn (array $metadata, string $hash): bool => $metadata['record_type'] === 'snapshot_descriptor' && !isset($keep[$hash]), ARRAY_FILTER_USE_BOTH);
        uasort($superseded, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        return [$state, array_slice(array_map('strval', array_keys($superseded)), 0, 256), $stale];
    }
}
