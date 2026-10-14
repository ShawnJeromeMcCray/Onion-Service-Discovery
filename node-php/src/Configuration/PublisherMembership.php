<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotMembership;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TransportException;
use OnionServiceDiscovery\Validation\StrictJson;

/**
 * Publisher-side summary of exact current-state membership at explicitly selected Nodes.
 * Each source is fetched and verified independently; key count never becomes trust.
 */
final readonly class PublisherMembership
{
    public const MAX_SOURCES = 16;

    /** @param \Closure(OnionAddress, ProtocolEndpoint): string $fetch */
    public function __construct(private RecordVerifier $verifier, private \Closure $fetch) {}

    /**
     * @param list<ValidatedRecord> $ownHistory Independently held owner continuity evidence.
     * @param list<array{0:string,1:string}> $sources Pairs of Node Onion and independently obtained Node key.
     * @return array<string,mixed>
     */
    public function check(array $ownHistory, array $sources, int $now): array
    {
        if ($sources === [] || count($sources) > self::MAX_SOURCES) { throw new ProtocolException('USAGE_ERROR'); }
        $selected = [];
        foreach ($sources as [$onion, $key]) {
            $address = OnionAddress::parse($onion); Encoding::publicKey($key);
            if (isset($selected[$address->hostname])) { throw new ProtocolException('SOURCE_DUPLICATE'); }
            $selected[$address->hostname] = [$address, $key];
        }
        $local = IdentityDeriver::derive($ownHistory, SnapshotMembership::MAX_RECORDS);
        if (count($local['identities']) !== 1 || $local['pending_hashes'] !== [] || get_object_vars($local['rejected']) !== []) {
            throw new ProtocolException('LOCAL_CONTINUITY_INCOMPLETE');
        }
        if ($local['identities'][0]['identity_state'] !== 'VALID' || $local['identities'][0]['current_hash'] === null) {
            throw new ProtocolException('LOCAL_IDENTITY_CONFLICT');
        }
        $own = $local['identities'][0]; $manifest = $own['current_manifest'];
        $expected = ['logical_service_id' => $own['genesis_key'], 'current_service_identity_public_key' => $manifest?->service_identity_public_key,
            'onion' => $manifest?->onion, 'revision' => $own['highest_revision'], 'manifest_hash' => $own['current_hash'],
            'active' => $own['discoverability'] === 'PUBLISHED'];
        $results = []; $observed = 0;
        $counts = ['included' => 0, 'stale' => 0, 'not_present' => 0, 'contradictory' => 0, 'unavailable' => 0, 'rejected' => 0];
        foreach ($selected as [$address, $key]) {
            $result = $this->source($address, $key, $ownHistory, $now);
            ++$counts[$result['category']];
            if ($result['category'] === 'included' && $result['node_claimed_direct_observation_at'] !== null) { ++$observed; }
            $results[] = $result;
        }
        $total = count($results);
        $status = match (true) {
            $counts['included'] === $total => 'CURRENT_STATE_INCLUDED_AT_ALL_SELECTED',
            $counts['included'] > 0 => 'CURRENT_STATE_INCLUDED_AT_SOME_SELECTED',
            default => 'CURRENT_STATE_NOT_CONFIRMED',
        };
        return [
            'status' => $status,
            'expected_state' => $expected,
            'selected_sources' => $total,
            'distinct_node_keys' => count(array_unique(array_column($results, 'node_identity_public_key'))),
            'counts' => $counts,
            'included_with_node_direct_observation' => $observed,
            'sources' => $results,
            'evidence_type' => 'COMPLETE_SIGNED_DRAFT_SNAPSHOT',
            'basis' => 'Each count is a locally verified complete signed snapshot from the selected Onion under the independently supplied Node key; acknowledgements and Node booleans are never counted.',
            'operator_independence' => 'NOT_ESTABLISHED',
            'checkpoint_consistency' => 'NOT_EVALUATED',
            'freshness' => 'NOT_EVALUATED',
            'state_mutated' => false,
            'network_side_effects' => true,
        ];
    }

    /** Private publisher evidence beside the configuration, readable by the status page. */
    public static function evidencePath(string $configPath): string
    {
        return dirname($configPath) . '/' . preg_replace('/\.json\z/D', '', basename($configPath)) . '.discoverability.json';
    }

    /**
     * Persist aggregates only: no Node addresses or keys reach the public status page.
     * @param array<string,mixed> $result
     */
    public static function save(array $result, string $configPath, int $now): string
    {
        $path = self::evidencePath($configPath);
        $evidence = ['evidence_version' => 1, 'checked_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
            'manifest_hash' => $result['expected_state']['manifest_hash'], 'revision' => $result['expected_state']['revision'],
            'selected_sources' => $result['selected_sources'], 'distinct_node_keys' => $result['distinct_node_keys'],
            'included_with_node_direct_observation' => $result['included_with_node_direct_observation']] + $result['counts'];
        if (is_link($path)) { throw new ProtocolException('EVIDENCE_PATH_UNSAFE'); }
        $mode = @fileperms($configPath);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false ||
            !chmod($temporary, $mode === false ? 0600 : ($mode & 0640)) || !rename($temporary, $path)) {
            @unlink($temporary); throw new ProtocolException('EVIDENCE_WRITE_FAILED');
        }
        return $path;
    }

    /** @return array<string,mixed>|null Strictly validated saved evidence, or null when absent/invalid. */
    public static function load(string $configPath): ?array
    {
        $path = self::evidencePath($configPath);
        if (is_link($path) || !is_file($path) || filesize($path) > 4096) { return null; }
        $wire = file_get_contents($path, false, null, 0, 4097);
        try { $value = $wire === false ? null : get_object_vars(StrictJson::parse($wire, 4096)); }
        catch (\InvalidArgumentException) { return null; }
        $counts = ['selected_sources', 'distinct_node_keys', 'included_with_node_direct_observation', 'included', 'stale', 'not_present', 'contradictory', 'unavailable', 'rejected', 'revision'];
        if ($value === null || count($value) !== 13 || ($value['evidence_version'] ?? null) !== 1 ||
            !is_string($value['checked_at'] ?? null) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D', $value['checked_at']) !== 1 ||
            !is_string($value['manifest_hash'] ?? null) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $value['manifest_hash']) !== 1) { return null; }
        foreach ($counts as $field) { if (!is_int($value[$field] ?? null) || $value[$field] < 0 || $value[$field] > 1000000) { return null; } }
        if ($value['included'] + $value['stale'] + $value['not_present'] + $value['contradictory'] + $value['unavailable'] + $value['rejected'] !== $value['selected_sources'] ||
            $value['included_with_node_direct_observation'] > $value['included'] || $value['selected_sources'] > self::MAX_SOURCES) { return null; }
        return $value;
    }

    /**
     * @param list<ValidatedRecord> $ownHistory
     * @return array<string,mixed>
     */
    private function source(OnionAddress $node, string $key, array $ownHistory, int $now): array
    {
        $base = ['node_onion' => $node->hostname, 'node_identity_public_key' => $key];
        try {
            $state = StrictJson::parse(($this->fetch)($node, ProtocolEndpoint::state()), 16384);
            $hash = $state->checkpoint_hash ?? null;
            if (!is_string($hash) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $hash) !== 1) { throw new ProtocolException('SOURCE_STATE_INVALID'); }
            if (($state->node_identity_public_key ?? null) !== $key) { throw new ProtocolException('SOURCE_KEY_MISMATCH'); }
            $wire = ($this->fetch)($node, ProtocolEndpoint::snapshot($hash));
            $checkpoint = $this->verifier->verifyWire($wire); $descriptor = $checkpoint->document();
            if ($checkpoint->type !== 'snapshot_descriptor' || $checkpoint->contentHash !== $hash ||
                $descriptor->generation !== ($state->generation ?? null) || $descriptor->cursor !== ($state->cursor ?? null)) {
                throw new ProtocolException('SOURCE_CHECKPOINT_BINDING_MISMATCH');
            }
            // Chunks are fetched lazily so SnapshotMembership enforces its work budget before any download.
            $chunks = (function () use ($node, $descriptor): \Generator {
                foreach ($descriptor->chunks as $chunk) { yield ($this->fetch)($node, ProtocolEndpoint::chunk($chunk->hash)); }
            })();
            $verified = (new SnapshotMembership($this->verifier))->verify($wire, $chunks, $key, $ownHistory);
        } catch (TransportException $error) {
            return $base + ['category' => 'unavailable', 'status' => 'SOURCE_UNAVAILABLE', 'error' => $error->getMessage()];
        } catch (\InvalidArgumentException $error) {
            return $base + ['category' => 'rejected', 'status' => 'SOURCE_EVIDENCE_REJECTED', 'error' => $error->getMessage()];
        }
        $category = match ($verified['status']) {
            'CURRENT_STATE_INCLUDED', 'WITHDRAWN_IN_SNAPSHOT' => 'included',
            'STALE_REVISION_INCLUDED' => 'stale',
            'IDENTITY_NOT_PRESENT' => 'not_present',
            default => 'contradictory',
        };
        $created = strtotime($verified['checkpoint_created_at']);
        return $base + [
            'category' => $category,
            'status' => $verified['status'],
            'snapshot_state' => $verified['snapshot_state'],
            'checkpoint_hash' => $verified['checkpoint_hash'],
            'checkpoint_generation' => $verified['checkpoint_generation'],
            'checkpoint_created_at' => $verified['checkpoint_created_at'],
            // Informational local-clock difference; no freshness threshold is frozen in the draft protocol.
            'checkpoint_age_seconds' => $created === false ? null : $now - $created,
            'node_claimed_direct_observation_at' => $verified['node_claimed_direct_observation_at'],
        ];
    }
}
