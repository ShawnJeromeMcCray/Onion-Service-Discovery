<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Validation\StrictJson;

/**
 * Private publisher evidence beside the configuration, written only by explicit `--save-status`
 * CLI runs and read by the status page. Aggregates only: Node addresses are stored as hashes
 * and keys are never stored, so the public page cannot reveal the publisher's selected sources.
 */
final class PublisherEvidence
{
    public const MAX_ANNOUNCED_NODES = 16;
    private const MAX_BYTES = 8192;
    private const MEMBERSHIP_COUNTS = ['revision', 'key_epoch', 'selected_sources', 'distinct_node_keys', 'included_with_node_direct_observation',
        'included', 'stale', 'not_present', 'contradictory', 'unavailable', 'rejected'];

    public static function path(string $configPath): string
    {
        return dirname($configPath) . '/' . preg_replace('/\.json\z/D', '', basename($configPath)) . '.discoverability.json';
    }

    /** @param array<string,mixed> $result Output of PublisherMembership::check(). */
    public static function recordMembership(array $result, string $configPath, int $now): void
    {
        $expected = $result['expected_state'];
        self::update($configPath, 'membership', ['checked_at' => self::time($now), 'manifest_hash' => $expected['manifest_hash'],
            'logical_service_id' => $expected['logical_service_id'], 'revision' => $expected['revision'], 'key_epoch' => $expected['key_epoch'],
            'selected_sources' => $result['selected_sources'], 'distinct_node_keys' => $result['distinct_node_keys'],
            'included_with_node_direct_observation' => $result['included_with_node_direct_observation']] + $result['counts']);
    }

    public static function recordSelfVerification(string $configPath, int $now, ?string $manifestHash, string $result): void
    {
        self::update($configPath, 'self_verification', ['checked_at' => self::time($now), 'manifest_hash' => $manifestHash, 'result' => $result]);
    }

    public static function recordAnnouncement(string $configPath, int $now, OnionAddress $node, string $result): void
    {
        $nodes = self::load($configPath)['announcement']['nodes'] ?? [];
        $nodes[hash('sha256', $node->hostname)] = ['at' => self::time($now), 'result' => $result];
        uasort($nodes, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
        self::update($configPath, 'announcement', ['nodes' => array_slice($nodes, 0, self::MAX_ANNOUNCED_NODES, true)]);
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
        return $sections;
    }

    /** @param array<string,mixed> $section */
    private static function update(string $configPath, string $name, array $section): void
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

    /** @param array<array-key,mixed> $m */
    private static function validMembership(array $m): bool
    {
        if (count($m) !== 14 || !self::isTime($m['checked_at'] ?? null) || !self::isHash($m['manifest_hash'] ?? null) ||
            !is_string($m['logical_service_id'] ?? null) || preg_match('/\Aed25519:[A-Za-z0-9_-]{43}\z/D', $m['logical_service_id']) !== 1) { return false; }
        foreach (self::MEMBERSHIP_COUNTS as $field) { if (!is_int($m[$field] ?? null) || $m[$field] < 0 || $m[$field] > 1000000) { return false; } }
        return $m['included'] + $m['stale'] + $m['not_present'] + $m['contradictory'] + $m['unavailable'] + $m['rejected'] === $m['selected_sources']
            && $m['selected_sources'] <= PublisherMembership::MAX_SOURCES && $m['included_with_node_direct_observation'] <= $m['included'] && $m['key_epoch'] >= 1;
    }

    /** @param array<array-key,mixed> $s */
    private static function validSelfVerification(array $s): bool
    {
        return count($s) === 3 && self::isTime($s['checked_at'] ?? null) && (($s['manifest_hash'] ?? null) === null || self::isHash($s['manifest_hash'])) && self::isCode($s['result'] ?? null);
    }

    /** @param array<array-key,mixed> $a */
    private static function validAnnouncement(array $a): bool
    {
        if (count($a) !== 1 || !is_array($a['nodes'] ?? null) || count($a['nodes']) > self::MAX_ANNOUNCED_NODES) { return false; }
        foreach ($a['nodes'] as $key => $node) {
            if (!is_string($key) || preg_match('/\A[0-9a-f]{64}\z/D', $key) !== 1 || !is_array($node) || count($node) !== 2 || !self::isTime($node['at'] ?? null) || !self::isCode($node['result'] ?? null)) { return false; }
        }
        return true;
    }
}
