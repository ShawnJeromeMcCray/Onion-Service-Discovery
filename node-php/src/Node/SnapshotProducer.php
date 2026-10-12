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
        $state = $this->store->snapshot(); $all = [];
        foreach (array_chunk(array_keys($state['records']), 256) as $batch) {
            foreach ($this->store->readRecords($batch) as $hash => $wire) {
                $record = $this->verifier->verifyWire($wire);
                if ($record->contentHash !== $hash) { throw new ProtocolException('STORAGE_CORRUPT'); }
                $all[] = $record;
            }
        }
        $selected = PublicEvidencePolicy::select($all); $hashes = array_keys($selected); sort($hashes, SORT_STRING);
        $byHash = []; foreach ($all as $record) { $byHash[$record->contentHash] = $record; }
        $publicCursor = 0; foreach ($hashes as $hash) { $publicCursor = max($publicCursor, $state['records'][$hash]['sequence']); }
        $chunks = []; $chunk = []; $bytes = 2; $count = 0;
        foreach ($hashes as $hash) {
            $record = $byHash[$hash];
            if (count($chunk) === 256 || $bytes + strlen($record->canonicalWire) + (int) ($chunk !== []) > 524288) { $this->flush($chunk, $chunks); $bytes = 2; }
            $bytes += strlen($record->canonicalWire) + (int) ($chunk !== []); $chunk[] = $record->document(); ++$count;
        }
        $this->flush($chunk, $chunks); $bytes = 2;
        $previous = $state['tables']['node']['key:published-snapshot'] ?? null;
        $previousCreated = 0;
        if ($previous !== null) {
            $previousWire = $this->store->readRecord($previous['hash']);
            if ($previousWire === null) { throw new ProtocolException('STORAGE_CORRUPT'); }
            $previousCreated = strtotime($this->verifier->verifyWire($previousWire)->document()->created_at);
            if ($previousCreated === false || $now < $previousCreated) { throw new ProtocolException('CLOCK_ROLLBACK'); }
        }
        if ($previous !== null && $now - $previousCreated < 86400 && $previous['root_hash'] === SnapshotFormat::rootHash($chunks) && $previous['cursor'] === (string) $publicCursor) { return ['status' => 'UNCHANGED', 'checkpoint_hash' => $previous['hash']]; }
        $generation = ($previous['generation'] ?? 0) + 1;
        $descriptor = $this->verifier->verify($this->signer->signRecord((object) [
            'protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'snapshot_descriptor',
            'node_identity_public_key' => $this->signer->publicKey(), 'generation' => $generation, 'previous_checkpoint_hash' => $previous['hash'] ?? null,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z', $now), 'root_hash' => SnapshotFormat::rootHash($chunks),
            'record_count' => $count, 'cursor' => (string) $publicCursor, 'chunks' => $chunks,
        ]));
        $this->blobs->put($descriptor->contentHash, $descriptor->canonicalWire);
        $this->store->transaction(static function (StorageTransaction $tx) use ($state, $descriptor, $generation, $chunks, $publicCursor, $hashes): void {
            if ($tx->state()['generation'] !== $state['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
            $tx->putRecord($descriptor);
            foreach ($tx->state()['tables']['node'] as $key => $_) { if (str_starts_with($key, 'key:public-index-')) { $tx->deleteOperational('node', substr($key,4)); } }
            $publicHashes = $hashes;
            foreach ($state['records'] as $hash => $metadata) { if ($metadata['record_type'] === 'snapshot_descriptor') { $publicHashes[] = $hash; } }
            $publicHashes[] = $descriptor->contentHash;
            foreach (array_chunk(array_values(array_unique($publicHashes)),64) as $index => $part) { $tx->set('node','public-index-' . $index,['hashes'=>$part]); }
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
