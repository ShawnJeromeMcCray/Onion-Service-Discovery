<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Protocol\SnapshotChunkVerifier;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Transport\TorTransport;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TransportException;
use OnionServiceDiscovery\Validation\StrictJson;

/** Resumable bounded snapshot sync. Source views never replace global evidence. */
final class PeerSync
{
    private int $requests = 0;
    public function __construct(private readonly StorageBackend $store, private readonly RecordVerifier $verifier, private readonly TorTransport $transport, private readonly bool $discoverServices = false) {}
    /** @return array<string,mixed> */
    public function step(OnionAddress $onion, int $now): array
    {
        if ($now < 0 || $now > 9007199254146191) { throw new ProtocolException('CLOCK_INVALID'); }
        $claim = bin2hex(random_bytes(16));
        $peer = $this->store->transaction(static function (StorageTransaction $tx) use ($onion, $now, $claim): ?array {
            $peer = $tx->get('peers', $onion->hostname);
            if ($peer === null) { throw new ProtocolException('PEER_NOT_CONFIGURED'); }
            if (in_array($peer['state'], ['QUARANTINED','DISABLED'], true) || $peer['next_attempt'] > $now || $peer['lease_until'] > $now) { return null; }
            $peer['claim'] = $claim; $peer['lease_until'] = $now + 300; $tx->set('peers', $onion->hostname, $peer); return $peer;
        });
        if ($peer === null) { return ['status' => 'IDLE']; }
        $this->requests = 0;
        try {
            $descriptor = $this->verifier->verifyWire($this->fetch($onion, ProtocolEndpoint::node())); $node = $descriptor->document();
            if ($descriptor->type !== 'node_descriptor' || $node->node_onion !== $onion->hostname || $node->node_identity_public_key !== $peer['node_identity_public_key']) { throw new ProtocolException('PEER_BINDING_MISMATCH'); }
            $this->observe($onion, $claim, $descriptor);
            if ($node->descriptor_revision < $peer['descriptor_revision'] || ($node->descriptor_revision === $peer['descriptor_revision'] && $descriptor->contentHash !== $peer['descriptor_hash'])) { throw new ProtocolException('NODE_DESCRIPTOR_ROLLBACK_OR_FORK'); }
            if ($peer['descriptor_hash'] !== null && $node->descriptor_revision > $peer['descriptor_revision'] && ($node->descriptor_revision !== $peer['descriptor_revision'] + 1 || $node->previous_descriptor_hash !== $peer['descriptor_hash'])) { throw new ProtocolException('NODE_DESCRIPTOR_HISTORY_INCOMPLETE'); }
            $this->store->transaction(function (StorageTransaction $tx) use ($onion, $claim, $descriptor, $node): void {
                $row = $this->claimed($tx, $onion, $claim); $tx->putRecord($descriptor); $row['descriptor_hash'] = $descriptor->contentHash; $row['descriptor_revision'] = $node->descriptor_revision; $tx->set('peers', $onion->hostname, $row);
            });
            $remote = StrictJson::parse($this->fetch($onion, ProtocolEndpoint::state()), 16384);
            if (count(get_object_vars($remote)) !== 6 || array_diff(array_keys(get_object_vars($remote)), ['protocol','protocol_version','checkpoint_hash','generation','cursor','node_identity_public_key']) !== [] || $remote->protocol !== 'Onion Service Discovery' || $remote->protocol_version !== 1 || $remote->node_identity_public_key !== $peer['node_identity_public_key'] || !is_int($remote->generation) || $remote->generation < 1 || !is_string($remote->cursor) || !is_string($remote->checkpoint_hash)) { throw new ProtocolException('SCHEMA_INVALID'); }
            Encoding::hash($remote->checkpoint_hash);
            $wire = $this->store->readRecord($remote->checkpoint_hash) ?? $this->fetch($onion, ProtocolEndpoint::snapshot($remote->checkpoint_hash));
            $checkpoint = $this->verifier->verifyWire($wire); $document = $checkpoint->document();
            if ($checkpoint->type !== 'snapshot_descriptor' || $checkpoint->contentHash !== $remote->checkpoint_hash || $document->node_identity_public_key !== $peer['node_identity_public_key'] || $document->generation !== $remote->generation || $document->cursor !== $remote->cursor) { throw new ProtocolException('SOURCE_CHECKPOINT_BINDING_MISMATCH'); }
            if ($document->record_count > 10000 || array_sum(array_map(static fn (\stdClass $chunk): int => $chunk->bytes, $document->chunks)) > 33554432) { throw new ProtocolException('SOURCE_WORK_BUDGET_EXHAUSTED'); }
            $this->observe($onion, $claim, $checkpoint);
            $sync = $this->store->snapshot()['tables']['sync']['key:' . $onion->hostname] ?? [];
            $anchorGeneration = $sync['generation'] ?? 0; $anchorHash = $sync['checkpoint_hash'] ?? null;
            if ($document->generation < ($sync['seen_generation'] ?? 0) || $document->generation < $anchorGeneration) { throw new ProtocolException('SOURCE_CHECKPOINT_ROLLBACK'); }
            if ($document->generation === $anchorGeneration) {
                if ($checkpoint->contentHash !== $anchorHash) { throw new ProtocolException('SOURCE_CHECKPOINT_EQUIVOCATION'); }
                $this->finish($onion, $claim, $now, 'ACTIVE'); return ['status' => 'CURRENT', 'generation' => $anchorGeneration];
            }
            if (($sync['pending_hash'] ?? null) !== $checkpoint->contentHash) {
                $sync = array_merge($sync, ['seen_generation' => $document->generation, 'pending_hash' => $checkpoint->contentHash, 'walk_hash' => $document->previous_checkpoint_hash, 'walk_generation' => $document->generation - 1, 'chain_complete' => $anchorGeneration === 0, 'chunk_index' => 0, 'received' => 0, 'transfer_bytes' => 0, 'last_record_hash' => null]);
                $this->saveSync($onion, $claim, $sync);
            }
            $walks = 0;
            while (!$sync['chain_complete']) {
                if ($sync['walk_hash'] === $anchorHash && $sync['walk_generation'] === $anchorGeneration) { $sync['chain_complete'] = true; $this->saveSync($onion, $claim, $sync); break; }
                if ($sync['walk_generation'] <= $anchorGeneration) { throw new ProtocolException('SOURCE_CHECKPOINT_CHAIN_BROKEN'); }
                if (++$walks > 32) { $this->finish($onion, $claim, $now, 'CANDIDATE'); return ['status' => 'PROGRESS', 'phase' => 'CHECKPOINT_CHAIN']; }
                $cached = $this->store->readRecord($sync['walk_hash']);
                if ($cached === null && $this->requests >= 6) { $this->finish($onion, $claim, $now, 'CANDIDATE'); return ['status' => 'PROGRESS', 'phase' => 'CHECKPOINT_CHAIN']; }
                $parent = $this->verifier->verifyWire($cached ?? $this->fetch($onion, ProtocolEndpoint::snapshot($sync['walk_hash']))); $p = $parent->document();
                if ($parent->type !== 'snapshot_descriptor' || $parent->contentHash !== $sync['walk_hash'] || $p->node_identity_public_key !== $peer['node_identity_public_key'] || $p->generation !== $sync['walk_generation']) { throw new ProtocolException('SOURCE_CHECKPOINT_CHAIN_BROKEN'); }
                $this->observe($onion, $claim, $parent); $sync['walk_hash'] = $p->previous_checkpoint_hash; --$sync['walk_generation']; $this->saveSync($onion, $claim, $sync);
            }
            $checker = new SnapshotChunkVerifier($this->verifier); $processed = 0; $queued = 0;
            while ($sync['chunk_index'] < count($document->chunks) && $processed < 2 && $this->requests < 6) {
                $chunk = $document->chunks[$sync['chunk_index']]; $bytes = $this->fetch($onion, ProtocolEndpoint::chunk($chunk->hash));
                $verified = $checker->verify($bytes, $checkpoint, $sync['chunk_index'], $sync['last_record_hash']);
                $sync['received'] += count($verified['records']); $sync['transfer_bytes'] += strlen($bytes); ++$sync['chunk_index']; $sync['last_record_hash'] = $verified['last_hash'];
                if ($sync['received'] > $document->record_count || $sync['transfer_bytes'] > 33554432) { throw new ProtocolException('SOURCE_WORK_BUDGET_EXHAUSTED'); }
                $this->store->transaction(function (StorageTransaction $tx) use ($onion, $claim, $verified, $sync, $peer, $checkpoint, $now, &$queued): void {
                    $this->claimed($tx, $onion, $claim);
                    foreach ($verified['records'] as $record) {
                        $tx->putRecord($record);
                        $assertion = $tx->get('assertions', $record->contentHash) ?? ['subject_hash' => $record->contentHash, 'kind' => 'REMOTE_PROVENANCE', 'sources' => []];
                        $assertion['sources'] = array_values(array_filter($assertion['sources'], static fn (array $source): bool => $source['expires_at'] > $now && $source['source_onion'] !== $onion->hostname));
                        if (count($assertion['sources']) < 8) {
                            $assertion['sources'][] = ['source_onion' => $onion->hostname, 'source_node_key' => $peer['node_identity_public_key'], 'checkpoint_hash' => $checkpoint->contentHash, 'received_at' => $now, 'expires_at' => $now + 604800];
                        }
                        $tx->set('assertions', $record->contentHash, $assertion);
                        if ($this->discoverServices && $queued < 32 && $record->type === 'service_manifest' && $record->document()->discovery->publish_service === true) {
                            $candidateOnion = OnionAddress::parse($record->document()->onion);
                            if (CandidateAdmission::enqueue($tx, $candidateOnion, $onion->hostname, $now)) { ++$queued; }
                        }
                        $sources = $tx->get('candidate_sources', $record->contentHash)['sources'] ?? [];
                        if (!in_array($onion->hostname, $sources, true) && count($sources) < 8) { $sources[] = $onion->hostname; sort($sources,SORT_STRING); $tx->set('candidate_sources', $record->contentHash, ['sources' => $sources]); }
                    }
                    $tx->set('sync', $onion->hostname, $sync);
                });
                ++$processed;
            }
            if ($sync['chunk_index'] === count($document->chunks)) {
                if ($sync['received'] !== $document->record_count) { throw new ProtocolException('SOURCE_RECORD_COUNT_INVALID'); }
                $sync['checkpoint_hash'] = $checkpoint->contentHash; $sync['generation'] = $document->generation; $sync['cursor'] = $document->cursor; $sync['last_sync'] = $now; $sync['baseline_history_proven'] = $anchorGeneration > 0 || $document->generation === 1;
                $this->store->transaction(function (StorageTransaction $tx) use ($onion, $claim, $sync, $now): void {
                    $row = $this->claimed($tx, $onion, $claim); $tx->set('sync', $onion->hostname, $sync);
                    $row['state'] = 'ACTIVE'; $row['failures'] = 0; $row['claim'] = null; $row['lease_until'] = 0; $row['next_attempt'] = $now + 3600 + random_int(0,300); $row['error'] = null; $tx->set('peers', $onion->hostname, $row);
                });
                return ['status' => 'CURRENT', 'generation' => $document->generation, 'record_count' => $document->record_count];
            }
            $this->finish($onion, $claim, $now, 'CANDIDATE'); return ['status' => 'PROGRESS', 'phase' => 'CHUNKS', 'next_chunk' => $sync['chunk_index']];
        } catch (TransportException | \InvalidArgumentException | StorageException $error) {
            if ($error->getMessage() === 'PEER_CLAIM_LOST') { return ['status' => 'CLAIM_LOST']; }
            $transient = $error instanceof StorageException || in_array($error->getMessage(), ['TOR_REQUEST_FAILED','HTTP_STATUS_REJECTED'], true);
            $failureState = $this->store->transaction(function (StorageTransaction $tx) use ($onion,$claim,$now,$error,$transient): string {
                $row = $this->claimed($tx,$onion,$claim); ++$row['failures']; $row['state'] = !$transient || $row['failures'] >= 8 ? 'QUARANTINED' : 'BACKOFF';
                $row['claim'] = null; $row['lease_until'] = 0; $row['next_attempt'] = $now + min(86400,3600 * (2 ** min(5,$row['failures'] - 1))) + random_int(0,300); $row['error'] = $error->getMessage(); $tx->set('peers',$onion->hostname,$row); return $row['state'];
            });
            return ['status' => $failureState,'error' => $error->getMessage()];
        }
    }
    private function fetch(OnionAddress $onion, ProtocolEndpoint $endpoint): string
    { if (++$this->requests > 6) { throw new ProtocolException('SOURCE_WORK_BUDGET_EXHAUSTED'); } return $this->transport->get($onion,$endpoint); }
    /** @param array<array-key,mixed> $sync */
    private function saveSync(OnionAddress $onion,string $claim,array $sync): void
    { $this->store->transaction(function (StorageTransaction $tx) use ($onion,$claim,$sync): void { $this->claimed($tx,$onion,$claim); $tx->set('sync',$onion->hostname,$sync); }); }
    /** @return array<array-key,mixed> */
    private function claimed(StorageTransaction $tx,OnionAddress $onion,string $claim): array
    { $peer = $tx->get('peers',$onion->hostname); if ($peer === null || $peer['claim'] !== $claim) { throw new ProtocolException('PEER_CLAIM_LOST'); } return $peer; }
    private function finish(OnionAddress $onion,string $claim,int $now,string $state): void
    { $this->store->transaction(function (StorageTransaction $tx) use ($onion,$claim,$now,$state): void { $row=$this->claimed($tx,$onion,$claim); $row['state']=$state; $row['claim']=null; $row['lease_until']=0; $row['failures']=0; $row['error']=null; $row['next_attempt']=$state === 'ACTIVE' ? $now + 3600 : $now + 1; $tx->set('peers',$onion->hostname,$row); }); }
    private function observe(OnionAddress $onion,string $claim,ValidatedRecord $checkpoint): void
    {
        $doc=$checkpoint->document(); $key=($checkpoint->type === 'snapshot_descriptor' ? 'checkpoint:' : 'descriptor:') . hash('sha256',$doc->node_identity_public_key) . ':' . ($checkpoint->type === 'snapshot_descriptor' ? $doc->generation : $doc->descriptor_revision);
        $fork=$this->store->transaction(function (StorageTransaction $tx) use ($onion,$claim,$checkpoint,$key): bool {
            $this->claimed($tx,$onion,$claim); $tx->putRecord($checkpoint); $evidence=$tx->get('checkpoint_evidence',$key) ?? ['hashes'=>[],'equivocated'=>false];
            if (!in_array($checkpoint->contentHash,$evidence['hashes'],true)) { if (count($evidence['hashes']) < 8) { $evidence['hashes'][]=$checkpoint->contentHash; sort($evidence['hashes'],SORT_STRING); } $evidence['equivocated']=count($evidence['hashes']) > 1; $tx->set('checkpoint_evidence',$key,$evidence); }
            return $evidence['equivocated'];
        });
        if ($fork) { throw new ProtocolException($checkpoint->type === 'snapshot_descriptor' ? 'SOURCE_CHECKPOINT_EQUIVOCATION' : 'NODE_DESCRIPTOR_ROLLBACK_OR_FORK'); }
    }
}
