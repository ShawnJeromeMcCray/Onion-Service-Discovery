<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;
use OnionServiceDiscovery\Storage\StorageException;
/** Aggregate token bucket: stores no IP, cookie, User-Agent, referrer or query. */
final readonly class PublicRequestBudget
{
    public function __construct(private string $path, private int $burst = 20, private float $tokensPerSecond = 2.0)
    {
        if ($burst < 1 || $burst > 1000 || $tokensPerSecond <= 0 || $tokensPerSecond > 100 || str_contains($path, '://') || is_link($path)) { throw new StorageException('RATE_LIMIT_CONFIGURATION_INVALID'); }
    }
    public function admit(float $now): bool
    {
        if (!is_finite($now) || $now < 0) { throw new StorageException('CLOCK_INVALID'); }
        $mask = umask(0077); try { $stream = fopen($this->path, 'c+b'); } finally { umask($mask); }
        if ($stream === false || (fileperms($this->path) & 0077) !== 0) { if (is_resource($stream)) { fclose($stream); } throw new StorageException('RATE_LIMIT_UNAVAILABLE'); }
        $deadline = hrtime(true) + 100000000;
        while (!flock($stream, LOCK_EX | LOCK_NB)) { if (hrtime(true) >= $deadline) { fclose($stream); return false; } usleep(5000); }
        try {
            $bytes = stream_get_contents($stream, 1025);
            if ($bytes === false || strlen($bytes) > 1024) { throw new StorageException('RATE_LIMIT_CORRUPT'); }
            $state = $bytes === '' ? ['tokens' => (float) $this->burst, 'time' => $now] : json_decode($bytes, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($state) || count($state) !== 2 || !is_numeric($state['tokens'] ?? null) || !is_numeric($state['time'] ?? null) || !is_finite((float) $state['tokens']) || !is_finite((float) $state['time']) || $state['tokens'] < 0 || $state['tokens'] > $this->burst) { throw new StorageException('RATE_LIMIT_CORRUPT'); }
            $tokens = min($this->burst, $state['tokens'] + max(0.0, min(3600.0, $now - $state['time'])) * $this->tokensPerSecond);
            $allowed = $tokens >= 1.0; if ($allowed) { $tokens -= 1.0; }
            $output = json_encode(['tokens' => $tokens, 'time' => max($now, (float) $state['time'])], JSON_THROW_ON_ERROR);
            rewind($stream); if (!ftruncate($stream, 0) || fwrite($stream, $output) !== strlen($output) || !fflush($stream)) { throw new StorageException('RATE_LIMIT_UNAVAILABLE'); }
            return $allowed;
        } finally { flock($stream, LOCK_UN); fclose($stream); }
    }
}
