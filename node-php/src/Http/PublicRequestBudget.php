<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;
use OnionServiceDiscovery\Storage\StorageException;
/**
 * Aggregate token bucket: stores no IP, cookie, User-Agent, referrer or query. A damaged state file
 * (for example after a crash or a full disk) counts as a full bucket and is replaced, so it never takes
 * the routes offline; the new state is written to a temporary file and renamed over the old one.
 */
final readonly class PublicRequestBudget
{
    /** The budget files the front controller keeps in the export directory. */
    public const FILES = ['.request-budget', '.relay-budget', '.visitor-relay-budget'];
    public function __construct(private string $path, private int $burst = 20, private float $tokensPerSecond = 2.0)
    {
        if ($burst < 1 || $burst > 1000 || $tokensPerSecond <= 0 || $tokensPerSecond > 100 || str_contains($path, '://') || is_link($path)) { throw new StorageException('RATE_LIMIT_CONFIGURATION_INVALID'); }
    }
    public function admit(float $now): bool
    {
        if (!is_finite($now) || $now < 0) { throw new StorageException('CLOCK_INVALID'); }
        $deadline = hrtime(true) + 100000000;
        // A writer replaces the file by rename, so after the lock the holder checks that it locked the current file.
        while (true) {
            $mask = umask(0077); try { $stream = @fopen($this->path, 'c+b'); } finally { umask($mask); }
            clearstatcache(true, $this->path);
            if ($stream === false || is_link($this->path) || (fileperms($this->path) & 0077) !== 0) { if (is_resource($stream)) { fclose($stream); } throw new StorageException('RATE_LIMIT_UNAVAILABLE'); }
            while (!flock($stream, LOCK_EX | LOCK_NB)) { if (hrtime(true) >= $deadline) { fclose($stream); return false; } usleep(5000); }
            clearstatcache(true, $this->path); $current = @stat($this->path); $locked = fstat($stream);
            if ($current !== false && $locked !== false && $current['ino'] === $locked['ino'] && $current['dev'] === $locked['dev']) { break; }
            flock($stream, LOCK_UN); fclose($stream);
            if (hrtime(true) >= $deadline) { return false; }
        }
        try {
            $bytes = stream_get_contents($stream, 1025);
            if ($bytes === false) { throw new StorageException('RATE_LIMIT_UNAVAILABLE'); }
            $state = self::parse($bytes, $this->burst) ?? ['tokens' => (float) $this->burst, 'time' => $now];
            $tokens = min($this->burst, $state['tokens'] + max(0.0, min(3600.0, $now - $state['time'])) * $this->tokensPerSecond);
            $allowed = $tokens >= 1.0; if ($allowed) { $tokens -= 1.0; }
            $this->replace(json_encode(['tokens' => $tokens, 'time' => max($now, $state['time'])], JSON_THROW_ON_ERROR));
            return $allowed;
        } finally { flock($stream, LOCK_UN); fclose($stream); }
    }
    /**
     * For doctor: ABSENT, PASS, UNAVAILABLE (a symlink, unreadable or open to group or others: requests
     * fail with 503) or CORRUPT (unparsable: the next request refills and rewrites it).
     */
    public static function inspect(string $path): string
    {
        clearstatcache(true, $path);
        if (is_link($path)) { return 'UNAVAILABLE'; }
        if (!file_exists($path)) { return 'ABSENT'; }
        if (!is_file($path) || !is_readable($path) || (fileperms($path) & 0077) !== 0) { return 'UNAVAILABLE'; }
        $bytes = @file_get_contents($path, false, null, 0, 1025);
        if ($bytes === false) { return 'UNAVAILABLE'; }
        return $bytes === '' || self::parse($bytes, 1000) !== null ? 'PASS' : 'CORRUPT';
    }
    /** @return array{tokens:float,time:float}|null Null for anything but a valid saved state. */
    private static function parse(string $bytes, int $burst): ?array
    {
        if ($bytes === '' || strlen($bytes) > 1024) { return null; }
        try { $state = json_decode($bytes, true, 4, JSON_THROW_ON_ERROR); } catch (\JsonException) { return null; }
        if (!is_array($state) || count($state) !== 2 || !(is_int($state['tokens'] ?? null) || is_float($state['tokens'] ?? null)) || !(is_int($state['time'] ?? null) || is_float($state['time'] ?? null))) { return null; }
        $tokens = (float) $state['tokens']; $time = (float) $state['time'];
        return is_finite($tokens) && is_finite($time) && $tokens >= 0 && $tokens <= $burst && $time >= 0 ? ['tokens' => $tokens, 'time' => $time] : null;
    }
    /** Temporary file and rename beside the state file, so a crash or short write leaves the old state. */
    private function replace(string $output): void
    {
        $temporary = dirname($this->path) . '/.tmp-' . bin2hex(random_bytes(12));
        $mask = umask(0077); try { $stream = @fopen($temporary, 'xb'); } finally { umask($mask); }
        if ($stream === false) { throw new StorageException('RATE_LIMIT_UNAVAILABLE'); }
        try { $written = fwrite($stream, $output) === strlen($output) && fflush($stream); } finally { fclose($stream); }
        if (!$written || !@rename($temporary, $this->path)) { @unlink($temporary); throw new StorageException('RATE_LIMIT_UNAVAILABLE'); }
    }
}
