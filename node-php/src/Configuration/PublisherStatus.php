<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;

/**
 * Public, read-only evidence summary (spec 47). No key, storage, network or job capability:
 * external facts come only from evidence saved by explicit `--save-status` CLI runs.
 */
final class PublisherStatus
{
    /** Proposed local default pending D-036 staleness thresholds; not a wire or trust rule. */
    public const EVIDENCE_DEGRADED_AFTER_SECONDS = 604800;

    private const ANSWERS = [
        'DISCOVERABLE' => 'YES',
        'DISCOVERABLE_DEGRADED' => 'DEGRADED',
        'IDENTITY_CONFLICT' => 'PROBLEM',
        'READY_LOCALLY' => 'NOT YET',
        'CONFIGURED_NOT_PUBLISHED' => 'NO',
        'WITHDRAWN' => 'NO',
        'ERROR' => 'NO',
        'NOT_APPLICABLE' => 'NOT APPLICABLE',
        'NOT_CONFIGURED' => 'NO',
    ];

    /**
     * @param string|null $configPath Locates evidence saved beside the private configuration.
     * @return array<string,mixed>
     */
    public static function inspect(NodeConfig $config, RecordVerifier $verifier, string $manifestPath, ?string $configPath = null, ?int $now = null): array
    {
        $now ??= time();
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
        $hash = null;
        if ($config->nodeOnion === null) { $status['overall'] = 'NOT_APPLICABLE'; $status['next_action'] = 'This installation does not publish an Onion Service.'; return self::finish($status, $config, []); }
        try {
            if (is_link($manifestPath) || !is_file($manifestPath) || filesize($manifestPath) > 16384) {
                $status['next_action'] = $config->features['publish_service']
                    ? 'No signed Manifest is installed, so /.well-known/onion-service-discovery.json cannot be served. Sign one offline and run manifest:install.'
                    : 'Publishing is disabled in configuration. Your Service is not intentionally advertised by this installation.';
                return self::finish($status, $config, []);
            }
            $wire = file_get_contents($manifestPath, false, null, 0, 16385);
            if ($wire === false || strlen($wire) > 16384) { return self::finish($status, $config, []); }
            $record = $verifier->verifyWire($wire, OnionAddress::parse($config->nodeOnion));
            if ($record->type !== 'service_manifest') { throw new \InvalidArgumentException('MANIFEST_INVALID'); }
            $doc = $record->document(); $hash = $record->contentHash;
            $status['manifest'] = ['state' => 'SIGNATURE_VALID', 'revision' => $doc->revision, 'hash' => $record->contentHash, 'current_key' => $doc->service_identity_public_key, 'publish_service' => $doc->discovery->publish_service,
                'continuity' => $doc->revision === 1 ? 'GENESIS' : 'NOT_CHECKED'];
            // Do not invent a logical identifier or key epoch from a non-genesis record.
            if ($doc->revision === 1) { $status['manifest']['genesis_anchor'] = $doc->service_identity_public_key; $status['manifest']['logical_service_id'] = $doc->service_identity_public_key; $status['manifest']['key_epoch'] = 1; }
            if (!$doc->discovery->publish_service) {
                $status['overall'] = 'WITHDRAWN';
                $status['next_action'] = 'Withdrawn from active discovery. Other independently controlled Nodes may retain historical public evidence; global deletion cannot be guaranteed. Distinct Node keys alone do not prove independent control.';
            } elseif (!$config->features['publish_service']) {
                $status['overall'] = 'CONFIGURED_NOT_PUBLISHED';
                $status['next_action'] = 'Publishing is disabled in configuration. Your Service is not intentionally advertised by this installation; enable it explicitly to serve this opted-in Manifest.';
            } else {
                $status['overall'] = 'READY_LOCALLY';
                $status['next_action'] = 'Run service:verify --save-status through your own Onion, announce to selected Nodes, then run network:discoverability-check --save-status. External observation and signed snapshot membership have not been confirmed.';
            }
        } catch (\InvalidArgumentException) {
            $status['overall'] = 'ERROR';
            $status['manifest'] = ['state' => 'INVALID'];
            $status['next_action'] = 'The installed Manifest is invalid for this Onion. Run manifest:verify locally and install a valid signed Manifest.';
            return self::finish($status, $config, []);
        }
        $evidence = $configPath === null ? [] : PublisherEvidence::load($configPath);
        if ($status['overall'] === 'READY_LOCALLY') { self::applyEvidence($status, $evidence, (string) $hash, $now); }
        return self::finish($status, $config, $evidence, (string) $hash, $now);
    }

    /**
     * DISCOVERABLE needs a checked Node that both includes the exact current revision in its
     * verified complete signed snapshot and exports its own direct observation of it (spec 47 section 10).
     * @param array<string,mixed> $status
     * @param array<string,mixed> $evidence
     */
    private static function applyEvidence(array &$status, array $evidence, string $currentHash, int $now): void
    {
        $self = $evidence['self_verification'] ?? null;
        if ($self !== null) {
            $status['self_verification'] = $self['result'] === 'SIGNATURE_AND_BINDING_VALID'
                ? ($self['manifest_hash'] === $currentHash ? 'PASS' : 'SERVED_MANIFEST_DIFFERS') : 'FAIL_' . $self['result'];
            $status['self_verification_checked_at'] = $self['checked_at'];
            if ($status['self_verification'] === 'SERVED_MANIFEST_DIFFERS') {
                $status['next_action'] = 'Your Onion served a different signed Manifest than the one installed here. Check the web routing, then rerun service:verify --save-status.';
            } elseif ($status['self_verification'] !== 'PASS') {
                $status['next_action'] = $self['result'] === 'TOR_REQUEST_FAILED'
                    ? 'Tor is not reachable through the configured SOCKS endpoint, or your Onion did not answer. Check Tor and rerun service:verify --save-status.'
                    : 'Fetching your own Well-Known Manifest through Tor failed with ' . $self['result'] . '. Check that the route serves the installed Manifest.';
            }
        }
        $nodes = $evidence['announcement']['nodes'] ?? [];
        if ($nodes !== []) {
            $accepted = count(array_filter($nodes, static fn (array $node): bool => $node['result'] === 'HINT_RECEIVED_UNCONFIRMED'));
            $status['announcement'] = sprintf('HINT_ACCEPTED_BY_%d_OF_%d_ATTEMPTED_NODES', $accepted, count($nodes));
            $last = ''; foreach ($nodes as $node) { if (strcmp($node['at'], $last) > 0) { $last = $node['at']; } }
            $status['announcement_last_at'] = $last;
        }
        $membership = $evidence['membership'] ?? null;
        if ($membership === null) { return; }
        if ($membership['manifest_hash'] !== $currentHash) {
            $status['next_action'] = 'Checked Nodes were last compared with revision ' . $membership['revision'] . ', not the installed Manifest. Run network:discoverability-check --save-status again.';
            return;
        }
        $checked = $membership['selected_sources']; $observed = $membership['included_with_node_direct_observation'];
        $age = max(0, $now - (int) strtotime($membership['checked_at']));
        $status['manifest']['logical_service_id'] = $membership['logical_service_id'];
        $status['manifest']['key_epoch'] = $membership['key_epoch'];
        $status['manifest']['continuity'] = 'VERIFIED_FROM_OWNER_HISTORY';
        $status['evidence_checked_at'] = $membership['checked_at'];
        $status['bootstrap'] = $membership['unavailable'] === $checked ? 'NO_CHECKED_NODE_REACHABLE' : sprintf('%d_OF_%d_CHECKED_NODES_REACHED', $checked - $membership['unavailable'], $checked);
        $status['active_index_membership'] = sprintf('CURRENT_REVISION_IN_%d_OF_%d_CHECKED_NODE_SNAPSHOTS', $membership['included'], $checked);
        $status['external_observation'] = match (true) {
            $membership['contradictory'] > 0 => 'CONFLICT_REPORTED',
            $observed > 0 => 'OBSERVED_CURRENT_REVISION',
            $membership['stale'] > 0 => 'OBSERVED_STALE_REVISION',
            default => 'NO_EXTERNAL_OBSERVATION',
        };
        $unreached = $membership['unavailable'] + $membership['rejected'];
        if ($membership['contradictory'] > 0) {
            $status['overall'] = 'IDENTITY_CONFLICT';
            $status['next_action'] = sprintf('Conflicting signed identity state detected at %d checked Node(s). Compare their evidence with your own signed history before publishing another revision.', $membership['contradictory']);
        } elseif ($observed > 0) {
            $stale = $age > self::EVIDENCE_DEGRADED_AFTER_SECONDS;
            $status['overall'] = $stale ? 'DISCOVERABLE_DEGRADED' : 'DISCOVERABLE';
            $status['sampled_discoverability'] = sprintf('Current revision %d verified in the signed snapshots of %d of %d checked Discovery Nodes', $membership['revision'], $observed, $checked);
            $status['next_action'] = $stale
                ? 'The saved evidence is older than seven days. Run network:discoverability-check --save-status again.'
                : ($unreached > 0 ? sprintf('%d checked Node(s) could not be verified. Several Node keys may still belong to one operator.', $unreached)
                    : 'No action needed. Recheck after each new revision. Several Node keys may still belong to one operator.');
        } elseif ($membership['included'] > 0) {
            $status['next_action'] = 'The current revision is included by checked Nodes that exported no direct observation of their own. Ask a cooperating Node to run its direct verification worker.';
        } elseif ($membership['stale'] > 0) {
            $status['next_action'] = sprintf('Checked Nodes still expose an older revision (%d of %d). This is ordinary propagation delay; recheck after their next snapshot.', $membership['stale'], $checked);
        } else {
            $status['next_action'] = sprintf('No checked Node includes this Service yet (%d not present, %d unreachable or rejected). Announce it or ask the Node operators to admit it, then recheck.', $membership['not_present'], $unreached);
        }
    }

    /**
     * Adds the plain-language answer (section 30), role explanations (34/35) and the step
     * pipeline (11); every step is derived from the facts above, never hand-set.
     * @param array<string,mixed> $status
     * @param array<string,mixed> $evidence
     * @return array<string,mixed>
     */
    private static function finish(array $status, NodeConfig $config, array $evidence, ?string $hash = null, ?int $now = null): array
    {
        $overall = $status['overall'];
        $status['answer'] = self::ANSWERS[$overall] ?? 'NO';
        $publishing = in_array($overall, ['READY_LOCALLY', 'DISCOVERABLE', 'DISCOVERABLE_DEGRADED', 'IDENTITY_CONFLICT'], true);
        $manifestValid = ($status['manifest']['state'] ?? null) === 'SIGNATURE_VALID';
        $na = static fn (string $state): string => $publishing ? $state : 'NOT_APPLICABLE';
        $self = $status['self_verification'];
        $membership = ($evidence['membership']['manifest_hash'] ?? null) === $hash ? ($evidence['membership'] ?? null) : null;
        $status['pipeline'] = [
            'installation' => 'PASS',
            'configuration' => 'PASS',
            'identity' => $config->nodeOnion === null ? 'NOT_APPLICABLE' : ($manifestValid ? 'PASS' : ($status['manifest']['state'] === 'INVALID' ? 'FAIL' : 'PENDING')),
            'manifest' => $config->nodeOnion === null ? 'NOT_APPLICABLE' : ($manifestValid ? 'PASS' : ($status['manifest']['state'] === 'INVALID' ? 'FAIL' : ($config->features['publish_service'] ? 'FAIL' : 'NOT_APPLICABLE'))),
            'onion_self_verification' => $na($self === 'PASS' ? 'PASS' : ($self === 'NOT_CHECKED' ? 'PENDING' : 'FAIL')),
            'bootstrap_connectivity' => $na($membership === null ? 'PENDING' : ($membership['unavailable'] === $membership['selected_sources'] ? 'FAIL' : 'PASS')),
            'announcement' => $na(str_starts_with($status['announcement'], 'HINT_ACCEPTED_BY_0_') ? 'WARN' : (str_starts_with($status['announcement'], 'HINT_ACCEPTED_BY_') ? 'PASS' : 'PENDING')),
            'external_direct_observation' => $na(match ($status['external_observation']) { 'OBSERVED_CURRENT_REVISION' => 'PASS', 'CONFLICT_REPORTED' => 'FAIL', 'OBSERVED_STALE_REVISION' => 'PENDING', default => 'PENDING' }),
            'searchable_state' => $na($membership === null ? 'PENDING' : ($membership['contradictory'] > 0 ? 'FAIL' : ($membership['included'] > 0 ? 'PASS' : 'PENDING'))),
            'redundancy_freshness' => $na($membership === null || $membership['included_with_node_direct_observation'] === 0 ? 'PENDING'
                : (($now ?? time()) - (int) strtotime($membership['checked_at']) > self::EVIDENCE_DEGRADED_AFTER_SECONDS || $membership['included_with_node_direct_observation'] < 2 ? 'WARN' : 'PASS')),
        ];
        $status['role_explanations'] = [
            'publish_service' => $config->features['publish_service'] ? 'Your signed public metadata is eligible for decentralized discovery.' : 'Your Service is not intentionally advertised by this installation.',
            'mirror_directory' => $config->features['mirror_directory'] ? 'This installation stores and serves signed public directory data.' : 'This installation does not serve directory data for others.',
            'discover_services' => $config->features['discover_services'] ? 'This installation verifies admitted candidate Services over Tor.' : 'This installation does not check other Services.',
            'sync_with_peers' => $config->features['sync_with_peers'] ? 'This installation imports signed snapshots from explicitly pinned peers.' : 'This installation does not synchronize with other Nodes.',
        ];
        return $status;
    }
}
