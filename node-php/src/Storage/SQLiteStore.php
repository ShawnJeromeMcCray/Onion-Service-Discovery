<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

/** Selected-backend-only PDO adapter with immutable rows and atomic cursor/state commits. */
final class SQLiteStore implements StorageBackend
{
    private readonly \PDO $database;
    private readonly StorageLimits $limits;
    private readonly ?\Closure $faultHook;
    private readonly string $path;

    public function __construct(string $path, ?StorageLimits $limits = null, bool $create = false, ?\Closure $faultHook = null, private readonly bool $readOnly = false)
    {
        if ($create && $readOnly) { throw new StorageException('STORAGE_MODE_INVALID'); }
        $this->limits = $limits ?? new StorageLimits();
        $this->faultHook = $faultHook;
        $this->path = $path;
        if (!class_exists(\PDO::class) || !in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            throw new StorageException('SQLITE_DRIVER_UNAVAILABLE');
        }
        if (in_array(basename($path), ['hs_ed25519_secret_key', 'private_key'], true) || str_contains($path, '://') || is_link($path) || (!is_file($path) && !$create)) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        if (!is_dir(dirname($path)) && (!$create || !mkdir(dirname($path), 0700, true))) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        $existing = is_file($path);
        if (!$existing) {
            $created = fopen($path, 'x+b');
            if ($created === false || !chmod($path, 0600)) {
                throw new StorageException('STORAGE_UNAVAILABLE');
            }
            fclose($created);
        } elseif ((fileperms($path) & 0077) !== 0) {
            throw new StorageException('STORAGE_PERMISSIONS_UNSAFE');
        }
        try {
            $this->database = new \PDO($readOnly ? 'sqlite:file:' . rawurlencode($path) . '?mode=ro' : 'sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
            $this->database->exec('PRAGMA foreign_keys=ON');
            $this->database->exec('PRAGMA synchronous=FULL');
            $this->database->exec('PRAGMA busy_timeout=' . $this->limits->lockWaitMilliseconds);
            if ($readOnly) { $this->database->exec('PRAGMA query_only=ON'); }
            else { $this->database->exec('PRAGMA journal_mode=WAL'); }
            if (!$existing) {
                chmod($path, 0600);
                $this->initialize();
            }
            if ((int) $this->query('PRAGMA user_version')->fetchColumn() !== 1 || (int) $this->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
                throw new StorageException('STORAGE_VERSION_UNSUPPORTED');
            }
            $this->snapshot();
        } catch (\PDOException) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
    }

    private function query(string $sql): \PDOStatement
    {
        $statement = $this->database->query($sql);
        if ($statement === false) { throw new StorageException('STORAGE_ERROR'); }
        return $statement;
    }
    private function prepare(string $sql): \PDOStatement
    {
        $statement = $this->database->prepare($sql);
        if ($statement === false) { throw new StorageException('STORAGE_ERROR'); }
        return $statement;
    }

    public function backendName(): string { return 'sqlite'; }

    /** Bound WAL cleanup without rewriting immutable history. Busy readers preserve the WAL.
     * @return array<string,int>
     */
    public function checkpoint(): array
    {
        if ($this->readOnly) { throw new StorageException('STORAGE_READ_ONLY'); }
        try {
            $row = $this->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(\PDO::FETCH_NUM);
            if (!is_array($row) || count($row) !== 3) { throw new StorageException('STORAGE_ERROR'); }
            return ['busy' => (int)$row[0], 'log_frames' => (int)$row[1], 'checkpointed_frames' => (int)$row[2]];
        } catch (\PDOException) { throw new StorageException('STORAGE_BUSY'); }
    }

    private function initialize(): void
    {
        $this->database->exec('BEGIN IMMEDIATE');
        try {
            $this->database->exec('CREATE TABLE metadata (name TEXT PRIMARY KEY, value INTEGER NOT NULL CHECK(value>=0)) WITHOUT ROWID');
            $this->database->exec('CREATE TABLE immutable_objects (object_hash TEXT PRIMARY KEY CHECK(length(object_hash)=64), wire TEXT NOT NULL) WITHOUT ROWID');
            $this->database->exec('CREATE TABLE records (content_hash TEXT PRIMARY KEY CHECK(length(content_hash)=71), object_hash TEXT NOT NULL REFERENCES immutable_objects(object_hash), record_type TEXT NOT NULL, sequence INTEGER NOT NULL UNIQUE CHECK(sequence>0), service_key TEXT, onion TEXT, revision INTEGER) WITHOUT ROWID');
            $this->database->exec('CREATE TABLE operational_state (table_name TEXT NOT NULL, entry_key TEXT NOT NULL, value TEXT NOT NULL, PRIMARY KEY(table_name,entry_key)) WITHOUT ROWID');
            $this->database->exec('CREATE INDEX records_onion_revision ON records(onion, revision)');
            $this->database->exec('CREATE INDEX records_service_key_revision ON records(service_key, revision)');
            $this->database->exec("INSERT INTO metadata(name,value) VALUES ('generation',0),('last_sequence',0)");
            $this->database->exec('PRAGMA user_version=1');
            $this->database->exec('COMMIT');
        } catch (\Throwable $error) {
            $this->rollbackQuietly();
            throw $error;
        }
    }

    /**
     * Ends a transaction this store began with SQL. PDO::inTransaction() does not report such
     * transactions before PHP 8.4, and SQLITE_FULL/IOERR may already have rolled one back.
     */
    private function rollbackQuietly(): void
    {
        try { $this->database->exec('ROLLBACK'); } catch (\PDOException) {}
    }

    public function transaction(callable $mutation): mixed
    {
        if ($this->readOnly) { throw new StorageException('STORAGE_READ_ONLY'); }
        if ($this->diskUsage() >= $this->limits->maxBytes) {
            throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
        }
        try {
            $this->database->exec('BEGIN IMMEDIATE');
        } catch (\PDOException) {
            throw new StorageException('STORAGE_BUSY');
        }
        $committed = false;
        try {
            $previous = $this->readState();
            $transaction = new StorageTransaction($previous, $this->limits);
            $result = $mutation($transaction);
            if (!$transaction->changed()) {
                $this->database->exec('ROLLBACK');
                return $result;
            }
            $state = $transaction->state();
            if ($state['generation'] >= 9007199254740991 || strlen(StorageJson::encode($state)) > $this->limits->maxCheckpointBytes) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            $state['generation']++;
            $incoming = array_sum(array_map('strlen', $transaction->newObjects())) + strlen(StorageJson::encode($state)) * 2 + 4096;
            if ($this->diskUsage() + $incoming > $this->limits->maxBytes) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            $insertObject = $this->prepare('INSERT INTO immutable_objects(object_hash,wire) VALUES (:hash,:wire) ON CONFLICT(object_hash) DO NOTHING');
            foreach ($transaction->newObjects() as $hash => $wire) {
                $insertObject->execute(['hash' => $hash, 'wire' => $wire]);
                $check = $this->prepare('SELECT wire FROM immutable_objects WHERE object_hash=:hash');
                $check->execute(['hash' => $hash]);
                if ($check->fetchColumn() !== $wire) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
                $check->closeCursor();
            }
            $this->fault('after_objects');
            $insertRecord = $this->prepare('INSERT INTO records(content_hash,object_hash,record_type,sequence,service_key,onion,revision) VALUES (:hash,:object,:type,:sequence,:service_key,:onion,:revision)');
            foreach (array_diff_key($state['records'], $previous['records']) as $hash => $metadata) {
                $wire = $transaction->newObjects()[$metadata['object_hash']];
                if (!is_string($wire)) { throw new StorageException('STORAGE_CORRUPT'); }
                $document = json_decode($wire, true, 32, JSON_THROW_ON_ERROR);
                $insertRecord->execute(['hash' => $hash, 'object' => $metadata['object_hash'], 'type' => $metadata['record_type'], 'sequence' => $metadata['sequence'],
                    'service_key' => $document['service_identity_public_key'] ?? null, 'onion' => $document['onion'] ?? null, 'revision' => $document['revision'] ?? null]);
            }
            $this->fault('after_records');
            $set = $this->prepare('INSERT INTO operational_state(table_name,entry_key,value) VALUES (:table_name,:entry_key,:value) ON CONFLICT(table_name,entry_key) DO UPDATE SET value=excluded.value');
            $delete = $this->prepare('DELETE FROM operational_state WHERE table_name=:table_name AND entry_key=:entry_key');
            foreach (StorageTransaction::TABLES as $table) {
                foreach ($state['tables'][$table] as $key => $value) {
                    if (($previous['tables'][$table][$key] ?? null) !== $value) {
                        $set->execute(['table_name' => $table, 'entry_key' => $key, 'value' => StorageJson::encode($value)]);
                    }
                }
                foreach (array_diff_key($previous['tables'][$table], $state['tables'][$table]) as $key => $_) {
                    $delete->execute(['table_name' => $table, 'entry_key' => $key]);
                }
            }
            $this->fault('before_cursor_commit');
            $metadata = $this->prepare('UPDATE metadata SET value=:value WHERE name=:name');
            $metadata->execute(['name' => 'generation', 'value' => $state['generation']]);
            $metadata->execute(['name' => 'last_sequence', 'value' => $state['last_sequence']]);
            $this->database->exec('COMMIT');
            $committed = true;
            $this->fault('after_commit');
            return $result;
        } catch (\Throwable $error) {
            if (!$committed) { $this->rollbackQuietly(); }
            if ($error instanceof StorageException || $error instanceof \OnionServiceDiscovery\Protocol\ProtocolException) {
                throw $error;
            }
            throw new StorageException('STORAGE_ERROR');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function snapshot(): array
    {
        $begun = false;
        try {
            $this->database->exec('BEGIN');
            $begun = true;
            $state = $this->readState();
            $this->database->exec('COMMIT');
            return $state;
        } catch (\Throwable $error) {
            // Never roll back a transaction this call did not begin.
            if ($begun) { $this->rollbackQuietly(); }
            if ($error instanceof StorageException) {
                throw $error;
            }
            throw new StorageException('STORAGE_CORRUPT');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readState(): array
    {
        $state = StorageTransaction::emptyState();
        $metadata = $this->query('SELECT name,value FROM metadata')->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (count($metadata) !== 2 || !isset($metadata['generation'], $metadata['last_sequence'])) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $state['generation'] = (int) $metadata['generation'];
        $state['last_sequence'] = (int) $metadata['last_sequence'];
        $rows = $this->query('SELECT content_hash,object_hash,record_type,sequence FROM records ORDER BY content_hash LIMIT ' . ($this->limits->maxRecords + 1));
        foreach ($rows as $row) {
            $state['records'][$row['content_hash']] = ['object_hash' => $row['object_hash'], 'record_type' => $row['record_type'], 'sequence' => (int) $row['sequence']];
        }
        foreach (StorageTransaction::TABLES as $table) {
            $rows = $this->prepare('SELECT entry_key, CASE WHEN LENGTH(CAST(value AS BLOB)) <= ' . $this->limits->maxValueBytes . ' THEN value ELSE NULL END AS value FROM operational_state WHERE table_name=:name ORDER BY entry_key LIMIT ' . ($this->limits->maxOperationalRows + 1));
            $rows->execute(['name' => $table]);
            foreach ($rows as $row) {
                if (!is_string($row['value'])) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
                $state['tables'][$table][$row['entry_key']] = StorageJson::decode($row['value'], $this->limits->maxValueBytes);
            }
        }
        $unexpected = $this->query('SELECT DISTINCT table_name FROM operational_state')->fetchAll(\PDO::FETCH_COLUMN);
        if (array_diff($unexpected, StorageTransaction::TABLES) !== []) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        StorageTransaction::validateState($state, $this->limits);
        return $state;
    }

    /**
     * @param array<array-key, mixed> $contentHashes
     * @return array<array-key, mixed>
     */
    public function readRecords(array $contentHashes): array
    {
        if (count($contentHashes) > 256) { throw new StorageException('WORK_BUDGET_EXHAUSTED'); }
        $result = [];
        foreach ($contentHashes as $hash) {
            if (!is_string($hash)) { throw new StorageException('STORAGE_ADDRESS_INVALID'); }
            $wire = $this->readRecord((string) $hash);
            if ($wire !== null) { $result[$hash] = $wire; }
        }
        return $result;
    }

    public function readRecord(string $contentHash): ?string
    {
        if (strlen($contentHash) !== 71 || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $contentHash) !== 1) {
            throw new StorageException('STORAGE_ADDRESS_INVALID');
        }
        try {
            $query = $this->prepare('SELECT CASE WHEN LENGTH(CAST(o.wire AS BLOB)) <= 65536 THEN o.wire ELSE NULL END AS wire,o.object_hash FROM records r JOIN immutable_objects o ON r.object_hash=o.object_hash WHERE r.content_hash=:hash');
            $query->execute(['hash' => $contentHash]);
            $row = $query->fetch();
            if ($row === false) {
                return null;
            }
            if (!is_string($row['wire']) || strlen($row['wire']) > 65536 || !hash_equals($row['object_hash'], hash('sha256', $row['wire']))) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            return $row['wire'];
        } catch (\PDOException) {
            throw new StorageException('STORAGE_CORRUPT');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function integrityCheck(): array
    {
        try {
            if ($this->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $this->query('PRAGMA foreign_key_check')->fetch() !== false) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            $state = $this->snapshot();
            foreach (array_keys($state['records']) as $hash) {
                if ($this->readRecord((string) $hash) === null) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
            }
            return ['backend' => 'sqlite', 'storage_schema' => 1, 'generation' => $state['generation'], 'records' => count($state['records']), 'integrity' => 'PASS'];
        } catch (\PDOException) {
            throw new StorageException('STORAGE_CORRUPT');
        }
    }

    private function diskUsage(): int
    {
        clearstatcache();
        $bytes = 0;
        foreach ([$this->path, $this->path . '-wal', $this->path . '-shm'] as $path) {
            if (is_link($path)) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            if (is_file($path)) {
                $bytes += filesize($path);
            }
        }
        return $bytes;
    }

    private function fault(string $stage): void { if ($this->faultHook !== null) { ($this->faultHook)($stage); } }
}
