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
    public function __construct(private StorageBackend $store, private PublicBlobStore $blobs, private RecordVerifier $verifier, private SignerProvider $signer, private bool $serverSearch = false, private bool $forceDerived = false)
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
        if (count($all) !== count($state['records'])) { throw new ProtocolException('STORAGE_CORRUPT'); }
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
            $previousRecord = $this->verifier->verifyWire($previousWire); $previousDoc = $previousRecord->document();
            if ($previousRecord->type !== 'snapshot_descriptor' || $previousRecord->contentHash !== $previous['hash'] || $previousDoc->generation !== ($previous['generation'] ?? null) || $previousDoc->root_hash !== ($previous['root_hash'] ?? null) || $previousDoc->cursor !== ($previous['cursor'] ?? null)) { throw new ProtocolException('STORAGE_CORRUPT'); }
            if ($previousDoc->node_identity_public_key !== $this->signer->publicKey()) { throw new ProtocolException('SIGNER_BINDING_MISMATCH'); }
            $previousCreated = strtotime($previousDoc->created_at);
            if ($previousCreated === false || $now < $previousCreated) { throw new ProtocolException('CLOCK_ROLLBACK'); }
        }
        if (!$this->forceDerived && $previous !== null && ($previous['service_index_version'] ?? 0) === 2 && (!$this->serverSearch || isset($state['tables']['node']['key:server-search-index'])) && $now - $previousCreated < 86400 && $previous['root_hash'] === SnapshotFormat::rootHash($chunks) && $previous['cursor'] === (string) $publicCursor) { return ['status' => 'UNCHANGED', 'checkpoint_hash' => $previous['hash']]; }
        $generation = ($previous['generation'] ?? 0) + 1;
        $descriptor = $this->verifier->verify($this->signer->signRecord((object) [
            'protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'snapshot_descriptor',
            'node_identity_public_key' => $this->signer->publicKey(), 'generation' => $generation, 'previous_checkpoint_hash' => $previous['hash'] ?? null,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z', $now), 'root_hash' => SnapshotFormat::rootHash($chunks),
            'record_count' => $count, 'cursor' => (string) $publicCursor, 'chunks' => $chunks,
        ]));
        $this->blobs->put($descriptor->contentHash, $descriptor->canonicalWire);
        $serviceBuckets = [];
        foreach ($hashes as $hash) {
            $doc = $byHash[$hash]->document();
            foreach (array_unique(array_filter([$doc->onion ?? null, $doc->from_onion ?? null, $doc->to_onion ?? null])) as $onion) {
                $prefix = substr(hash('sha256', $onion), 0, 2);
                $serviceBuckets[$prefix][$onion][] = $hash;
            }
        }
        // Stable identifiers only come from complete, independently derived genesis lineages.
        $publicRecords = array_map(static fn (string $hash) => $byHash[$hash], $hashes);
        $view = \OnionServiceDiscovery\Protocol\IdentityDeriver::derive($publicRecords, 10000);
        $observations = [];
        foreach ($publicRecords as $record) {
            if ($record->type === 'direct_observation') { $observations[$record->document()->manifest_hash][] = $record->contentHash; }
        }
        foreach ($view['identities'] as $identity) {
            $references = array_merge($identity['history_hashes'], $identity['rotation_hashes'], $identity['migration_hashes']);
            foreach ($identity['transition_conflicts'] as $conflict) { $references = array_merge($references, $conflict['record_hashes']); }
            foreach ($identity['history_hashes'] as $hash) { $references = array_merge($references, $observations[$hash] ?? []); }
            $references = array_values(array_unique($references)); sort($references, SORT_STRING);
            $id = $identity['genesis_key']; $prefix = substr(hash('sha256', $id), 0, 2);
            $serviceBuckets[$prefix][$id] = $references;
            foreach ($identity['history_hashes'] as $hash) {
                $onion = $byHash[$hash]->document()->onion; $prefix = substr(hash('sha256', $onion), 0, 2);
                $existing = $serviceBuckets[$prefix][$onion] ?? [];
                $serviceBuckets[$prefix][$onion] = array_values(array_unique(array_merge($existing, $references)));
            }
        }
        $serviceIndexes = [];
        foreach ($serviceBuckets as $prefix => $entries) {
            foreach ($entries as $onion => $references) {
                // Bounded individual responses; use signed snapshots for longer histories.
                if (count($references) > 256) { $entries[$onion] = ['too_large' => true]; }
            }
            $wire = \OnionServiceDiscovery\Storage\StorageJson::encode($entries);
            if (strlen($wire) > 524288) { $wire = \OnionServiceDiscovery\Storage\StorageJson::encode(['too_large' => true]); }
            $hash = 'sha256:' . hash('sha256', "Onion Service Discovery/service-lookup/v1\0" . $wire);
            $this->blobs->put($hash, $wire); $serviceIndexes[(string)$prefix] = $hash;
        }
        $searchIndex = $this->serverSearch ? \OnionServiceDiscovery\Search\SearchIndex::build($this->blobs, \OnionServiceDiscovery\Search\LocalSearch::build($publicRecords), $descriptor) : null;
        $this->store->transaction(static function (StorageTransaction $tx) use ($state, $descriptor, $generation, $chunks, $publicCursor, $hashes, $serviceIndexes, $searchIndex): void {
            if ($tx->state()['generation'] !== $state['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
            $tx->putRecord($descriptor);
            foreach ($tx->state()['tables']['node'] as $key => $_) { if ((str_starts_with($key, 'key:public-index-') || str_starts_with($key, 'key:service-index-'))) { $tx->deleteOperational('node', substr($key,4)); } }
            $publicHashes = $hashes;
            foreach ($state['records'] as $hash => $metadata) { if ($metadata['record_type'] === 'snapshot_descriptor') { $publicHashes[] = $hash; } }
            $publicHashes[] = $descriptor->contentHash;
            foreach (array_chunk(array_values(array_unique($publicHashes)),64) as $index => $part) { $tx->set('node','public-index-' . $index,['hashes'=>$part]); }
            foreach ($serviceIndexes as $prefix => $hash) { $tx->set('node', 'service-index-' . $prefix, ['hash' => $hash]); }
            if ($searchIndex !== null) { $tx->set('node', 'server-search-index', ['format_version' => \OnionServiceDiscovery\Search\SearchIndex::FORMAT_VERSION, 'hash' => $searchIndex]); }
            else { $tx->deleteOperational('node', 'server-search-index'); }
            $tx->set('node', 'published-snapshot', ['hash' => $descriptor->contentHash, 'root_hash' => SnapshotFormat::rootHash($chunks), 'generation' => $generation, 'cursor' => (string) $publicCursor, 'service_index_version' => 2]);
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
