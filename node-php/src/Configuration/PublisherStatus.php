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
    /** Owner-approved local threshold (D-036, 2026-10-14); not a wire or trust rule. */
    public const EVIDENCE_DEGRADED_AFTER_SECONDS = 604800;

    /** D-052 rejections: a checked Node contradicted the checkpoint it served at the previous check. */
    public const CHECKPOINT_CONFLICT_ERRORS = ['SOURCE_CHECKPOINT_ROLLBACK', 'SOURCE_CHECKPOINT_EQUIVOCATION', 'SOURCE_CHECKPOINT_CHAIN_BROKEN'];

    /** Spec 47 section 9, in the order a new Service normally passes them. */
    private const ANSWERS = [
        'DISCOVERABLE' => 'YES',
        'DISCOVERABLE_DEGRADED' => 'DEGRADED',
        'IDENTITY_CONFLICT' => 'PROBLEM',
        'EXTERNALLY_OBSERVED' => 'NOT YET',
        'ANNOUNCED_PENDING_VERIFICATION' => 'NOT YET',
        'CONNECTING' => 'NOT YET',
        'READY_LOCALLY' => 'NOT YET',
        'CONFIGURED_NOT_PUBLISHED' => 'NO',
        'WITHDRAWN' => 'NO',
        'ERROR' => 'NO',
        'NOT_APPLICABLE' => 'NOT APPLICABLE',
        'NOT_CONFIGURED' => 'NO',
    ];
    /** States in which a valid Manifest is published and saved evidence decides the progress. */
    private const PUBLISHING = ['READY_LOCALLY', 'CONNECTING', 'ANNOUNCED_PENDING_VERIFICATION', 'EXTERNALLY_OBSERVED', 'DISCOVERABLE', 'DISCOVERABLE_DEGRADED', 'IDENTITY_CONFLICT'];

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
        $hash = null; $wire = null;
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
                'continuity' => $doc->revision === 1 ? 'GENESIS' : 'NOT_CHECKED',
                'history_bundle' => is_file(dirname($manifestPath) . '/onion-service-discovery-history.json') ? 'INSTALLED' : 'NOT_INSTALLED'];
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
        } catch (\InvalidArgumentException $error) {
            $status['overall'] = 'ERROR';
            $status['manifest'] = ['state' => 'INVALID'];
            $status['next_action'] = 'The installed Manifest is invalid for this Onion. Run manifest:verify locally and install a valid signed Manifest.';
            // After an Onion migration the new Manifest is valid, but for the new address (spec 47 section 59).
            if ($error->getMessage() === 'ONION_BINDING_MISMATCH' && is_string($wire)) {
                try {
                    $other = $verifier->verifyWire($wire)->document();
                    $status['manifest'] = ['state' => 'OTHER_ONION', 'onion' => $other->onion, 'revision' => $other->revision];
                    $status['next_action'] = sprintf('The installed Manifest is signed for %s, not for the configured %s. After an Onion migration, set node.onion to the new address and serve this installation on the new Onion; otherwise install the Manifest that belongs to this Onion.', $other->onion, $config->nodeOnion);
                } catch (\InvalidArgumentException) {}
            }
            return self::finish($status, $config, []);
        }
        $evidence = $configPath === null ? [] : PublisherEvidence::load($configPath);
        if ($status['overall'] === 'READY_LOCALLY') { self::applyEvidence($status, $evidence, (string) $hash, $now, (int) @filemtime($manifestPath), $config->publisherNodes !== []); }
        if ($status['overall'] === 'READY_LOCALLY' && $evidence === [] && $configPath !== null && PublisherEvidence::unreadable($configPath)) {
            $status['next_action'] = 'Saved check results exist, but this web server cannot read them. Run the checks and the publisher timer as the same service user as the web server (the templates use osd-worker).';
        }
        return self::finish($status, $config, $evidence, (string) $hash, $now);
    }

    /**
     * Derives the overall state (spec 47 section 9) and the most urgent next action from saved evidence.
     * DISCOVERABLE needs a checked Node that both includes the exact current revision in its verified
     * complete signed snapshot and exports its own direct observation of it (spec 47 section 10).
     * @param array<string,mixed> $status
     * @param array<string,mixed> $evidence
     */
    private static function applyEvidence(array &$status, array $evidence, string $currentHash, int $now, int $installedAt, bool $nodesConfigured): void
    {
        $actions = []; // candidate next actions keyed by priority: the lowest key is shown
        $self = $evidence['self_verification'] ?? null;
        if ($self !== null) {
            $checkedAt = (int) strtotime($self['checked_at']);
            $status['self_verification_checked_at'] = $self['checked_at'];
            if ($self['result'] !== 'SIGNATURE_AND_BINDING_VALID') {
                $status['self_verification'] = 'FAIL_' . $self['result'];
                $actions[20] = self::selfCheckAction($self['result']);
            } elseif ($self['manifest_hash'] !== $currentHash && $checkedAt < $installedAt) {
                // A check from before this revision was installed says nothing about it.
                $status['self_verification'] = 'NOT_CHECKED_SINCE_INSTALL';
                $actions[30] = 'The installed revision has not been fetched through your Onion yet. Run service:verify --save-status; the publisher timer does this every six hours.';
            } elseif ($self['manifest_hash'] !== $currentHash) {
                $status['self_verification'] = 'SERVED_MANIFEST_DIFFERS';
                $actions[20] = 'Your Onion served a different signed Manifest than the one installed here. Check that your web server passes the Well-Known path to this checkout\'s OSD front controller, then rerun service:verify --save-status.';
            } elseif ($now - $checkedAt > self::EVIDENCE_DEGRADED_AFTER_SECONDS) {
                $status['self_verification'] = 'PASS_OUTDATED';
                $actions[60] = 'Your last successful self-check is older than seven days. Run service:verify --save-status again and check that the publisher timer runs.';
            } else {
                $status['self_verification'] = 'PASS';
            }
        }
        $nodes = $evidence['announcement']['nodes'] ?? [];
        $accepted = 0; $acceptedAny = 0;
        if ($nodes !== []) {
            $results = ['accepted' => 0, 'rate limited' => 0, 'not accepting announcements' => 0, 'unreachable' => 0, 'local Tor unavailable' => 0, 'in progress' => 0, 'other failures' => 0];
            foreach ($nodes as $node) {
                ++$results[match ($node['result']) { 'HINT_RECEIVED_UNCONFIRMED' => 'accepted', 'ANNOUNCEMENT_RATE_LIMITED' => 'rate limited', 'HTTP_NOT_FOUND' => 'not accepting announcements',
                    'ONION_UNREACHABLE', 'TOR_REQUEST_TIMEOUT', 'TOR_REQUEST_FAILED' => 'unreachable', 'TOR_PROXY_UNREACHABLE' => 'local Tor unavailable',
                    AnnouncementSchedule::STARTED => 'in progress', default => 'other failures' }];
                if ($node['result'] === 'HINT_RECEIVED_UNCONFIRMED') { ++$acceptedAny; if (($node['manifest_hash'] ?? null) === $currentHash) { ++$accepted; } }
            }
            $status['announcement'] = sprintf('HINT_ACCEPTED_BY_%d_OF_%d_ATTEMPTED_NODES', $results['accepted'], count($nodes));
            $status['announcement_results'] = implode(', ', array_map(static fn (string $name, int $count): string => $name . ' ' . $count, array_keys($results), $results));
            $last = ''; foreach ($nodes as $node) { if (strcmp($node['at'], $last) > 0) { $last = $node['at']; } }
            $status['announcement_last_at'] = $last;
            // Spec 47 section 23: the next bounded retry for Nodes that still lack the current revision.
            $categories = ($evidence['membership']['manifest_hash'] ?? null) === $currentHash ? ($evidence['membership']['nodes'] ?? []) : [];
            $retry = null;
            foreach ($nodes as $key => $node) {
                $decision = AnnouncementSchedule::decide($node, $categories[$key] ?? null, $currentHash, $now);
                if (in_array($decision['reason'], ['BACKOFF', 'DAILY_LIMIT'], true)) { $retry = min($retry ?? PHP_INT_MAX, (int) $decision['next_due_at']); }
            }
            if ($retry !== null) { $status['announcement_next_retry_at'] = gmdate('Y-m-d\TH:i:s\Z', $retry); }
            if ($acceptedAny > 0) { $status['bootstrap'] = sprintf('ANNOUNCEMENT_ACCEPTED_BY_%d_NODES', $acceptedAny); }
        }
        $membership = $evidence['membership'] ?? null;
        $overall = 'READY_LOCALLY';
        if ($membership !== null) {
            // Reachability and checkpoint integrity concern the Nodes, whatever revision they were compared with.
            $reached = $membership['selected_sources'] - $membership['unavailable'];
            if ($reached > 0 || $acceptedAny === 0) {
                $status['bootstrap'] = $reached === 0 ? 'NO_CHECKED_NODE_REACHABLE' : sprintf('%d_OF_%d_CHECKED_NODES_REACHED', $reached, $membership['selected_sources']);
            }
            $conflicts = $membership['checkpoint_conflicts'] ?? 0;
            $status['source_integrity'] = $conflicts > 0 ? sprintf('CHECKPOINT_CONFLICT_AT_%d_CHECKED_NODES', $conflicts) : 'NO_CONFLICT_DETECTED';
            if ($conflicts > 0) {
                // Spec 47 section 79.
                $status['warnings'][] = sprintf('Conflicting signed index checkpoints detected at %d checked Discovery Node(s): a rollback, two different checkpoints for one generation, or a broken chain since your previous check. Their answers are not used. Keep the saved evidence file and tell the Node operators.', $conflicts);
            }
            if ($membership['manifest_hash'] !== $currentHash) {
                $actions[50] = 'Checked Nodes were last compared with revision ' . $membership['revision'] . ', not the installed Manifest. Run network:discoverability-check --save-status again.';
            } else {
                $checked = $membership['selected_sources']; $observed = $membership['included_with_node_direct_observation'];
                $age = max(0, $now - (int) strtotime($membership['checked_at']));
                $status['manifest']['logical_service_id'] = $membership['logical_service_id'];
                $status['manifest']['key_epoch'] = $membership['key_epoch'];
                $status['manifest']['continuity'] = 'VERIFIED_FROM_OWNER_HISTORY';
                $status['evidence_checked_at'] = $membership['checked_at'];
                $status['active_index_membership'] = sprintf('CURRENT_REVISION_IN_%d_OF_%d_CHECKED_NODE_SNAPSHOTS', $membership['included'], $checked);
                $status['external_observation'] = match (true) {
                    $membership['contradictory'] > 0 => 'CONFLICT_REPORTED',
                    $observed > 0 => 'OBSERVED_CURRENT_REVISION',
                    $membership['stale'] > 0 => 'OBSERVED_STALE_REVISION',
                    default => 'NO_EXTERNAL_OBSERVATION',
                };
                $unreached = $membership['unavailable'] + $membership['rejected'];
                if ($membership['contradictory'] > 0) {
                    $overall = 'IDENTITY_CONFLICT';
                    $actions[10] = sprintf('Conflicting signed identity state detected at %d checked Node(s). Compare their evidence with your own signed history before publishing another revision.', $membership['contradictory']);
                } elseif ($observed > 0) {
                    $stale = $age > self::EVIDENCE_DEGRADED_AFTER_SECONDS;
                    $overall = $stale ? 'DISCOVERABLE_DEGRADED' : 'DISCOVERABLE';
                    $status['sampled_discoverability'] = sprintf('Current revision %d verified in the signed snapshots of %d of %d checked Discovery Nodes', $membership['revision'], $observed, $checked);
                    if ($stale) { $actions[40] = 'The saved evidence is older than seven days. Run network:discoverability-check --save-status again.'; }
                    else {
                        $actions[90] = $unreached > 0 ? sprintf('%d checked Node(s) could not be verified. Several Node keys may still belong to one operator.', $unreached)
                            : 'No action needed. Recheck after each new revision. Several Node keys may still belong to one operator.';
                    }
                } elseif ($membership['included'] > 0) {
                    $overall = 'EXTERNALLY_OBSERVED';
                    $actions[80] = 'The current revision is included by checked Nodes that exported no direct observation of their own. Ask a cooperating Node to run its direct verification worker.';
                } elseif ($membership['stale'] > 0) {
                    $overall = 'EXTERNALLY_OBSERVED';
                    $actions[80] = sprintf('Checked Nodes still expose an older revision (%d of %d). This is ordinary propagation delay; recheck after their next snapshot.', $membership['stale'], $checked);
                } elseif ($membership['unavailable'] === $checked) {
                    $actions[80] = 'None of the checked Nodes answered through Tor. Check that Tor works (service:verify) and that the Node addresses are right; Nodes can also be offline for a while.';
                } else {
                    $actions[80] = $accepted > 0
                        ? sprintf('%d Node(s) accepted the announcement of this revision but do not include it yet. They fetch and verify your Manifest themselves, usually within a day; the next check shows the result.', $accepted)
                        : sprintf('No checked Node includes this Service yet (%d not present, %d unreachable or rejected). Announce it with network:announce or ask the Node operators to admit it, then recheck.', $membership['not_present'], $unreached);
                }
            }
        }
        if ($overall === 'READY_LOCALLY') {
            if ($accepted > 0) {
                $overall = 'ANNOUNCED_PENDING_VERIFICATION';
                $actions[85] = sprintf('%d Node(s) accepted the announcement of this revision. They fetch and verify your Manifest themselves, usually within a day. Then run network:discoverability-check --save-status; the publisher timer does both.', $accepted);
            } elseif (in_array($status['self_verification'], ['PASS', 'PASS_OUTDATED'], true)) {
                $overall = 'CONNECTING';
                $actions[85] = $nodesConfigured
                    ? 'Your Manifest is reachable through your Onion. Next, announce it to the Nodes you selected and check them (network:announce … --save-status, then network:discoverability-check … --save-status), or enable the publisher timer, which does both.'
                    : 'Your Manifest is reachable through your Onion. Next, choose Discovery Nodes and put each Onion with its independently obtained Node key into publisher_nodes in the configuration; then announce it and check them, or enable the publisher timer, which does both.';
            }
        }
        $status['overall'] = $overall;
        if ($actions !== []) { ksort($actions); $status['next_action'] = reset($actions); }
    }

    /** Plain-language remediation for a failed self-check through the own Onion (spec 47 sections 16 and 31). */
    private static function selfCheckAction(string $code): string
    {
        $rerun = ' Then run service:verify --save-status again.';
        return match ($code) {
            'TOR_PROXY_UNREACHABLE' => 'The configured Tor SOCKS listener did not accept a connection. Start Tor or correct transport.proxy_port in the configuration.' . $rerun,
            'ONION_UNREACHABLE' => 'Tor is running but could not reach your Onion: Tor may still be starting, your Onion Service may be offline, or its descriptor is not published yet. Open your Onion in Tor Browser to compare.' . $rerun,
            'TOR_REQUEST_TIMEOUT' => 'Your Onion did not answer within the time limit (transport.timeout_ms). The first connection to an Onion can be slow; open it in Tor Browser to compare.' . $rerun,
            'HTTP_NOT_FOUND' => 'Your Onion answered 404 for /.well-known/onion-service-discovery.json: its web server does not pass this path to the OSD front controller. Add the OSD locations to your web server configuration.' . $rerun,
            'HTTP_REDIRECT_REJECTED' => 'Your Onion answered the Well-Known path with a redirect. Nodes never follow redirects: serve the Manifest directly over HTTP on the Onion, without a redirect to HTTPS or another host.' . $rerun,
            'HTTP_SERVER_ERROR' => 'Your Onion answered with a server error (5xx). If the OSD front controller answered, the web server user probably cannot read the configuration: run every OSD command as the service user of the web server.' . $rerun,
            'HTTP_STATUS_REJECTED' => 'Your Onion answered the Well-Known path with an unexpected HTTP status. Check that the path reaches the OSD front controller.' . $rerun,
            'CONTENT_TYPE_REJECTED' => 'Your Onion served the Manifest without the content type application/json. Pass the Well-Known path to the OSD front controller instead of serving the file directly.' . $rerun,
            'ONION_BINDING_MISMATCH' => 'Your Onion served a Manifest signed for another Onion address. Check that this web server belongs to this Onion.' . $rerun,
            default => 'Fetching your own Well-Known Manifest through Tor failed with ' . $code . '. Check that the route serves the installed Manifest.' . $rerun,
        };
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
        // A Discovery Node that does not advertise its own Onion is not "not found": the question does not apply.
        if (in_array($config->role, ['node', 'combined'], true) && !$config->features['publish_service']) {
            $status['answer'] = 'NOT APPLICABLE';
            $status['next_action'] = 'This installation runs a Discovery Node and does not advertise an Onion Service of its own. Its Node facts are listed below.';
        }
        $publishing = in_array($overall, self::PUBLISHING, true);
        $manifestValid = ($status['manifest']['state'] ?? null) === 'SIGNATURE_VALID';
        $manifestFailed = in_array($status['manifest']['state'] ?? null, ['INVALID', 'OTHER_ONION'], true);
        $na = static fn (string $state): string => $publishing ? $state : 'NOT_APPLICABLE';
        $self = $status['self_verification'];
        $membership = ($evidence['membership']['manifest_hash'] ?? null) === $hash ? ($evidence['membership'] ?? null) : null;
        $status['pipeline'] = [
            'installation' => Diagnostics::runtimeState(),
            // Reaching this point means NodeConfig::load accepted every configuration key.
            'configuration' => 'PASS',
            'identity' => $config->nodeOnion === null ? 'NOT_APPLICABLE' : ($manifestValid ? 'PASS' : ($manifestFailed ? 'FAIL' : 'PENDING')),
            'manifest' => $config->nodeOnion === null ? 'NOT_APPLICABLE' : ($manifestValid ? 'PASS' : ($manifestFailed ? 'FAIL' : ($config->features['publish_service'] ? 'FAIL' : 'NOT_APPLICABLE'))),
            'onion_self_verification' => $na(match (true) { $self === 'PASS' => 'PASS', $self === 'PASS_OUTDATED' => 'WARN', in_array($self, ['NOT_CHECKED', 'NOT_CHECKED_SINCE_INSTALL'], true) => 'PENDING', default => 'FAIL' }),
            'bootstrap_connectivity' => $na(match (true) { $status['bootstrap'] === 'NOT_CHECKED' => 'PENDING', $status['bootstrap'] === 'NO_CHECKED_NODE_REACHABLE' => 'FAIL', default => 'PASS' }),
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
