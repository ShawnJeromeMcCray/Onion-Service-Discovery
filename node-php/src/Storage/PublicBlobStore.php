<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Storage;
/** Immutable public export bytes, kept outside the HTTP document root. */
final readonly class PublicBlobStore
{
    public function __construct(private string $directory, bool $create = false, private int $maxBytes = 268435456)
    {
        if ($maxBytes < 1 || $maxBytes > 1073741824 || str_contains($directory, '://') || is_link($directory) || (!is_dir($directory) && (!$create || !mkdir($directory, 0700, true))) || (fileperms($directory) & 0077) !== 0) { throw new StorageException('EXPORT_DIRECTORY_UNSAFE'); }
    }
    public function put(string $hash, string $bytes): void
    {
        $path = $this->path($hash);
        if (strlen($bytes) > 524288) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
        if (is_file($path)) { if ($this->get($hash) !== $bytes) { throw new StorageException('EXPORT_CORRUPT'); } return; }
        $total = 0; $count = 0;
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->isDot()) { continue; }
            if ($file->isLink() || !$file->isFile() || ++$count > 4096) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
            $total += $file->getSize();
        }
        if ($total + strlen($bytes) > $this->maxBytes) { throw new StorageException('EXPORT_BUDGET_EXHAUSTED'); }
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
