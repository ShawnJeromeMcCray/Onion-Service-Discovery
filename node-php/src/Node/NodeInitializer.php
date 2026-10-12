<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Signing\SignerProvider;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\PublicBlobStore;
final class NodeInitializer
{
    /**
     * @return array<array-key, mixed>
     */
    public static function initialize(NodeConfig $config, SignerProvider $signer, RecordVerifier $verifier): array
    {
        if ($config->role === 'client-host' || $signer->role() !== 'node') { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        $store = $config->store(true); new PublicBlobStore($config->exportPath, true);
        $state = $store->snapshot(); $pointer = $state['tables']['node']['key:descriptor'] ?? null; $previous = null;
        if ($pointer !== null) {
            $wire = $store->readRecord($pointer['hash']); if ($wire === null) { throw new ProtocolException('STORAGE_CORRUPT'); }
            $previous = $verifier->verifyWire($wire)->document();
            if ($previous->node_identity_public_key !== $signer->publicKey() || $previous->node_onion !== $config->nodeOnion) { throw new ProtocolException('NODE_IDENTITY_CHANGE_REQUIRES_MIGRATION'); }
        }
        $capabilities = [];
        if ($config->features['mirror_directory']) { $capabilities[] = 'directory.snapshot-v1'; }
        if ($config->features['sync_with_peers']) { $capabilities[] = 'sync.delta-v1'; }
        sort($capabilities, SORT_STRING);
        if ($previous !== null && $previous->capabilities === $capabilities) { return ['status' => 'UNCHANGED', 'descriptor_hash' => $pointer['hash']]; }
        $descriptor = $verifier->verify($signer->signRecord((object) [
            'protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'node_descriptor',
            'node_identity_public_key' => $signer->publicKey(), 'node_onion' => $config->nodeOnion,
            'descriptor_revision' => ($previous->descriptor_revision ?? 0) + 1, 'previous_descriptor_hash' => $pointer['hash'] ?? null,
            'supported_protocol_versions' => [1], 'capabilities' => $capabilities,
            'software' => (object) ['name' => 'Onion Service Discovery', 'version' => '0.1.0-dev'],
        ]));
        $store->transaction(static function (StorageTransaction $tx) use ($state, $descriptor): void {
            if ($tx->state()['generation'] !== $state['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
            $tx->putRecord($descriptor); $tx->set('node', 'descriptor', ['hash' => $descriptor->contentHash]);
        });
        return ['status' => 'INITIALIZED', 'descriptor_hash' => $descriptor->contentHash];
    }
}
