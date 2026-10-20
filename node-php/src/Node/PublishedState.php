<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;

use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageJson;
use OnionServiceDiscovery\Validation\StrictJson;

/**
 * The last published state a Discovery Node keeps in its export directory (D-045): the state
 * document of its published checkpoint and its signed Node descriptor, written after every
 * publication. While the store cannot be read, the front controller serves the last published
 * snapshot from this file and the content-addressed export blobs. Every read verifies the file
 * against the signed records again, so a damaged or inconsistent file serves nothing.
 */
final readonly class PublishedState
{
    private function __construct(public ValidatedRecord $checkpoint, public string $stateWire, public ?ValidatedRecord $nodeDescriptor) {}

    /** The state route's document for a published checkpoint, byte for byte as the data API serves it. */
    public static function stateWire(ValidatedRecord $checkpoint): string
    {
        $document = $checkpoint->document();
        return CanonicalJson::encode((object) ['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'checkpoint_hash' => $checkpoint->contentHash,
            'generation' => $document->generation, 'cursor' => $document->cursor, 'node_identity_public_key' => $document->node_identity_public_key]);
    }

    /**
     * Copies the store's published checkpoint and current Node descriptor into the export
     * directory; returns whether the file changed. Nothing is written before the first snapshot.
     */
    public static function save(StorageBackend $store, PublicBlobStore $blobs, RecordVerifier $verifier): bool
    {
        // The store is read inside the export lock, so the last writer always leaves the newest state.
        return $blobs->replacePublishedState(static function () use ($store, $verifier): ?string {
            $state = $store->snapshot();
            $published = $state['tables']['node']['key:published-snapshot'] ?? null;
            if ($published === null) { return null; }
            $checkpoint = self::stored($store, $verifier, $published['hash'], 'snapshot_descriptor');
            $pointer = $state['tables']['node']['key:descriptor'] ?? null;
            $descriptor = $pointer === null ? null : self::stored($store, $verifier, $pointer['hash'], 'node_descriptor');
            return StorageJson::encode(['format_version' => 1, 'state' => self::stateWire($checkpoint), 'node_descriptor' => $descriptor?->canonicalWire]);
        });
    }

    /**
     * The verified last published state, or null before the first one. Throws EXPORT_CORRUPT when
     * the file, the checkpoint it names or the Node descriptor is damaged or does not belong together.
     */
    public static function load(PublicBlobStore $blobs, RecordVerifier $verifier, ?string $nodeOnion): ?self
    {
        $bytes = $blobs->publishedState();
        if ($bytes === null) { return null; }
        try {
            $file = StorageJson::decode($bytes, PublicBlobStore::PUBLISHED_STATE_MAX_BYTES);
            $state = $file['state'] ?? null; $node = $file['node_descriptor'] ?? null;
            if (array_keys($file) !== ['format_version', 'node_descriptor', 'state'] || $file['format_version'] !== 1 || !is_string($state) || ($node !== null && !is_string($node))) { throw new StorageException('EXPORT_CORRUPT'); }
            $hash = StrictJson::parse($state, 16384)->checkpoint_hash ?? null;
            if (!is_string($hash) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $hash) !== 1) { throw new StorageException('EXPORT_CORRUPT'); }
            // The checkpoint itself comes from its content-addressed blob, never from the file.
            $wire = $blobs->get($hash);
            $checkpoint = $wire === null ? null : $verifier->verifyWire($wire);
            if ($checkpoint === null || $checkpoint->type !== 'snapshot_descriptor' || $checkpoint->contentHash !== $hash || $checkpoint->canonicalWire !== $wire || self::stateWire($checkpoint) !== $state) { throw new StorageException('EXPORT_CORRUPT'); }
            $descriptor = $node === null ? null : $verifier->verifyWire($node);
            if ($descriptor !== null && ($descriptor->type !== 'node_descriptor' || $descriptor->canonicalWire !== $node || $descriptor->document()->node_onion !== $nodeOnion
                || $descriptor->document()->node_identity_public_key !== $checkpoint->document()->node_identity_public_key)) { throw new StorageException('EXPORT_CORRUPT'); }
            return new self($checkpoint, $state, $descriptor);
        } catch (\InvalidArgumentException) {
            throw new StorageException('EXPORT_CORRUPT');
        } catch (StorageException $error) {
            throw str_starts_with($error->errorCode, 'EXPORT_') ? $error : new StorageException('EXPORT_CORRUPT');
        }
    }

    /**
     * Degraded mode: null while the store opens read-only, otherwise the last published state that
     * stays online in its place. Without one the store's own failure is rethrown: nothing can be served.
     */
    public static function degraded(NodeConfig $config, RecordVerifier $verifier): ?self
    {
        try { $config->store(readOnly: true); return null; }
        catch (StorageException $error) { return self::load(new PublicBlobStore($config->exportPath), $verifier, $config->nodeOnion) ?? throw $error; }
    }

    /** A signed record from the store, checked for its type and address. */
    private static function stored(StorageBackend $store, RecordVerifier $verifier, string $hash, string $type): ValidatedRecord
    {
        $wire = $store->readRecord($hash);
        $record = $wire === null ? null : $verifier->verifyWire($wire);
        if ($record === null || $record->type !== $type || $record->contentHash !== $hash) { throw new StorageException('STORAGE_CORRUPT'); }
        return $record;
    }
}
