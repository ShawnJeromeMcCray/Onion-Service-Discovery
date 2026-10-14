<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

/**
 * D-018 bounded automatic re-announcement. A Node is due only for a new revision or while its
 * observation of the current revision is missing; at most once per day per Node, with
 * exponential backoff (1, 2, 4, ... 32 days) while the observation stays missing. A sent hint
 * stays an unconfirmed candidate: only verified membership evidence stops the schedule.
 */
final class AnnouncementSchedule
{
    public const DAY = 86400;
    public const MAX_INTERVAL_DAYS = 32;
    public const STARTED = 'ANNOUNCEMENT_STARTED';

    /** Waiting time after the given number of consecutive attempts for one revision. */
    public static function interval(int $attempts): int
    {
        return self::DAY * min(self::MAX_INTERVAL_DAYS, 2 ** max(0, min($attempts - 1, 5)));
    }

    /**
     * @param array<string,mixed>|null $entry Saved announcement state for this Node.
     * @param string|null $category This Node's saved membership category for $manifestHash, if checked.
     * @return array{due:bool,reason:string,next_due_at:?int}
     */
    public static function decide(?array $entry, ?string $category, string $manifestHash, int $now): array
    {
        if ($category === 'included') { return ['due' => false, 'reason' => 'ALREADY_INCLUDED', 'next_due_at' => null]; }
        // A hint cannot resolve a conflicting state, a wrong expected key or a broken snapshot.
        if ($category === 'contradictory' || $category === 'rejected') { return ['due' => false, 'reason' => 'NODE_EVIDENCE_NEEDS_REVIEW', 'next_due_at' => null]; }
        if ($entry === null) { return ['due' => true, 'reason' => 'INITIAL', 'next_due_at' => $now]; }
        // A last attempt in the future (clock rollback) also blocks until a full day after it.
        $last = self::seconds($entry['at']);
        if ($now < $last + self::DAY) { return ['due' => false, 'reason' => 'DAILY_LIMIT', 'next_due_at' => $last + self::DAY]; }
        if (($entry['manifest_hash'] ?? null) !== $manifestHash) { return ['due' => true, 'reason' => 'NEW_REVISION', 'next_due_at' => $now]; }
        $next = isset($entry['next_at']) ? self::seconds($entry['next_at']) : $last + self::DAY;
        return $now >= $next ? ['due' => true, 'reason' => 'MISSING_OBSERVATION', 'next_due_at' => $now]
            : ['due' => false, 'reason' => 'BACKOFF', 'next_due_at' => $next];
    }

    /**
     * The saved state after an attempt at $now: consecutive attempts count per revision.
     * @param array<string,mixed>|null $entry
     * @return array{at:string,result:string,manifest_hash:string,attempts:int,next_at:string}
     */
    public static function attempt(?array $entry, string $manifestHash, int $now, string $result): array
    {
        $attempts = $entry !== null && ($entry['manifest_hash'] ?? null) === $manifestHash && is_int($entry['attempts'] ?? null) ? min(1000, $entry['attempts'] + 1) : 1;
        return ['at' => gmdate('Y-m-d\TH:i:s\Z', $now), 'result' => $result, 'manifest_hash' => $manifestHash, 'attempts' => $attempts,
            'next_at' => gmdate('Y-m-d\TH:i:s\Z', $now + self::interval($attempts))];
    }

    private static function seconds(mixed $time): int
    {
        $seconds = is_string($time) ? strtotime($time) : false;
        // An unreadable time blocks like a future one instead of permitting an attempt.
        return $seconds === false ? PHP_INT_MAX - self::DAY : $seconds;
    }
}
