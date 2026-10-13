<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageException;

/** Local resource policy, not an assertion of independent observers or protocol consensus. */
final class CandidateAdmission
{
    public const MAX_QUEUE = 4096;
    public const MAX_ADMISSIONS = 256;
    public const MAX_SOURCE_BUCKET_ADMISSIONS = 32;
    public const MAX_WORK = 60;
    public const MAX_CONCURRENT = 2;
    private const WINDOW_SECONDS = 3600;
    private const SOURCE_BUCKETS = 16;

    public static function enqueue(StorageTransaction $tx, OnionAddress $onion, string $source, int $now): bool
    {
        self::time($now);
        if (preg_match('/\A[a-zA-Z0-9_.:-]{1,96}\z/D', $source) !== 1) { throw new ProtocolException('SOURCE_INVALID'); }
        $candidate = $tx->get('candidates', $onion->hostname);
        if ($candidate !== null) {
            $sources = $candidate['sources'];
            if (!in_array($source, $sources, true) && count($sources) < 8) {
                $sources[] = $source; sort($sources, SORT_STRING); $candidate['sources'] = $sources;
                $tx->set('candidates', $onion->hostname, $candidate);
            }
            // Reannouncing a target must never reset its retry time, lease or quarantine.
            return false;
        }
        if (count($tx->state()['tables']['candidates']) >= self::MAX_QUEUE) { return false; }
        $budget = self::window($tx->get('maintenance', 'candidate-admissions'), $now);
        if ($budget === null) { return false; }
        $bucket = 'bucket-' . (hexdec(substr(hash('sha256', $source), 0, 2)) % self::SOURCE_BUCKETS);
        $used = $budget['sources'][$bucket] ?? 0;
        if ($budget['count'] >= self::MAX_ADMISSIONS || $used >= self::MAX_SOURCE_BUCKET_ADMISSIONS) { return false; }
        ++$budget['count']; $budget['sources'][$bucket] = $used + 1;
        $tx->set('maintenance', 'candidate-admissions', $budget);
        $tx->set('candidates', $onion->hostname, ['state' => 'QUEUED', 'attempts' => 0, 'next_attempt' => 0, 'lease_until' => 0, 'claim' => null, 'sources' => [$source], 'error' => null]);
        return true;
    }

    public static function claimBudget(StorageTransaction $tx, int $now): bool
    {
        self::time($now);
        $budget = self::window($tx->get('maintenance', 'candidate-work'), $now);
        if ($budget === null || $budget['count'] >= self::MAX_WORK) { return false; }
        ++$budget['count']; $tx->set('maintenance', 'candidate-work', $budget);
        return true;
    }

    /**
     * @param array<array-key,mixed>|null $previous
     * @return array<array-key,mixed>|null
     */
    private static function window(?array $previous, int $now): ?array
    {
        if ($previous !== null) {
            if (count($previous) !== 4 || !is_int($previous['start'] ?? null) || !is_int($previous['last_seen'] ?? null) ||
                !is_int($previous['count'] ?? null) || !is_array($previous['sources'] ?? null) || $previous['start'] < 0 ||
                $previous['last_seen'] < $previous['start'] || $previous['last_seen'] > 9007199254740991 - 86430 ||
                $previous['count'] < 0 || $previous['count'] > self::MAX_ADMISSIONS || count($previous['sources']) > self::SOURCE_BUCKETS) {
                throw new StorageException('STORAGE_CORRUPT');
            }
            foreach ($previous['sources'] as $bucket => $count) {
                if (!is_string($bucket) || preg_match('/\Abucket-(?:[0-9]|1[0-5])\z/D', $bucket) !== 1 ||
                    !is_int($count) || $count < 1 || $count > self::MAX_SOURCE_BUCKET_ADMISSIONS) { throw new StorageException('STORAGE_CORRUPT'); }
            }
        }
        if ($previous !== null && $now < $previous['last_seen']) { return null; }
        if ($previous === null || $now - $previous['start'] >= self::WINDOW_SECONDS) {
            return ['start' => $now, 'last_seen' => $now, 'count' => 0, 'sources' => []];
        }
        $previous['last_seen'] = $now;
        return $previous;
    }

    private static function time(int $now): void
    {
        if ($now < 0 || $now > 9007199254740991 - 86430) { throw new ProtocolException('CLOCK_INVALID'); }
    }
}
