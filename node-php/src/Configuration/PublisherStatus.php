<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;

/** Public, read-only evidence summary. No key, storage, network or job capability. */
final class PublisherStatus
{
    /** Proposed local default pending D-036 staleness thresholds; not a wire or trust rule. */
    public const EVIDENCE_DEGRADED_AFTER_SECONDS = 604800;

    /**
     * @param string|null $configPath Locates saved aggregate evidence from network:discoverability-check --save-status.
     * @return array<string,mixed>
     */
    public static function inspect(NodeConfig $config, RecordVerifier $verifier, string $manifestPath, ?string $configPath = null, ?int $now = null): array
    {
        $status = [
            'overall' => $config->features['publish_service'] ? 'ERROR' : 'CONFIGURED_NOT_PUBLISHED',
            'roles' => $config->features,
            'onion' => $config->nodeOnion,
            'manifest' => ['state' => 'MISSING'],
            'self_verification' => 'NOT_CHECKED',
            'bootstrap' => 'NOT_CHECKED',
            'announcement' => 'NOT_ATTEMPTED',
            'external_observation' => 'NOT_CHECKED',
            'active_index_membership' => 'NOT_CHECKED',
            'sampled_discoverability' => 'UNCONFIRMED',
            'next_action' => 'Install a signed public Manifest using the documented offline signing workflow.',
        ];
        if ($config->nodeOnion === null) { $status['overall'] = 'NOT_APPLICABLE'; return $status; }
        try {
            if (is_link($manifestPath) || !is_file($manifestPath) || filesize($manifestPath) > 16384) { return $status; }
            $wire = file_get_contents($manifestPath, false, null, 0, 16385);
            if ($wire === false || strlen($wire) > 16384) { return $status; }
            $record = $verifier->verifyWire($wire, OnionAddress::parse($config->nodeOnion));
            if ($record->type !== 'service_manifest') { throw new \InvalidArgumentException('MANIFEST_INVALID'); }
            $doc = $record->document();
            $status['manifest'] = ['state' => 'SIGNATURE_VALID', 'revision' => $doc->revision, 'hash' => $record->contentHash, 'current_key' => $doc->service_identity_public_key, 'publish_service' => $doc->discovery->publish_service,
                'continuity' => $doc->revision === 1 ? 'GENESIS' : 'NOT_CHECKED'];
            // Do not invent a logical identifier or key epoch from a non-genesis record.
            if ($doc->revision === 1) { $status['manifest']['genesis_anchor'] = $doc->service_identity_public_key; }
            if (!$doc->discovery->publish_service) {
                $status['overall'] = 'WITHDRAWN';
                $status['next_action'] = 'This signed Manifest withdraws publication. Independently controlled Nodes may retain historical public evidence.';
            } elseif (!$config->features['publish_service']) {
                $status['overall'] = 'CONFIGURED_NOT_PUBLISHED';
                $status['next_action'] = 'Publishing is disabled in configuration. Enable it explicitly to serve this opted-in Manifest.';
            } else {
                $status['overall'] = 'READY_LOCALLY';
                $status['next_action'] = 'Verify this Manifest through your own Onion using service:verify, then run network:discoverability-check with --save-status against independently selected Nodes. External observation and signed snapshot membership have not been confirmed.';
                $evidence = $configPath === null ? null : PublisherMembership::load($configPath);
                if ($evidence !== null) { self::applyEvidence($status, $evidence, $record->contentHash, $now ?? time()); }
            }
        } catch (\InvalidArgumentException) {
            $status['overall'] = 'ERROR';
            $status['manifest'] = ['state' => 'INVALID'];
            $status['next_action'] = 'Run manifest:verify locally and install a valid signed Manifest for this exact Onion.';
        }
        return $status;
    }

    /**
     * DISCOVERABLE needs a checked Node that both includes the exact current revision in its
     * verified complete signed snapshot and exports its own direct observation of it (spec 47 section 10).
     * @param array<string,mixed> $status
     * @param array<string,mixed> $evidence
     */
    private static function applyEvidence(array &$status, array $evidence, string $currentHash, int $now): void
    {
        if ($evidence['manifest_hash'] !== $currentHash) {
            $status['next_action'] = 'Saved discoverability evidence refers to revision ' . $evidence['revision'] . ', not the installed Manifest. Run network:discoverability-check --save-status again.';
            return;
        }
        $checked = $evidence['selected_sources']; $observed = $evidence['included_with_node_direct_observation'];
        $age = max(0, $now - (int) strtotime($evidence['checked_at']));
        $status['evidence_checked_at'] = $evidence['checked_at'];
        $status['active_index_membership'] = sprintf('CURRENT_REVISION_IN_%d_OF_%d_CHECKED_NODE_SNAPSHOTS', $evidence['included'], $checked);
        $status['external_observation'] = match (true) {
            $evidence['contradictory'] > 0 => 'CONFLICT_REPORTED',
            $observed > 0 => 'OBSERVED_CURRENT_REVISION',
            $evidence['stale'] > 0 => 'OBSERVED_STALE_REVISION',
            default => 'NO_EXTERNAL_OBSERVATION',
        };
        $unreached = $evidence['unavailable'] + $evidence['rejected'];
        if ($evidence['contradictory'] > 0) {
            $status['overall'] = 'IDENTITY_CONFLICT';
            $status['next_action'] = sprintf('%d checked Node(s) hold a conflicting state for this identity. Compare their evidence with your own signed history before publishing another revision.', $evidence['contradictory']);
        } elseif ($observed > 0) {
            $stale = $age > self::EVIDENCE_DEGRADED_AFTER_SECONDS;
            $status['overall'] = $stale ? 'DISCOVERABLE_DEGRADED' : 'DISCOVERABLE';
            $status['sampled_discoverability'] = sprintf('Current revision %d verified in the signed snapshots of %d of %d checked Discovery Nodes', $evidence['revision'], $observed, $checked);
            $status['next_action'] = $stale
                ? 'The saved evidence is older than seven days. Run network:discoverability-check --save-status again.'
                : ($unreached > 0 ? sprintf('%d checked Node(s) could not be verified. Several Node keys may still belong to one operator.', $unreached)
                    : 'No action needed. Recheck after each new revision. Several Node keys may still belong to one operator.');
        } elseif ($evidence['included'] > 0) {
            $status['next_action'] = 'The current revision is included by checked Nodes that exported no direct observation of their own. Ask a cooperating Node to run its direct verification worker.';
        } else {
            $status['next_action'] = sprintf('No checked Node includes the current revision yet (%d stale, %d not present, %d unreachable or rejected). Announce the Service or ask the Node operators to admit it, then recheck.', $evidence['stale'], $evidence['not_present'], $unreached);
        }
    }
}
