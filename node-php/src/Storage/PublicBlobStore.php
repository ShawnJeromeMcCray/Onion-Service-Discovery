<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Storage;
/** Immutable public export bytes, kept outside the HTTP document root. */
final readonly class PublicBlobStore
{
    public function __construct(private string $directory, bool $create = false, private int $maxBytes = 268435456, private bool $repairDerived = false)
    {
        if ($maxBytes < 1 || $maxBytes > 1073741824 || str_contains($directory, '://') || is_link($directory) || (!is_dir($directory) && (!$create || !mkdir($directory, 0700, true))) || (fileperms($directory) & 0077) !== 0) { throw new StorageException('EXPORT_DIRECTORY_UNSAFE'); }
    }
    public function put(string $hash, string $bytes): void
    {
        $this->withWriterLock(function () use ($hash, $bytes): void { $this->putLocked($hash, $bytes); });
    }
    private function withWriterLock(callable $action): mixed
    {
        $lockPath = $this->directory . '/.writer.lock';
        if (is_link($lockPath)) { throw new StorageException('EXPORT_DIRECTORY_UNSAFE'); }
        $mask = umask(0077); try { $lock = fopen($lockPath, 'c+b'); } finally { umask($mask); }
        if ($lock === false) { throw new StorageException('EXPORT_UNAVAILABLE'); }
        try {
            $deadline = hrtime(true) + 2000000000;
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (hrtime(true) >= $deadline) { throw new StorageException('EXPORT_BUSY'); }
                usleep(10000);
            }
            return $action();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    /** The last published state node:snapshot keeps beside the blobs for degraded serving (D-045). */
    public const PUBLISHED_STATE = '.published-state.json';
    public const PUBLISHED_STATE_MAX_BYTES = 65536;
    /** Lock and request-budget files the front controller keeps beside the blobs, and the last published state; never pruned. */
    public const CONTROL_FILES = ['.writer.lock', '.http-budget', '.request-budget', '.relay-budget', '.visitor-relay-budget', self::PUBLISHED_STATE];

    /**
     * Replaces the last published state under the writer lock. $build runs inside the lock and
     * returns the new bytes, or null to leave the file as it is; returns whether the file changed.
     * @param callable(): ?string $build
     */
    public function replacePublishedState(callable $build): bool
    {
        return $this->withWriterLock(function () use ($build): bool {
            $bytes = $build();
            if ($bytes === null || $bytes === $this->publishedState()) { return false; }
            if (strlen($bytes) > self::PUBLISHED_STATE_MAX_BYTES) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
            $this->atomicWrite($this->directory . '/' . self::PUBLISHED_STATE, $bytes);
            return true;
        });
    }
    /** The last published state as written, or null before the first one. */
    public function publishedState(): ?string
    {
        $path = $this->directory . '/' . self::PUBLISHED_STATE;
        if (is_link($path)) { throw new StorageException('EXPORT_CORRUPT'); }
        if (!is_file($path)) { return null; }
        $stream = fopen($path, 'rb'); if ($stream === false) { throw new StorageException('EXPORT_UNAVAILABLE'); }
        try { $bytes = stream_get_contents($stream, self::PUBLISHED_STATE_MAX_BYTES + 1); } finally { fclose($stream); }
        if ($bytes === false || strlen($bytes) > self::PUBLISHED_STATE_MAX_BYTES) { throw new StorageException('EXPORT_CORRUPT'); }
        return $bytes;
    }

    /** Cleanup that keeps all given references and deletes at most 256 other blobs. With $olderThan
     * (a Unix time) only files last written before it are deleted, so a snapshot writing new blobs
     * at the same time is never cut short; without it the operator has stopped the workers.
     * @param list<string> $keep
     * @return array<string,int>
     */
    public function pruneUnreferenced(array $keep, ?int $olderThan = null): array
    {
        if (count($keep) > 4096) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
        $names = [];
        foreach ($keep as $hash) { $path = $this->path($hash); if (!is_file($path) || is_link($path)) { throw new StorageException('EXPORT_CORRUPT'); } $names[basename($path)] = true; }
        return $this->withWriterLock(function () use ($names, $olderThan): array {
            $removed = 0; $freed = 0; $scanned = 0;
            foreach (new \DirectoryIterator($this->directory) as $file) {
                if ($file->isDot() || in_array($file->getFilename(), self::CONTROL_FILES, true)) { continue; }
                if ($file->isLink() || !$file->isFile() || ++$scanned > 4096) { throw new StorageException('EXPORT_DIRECTORY_UNSAFE'); }
                $name = $file->getFilename();
                if (preg_match('/\A[0-9a-f]{64}\.json\z/D', $name) !== 1 && preg_match('/\A\.tmp-[0-9a-f]{24}\z/D', $name) !== 1) { throw new StorageException('EXPORT_DIRECTORY_UNSAFE'); }
                if (isset($names[$name]) || $removed >= 256 || ($olderThan !== null && $file->getMTime() >= $olderThan)) { continue; }
                $size = $file->getSize();
                if (!unlink($file->getPathname())) { throw new StorageException('EXPORT_UNAVAILABLE'); }
                ++$removed; $freed += $size;
            }
            $directory = fopen($this->directory, 'r'); if ($directory === false) { throw new StorageException('EXPORT_UNAVAILABLE'); }
            try { if (!fsync($directory)) { throw new StorageException('FILESYSTEM_SEMANTICS_UNSUPPORTED'); } } finally { fclose($directory); }
            return ['blobs_removed' => $removed, 'bytes_freed' => $freed, 'signed_records_removed' => 0];
        });
    }
    private function putLocked(string $hash, string $bytes): void
    {
        $path = $this->path($hash);
        if (strlen($bytes) > 524288) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
        if (is_link($path)) { throw new StorageException('EXPORT_CORRUPT'); }
        if (is_file($path)) {
            $existing = null;
            try { $existing = $this->get($hash); }
            catch (StorageException $error) { if (!$this->repairDerived || $error->errorCode !== 'EXPORT_CORRUPT') { throw $error; } }
            // Reused by a new snapshot: renew the mtime so exports:prune keeps its grace period for this blob.
            if ($existing === $bytes) { if (!touch($path)) { throw new StorageException('EXPORT_UNAVAILABLE'); } return; }
            if (!$this->repairDerived) { throw new StorageException('EXPORT_CORRUPT'); }
            // Explicit operator rebuild only: canonical bytes were reconstructed
            // from independently verified immutable records by SnapshotProducer.
        }
        $total = 0; $count = 0;
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->isDot() || $file->getFilename() === '.writer.lock' || ($this->repairDerived && $file->getFilename() === basename($path))) { continue; }
            if ($file->isLink() || !$file->isFile() || ++$count > 4096) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
            $total += $file->getSize();
        }
        if ($count >= 4096 || $total + strlen($bytes) > $this->maxBytes) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
        $this->atomicWrite($path, $bytes);
    }
    /** Durable replacement: a synced temporary file renamed over $path, then the directory synced. */
    private function atomicWrite(string $path, string $bytes): void
    {
        $temporary = $this->directory . '/.tmp-' . bin2hex(random_bytes(12));
        $mask = umask(0077); try { $stream = fopen($temporary, 'x+b'); } finally { umask($mask); }
        if ($stream === false) { throw new StorageException('EXPORT_UNAVAILABLE'); }
        try {
            $offset = 0; while ($offset < strlen($bytes)) { $written = fwrite($stream, substr($bytes, $offset)); if ($written === false || $written < 1) { throw new StorageException('EXPORT_UNAVAILABLE'); } $offset += $written; }
            if (!fflush($stream) || !fsync($stream)) { throw new StorageException('EXPORT_UNAVAILABLE'); }
        } catch (\Throwable $error) { @unlink($temporary); throw $error; } finally { fclose($stream); }
        if (!rename($temporary, $path)) { @unlink($temporary); throw new StorageException('EXPORT_UNAVAILABLE'); }
        $directory = fopen($this->directory, 'r'); if ($directory === false) { throw new StorageException('EXPORT_UNAVAILABLE'); }
        try { if (!fsync($directory)) { throw new StorageException('FILESYSTEM_SEMANTICS_UNSUPPORTED'); } } finally { fclose($directory); }
    }
    public function get(string $hash): ?string
    {
        $path = $this->path($hash);
        if (is_link($path)) { throw new StorageException('EXPORT_CORRUPT'); }
        if (!is_file($path)) { return null; }
        $stream = fopen($path, 'rb'); if ($stream === false) { throw new StorageException('EXPORT_UNAVAILABLE'); }
        try { $bytes = stream_get_contents($stream, 524289); } finally { fclose($stream); }
        if ($bytes === false || strlen($bytes) > 524288) { throw new StorageException('EXPORT_CORRUPT'); }
        return $bytes;
    }
    private function path(string $hash): string
    {
        if (preg_match('/\Asha256:[0-9a-f]{64}\z/D', $hash) !== 1) { throw new StorageException('STORAGE_ADDRESS_INVALID'); }
        return $this->directory . '/' . substr($hash, 7) . '.json';
    }
}
