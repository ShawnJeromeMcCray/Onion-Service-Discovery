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
    /** Parent checkpoints fetched per Node and check before ancestry counts as not evaluated. */
    public const MAX_ANCESTRY_FETCHES = 32;
    /** D-036 (owner-approved): a Node's signed checkpoint counts as fresh for seven days, like the browser's STALE rule. */
    public const CHECKPOINT_FRESH_SECONDS = 604800;
    /** A checkpoint dated further ahead of the local clock than this is not counted either. */
    public const CLOCK_TOLERANCE_SECONDS = 300;

    /** @param \Closure(OnionAddress, ProtocolEndpoint): string $fetch */
    public function __construct(private RecordVerifier $verifier, private \Closure $fetch) {}

    /**
     * @param list<ValidatedRecord> $ownHistory Independently held owner continuity evidence.
     * @param list<array{0:string,1:string}> $sources Pairs of Node Onion and independently obtained Node key.
     * @param array<string,array{node_key:string,checkpoint_hash:string,generation:int,checked_at:string}> $anchors
     *        Checkpoints saved at the previous check, keyed by PublisherEvidence::nodeKey().
     * @return array<string,mixed>
     */
    public function check(array $ownHistory, array $sources, int $now, array $anchors = []): array
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
        // Key epoch counts countersigned key changes along the verified history (spec 47 section 40).
        $keys = []; foreach ($ownHistory as $record) { if ($record->type === 'service_manifest') { $keys[$record->contentHash] = $record->document()->service_identity_public_key; } }
        $epoch = 1; $key = $own['genesis_key'];
        foreach ($own['history_hashes'] as $hash) { if ($keys[$hash] !== $key) { ++$epoch; $key = $keys[$hash]; } }
        $expected = ['logical_service_id' => $own['genesis_key'], 'key_epoch' => $epoch, 'current_service_identity_public_key' => $manifest?->service_identity_public_key,
            'onion' => $manifest?->onion, 'revision' => $own['highest_revision'], 'manifest_hash' => $own['current_hash'],
            'active' => $own['discoverability'] === 'PUBLISHED'];
        $results = []; $observed = 0; $fresh = 0; $outdated = 0;
        $counts = ['included' => 0, 'stale' => 0, 'not_present' => 0, 'contradictory' => 0, 'unavailable' => 0, 'rejected' => 0];
        $consistency = ['consistent' => 0, 'baseline' => 0, 'not_evaluated' => 0];
        foreach ($selected as [$address, $key]) {
            $result = $this->source($address, $key, $ownHistory, $now, $anchors[PublisherEvidence::nodeKey($address)] ?? null);
            ++$counts[$result['category']];
            if (isset($result['checkpoint_consistency'])) { ++$consistency[strtolower($result['checkpoint_consistency'])]; }
            if ($result['category'] === 'included') {
                // A Node whose signed index is older than seven days (or dated ahead) is no evidence of current discoverability.
                if ($result['checkpoint_freshness'] !== 'FRESH') { ++$outdated; }
                else { ++$fresh; if ($result['node_claimed_direct_observation_at'] !== null) { ++$observed; } }
            }
            $results[] = $result;
        }
        $total = count($results);
        $status = match (true) {
            $fresh === $total => 'CURRENT_STATE_INCLUDED_AT_ALL_SELECTED',
            $fresh > 0 => 'CURRENT_STATE_INCLUDED_AT_SOME_SELECTED',
            default => 'CURRENT_STATE_NOT_CONFIRMED',
        };
        return [
            'status' => $status,
            'expected_state' => $expected,
            'selected_sources' => $total,
            'distinct_node_keys' => count(array_unique(array_column($results, 'node_identity_public_key'))),
            'counts' => $counts,
            // Only Nodes with a fresh checkpoint count as observing the current revision (D-036).
            'included_with_node_direct_observation' => $observed,
            'included_with_outdated_checkpoint' => $outdated,
            'sources' => $results,
            'evidence_type' => 'COMPLETE_SIGNED_DRAFT_SNAPSHOT',
            'basis' => 'Each count is a locally verified complete signed snapshot from the selected Onion under the independently supplied Node key; acknowledgements and Node booleans are never counted.',
            'operator_independence' => 'NOT_ESTABLISHED',
            // D-052: rollback, equivocation and broken chains are rejected above; these count the rest.
            'checkpoint_consistency' => $consistency,
            'freshness' => 'CHECKPOINT_WITHIN_7_DAYS',
            'state_mutated' => false,
            'network_side_effects' => true,
        ];
    }

    /**
     * @param list<ValidatedRecord> $ownHistory
     * @param array{node_key:string,checkpoint_hash:string,generation:int,checked_at:string}|null $anchor
     * @return array<string,mixed>
     */
    private function source(OnionAddress $node, string $key, array $ownHistory, int $now, ?array $anchor): array
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
            // Before any chunk download: a Node whose checkpoints do not form one chain is not evidence.
            $consistency = $this->ancestry($node, $key, $checkpoint, $anchor);
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
        $age = $created === false ? null : $now - $created;
        return $base + [
            'category' => $category,
            'status' => $verified['status'],
            'snapshot_state' => $verified['snapshot_state'],
            'checkpoint_hash' => $verified['checkpoint_hash'],
            'checkpoint_generation' => $verified['checkpoint_generation'],
            'checkpoint_created_at' => $verified['checkpoint_created_at'],
            // Local-clock age of the Node's signed index; D-036 counts it as current for seven days.
            'checkpoint_age_seconds' => $age,
            'checkpoint_freshness' => match (true) {
                $age === null || $age > self::CHECKPOINT_FRESH_SECONDS => 'OUTDATED',
                $age < -self::CLOCK_TOLERANCE_SECONDS => 'CLOCK_AHEAD',
                default => 'FRESH',
            },
            'node_claimed_direct_observation_at' => $verified['node_claimed_direct_observation_at'],
            'checkpoint_consistency' => $consistency,
            'anchor' => ['node_key' => hash('sha256', $key), 'checkpoint_hash' => $checkpoint->contentHash, 'generation' => $descriptor->generation, 'checked_at' => gmdate('Y-m-d\TH:i:s\Z', $now)],
        ];
    }

    /**
     * D-052 (owner-approved v1): each checkpoint has generation +1 and names its predecessor.
     * Compares the Node's checkpoint with the one saved at the previous check under the same
     * Node key: an older generation, another checkpoint at the same generation or a chain that
     * misses the saved one is rejected. An unreachable or too long gap is only not evaluated.
     * @param array{node_key:string,checkpoint_hash:string,generation:int,checked_at:string}|null $anchor
     */
    private function ancestry(OnionAddress $node, string $key, ValidatedRecord $checkpoint, ?array $anchor): string
    {
        if ($anchor === null || $anchor['node_key'] !== hash('sha256', $key)) { return 'BASELINE'; }
        $document = $checkpoint->document();
        if ($document->generation < $anchor['generation']) { throw new ProtocolException('SOURCE_CHECKPOINT_ROLLBACK'); }
        if ($document->generation === $anchor['generation']) {
            if ($checkpoint->contentHash !== $anchor['checkpoint_hash']) { throw new ProtocolException('SOURCE_CHECKPOINT_EQUIVOCATION'); }
            return 'CONSISTENT';
        }
        // $hash names the checkpoint at $generation; walk back until the saved generation.
        $hash = $document->previous_checkpoint_hash; $generation = $document->generation - 1;
        for ($fetches = 0; $generation > $anchor['generation']; ++$fetches) {
            if (!is_string($hash)) { throw new ProtocolException('SOURCE_CHECKPOINT_CHAIN_BROKEN'); }
            if ($fetches === self::MAX_ANCESTRY_FETCHES) { return 'NOT_EVALUATED'; }
            try { $parent = $this->verifier->verifyWire(($this->fetch)($node, ProtocolEndpoint::snapshot($hash))); }
            catch (TransportException) { return 'NOT_EVALUATED'; }
            $p = $parent->document();
            if ($parent->type !== 'snapshot_descriptor' || $parent->contentHash !== $hash || $p->node_identity_public_key !== $key || $p->generation !== $generation) {
                throw new ProtocolException('SOURCE_CHECKPOINT_CHAIN_BROKEN');
            }
            $hash = $p->previous_checkpoint_hash; --$generation;
        }
        if ($hash !== $anchor['checkpoint_hash']) { throw new ProtocolException('SOURCE_CHECKPOINT_CHAIN_BROKEN'); }
        return 'CONSISTENT';
    }
}
