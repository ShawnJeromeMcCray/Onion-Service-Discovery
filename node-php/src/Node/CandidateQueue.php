<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Protocol\ProtocolException;

final readonly class CandidateQueue
{
    /** Provisional default (D-041 review): a target that failed verification is checked again after a week. */
    public const QUARANTINE_RECHECK_SECONDS = 604800;
    /**
     * Longer than any wait the queue schedules (leases of 120 s, a quarantine recheck of a week plus an hour): a next
     * attempt or lease further ahead was written while the clock ran ahead, so it is due or expired now (as
     * CandidateAdmission restarts its window), not when the clock catches up.
     */
    public const SCHEDULE_HORIZON = self::QUARANTINE_RECHECK_SECONDS + 86400;
    public function __construct(private StorageBackend $store) {}
    public function enqueue(OnionAddress $onion, string $source = 'manual', ?int $now = null): bool
    {
        if (preg_match('/\A[a-zA-Z0-9_.:-]{1,96}\z/D', $source) !== 1) { throw new ProtocolException('SOURCE_INVALID'); }
        $now ??= time();
        return $this->store->transaction(static fn (StorageTransaction $tx): bool => CandidateAdmission::enqueue($tx, $onion, $source, $now));
    }
    /**
     * @return array<array-key, mixed>
     */
    public function claim(int $now): ?array
    {
        self::time($now);
        return $this->store->transaction(static function (StorageTransaction $tx) use ($now): ?array {
            // A bounded rotating scan avoids starvation without an unbounded per-job walk.
            $state = $tx->state(); /** @var list<string> $keys */ $keys = array_keys($state['tables']['candidates']); sort($keys, SORT_STRING);
            $count = count($keys);
            if ($count === 0) { return null; }
            // The saved offset only moves on a claim; an idle scan rotates by time instead, so polling an idle
            // queue writes nothing (each write is a new storage generation that aborts a running snapshot).
            $scan = (($tx->get('maintenance', 'candidate-scan')['offset'] ?? 0) + 256 * intdiv($now, 60)) % $count;
            $inFlight = 0; $waits = static fn (int $until): bool => $until > $now && $until <= $now + self::SCHEDULE_HORIZON;
            foreach ($state['tables']['candidates'] as $candidate) {
                if ($candidate['state'] === 'DIRECT_CHECK_PENDING' && $waits($candidate['lease_until'])) { ++$inFlight; }
            }
            if ($inFlight >= CandidateAdmission::MAX_CONCURRENT) { return null; }
            for ($index = 0; $index < min(256, $count); ++$index) {
                $offset = ($scan + $index) % $count; $key = substr($keys[$offset], 4); $row = $tx->get('candidates', $key);
                if ($row === null) { continue; }
                if ($row['state'] === 'DIRECT_CHECK_PENDING' && !$waits($row['lease_until'])) {
                    $row['state'] = 'BACKOFF'; $row['claim'] = null; $row['lease_until'] = 0; $row['next_attempt'] = $now + 60; $row['error'] = 'WORKER_INTERRUPTED'; $tx->set('candidates', $key, $row); continue;
                }
                // A quarantined target is long backoff, not a permanent verdict: it is checked again after a week.
                if (!in_array($row['state'], ['QUEUED', 'BACKOFF', 'VERIFIED', 'QUARANTINED'], true) || $waits($row['next_attempt'])) { continue; }
                if ($row['state'] === 'VERIFIED') { $row['attempts'] = 0; }
                OnionAddress::parse($key);
                if (!CandidateAdmission::claimBudget($tx, $now)) { return null; }
                $row['state'] = 'DIRECT_CHECK_PENDING'; $row['attempts'] = min(8, $row['attempts'] + 1); $row['claim'] = bin2hex(random_bytes(16)); $row['lease_until'] = $now + 120;
                $tx->set('candidates', $key, $row); $tx->set('maintenance', 'candidate-scan', ['offset' => ((($offset + 1 - 256 * intdiv($now, 60)) % $count) + $count) % $count]);
                return ['onion' => $key, 'claim' => $row['claim'], 'attempt' => $row['attempts']];
            }
            return null;
        });
    }
    /** Operator release: the target is checked at the next worker run; its sources are kept. */
    public function retry(OnionAddress $onion): void
    {
        $this->store->transaction(static function (StorageTransaction $tx) use ($onion): void {
            $row = $tx->get('candidates', $onion->hostname);
            if ($row === null) { throw new ProtocolException('CANDIDATE_NOT_FOUND'); }
            if ($row['state'] === 'DIRECT_CHECK_PENDING') { throw new ProtocolException('CANDIDATE_BUSY'); }
            $row['state'] = 'QUEUED'; $row['attempts'] = 0; $row['next_attempt'] = 0; $row['claim'] = null; $row['lease_until'] = 0; $row['error'] = null;
            $tx->set('candidates', $onion->hostname, $row);
        });
    }
    /**
     * @param array<array-key, mixed> $job
     */
    public function fail(array $job, int $now, string $error, bool $quarantine = false): void
    {
        self::time($now);
        if (preg_match('/\A[A-Z_]{1,64}\z/D', $error) !== 1) { throw new ProtocolException('ERROR_CODE_INVALID'); }
        $this->store->transaction(static function (StorageTransaction $tx) use ($job, $now, $error, $quarantine): void {
            $row = self::claimedRow($tx, $job);
            $row['state'] = $quarantine ? 'QUARANTINED' : 'BACKOFF';
            $row['error'] = $error;
            $row['next_attempt'] = $now + ($quarantine ? self::QUARANTINE_RECHECK_SECONDS + random_int(0, 3600) : min(86400, 60 * (2 ** min(10, $row['attempts'] - 1))) + random_int(0, 30));
            $row['lease_until'] = 0; $row['claim'] = null; $tx->set('candidates', $job['onion'], $row);
        });
    }
    /**
     * @param array<array-key, mixed> $job
     * @return array<array-key, mixed>
     */
    public static function claimedRow(StorageTransaction $tx, array $job): array
    {
        $row = $tx->get('candidates', $job['onion']);
        if ($row === null || $row['state'] !== 'DIRECT_CHECK_PENDING' || $row['claim'] !== $job['claim']) { throw new ProtocolException('CANDIDATE_CLAIM_LOST'); }
        return $row;
    }
    private static function time(int $now): void { if ($now < 0 || $now > 9007199254740991 - 86430) { throw new ProtocolException('CLOCK_INVALID'); } }
}
