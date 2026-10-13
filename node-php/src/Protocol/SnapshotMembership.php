<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

use OnionServiceDiscovery\Crypto\Encoding;

/** Offline membership in an authenticated complete draft snapshot, never a Node boolean. */
final readonly class SnapshotMembership
{
    public const MAX_RECORDS = 10000;
    public const MAX_BYTES = 33554432;

    public function __construct(private RecordVerifier $verifier) {}

    /**
     * @param iterable<string> $chunks Canonical chunk bytes in signed descriptor order.
     * @param list<ValidatedRecord> $localHistory Independently validated owner continuity evidence.
     * @return array<string,mixed>
     */
    public function verify(string $checkpointWire, iterable $chunks, string $expectedNodeKey, array $localHistory): array
    {
        Encoding::publicKey($expectedNodeKey);
        $checkpoint = $this->verifier->verifyWire($checkpointWire);
        $descriptor = $checkpoint->document();
        if ($checkpoint->type !== 'snapshot_descriptor' || $descriptor->node_identity_public_key !== $expectedNodeKey) {
            throw new ProtocolException('CHECKPOINT_NODE_MISMATCH');
        }
        $totalBytes = 0;
        foreach ($descriptor->chunks as $metadata) { $totalBytes += $metadata->bytes; }
        if ($descriptor->record_count > self::MAX_RECORDS || $totalBytes > self::MAX_BYTES) {
            throw new ProtocolException('WORK_BUDGET_EXHAUSTED');
        }
        $local = IdentityDeriver::derive($localHistory, self::MAX_RECORDS);
        if (count($local['identities']) !== 1 || $local['pending_hashes'] !== [] || get_object_vars($local['rejected']) !== []) {
            throw new ProtocolException('LOCAL_CONTINUITY_INCOMPLETE');
        }
        $own = $local['identities'][0];
        if ($own['identity_state'] !== 'VALID' || $own['current_hash'] === null) {
            throw new ProtocolException('LOCAL_IDENTITY_CONFLICT');
        }
        $records = []; $position = 0; $lastHash = null;
        $chunkVerifier = new SnapshotChunkVerifier($this->verifier);
        foreach ($chunks as $wire) {
            if ($position >= count($descriptor->chunks)) { throw new ProtocolException('SNAPSHOT_INCOMPLETE'); }
            $verified = $chunkVerifier->verify($wire, $checkpoint, $position, $lastHash);
            foreach ($verified['records'] as $record) { $records[] = $record; }
            $lastHash = $verified['last_hash']; ++$position;
        }
        if ($position !== count($descriptor->chunks) || count($records) !== $descriptor->record_count) {
            throw new ProtocolException('SNAPSHOT_INCOMPLETE');
        }
        $view = IdentityDeriver::derive($records, self::MAX_RECORDS);
        $combined = [];
        foreach (array_merge($localHistory, $records) as $record) { $combined[$record->contentHash] = $record; }
        $conflicting = false;
        foreach (IdentityDeriver::derive(array_values($combined), 2 * self::MAX_RECORDS)['identities'] as $identity) {
            if ($identity['genesis_key'] === $own['genesis_key'] && $identity['identity_state'] === 'FORKED') { $conflicting = true; }
        }
        $found = null;
        foreach ($view['identities'] as $identity) {
            if ($identity['genesis_key'] === $own['genesis_key']) { $found = $identity; break; }
        }
        $status = 'IDENTITY_NOT_PRESENT';
        if ($conflicting) { $status = 'CONFLICTING_SERVICE_STATE'; }
        elseif ($found !== null) {
            if ($found['identity_state'] !== 'VALID') { $status = 'CONFLICTING_SERVICE_STATE'; }
            elseif ($found['current_hash'] === $own['current_hash']) {
                $status = $found['discoverability'] === 'PUBLISHED' ? 'CURRENT_STATE_INCLUDED' : 'WITHDRAWN_IN_SNAPSHOT';
            } elseif ($found['highest_revision'] < $own['highest_revision']) { $status = 'STALE_REVISION_INCLUDED'; }
            else { $status = 'DIFFERENT_SERVICE_STATE'; }
        }
        // Imported observations remain the selected Node's signed claims, never local direct checks.
        $observation = null;
        foreach ($records as $record) {
            if ($record->type !== 'direct_observation') { continue; }
            $doc = $record->document(); $manifest = $own['current_manifest'];
            if ($doc->node_identity_public_key === $expectedNodeKey && $doc->manifest_hash === $own['current_hash'] &&
                $doc->onion === $manifest->onion && $doc->service_identity_public_key === $manifest->service_identity_public_key &&
                $doc->revision === $manifest->revision && ($observation === null || strcmp($doc->observed_at, $observation) > 0)) {
                $observation = $doc->observed_at;
            }
        }
        return [
            'status' => $status,
            'expected_state' => self::tuple($own, $localHistory),
            'snapshot_state' => $found === null ? null : self::tuple($found, $records),
            'checkpoint_hash' => $checkpoint->contentHash,
            'node_identity_public_key' => $expectedNodeKey,
            'checkpoint_generation' => $descriptor->generation,
            'checkpoint_created_at' => $descriptor->created_at,
            'checkpoint_root' => $descriptor->root_hash,
            'evidence_type' => 'COMPLETE_SIGNED_DRAFT_SNAPSHOT',
            'node_claimed_direct_observation_at' => $observation,
            'checkpoint_consistency' => 'NOT_EVALUATED',
            'freshness' => 'NOT_EVALUATED',
            'network_side_effects' => false,
        ];
    }

    /** @param array<array-key,mixed> $identity
     * @param list<ValidatedRecord> $records
     * @return array<string,mixed>
     */
    private static function tuple(array $identity, array $records): array
    {
        $manifest = $identity['current_manifest'];
        $epoch = null;
        if ($manifest !== null) {
            $documents = [];
            foreach ($records as $record) { if ($record->type === 'service_manifest') { $documents[$record->contentHash] = $record->document(); } }
            $epoch = 1; $key = $identity['genesis_key'];
            foreach ($identity['history_hashes'] as $hash) {
                $nextKey = $documents[$hash]->service_identity_public_key;
                if ($nextKey !== $key) { ++$epoch; $key = $nextKey; }
            }
        }
        return ['logical_service_id' => $identity['genesis_key'], 'current_service_identity_public_key' => $manifest?->service_identity_public_key,
            'key_epoch' => $epoch, 'onion' => $manifest?->onion, 'revision' => $identity['highest_revision'],
            'manifest_hash' => $identity['current_hash'], 'protocol_version' => 1, 'active' => $identity['discoverability'] === 'PUBLISHED'];
    }
}
