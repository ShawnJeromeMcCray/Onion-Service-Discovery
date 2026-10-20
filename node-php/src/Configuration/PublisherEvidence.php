<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Validation\StrictJson;

/**
 * Private publisher evidence beside the configuration, written only by explicit `--save-status`
 * or `--if-due` CLI runs and read by the status page. Aggregates only: Node addresses are stored
 * as hashes and keys are never stored, so the public page cannot reveal the selected sources.
 */
final class PublisherEvidence
{
    public const MAX_ANNOUNCED_NODES = 16;
    private const MAX_BYTES = 16384;
    private const CATEGORIES = ['included', 'stale', 'not_present', 'contradictory', 'unavailable', 'rejected'];
    private const MEMBERSHIP_COUNTS = ['revision', 'key_epoch', 'selected_sources', 'distinct_node_keys', 'included_with_node_direct_observation',
        'included', 'stale', 'not_present', 'contradictory', 'unavailable', 'rejected'];

    public static function path(string $configPath): string
    {
        return dirname($configPath) . '/' . preg_replace('/\.json\z/D', '', basename($configPath)) . '.discoverability.json';
    }

    /** The saved form of a Node address; the status page never needs the address itself. */
    public static function nodeKey(OnionAddress $node): string { return hash('sha256', $node->hostname); }

    /** @param array<string,mixed> $result Output of PublisherMembership::check(). */
    public static function recordMembership(array $result, string $configPath, int $now): void
    {
        $expected = $result['expected_state'];
        // Per-Node categories let the bounded announcement skip Nodes that already include this revision.
        $nodes = []; $conflicts = 0;
        foreach ($result['sources'] as $source) {
            $nodes[self::nodeKey(OnionAddress::parse($source['node_onion']))] = $source['category'];
            // The status page names D-052 integrity rejections separately from ordinary failures (spec 47 section 79).
            if ($source['category'] === 'rejected' && in_array($source['error'] ?? null, PublisherStatus::CHECKPOINT_CONFLICT_ERRORS, true)) { ++$conflicts; }
        }
        self::locked($configPath, static function () use ($configPath, $now, $expected, $result, $nodes, $conflicts): void {
            self::write($configPath, 'membership', ['checked_at' => self::time($now), 'manifest_hash' => $expected['manifest_hash'],
                'logical_service_id' => $expected['logical_service_id'], 'revision' => $expected['revision'], 'key_epoch' => $expected['key_epoch'],
                'selected_sources' => $result['selected_sources'], 'distinct_node_keys' => $result['distinct_node_keys'],
                'included_with_node_direct_observation' => $result['included_with_node_direct_observation']] + $result['counts'] + ['nodes' => $nodes, 'checkpoint_conflicts' => $conflicts,
                'outdated_checkpoints' => $result['included_with_outdated_checkpoint'] ?? 0]);
            // D-052 anchors: the checkpoint each consistent Node served. A rejected or unreachable Node keeps its
            // previous anchor, so a rollback or equivocation stays visible until the operator acts.
            $anchors = self::load($configPath)['checkpoints'] ?? [];
            foreach ($result['sources'] as $source) {
                if (isset($source['anchor'])) { $anchors[self::nodeKey(OnionAddress::parse($source['node_onion']))] = $source['anchor']; }
            }
            uasort($anchors, static fn (array $a, array $b): int => strcmp($b['checked_at'], $a['checked_at']));
            self::write($configPath, 'checkpoints', array_slice($anchors, 0, PublisherMembership::MAX_SOURCES, true));
        });
    }

    /**
     * Checkpoints saved at the previous membership check, keyed by nodeKey().
     * @return array<string,array{node_key:string,checkpoint_hash:string,generation:int,checked_at:string}>
     */
    public static function checkpoints(string $configPath): array
    {
        return self::load($configPath)['checkpoints'] ?? [];
    }

    public static function recordSelfVerification(string $configPath, int $now, ?string $manifestHash, string $result): void
    {
        self::update($configPath, 'self_verification', ['checked_at' => self::time($now), 'manifest_hash' => $manifestHash, 'result' => $result]);
    }

    /** An explicit operator announcement; it also counts toward the automatic daily limit. */
    public static function recordAnnouncement(string $configPath, int $now, OnionAddress $node, string $result, string $manifestHash): void
    {
        self::locked($configPath, static function () use ($configPath, $now, $node, $result, $manifestHash): void {
            $nodes = self::load($configPath)['announcement']['nodes'] ?? [];
            $nodes[self::nodeKey($node)] = AnnouncementSchedule::attempt($nodes[self::nodeKey($node)] ?? null, $manifestHash, $now, $result);
            self::writeAnnouncements($configPath, $nodes);
        });
    }

    /**
     * Decides which Nodes are due and reserves them in one locked step, so concurrent runs
     * cannot exceed the daily limit. A reservation counts as an attempt even if the run dies.
     * @param list<OnionAddress> $nodes
     * @return list<array{node:OnionAddress,due:bool,reason:string,next_due_at:?int,attempt:?int}>
     */
    public static function reserveAnnouncements(string $configPath, int $now, string $manifestHash, array $nodes): array
    {
        return self::locked($configPath, static function () use ($configPath, $now, $manifestHash, $nodes): array {
            $evidence = self::load($configPath);
            $saved = $evidence['announcement']['nodes'] ?? [];
            $membership = $evidence['membership'] ?? null;
            $categories = $membership !== null && $membership['manifest_hash'] === $manifestHash ? ($membership['nodes'] ?? []) : [];
            $plan = []; $reserved = false;
            foreach ($nodes as $node) {
                $key = self::nodeKey($node);
                $decision = AnnouncementSchedule::decide($saved[$key] ?? null, $categories[$key] ?? null, $manifestHash, $now) + ['attempt' => null];
                if ($decision['due']) {
                    $saved[$key] = AnnouncementSchedule::attempt($saved[$key] ?? null, $manifestHash, $now, AnnouncementSchedule::STARTED);
                    $decision['attempt'] = $saved[$key]['attempts']; $decision['next_due_at'] = $now + AnnouncementSchedule::interval($saved[$key]['attempts']);
                    $reserved = true;
                }
                $plan[] = ['node' => $node] + $decision;
            }
            if ($reserved) { self::writeAnnouncements($configPath, $saved); }
            return $plan;
        });
    }

    /** Replaces this run's reservation with the typed result; a newer entry is left alone. */
    public static function finishAnnouncement(string $configPath, int $startedAt, OnionAddress $node, string $result): void
    {
        self::locked($configPath, static function () use ($configPath, $startedAt, $node, $result): void {
            $nodes = self::load($configPath)['announcement']['nodes'] ?? [];
            $key = self::nodeKey($node);
            if (($nodes[$key]['result'] ?? null) !== AnnouncementSchedule::STARTED || $nodes[$key]['at'] !== self::time($startedAt)) { return; }
            $nodes[$key]['result'] = $result;
            self::writeAnnouncements($configPath, $nodes);
        });
    }

    /** True when saved evidence exists but this process cannot read it (checks run as another user). */
    public static function unreadable(string $configPath): bool
    {
        $path = self::path($configPath);
        return is_file($path) && !is_readable($path);
    }

    /** @return array<string,mixed> Validated sections; an invalid section is dropped, never trusted. */
    public static function load(string $configPath): array
    {
        $path = self::path($configPath);
        if (is_link($path) || !is_file($path) || filesize($path) > self::MAX_BYTES) { return []; }
        $wire = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        try { $value = $wire === false ? null : json_decode(json_encode(StrictJson::parse($wire, self::MAX_BYTES), JSON_THROW_ON_ERROR), true, 8, JSON_THROW_ON_ERROR); }
        catch (\InvalidArgumentException | \JsonException) { return []; }
        if (!is_array($value) || ($value['evidence_version'] ?? null) !== 1) { return []; }
        $sections = [];
        if (is_array($value['membership'] ?? null) && self::validMembership($value['membership'])) { $sections['membership'] = $value['membership']; }
        if (is_array($value['self_verification'] ?? null) && self::validSelfVerification($value['self_verification'])) { $sections['self_verification'] = $value['self_verification']; }
        if (is_array($value['announcement'] ?? null) && self::validAnnouncement($value['announcement'])) { $sections['announcement'] = $value['announcement']; }
        if (is_array($value['checkpoints'] ?? null) && self::validCheckpoints($value['checkpoints'])) { $sections['checkpoints'] = $value['checkpoints']; }
        return $sections;
    }

    /** @param array<string,array<string,mixed>> $nodes */
    private static function writeAnnouncements(string $configPath, array $nodes): void
    {
        uasort($nodes, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
        self::write($configPath, 'announcement', ['nodes' => array_slice($nodes, 0, self::MAX_ANNOUNCED_NODES, true)]);
    }

    /** @param array<string,mixed> $section */
    private static function update(string $configPath, string $name, array $section): void
    {
        self::locked($configPath, static fn () => self::write($configPath, $name, $section));
    }

    /**
     * Serializes evidence updates of all CLI runs on the configuration file itself, which every
     * writer can already read and no update replaces; flock needs no write permission.
     * @template T
     * @param \Closure(): T $critical
     * @return T
     */
    private static function locked(string $configPath, \Closure $critical): mixed
    {
        $handle = @fopen($configPath, 'rb');
        if ($handle === false) { throw new ProtocolException('EVIDENCE_WRITE_FAILED'); }
        try {
            $deadline = hrtime(true) + 10000000000;
            while (!flock($handle, LOCK_EX | LOCK_NB)) {
                if (hrtime(true) >= $deadline) { throw new ProtocolException('PUBLISHER_BUSY'); }
                usleep(20000);
            }
            try { return $critical(); } finally { flock($handle, LOCK_UN); }
        } finally { fclose($handle); }
    }

    /**
     * The caller holds the lock.
     * @param array<string,mixed> $section
     */
    private static function write(string $configPath, string $name, array $section): void
    {
        $path = self::path($configPath);
        if (is_link($path)) { throw new ProtocolException('EVIDENCE_PATH_UNSAFE'); }
        $evidence = ['evidence_version' => 1] + self::load($configPath);
        $evidence[$name] = $section;
        $wire = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        if (strlen($wire) > self::MAX_BYTES) { throw new ProtocolException('EVIDENCE_TOO_LARGE'); }
        // Same group and readability as the configuration, which the status page must already read.
        // The CLI usually runs as the operator, so the group must follow the configuration, not the caller.
        $mode = @fileperms($configPath); $group = @filegroup($configPath);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $wire, LOCK_EX) === false || ($group !== false && $group !== filegroup($temporary) && !chgrp($temporary, $group))
            || !chmod($temporary, $mode === false ? 0600 : ($mode & 0640)) || !rename($temporary, $path)) {
            if (is_file($temporary)) { unlink($temporary); }
            throw new ProtocolException('EVIDENCE_WRITE_FAILED');
        }
    }

    private static function time(int $now): string { return gmdate('Y-m-d\TH:i:s\Z', $now); }
    private static function isTime(mixed $value): bool { return is_string($value) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D', $value) === 1; }
    private static function isHash(mixed $value): bool { return is_string($value) && preg_match('/\Asha256:[0-9a-f]{64}\z/D', $value) === 1; }
    private static function isCode(mixed $value): bool { return is_string($value) && preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/D', $value) === 1; }
    private static function isNodeKey(mixed $value): bool { return is_string($value) && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1; }

    /** @param array<array-key,mixed> $m */
    private static function validMembership(array $m): bool
    {
        // Evidence saved before per-Node categories (nodes), integrity counts (checkpoint_conflicts) and D-036 freshness
        // (outdated_checkpoints) existed stays valid.
        if (count($m) !== 14 + (array_key_exists('nodes', $m) ? 1 : 0) + (array_key_exists('checkpoint_conflicts', $m) ? 1 : 0) + (array_key_exists('outdated_checkpoints', $m) ? 1 : 0) || !self::isTime($m['checked_at'] ?? null) || !self::isHash($m['manifest_hash'] ?? null) ||
            !is_string($m['logical_service_id'] ?? null) || preg_match('/\Aed25519:[A-Za-z0-9_-]{43}\z/D', $m['logical_service_id']) !== 1) { return false; }
        foreach (self::MEMBERSHIP_COUNTS as $field) { if (!is_int($m[$field] ?? null) || $m[$field] < 0 || $m[$field] > 1000000) { return false; } }
        if (array_key_exists('nodes', $m)) {
            if (!is_array($m['nodes']) || count($m['nodes']) !== $m['selected_sources']) { return false; }
            foreach ($m['nodes'] as $key => $category) { if (!self::isNodeKey($key) || !in_array($category, self::CATEGORIES, true)) { return false; } }
            $categories = array_count_values($m['nodes']);
            foreach (self::CATEGORIES as $category) { if (($categories[$category] ?? 0) !== $m[$category]) { return false; } }
        }
        if (array_key_exists('checkpoint_conflicts', $m) && (!is_int($m['checkpoint_conflicts']) || $m['checkpoint_conflicts'] < 0 || $m['checkpoint_conflicts'] > $m['rejected'])) { return false; }
        if (array_key_exists('outdated_checkpoints', $m) && (!is_int($m['outdated_checkpoints']) || $m['outdated_checkpoints'] < 0
            || $m['outdated_checkpoints'] + $m['included_with_node_direct_observation'] > $m['included'])) { return false; }
        return $m['included'] + $m['stale'] + $m['not_present'] + $m['contradictory'] + $m['unavailable'] + $m['rejected'] === $m['selected_sources']
            && $m['selected_sources'] <= PublisherMembership::MAX_SOURCES && $m['included_with_node_direct_observation'] <= $m['included'] && $m['key_epoch'] >= 1;
    }

    /** @param array<array-key,mixed> $s */
    private static function validSelfVerification(array $s): bool
    {
        return count($s) === 3 && self::isTime($s['checked_at'] ?? null) && (($s['manifest_hash'] ?? null) === null || self::isHash($s['manifest_hash'])) && self::isCode($s['result'] ?? null);
    }

    /** @param array<array-key,mixed> $c */
    private static function validCheckpoints(array $c): bool
    {
        if (count($c) > PublisherMembership::MAX_SOURCES) { return false; }
        foreach ($c as $key => $anchor) {
            if (!self::isNodeKey($key) || !is_array($anchor) || count($anchor) !== 4 || !self::isNodeKey($anchor['node_key'] ?? null) || !self::isHash($anchor['checkpoint_hash'] ?? null)
                || !is_int($anchor['generation'] ?? null) || $anchor['generation'] < 1 || $anchor['generation'] > 9007199254740991 || !self::isTime($anchor['checked_at'] ?? null)) { return false; }
        }
        return true;
    }

    /** @param array<array-key,mixed> $a */
    private static function validAnnouncement(array $a): bool
    {
        if (count($a) !== 1 || !is_array($a['nodes'] ?? null) || count($a['nodes']) > self::MAX_ANNOUNCED_NODES) { return false; }
        foreach ($a['nodes'] as $key => $node) {
            if (!self::isNodeKey($key) || !is_array($node) || !self::isTime($node['at'] ?? null) || !self::isCode($node['result'] ?? null)) { return false; }
            // Entries saved before the D-018 schedule have only a time and a result.
            if (count($node) !== 2 && (count($node) !== 5 || !self::isHash($node['manifest_hash'] ?? null) || !is_int($node['attempts'] ?? null)
                || $node['attempts'] < 1 || $node['attempts'] > 1000 || !self::isTime($node['next_at'] ?? null))) { return false; }
        }
        return true;
    }
}
