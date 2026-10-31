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
     * Signs the next Node descriptor revision when the capabilities changed. The signing ledger beside the
     * Node key refuses a store older than what the key signed (SIGNED_DESCRIPTOR_AHEAD_OF_STORE) and holds
     * the new revision as pending until the store committed it.
     * @return array<array-key, mixed>
     */
    public static function initialize(NodeConfig $config, SignerProvider $signer, RecordVerifier $verifier): array
    {
        if ($config->role === 'client-host' || $signer->role() !== 'node' || $config->nodeKeyPath === null) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        $ledger = SigningLedger::forKey($config->nodeKeyPath);
        return $ledger->exclusive(static fn (): array => self::sign($config, $signer, $verifier, $ledger));
    }
    /**
     * @return array<array-key, mixed>
     */
    private static function sign(NodeConfig $config, SignerProvider $signer, RecordVerifier $verifier, SigningLedger $ledger): array
    {
        $store = $config->store(true); $blobs = new PublicBlobStore($config->exportPath, true);
        $state = $ledger->reconcile($signer->publicKey(), $store, $verifier, $store->snapshot(), $blobs); $pointer = $state['tables']['node']['key:descriptor'] ?? null; $previous = null;
        if ($pointer !== null) {
            $wire = $store->readRecord($pointer['hash']); if ($wire === null) { throw new ProtocolException('STORAGE_CORRUPT'); }
            $previous = $verifier->verifyWire($wire)->document();
            if ($previous->node_identity_public_key !== $signer->publicKey() || $previous->node_onion !== $config->nodeOnion) { throw new ProtocolException('NODE_IDENTITY_CHANGE_REQUIRES_MIGRATION'); }
        }
        $capabilities = [];
        if ($config->features['mirror_directory']) { $capabilities = ['index.core.v1', 'sync.changes.v1', 'sync.snapshots.v1']; }
        sort($capabilities, SORT_STRING);
        if ($previous !== null && $previous->capabilities === $capabilities) { return ['status' => 'UNCHANGED', 'descriptor_hash' => $pointer['hash']]; }
        $revision = (int) ($previous->descriptor_revision ?? 0) + 1;
        $descriptor = $verifier->verify($signer->signRecord((object) [
            'protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'node_descriptor',
            'node_identity_public_key' => $signer->publicKey(), 'node_onion' => $config->nodeOnion,
            'descriptor_revision' => $revision, 'previous_descriptor_hash' => $pointer['hash'] ?? null,
            'supported_protocol_versions' => [1], 'capabilities' => $capabilities,
            'software' => (object) ['name' => 'Onion Service Discovery', 'version' => '0.1.0-dev'],
        ]));
        // The blob and a pending ledger entry first, so a crash after the commit is adopted, never signed over.
        $blobs->put($descriptor->contentHash, $descriptor->canonicalWire); $ledger->prepare($signer->publicKey(), 'descriptor', $revision, $descriptor->contentHash);
        try {
            $store->transaction(static function (StorageTransaction $tx) use ($state, $descriptor): void {
                if ($tx->state()['generation'] !== $state['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
                $tx->putRecord($descriptor); $tx->set('node', 'descriptor', ['hash' => $descriptor->contentHash]);
            });
        } catch (ProtocolException $error) { if ($error->getMessage() === 'EVIDENCE_CHANGED_RETRY') { $ledger->cancel($signer->publicKey(), 'descriptor', $descriptor->contentHash); } throw $error; }
        $ledger->record($signer->publicKey(), 'descriptor', $revision, $descriptor->contentHash);
        // A Node that already published keeps the new descriptor for degraded serving too (D-045).
        PublishedState::save($store, $blobs, $verifier);
        return ['status' => 'INITIALIZED', 'descriptor_hash' => $descriptor->contentHash];
    }
}
