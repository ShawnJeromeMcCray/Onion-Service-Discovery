<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;

/** Public, read-only evidence summary. No key, storage, network or job capability. */
final class PublisherStatus
{
    /** @return array<string,mixed> */
    public static function inspect(NodeConfig $config, RecordVerifier $verifier, string $manifestPath): array
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
                $status['next_action'] = 'Verify this Manifest through your own Onion using service:verify. External observation and signed active-index membership have not been confirmed.';
            }
        } catch (\InvalidArgumentException) {
            $status['overall'] = 'ERROR';
            $status['manifest'] = ['state' => 'INVALID'];
            $status['next_action'] = 'Run manifest:verify locally and install a valid signed Manifest for this exact Onion.';
        }
        return $status;
    }
}
