<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

use OnionServiceDiscovery\Protocol\ValidatedRecord;

final class StorageTransaction
{
    public const TABLES = ['peers', 'candidates', 'candidate_sources', 'quarantine', 'sync', 'node', 'maintenance', 'local_observations', 'assertions', 'snapshots', 'checkpoint_evidence', 'moderation'];
    private bool $changed = false;
    /** Whether the transaction admits a record or writes an operational row (removals only otherwise). */
    private bool $adds = false;
    private int $admissions = 0;
    private int $operationalMutations = 0;
    /**
     * @var array<array-key, mixed>
     */
    private array $newObjects = [];
    /** @var array<string, true> Objects of records removed in this transaction. */
    private array $removedObjects = [];

    /**
     * @param array<array-key, mixed> $state
     */
    public function __construct(private array $state, private readonly StorageLimits $limits)
    {
        self::validateState($state, $limits);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function emptyState(): array
    {
        return ['storage_schema' => 1, 'generation' => 0, 'last_sequence' => 0, 'records' => [], 'tables' => array_fill_keys(self::TABLES, [])];
    }

    /**
     * Schema 2 adds `pruned_records`: sequences of signed records removed by bounded retention
     * (LocalMaintenance). Sequences are never reused, so count(records) + pruned_records is
     * always last_sequence and a lost or invented record still fails validation.
     * @param array<array-key, mixed> $state
     */
    public static function validateState(array $state, StorageLimits $limits): void
    {
        $schema = $state['storage_schema'] ?? null; $pruned = $schema === 2 ? ($state['pruned_records'] ?? null) : 0;
        if (array_keys(array_diff_key($state, self::emptyState() + ($schema === 2 ? ['pruned_records' => 0] : []))) !== [] || !in_array($schema, [1, 2], true) || !is_int($pruned) || $pruned < 0 ||
            !is_int($state['generation'] ?? null) || $state['generation'] < 0 || $state['generation'] > 9007199254740991 ||
            !is_int($state['last_sequence'] ?? null) || $state['last_sequence'] < 0 || $state['last_sequence'] > 9007199254740991 ||
            !is_array($state['records'] ?? null) || !is_array($state['tables'] ?? null) || count($state['records']) > $limits->maxRecords) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        if (array_diff(array_keys($state['tables']), self::TABLES) !== [] || count($state['tables']) !== count(self::TABLES)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $sequences = [];
        foreach ($state['records'] as $hash => $metadata) {
            if (!is_string($hash) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $hash) !== 1 || !is_array($metadata) || count($metadata) !== 3 ||
                !is_string($metadata['object_hash'] ?? null) || preg_match('/\A[0-9a-f]{64}\z/D', $metadata['object_hash']) !== 1 ||
                !is_string($metadata['record_type'] ?? null) || !isset(\OnionServiceDiscovery\Protocol\RecordFormat::TYPES[$metadata['record_type']]) || !is_int($metadata['sequence'] ?? null) ||
                $metadata['sequence'] < 1 || $metadata['sequence'] > $state['last_sequence'] || isset($sequences[$metadata['sequence']])) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            $sequences[$metadata['sequence']] = true;
        }
        if (count($sequences) + $pruned !== $state['last_sequence']) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $rowCount = 0;
        foreach ($state['tables'] as $rows) {
            if (!is_array($rows) || count($rows) > $limits->maxOperationalRows) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            $rowCount += count($rows);
            if ($rowCount > $limits->maxOperationalRows) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            foreach ($rows as $key => $value) {
                if (!is_string($key) || !str_starts_with($key, 'key:') || preg_match('/\A[a-zA-Z0-9_.:\/-]{1,128}\z/D', substr($key, 4)) !== 1 ||
                    !is_array($value) || strlen(StorageJson::encode($value)) > $limits->maxValueBytes) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
            }
        }
    }

    public function putRecord(ValidatedRecord $record): bool
    {
        if (isset($this->state['records'][$record->contentHash])) {
            return false;
        }
        if (++$this->admissions > $this->limits->maxAdmissionsPerTransaction || count($this->state['records']) >= $this->limits->maxRecords || $this->state['last_sequence'] >= 9007199254740991) {
            throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
        }
        $objectHash = hash('sha256', $record->canonicalWire);
        $this->newObjects[$objectHash] = $record->canonicalWire; unset($this->removedObjects[$objectHash]);
        $this->state['records'][$record->contentHash] = ['object_hash' => $objectHash, 'record_type' => $record->type, 'sequence' => ++$this->state['last_sequence']];
        $this->changed = true; $this->adds = true;
        return true;
    }

    /**
     * Bounded retention only (LocalMaintenance): removes a superseded signed record. Its sequence
     * is retired, not reused; the backend refuses the commit until storage:migrate (schema 2).
     */
    public function removeRecord(string $contentHash): bool
    {
        $metadata = $this->state['records'][$contentHash] ?? null;
        if ($metadata === null) { return false; }
        if (++$this->admissions > $this->limits->maxAdmissionsPerTransaction) { throw new StorageException('WORK_BUDGET_EXHAUSTED'); }
        unset($this->state['records'][$contentHash], $this->newObjects[$metadata['object_hash']]);
        $this->removedObjects[$metadata['object_hash']] = true;
        $this->state['storage_schema'] = 2; $this->state['pruned_records'] = ($this->state['pruned_records'] ?? 0) + 1; $this->changed = true;
        return true;
    }

    /** Private backup restore only: retires the sequences a pruned source store no longer holds, so restored records keep theirs. */
    public function retireSequences(int $count): void
    {
        if ($count < 0 || $this->state['last_sequence'] + $count > 9007199254740991) { throw new StorageException('STORAGE_BUDGET_EXHAUSTED'); }
        if ($count === 0) { return; }
        $this->state['last_sequence'] += $count; $this->state['storage_schema'] = 2; $this->state['pruned_records'] = ($this->state['pruned_records'] ?? 0) + $count; $this->changed = true;
    }

    /**
     * Use of the record, state, operational-row and on-disk byte budgets for node:status and doctor; WARN from 80 %.
     * The store's bytes come from $store (both backends report them), so a full disk budget warns before admission stops.
     * @param array<array-key, mixed> $state
     * @return array<string, int|string|null>
     */
    public static function usage(array $state, StorageLimits $limits, ?StorageBackend $store = null): array
    {
        $bytes = strlen(StorageJson::encode($state)); $rows = array_sum(array_map('count', $state['tables']));
        $disk = $store instanceof SQLiteStore || $store instanceof FilesystemStore ? $store->diskBytes() : null;
        $ratio = max(count($state['records']) / $limits->maxRecords, $bytes / $limits->maxCheckpointBytes, $rows / $limits->maxOperationalRows, ($disk ?? 0) / $limits->maxBytes);
        return ['signed_records' => count($state['records']), 'max_records' => $limits->maxRecords, 'pruned_records' => $state['pruned_records'] ?? 0,
            'state_bytes' => $bytes, 'max_state_bytes' => $limits->maxCheckpointBytes, 'operational_rows' => $rows, 'max_operational_rows' => $limits->maxOperationalRows,
            'store_bytes' => $disk, 'max_store_bytes' => $limits->maxBytes,
            'usage_percent' => (int) floor($ratio * 100), 'state' => $ratio >= 1 ? 'FAIL' : ($ratio >= 0.8 ? 'WARN' : 'PASS')];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function get(string $table, string $key): ?array
    {
        $this->validateAddress($table, $key);
        return $this->state['tables'][$table]['key:' . $key] ?? null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function rows(string $table, int $limit = 256): array
    {
        $this->validateAddress($table, 'read');
        if ($limit < 1 || $limit > 256) {
            throw new StorageException('WORK_BUDGET_EXHAUSTED');
        }
        $result = [];
        foreach (array_slice($this->state['tables'][$table], 0, $limit, true) as $key => $value) {
            $result[substr($key, 4)] = $value;
        }
        return $result;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public function set(string $table, string $key, array $value): void
    {
        $this->validateAddress($table, $key);
        $key = 'key:' . $key;
        $bytes = StorageJson::encode($value);
        if (strlen($bytes) > $this->limits->maxValueBytes ||
            (!isset($this->state['tables'][$table][$key]) && array_sum(array_map('count', $this->state['tables'])) >= $this->limits->maxOperationalRows)) {
            throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
        }
        $value = StorageJson::decode($bytes, $this->limits->maxValueBytes);
        if (($this->state['tables'][$table][$key] ?? null) !== $value) {
            if (++$this->operationalMutations > 1024) {
                throw new StorageException('WORK_BUDGET_EXHAUSTED');
            }
            $this->state['tables'][$table][$key] = $value;
            $this->changed = true; $this->adds = true;
        }
    }

    public function deleteOperational(string $table, string $key): void
    {
        $this->validateAddress($table, $key);
        $key = 'key:' . $key;
        if (isset($this->state['tables'][$table][$key])) {
            if (++$this->operationalMutations > 1024) {
                throw new StorageException('WORK_BUDGET_EXHAUSTED');
            }
            unset($this->state['tables'][$table][$key]);
            $this->changed = true;
        }
    }

    public function changed(): bool { return $this->changed; }
    /** Only removals (bounded retention, row deletes): the backends let it commit at the byte budget, since it frees space. */
    public function removalOnly(): bool { return $this->changed && !$this->adds; }
    /**
     * @return array<array-key, mixed>
     */
    public function newObjects(): array { return $this->newObjects; }
    /** @return array<string, true> */
    public function removedObjects(): array { return $this->removedObjects; }
    /**
     * @return array<array-key, mixed>
     */
    public function state(): array { return $this->state; }

    private function validateAddress(string $table, string $key): void
    {
        if (!in_array($table, self::TABLES, true) || preg_match('/\A[a-zA-Z0-9_.:\/-]{1,128}\z/D', $key) !== 1) {
            throw new StorageException('STORAGE_ADDRESS_INVALID');
        }
    }
}
