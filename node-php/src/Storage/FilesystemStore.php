<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

/**
 * Local-filesystem MVCC checkpoint plus a checksummed prepare/commit journal. Format 3 is format 2
 * that may also hold storage schema 2 (records removed by bounded retention); format 2 stays
 * writable but refuses such a commit until storage:migrate, and older releases refuse format 3.
 */
final class FilesystemStore implements StorageBackend
{
    private readonly string $root;
    private readonly StorageLimits $limits;
    private readonly ?\Closure $faultHook;
    public const FORMAT = 3;
    private int $formatVersion = self::FORMAT;

    public function __construct(string $directory, ?StorageLimits $limits = null, bool $create = false, ?\Closure $faultHook = null, private readonly bool $readOnly = false)
    {
        if ($create && $readOnly) { throw new StorageException('STORAGE_MODE_INVALID'); }
        $this->limits = $limits ?? new StorageLimits();
        $this->faultHook = $faultHook; // Test injection only; never populated from configuration.
        if (str_contains($directory, '://') || is_link($directory)) { throw new StorageException('STORAGE_PATH_UNSAFE'); }
        if (!is_dir($directory)) {
            if (!$create || !mkdir($directory, 0700, true)) {
                throw new StorageException('STORAGE_UNAVAILABLE');
            }
        }
        if ((fileperms($directory) & 0077) !== 0) { throw new StorageException('STORAGE_PERMISSIONS_UNSAFE'); }
        $root = realpath($directory);
        if ($root === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        $this->root = $root;
        foreach (['objects', 'checkpoints', 'locks'] as $name) {
            $path = $this->root . '/' . $name;
            if (is_link($path) || (!is_dir($path) && (!$create || !mkdir($path, 0700)))) {
                throw new StorageException('STORAGE_UNAVAILABLE');
            }
            if ((fileperms($path) & 0077) !== 0) { throw new StorageException('STORAGE_PERMISSIONS_UNSAFE'); }
        }
        $this->withLock(function () use ($create): void {
            if (!is_file($this->root . '/HEAD')) {
                if (!$create || is_file($this->root . '/journal.log') || is_file($this->root . '/storage-version')) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
                $bytes = StorageJson::encode(StorageTransaction::emptyState());
                $hash = hash('sha256', $bytes);
                $this->writeImmutable($this->checkpointPath($hash), $bytes);
                $this->atomicWrite($this->root . '/storage-version', (string) self::FORMAT);
                $this->atomicWrite($this->root . '/HEAD', StorageJson::encode(['generation' => 0, 'checkpoint' => $hash]));
                $this->atomicWrite($this->root . '/journal.log', '');
            }
            $version = $this->readBounded($this->root . '/storage-version', 16);
            if (!in_array($version, ['1','2','3'], true)) {
                throw new StorageException('STORAGE_VERSION_UNSUPPORTED');
            }
            $this->formatVersion = (int)$version;
            if (!$this->readOnly && $this->formatVersion === 1) { throw new StorageException('STORAGE_MIGRATION_REQUIRED'); }
            if ($this->readOnly && $this->formatVersion === 1) { $this->loadCheckpoint($this->head()); }
            elseif ($this->readOnly) { $this->recover(false); }
            else { $this->recover(); }
        });
    }

    public function backendName(): string { return 'filesystem'; }
    public function formatVersion(): int { return $this->formatVersion; }
    /** Whether bounded retention may remove signed records (format 3). */
    public function supportsPruning(): bool { return $this->formatVersion >= 3; }

    /** Explicit local migration preserves format-1 recovery semantics before switching formats.
     * @return array<string,mixed>
     */
    public static function migrateFormat(string $directory, ?\Closure $faultHook = null): array
    {
        $store = new self($directory, faultHook: $faultHook, readOnly: true);
        return $store->withLock(function () use ($store): array {
            $version = $store->readBounded($store->root . '/storage-version', 16);
            if ($version === '3') { $store->recover(false); return ['status' => 'UNCHANGED', 'backend_format' => 3]; }
            if ($version === '2') {
                // Format 3 changes no written byte: it only allows storage schema 2 from now on.
                $store->recover(false); $state = $store->loadCheckpoint($store->head());
                $store->atomicWrite($store->root . '/storage-version', '3'); $store->formatVersion = 3;
                return ['status' => 'MIGRATED', 'from_format' => 2, 'backend_format' => 3, 'generation' => $state['generation'], 'records' => count($state['records']), 'evidence_rewritten' => false];
            }
            if ($version !== '1') { throw new StorageException('STORAGE_VERSION_UNSUPPORTED'); }
            $store->formatVersion = 1;
            $head = $store->head();
            $state = $store->loadCheckpoint($head);
            foreach ($state['records'] as $metadata) {
                $bytes = $store->readBounded($store->objectPath($metadata['object_hash']), 65536);
                if (!hash_equals($metadata['object_hash'], hash('sha256', $bytes))) { throw new StorageException('STORAGE_CORRUPT'); }
            }
            $store->recover(true);
            $store->fault('after_legacy_recovery');
            $store->atomicWrite($store->root . '/storage-version', '3');
            $store->formatVersion = 3;
            $store->fault('after_format_switch');
            return ['status' => 'MIGRATED', 'from_format' => 1, 'backend_format' => 3, 'generation' => $state['generation'], 'records' => count($state['records']), 'evidence_rewritten' => false];
        }, forceWriter: true);
    }

    public function transaction(callable $mutation): mixed
    {
        if ($this->readOnly) { throw new StorageException('STORAGE_READ_ONLY'); }
        return $this->withLock(function () use ($mutation): mixed {
            $journal = $this->recover();
            $previousHead = $this->head();
            $transaction = new StorageTransaction($this->loadCheckpoint($previousHead), $this->limits);
            $result = $mutation($transaction);
            if (!$transaction->changed()) {
                return $result;
            }
            if ($previousHead['generation'] >= 9007199254740991) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            $state = $transaction->state();
            if ($state['storage_schema'] !== 1 && $this->formatVersion < 3) { throw new StorageException('STORAGE_MIGRATION_REQUIRED'); }
            $state['generation'] = $previousHead['generation'] + 1;
            $checkpointBytes = StorageJson::encode($state);
            if (strlen($checkpointBytes) > $this->limits->maxCheckpointBytes) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            $incomingBytes = strlen($checkpointBytes) + array_sum(array_map('strlen', $transaction->newObjects())) + 4096;
            // At the byte budget only a transaction that removes (bounded retention) may still commit: it frees space.
            if (!$transaction->removalOnly() && $this->diskUsage() + $incomingBytes > $this->limits->maxBytes) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            foreach ($transaction->newObjects() as $hash => $bytes) {
                $this->writeImmutable($this->objectPath($hash, true), $bytes);
            }
            $this->fault('after_objects');
            $checkpointHash = hash('sha256', $checkpointBytes);
            $this->writeImmutable($this->checkpointPath($checkpointHash), $checkpointBytes);
            $nextHead = ['checkpoint' => $checkpointHash, 'generation' => $state['generation']];
            if (filesize($this->root . '/journal.log') > (int) ($this->limits->maxJournalBytes / 2)) {
                $journal['last_hash'] = $this->compactJournal($previousHead);
            }
            $last = $this->appendJournal('PREPARE', $nextHead, $previousHead['checkpoint'], $journal['last_hash']);
            $this->fault('after_prepare');
            $this->appendJournal('COMMIT', $nextHead, $previousHead['checkpoint'], $last);
            $this->fault('after_commit');
            $this->atomicWrite($this->root . '/HEAD', StorageJson::encode($nextHead));
            $this->fault('after_head');
            $this->cleanCheckpoints($previousHead['checkpoint'], $checkpointHash);
            // Only after HEAD names the new checkpoint; a crash before this leaves unreferenced objects, never a missing one.
            foreach ($transaction->removedObjects() as $hash => $_) {
                $path = $this->objectPath($hash);
                if (is_file($path) && !is_link($path) && !unlink($path)) { throw new StorageException('STORAGE_ERROR'); }
            }
            return $result;
        });
    }

    /**
     * @return array<array-key, mixed>
     */
    public function snapshot(): array
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $head = $this->head();
            try {
                return $this->loadCheckpoint($head);
            } catch (StorageException $error) {
                if ($head === $this->head()) {
                    throw $error;
                }
            }
        }
        throw new StorageException('STORAGE_BUSY');
    }

    public function readRecord(string $contentHash): ?string
    {
        return $this->readRecords([$contentHash])[$contentHash] ?? null;
    }

    /**
     * @param array<array-key, mixed> $contentHashes
     * @return array<array-key, mixed>
     */
    public function readRecords(array $contentHashes): array
    {
        if (count($contentHashes) > 256) { throw new StorageException('WORK_BUDGET_EXHAUSTED'); }
        $state = $this->snapshot(); $result = [];
        foreach ($contentHashes as $contentHash) {
            if (!is_string($contentHash) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $contentHash) !== 1) { throw new StorageException('STORAGE_ADDRESS_INVALID'); }
            $metadata = $state['records'][$contentHash] ?? null;
            if ($metadata === null) { continue; }
            // A record that retention removed after this snapshot was read is absent, not corrupt.
            if (!is_file($this->objectPath($metadata['object_hash'])) && !isset($this->snapshot()['records'][$contentHash])) { continue; }
            $wire = $this->readBounded($this->objectPath($metadata['object_hash']), 65536);
            if (!hash_equals($metadata['object_hash'], hash('sha256', $wire))) { throw new StorageException('STORAGE_CORRUPT'); }
            $result[$contentHash] = $wire;
        }
        return $result;
    }

    /**
     * Verifies every held object. A writable store (storage:check) also removes object files a crash during
     * pruning left behind (reclaimOrphans).
     * @return array<array-key, mixed>
     */
    public function integrityCheck(): array
    {
        return $this->withLock(function (): array {
            $this->recover(!$this->readOnly);
            $state = $this->snapshot();
            foreach ($state['records'] as $metadata) {
                $bytes = $this->readBounded($this->objectPath($metadata['object_hash']), 65536);
                if (!hash_equals($metadata['object_hash'], hash('sha256', $bytes))) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
            }
            return ['backend' => 'filesystem', 'backend_format' => $this->formatVersion, 'storage_schema' => $state['storage_schema'], 'generation' => $state['generation'], 'records' => count($state['records']), 'pruned_records' => $state['pruned_records'] ?? 0, 'integrity' => 'PASS']
                + ($this->readOnly ? [] : ['orphaned_objects_removed' => $this->reclaimOrphans()]);
        });
    }

    /**
     * Under the writer lock, after recover(): deletes object files (and stale temporary files) under objects/ that
     * no checkpoint file names. A crash between HEAD and the unlinks of a pruning commit leaves such files. No
     * transaction is running (the lock), recover() rolled a committed but unpublished HEAD forward and aborted
     * the rest, and HEAD's checkpoint and the previous one (which readers of the last generation may still use)
     * both stay in checkpoints/, so only objects no reader can reach go. More than 4 checkpoint files (a crash
     * before their cleanup), or one that does not verify, keeps everything.
     */
    private function reclaimOrphans(): int
    {
        $referenced = []; $files = 0;
        foreach (new \DirectoryIterator($this->root . '/checkpoints') as $file) {
            if ($file->isDot() || preg_match('/\A([0-9a-f]{64})\.json\z/D', $file->getFilename(), $match) !== 1) { continue; }
            if ($file->isLink() || !$file->isFile() || ++$files > 4) { return 0; }
            $bytes = $this->readBounded($file->getPathname(), $this->limits->maxCheckpointBytes);
            if (!hash_equals($match[1], hash('sha256', $bytes))) { return 0; }
            $checkpoint = StorageJson::decode($bytes, $this->limits->maxCheckpointBytes); StorageTransaction::validateState($checkpoint, $this->limits);
            foreach ($checkpoint['records'] as $metadata) { $referenced[$metadata['object_hash'] . '.json'] = true; }
        }
        $removed = 0; $scanned = 0;
        foreach (new \DirectoryIterator($this->root . '/objects') as $directory) {
            if ($directory->isDot() || $directory->isLink() || !$directory->isDir() || preg_match('/\A[0-9a-f]{2}\z/D', $directory->getFilename()) !== 1) { continue; }
            foreach (new \DirectoryIterator($directory->getPathname()) as $file) {
                if ($file->isDot() || $file->isLink() || !$file->isFile() || ++$scanned > $this->limits->maxRecords * 4 + 100000) { continue; }
                $name = $file->getFilename();
                $orphan = (preg_match('/\A[0-9a-f]{64}\.json\z/D', $name) === 1 && str_starts_with($name, $directory->getFilename()) && !isset($referenced[$name])) || preg_match('/\A\.tmp-[0-9a-f]{32}\z/D', $name) === 1;
                if ($orphan) { if (!unlink($file->getPathname())) { throw new StorageException('STORAGE_ERROR'); } ++$removed; }
            }
        }
        return $removed;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function head(): array
    {
        $head = StorageJson::decode($this->readBounded($this->root . '/HEAD', 1024), 1024);
        if (count($head) !== 2 || !is_int($head['generation'] ?? null) || $head['generation'] < 0 ||
            !is_string($head['checkpoint'] ?? null) || !$this->validHash($head['checkpoint'])) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        return $head;
    }

    /**
     * @param array<array-key, mixed> $head
     * @return array<array-key, mixed>
     */
    private function loadCheckpoint(array $head): array
    {
        $bytes = $this->readBounded($this->checkpointPath($head['checkpoint']), $this->limits->maxCheckpointBytes);
        if (!hash_equals($head['checkpoint'], hash('sha256', $bytes))) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $state = StorageJson::decode($bytes, $this->limits->maxCheckpointBytes);
        StorageTransaction::validateState($state, $this->limits);
        if ($state['generation'] !== $head['generation']) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        return $state;
    }

    /**
     * Format 2 commits in the journal before HEAD; format 1 is read/migrated explicitly.
     * @return array<array-key, mixed>
     */
    private function recover(bool $repair = true): array
    {
        $head = $this->head();
        $this->loadCheckpoint($head);
        $path = $this->root . '/journal.log';
        if (is_link($path) || !is_file($path) || filesize($path) > $this->limits->maxJournalBytes) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        $lastHash = str_repeat('0', 64);
        $expected = null;
        $lastCommittedPrevious = null;
        $pending = null;
        $partialOffset = null;
        try {
            while (!feof($stream)) {
                $offset = ftell($stream);
                if ($offset === false || $offset < 0) { throw new StorageException('STORAGE_CORRUPT'); }
                $line = fgets($stream, 2049);
                if ($line === false) {
                    break;
                }
                if (!str_ends_with($line, "\n")) {
                    if (!feof($stream)) {
                        throw new StorageException('STORAGE_CORRUPT');
                    }
                    $partialOffset = $offset;
                    break;
                }
                $entry = StorageJson::decode(substr($line, 0, -1), 2048);
                $claimedHash = $entry['entry_hash'] ?? null;
                unset($entry['entry_hash']);
                if (count($entry) !== 5 || array_diff(array_keys($entry), ['phase', 'generation', 'checkpoint', 'previous_checkpoint', 'prev_entry_hash']) !== [] ||
                    !is_string($entry['phase'] ?? null) || !in_array($entry['phase'], ['CHECKPOINT', 'PREPARE', 'COMMIT', 'ABORT'], true) ||
                    !array_key_exists('previous_checkpoint', $entry)) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
                if (!is_string($claimedHash) || !$this->validHash($claimedHash) ||
                    ($entry['prev_entry_hash'] ?? null) !== $lastHash || !hash_equals($claimedHash, hash('sha256', StorageJson::encode($entry))) ||
                    !is_string($entry['checkpoint'] ?? null) || !$this->validHash($entry['checkpoint']) || !is_int($entry['generation'] ?? null)) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
                $lastHash = $claimedHash;
                $next = ['checkpoint' => $entry['checkpoint'], 'generation' => $entry['generation']];
                if ($entry['phase'] === 'CHECKPOINT' && $expected === null && $offset === 0) {
                    $expected = $next;
                } elseif ($entry['phase'] === 'PREPARE' && $pending === null) {
                    if ($expected === null) {
                        $expected = ['checkpoint' => $entry['previous_checkpoint'], 'generation' => 0];
                    }
                    if ($next['generation'] !== $expected['generation'] + 1 || $entry['previous_checkpoint'] !== $expected['checkpoint']) {
                        throw new StorageException('STORAGE_CORRUPT');
                    }
                    $pending = ['next' => $next, 'previous' => $expected];
                } elseif (in_array($entry['phase'], ['COMMIT', 'ABORT'], true) && $pending !== null && $pending['next'] === $next && $entry['previous_checkpoint'] === $pending['previous']['checkpoint']) {
                    if ($entry['phase'] === 'COMMIT') {
                        $lastCommittedPrevious = $pending['previous']['checkpoint'];
                        $expected = $next;
                    }
                    $pending = null;
                } else {
                    throw new StorageException('STORAGE_CORRUPT');
                }
            }
        } finally {
            fclose($stream);
        }
        if ($pending !== null) {
            if (($this->formatVersion >= 2 && $head !== $pending['previous']) || ($head !== $pending['next'] && $head !== $pending['previous'])) {
                throw new StorageException('STORAGE_CORRUPT');
            }
        } elseif ($expected !== null && $head !== $expected) {
            if ($this->formatVersion < 2 || $head['generation'] + 1 !== $expected['generation']) { throw new StorageException('STORAGE_CORRUPT'); }
            // Only a valid committed successor can repair a lagging HEAD.
            if (($lastCommittedPrevious ?? null) !== $head['checkpoint']) { throw new StorageException('STORAGE_CORRUPT'); }
            $this->loadCheckpoint($expected);
            if (!$repair) { throw new StorageException('STORAGE_RECOVERY_REQUIRED'); }
            $this->atomicWrite($this->root . '/HEAD', StorageJson::encode($expected));
            $head = $expected;
        } elseif ($expected === null && $head['generation'] !== 0) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        if (!$repair && ($partialOffset !== null || $pending !== null)) { throw new StorageException('STORAGE_RECOVERY_REQUIRED'); }
        if ($partialOffset !== null) {
            $stream = fopen($path, 'r+b');
            if ($stream === false || !ftruncate($stream, $partialOffset) || !fflush($stream) || !fsync($stream)) {
                throw new StorageException('STORAGE_ERROR');
            }
            fclose($stream);
        }
        if ($pending !== null) {
            $phase = $this->formatVersion === 1 && $head === $pending['next'] ? 'COMMIT' : 'ABORT';
            $lastHash = $this->appendJournal($phase, $pending['next'], $pending['previous']['checkpoint'], $lastHash);
        }
        return ['last_hash' => $lastHash];
    }

    /**
     * @param array<array-key, mixed> $head
     */
    private function appendJournal(string $phase, array $head, ?string $previous, string $lastHash): string
    {
        $entry = ['phase' => $phase, 'generation' => $head['generation'], 'checkpoint' => $head['checkpoint'], 'previous_checkpoint' => $previous, 'prev_entry_hash' => $lastHash];
        $hash = hash('sha256', StorageJson::encode($entry));
        $entry['entry_hash'] = $hash;
        $bytes = StorageJson::encode($entry) . "\n";
        if (filesize($this->root . '/journal.log') + strlen($bytes) > $this->limits->maxJournalBytes) {
            throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
        }
        $stream = fopen($this->root . '/journal.log', 'ab');
        if ($stream === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        try {
            $this->writeStream($stream, $bytes);
        } finally {
            fclose($stream);
        }
        return $hash;
    }

    /**
     * @param array<array-key, mixed> $head
     */
    private function compactJournal(array $head): string
    {
        $entry = ['phase' => 'CHECKPOINT', 'generation' => $head['generation'], 'checkpoint' => $head['checkpoint'], 'previous_checkpoint' => null, 'prev_entry_hash' => str_repeat('0', 64)];
        $hash = hash('sha256', StorageJson::encode($entry));
        $entry['entry_hash'] = $hash;
        $this->atomicWrite($this->root . '/journal.log', StorageJson::encode($entry) . "\n");
        return $hash;
    }

    private function objectPath(string $hash, bool $create = false): string
    {
        if (!$this->validHash($hash)) {
            throw new StorageException('STORAGE_ADDRESS_INVALID');
        }
        $directory = $this->root . '/objects/' . substr($hash, 0, 2);
        if (is_link($directory) || (!is_dir($directory) && $create && !mkdir($directory, 0700))) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        return $directory . '/' . $hash . '.json';
    }

    private function checkpointPath(string $hash): string
    {
        if (!$this->validHash($hash)) {
            throw new StorageException('STORAGE_ADDRESS_INVALID');
        }
        return $this->root . '/checkpoints/' . $hash . '.json';
    }

    private function validHash(string $hash): bool { return preg_match('/\A[0-9a-f]{64}\z/D', $hash) === 1; }

    private function readBounded(string $path, int $max): string
    {
        if (is_link($path) || !is_file($path)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        try {
            $bytes = stream_get_contents($stream, $max + 1);
        } finally {
            fclose($stream);
        }
        if ($bytes === false || strlen($bytes) > $max) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        return $bytes;
    }

    private function writeImmutable(string $path, string $bytes): void
    {
        if (is_file($path)) {
            if ($this->readBounded($path, max(strlen($bytes), 65536)) !== $bytes) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            return;
        }
        $this->atomicWrite($path, $bytes);
    }

    private function atomicWrite(string $path, string $bytes): void
    {
        if (is_link($path)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $temporary = dirname($path) . '/.tmp-' . bin2hex(random_bytes(16));
        $stream = fopen($temporary, 'x+b');
        if ($stream === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        chmod($temporary, 0600);
        try {
            $this->writeStream($stream, $bytes);
        } catch (\Throwable $error) {
            fclose($stream);
            unlink($temporary);
            throw $error;
        }
        fclose($stream);
        if (!rename($temporary, $path)) {
            unlink($temporary);
            throw new StorageException('STORAGE_ERROR');
        }
        $directory = fopen(dirname($path), 'r');
        if ($directory === false || !fsync($directory)) {
            throw new StorageException('FILESYSTEM_SEMANTICS_UNSUPPORTED');
        }
        fclose($directory);
    }

    /** @param resource $stream */
    private function writeStream($stream, string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written < 1) {
                throw new StorageException('STORAGE_ERROR');
            }
            $offset += $written;
        }
        if (!fflush($stream) || !fsync($stream)) {
            throw new StorageException('STORAGE_ERROR');
        }
    }

    private function withLock(callable $action, bool $forceWriter = false): mixed
    {
        $path = $this->root . '/locks/writer.lock';
        if (is_link($path)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $readLock = $this->readOnly && !$forceWriter;
        $lock = fopen($path, $readLock ? 'rb' : 'c+b');
        if ($lock === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        if (!$readLock) { chmod($path, 0600); }
        $deadline = hrtime(true) + $this->limits->lockWaitMilliseconds * 1000000;
        while (!flock($lock, ($readLock ? LOCK_SH : LOCK_EX) | LOCK_NB)) {
            if (hrtime(true) >= $deadline) {
                fclose($lock);
                throw new StorageException('STORAGE_BUSY');
            }
            usleep(10000);
        }
        try {
            return $action();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function fault(string $stage): void { if ($this->faultHook !== null) { ($this->faultHook)($stage); } }

    /** Bytes under the store directory, for StorageTransaction::usage() (beyond the budget too). */
    public function diskBytes(): int { return $this->diskUsage(false); }

    private function diskUsage(bool $bounded = true): int
    {
        $bytes = 0;
        $files = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || ++$files > $this->limits->maxRecords * 4 + 100000) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
            if ($bounded && $bytes > $this->limits->maxBytes) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
        }
        return $bytes;
    }

    private function cleanCheckpoints(string $previous, string $current): void
    {
        foreach (new \DirectoryIterator($this->root . '/checkpoints') as $file) {
            if ($file->isFile() && !$file->isLink() && str_ends_with($file->getFilename(), '.json') &&
                $file->getFilename() !== $previous . '.json' && $file->getFilename() !== $current . '.json') {
                if (!unlink($file->getPathname())) {
                    throw new StorageException('STORAGE_ERROR');
                }
            }
        }
    }
}
