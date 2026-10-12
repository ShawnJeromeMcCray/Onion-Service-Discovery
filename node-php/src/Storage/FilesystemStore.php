<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

/** Local-filesystem MVCC checkpoint plus a checksummed prepare/commit journal. */
final class FilesystemStore implements StorageBackend
{
    private readonly string $root;
    private readonly StorageLimits $limits;
    private readonly ?\Closure $faultHook;

    public function __construct(string $directory, ?StorageLimits $limits = null, bool $create = false, ?\Closure $faultHook = null)
    {
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
                $this->atomicWrite($this->root . '/storage-version', '1');
                $this->atomicWrite($this->root . '/HEAD', StorageJson::encode(['generation' => 0, 'checkpoint' => $hash]));
                $this->atomicWrite($this->root . '/journal.log', '');
            }
            if ($this->readBounded($this->root . '/storage-version', 16) !== '1') {
                throw new StorageException('STORAGE_VERSION_UNSUPPORTED');
            }
            $this->recover();
        });
    }

    public function backendName(): string { return 'filesystem'; }

    public function transaction(callable $mutation): mixed
    {
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
            $state['generation'] = $previousHead['generation'] + 1;
            $checkpointBytes = StorageJson::encode($state);
            if (strlen($checkpointBytes) > $this->limits->maxCheckpointBytes) {
                throw new StorageException('STORAGE_BUDGET_EXHAUSTED');
            }
            $incomingBytes = strlen($checkpointBytes) + array_sum(array_map('strlen', $transaction->newObjects())) + 4096;
            if ($this->diskUsage() + $incomingBytes > $this->limits->maxBytes) {
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
            $this->atomicWrite($this->root . '/HEAD', StorageJson::encode($nextHead));
            $this->fault('after_head');
            $this->appendJournal('COMMIT', $nextHead, $previousHead['checkpoint'], $last);
            $this->fault('after_commit');
            $this->cleanCheckpoints($previousHead['checkpoint'], $checkpointHash);
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
            $wire = $this->readBounded($this->objectPath($metadata['object_hash']), 65536);
            if (!hash_equals($metadata['object_hash'], hash('sha256', $wire))) { throw new StorageException('STORAGE_CORRUPT'); }
            $result[$contentHash] = $wire;
        }
        return $result;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function integrityCheck(): array
    {
        return $this->withLock(function (): array {
            $this->recover();
            $state = $this->snapshot();
            foreach ($state['records'] as $metadata) {
                $bytes = $this->readBounded($this->objectPath($metadata['object_hash']), 65536);
                if (!hash_equals($metadata['object_hash'], hash('sha256', $bytes))) {
                    throw new StorageException('STORAGE_CORRUPT');
                }
            }
            return ['backend' => 'filesystem', 'storage_schema' => 1, 'generation' => $state['generation'], 'records' => count($state['records']), 'integrity' => 'PASS'];
        });
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
     * HEAD is the commit point. A complete PREPARE is rolled forward or aborted after a crash.
     * @return array<array-key, mixed>
     */
    private function recover(): array
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
            if ($head !== $pending['next'] && $head !== $pending['previous']) {
                throw new StorageException('STORAGE_CORRUPT');
            }
        } elseif (($expected !== null && $head !== $expected) || ($expected === null && $head['generation'] !== 0)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        if ($partialOffset !== null) {
            $stream = fopen($path, 'r+b');
            if ($stream === false || !ftruncate($stream, $partialOffset) || !fflush($stream) || !fsync($stream)) {
                throw new StorageException('STORAGE_ERROR');
            }
            fclose($stream);
        }
        if ($pending !== null) {
            $phase = $head === $pending['next'] ? 'COMMIT' : 'ABORT';
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

    private function withLock(callable $action): mixed
    {
        $path = $this->root . '/locks/writer.lock';
        if (is_link($path)) {
            throw new StorageException('STORAGE_CORRUPT');
        }
        $lock = fopen($path, 'c+b');
        if ($lock === false) {
            throw new StorageException('STORAGE_UNAVAILABLE');
        }
        chmod($path, 0600);
        $deadline = hrtime(true) + $this->limits->lockWaitMilliseconds * 1000000;
        while (!flock($lock, LOCK_EX | LOCK_NB)) {
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

    private function diskUsage(): int
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
            if ($bytes > $this->limits->maxBytes) {
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
