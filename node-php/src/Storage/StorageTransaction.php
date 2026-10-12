<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

use OnionServiceDiscovery\Protocol\ValidatedRecord;

final class StorageTransaction
{
    public const TABLES = ['peers', 'candidates', 'candidate_sources', 'quarantine', 'sync', 'node', 'maintenance', 'local_observations', 'assertions', 'snapshots', 'checkpoint_evidence', 'moderation'];
    private bool $changed = false;
    private int $admissions = 0;
    private int $operationalMutations = 0;
    /**
     * @var array<array-key, mixed>
     */
    private array $newObjects = [];

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
     * @param array<array-key, mixed> $state
     */
    public static function validateState(array $state, StorageLimits $limits): void
    {
        if (array_keys(array_diff_key($state, self::emptyState())) !== [] || ($state['storage_schema'] ?? null) !== 1 ||
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
        if (count($sequences) !== $state['last_sequence']) {
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
        $this->newObjects[$objectHash] = $record->canonicalWire;
        $this->state['records'][$record->contentHash] = ['object_hash' => $objectHash, 'record_type' => $record->type, 'sequence' => ++$this->state['last_sequence']];
        $this->changed = true;
        return true;
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
            $this->changed = true;
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
    /**
     * @return array<array-key, mixed>
     */
    public function newObjects(): array { return $this->newObjects; }
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
