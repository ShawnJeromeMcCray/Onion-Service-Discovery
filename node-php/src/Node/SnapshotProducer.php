<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotFormat;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Signing\SignerProvider;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\PublicBlobStore;

final readonly class SnapshotProducer
{
    public function __construct(private StorageBackend $store, private PublicBlobStore $blobs, private RecordVerifier $verifier, private SignerProvider $signer)
    { if ($signer->role() !== 'node') { throw new ProtocolException('SIGNER_ROLE_MISMATCH'); } }
    /**
     * @return array<array-key, mixed>
     */
    public function publish(int $now): array
    {
        if ($now < 0 || $now > 253402300799) { throw new ProtocolException('CLOCK_INVALID'); }
        $state = $this->store->snapshot(); $hashes = []; $publicCursor = 0;
        foreach ($state['records'] as $hash => $metadata) {
            if ($metadata['record_type'] !== 'snapshot_descriptor') { $hashes[] = $hash; $publicCursor = max($publicCursor, $metadata['sequence']); }
        }
        sort($hashes, SORT_STRING); $chunks = []; $chunk = []; $bytes = 2; $count = 0;
        foreach (array_chunk($hashes, 256) as $batch) {
            $wires = $this->store->readRecords($batch);
            foreach ($batch as $hash) {
                if (!isset($wires[$hash])) { throw new ProtocolException('STORAGE_CORRUPT'); }
                $record = $this->verifier->verifyWire($wires[$hash]);
                if ($record->contentHash !== $hash) { throw new ProtocolException('STORAGE_CORRUPT'); }
                if (count($chunk) === 256 || $bytes + strlen($record->canonicalWire) + (int) ($chunk !== []) > 524288) { $this->flush($chunk, $chunks); $bytes = 2; }
                $bytes += strlen($record->canonicalWire) + (int) ($chunk !== []); $chunk[] = $record->document(); ++$count;
            }
        }
        $this->flush($chunk, $chunks); $bytes = 2;
        $previous = $state['tables']['node']['key:published-snapshot'] ?? null;
        if ($previous !== null && $previous['root_hash'] === SnapshotFormat::rootHash($chunks) && $previous['cursor'] === (string) $publicCursor) { return ['status' => 'UNCHANGED', 'checkpoint_hash' => $previous['hash']]; }
        $generation = ($previous['generation'] ?? 0) + 1;
        $descriptor = $this->verifier->verify($this->signer->signRecord((object) [
            'protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'snapshot_descriptor',
            'node_identity_public_key' => $this->signer->publicKey(), 'generation' => $generation, 'previous_checkpoint_hash' => $previous['hash'] ?? null,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z', $now), 'root_hash' => SnapshotFormat::rootHash($chunks),
            'record_count' => $count, 'cursor' => (string) $publicCursor, 'chunks' => $chunks,
        ]));
        $this->blobs->put($descriptor->contentHash, $descriptor->canonicalWire);
        $this->store->transaction(static function (StorageTransaction $tx) use ($state, $descriptor, $generation, $chunks, $publicCursor): void {
            if ($tx->state()['generation'] !== $state['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
            $tx->putRecord($descriptor);
            $tx->set('node', 'published-snapshot', ['hash' => $descriptor->contentHash, 'root_hash' => SnapshotFormat::rootHash($chunks), 'generation' => $generation, 'cursor' => (string) $publicCursor]);
        });
        return ['status' => 'PUBLISHED', 'checkpoint_hash' => $descriptor->contentHash, 'record_count' => $count, 'chunks' => count($chunks)];
    }
    /** @param list<\stdClass> $chunk
     * @param list<\stdClass> $chunks
     */
    private function flush(array &$chunk, array &$chunks): void
    {
        if ($chunk === []) { return; }
        $wire = CanonicalJson::encode($chunk); $hash = SnapshotFormat::chunkHash($wire);
        $this->blobs->put($hash, $wire); $chunks[] = (object) ['hash' => $hash, 'bytes' => strlen($wire), 'record_count' => count($chunk)];
        if (count($chunks) > 256) { throw new ProtocolException('WORK_BUDGET_EXHAUSTED'); }
        $chunk = [];
    }

}
