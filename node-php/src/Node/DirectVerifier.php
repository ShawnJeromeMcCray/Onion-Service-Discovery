<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Signing\SignerProvider;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Transport\TorTransport;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;
use OnionServiceDiscovery\Transport\TransportException;

/** Only this transport-backed workflow may create local direct-observation evidence. */
final readonly class DirectVerifier
{
    public function __construct(private StorageBackend $store, private RecordVerifier $verifier, private TorTransport $transport, private SignerProvider $nodeSigner)
    {
        if ($nodeSigner->role() !== 'node') { throw new ProtocolException('SIGNER_ROLE_MISMATCH'); }
    }
    /**
     * @return array<array-key, mixed>
     */
    public function runOne(int $now): array
    {
        $queue = new CandidateQueue($this->store); $job = $queue->claim($now);
        if ($job === null) { return ['status' => 'IDLE']; }
        try {
            $address = OnionAddress::parse($job['onion']);
            $manifest = $this->verifier->verifyWire($this->transport->get($address, ProtocolEndpoint::manifest()), $address);
            if ($manifest->type !== 'service_manifest') { throw new ProtocolException('SCHEMA_INVALID'); }
            // Immutable evidence can be retained even if its continuity cannot yet be resolved.
            $this->store->transaction(static function (StorageTransaction $tx) use ($manifest, $job): void { CandidateQueue::claimedRow($tx, $job); $tx->putRecord($manifest); });
            $snapshot = $this->store->snapshot(); $records = [];
            $hashes = [];
            foreach ($snapshot['records'] as $hash => $metadata) {
                if (in_array($metadata['record_type'], ['service_manifest', 'service_identity_key_rotation', 'onion_service_migration'], true)) { $hashes[] = $hash; }
            }
            foreach (array_chunk($hashes, 256) as $batch) {
                $wires = $this->store->readRecords($batch);
                if (count($wires) !== count($batch)) { throw new ProtocolException('STORAGE_CORRUPT'); }
                foreach ($wires as $wire) { $records[] = $this->verifier->verifyWire($wire); }
            }
            $view = IdentityDeriver::derive($records, 10000); $matched = null;
            foreach ($view['identities'] as $identity) {
                if (in_array($manifest->contentHash, $identity['history_hashes'], true)) { $matched = $identity; break; }
            }
            if ($matched === null) { throw new ProtocolException('HISTORY_INCOMPLETE'); }
            if ($matched['identity_state'] !== 'VALID' || $matched['current_hash'] !== $manifest->contentHash) { throw new ProtocolException($matched['identity_state'] === 'FORKED' ? 'IDENTITY_FORKED' : 'REVISION_DOWNGRADE'); }
            $document = $manifest->document();
            $observation = $this->verifier->verify($this->nodeSigner->signRecord((object) [
                'protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'record_type' => 'direct_observation',
                'node_identity_public_key' => $this->nodeSigner->publicKey(), 'onion' => $address->hostname,
                'service_identity_public_key' => $document->service_identity_public_key, 'revision' => $document->revision,
                'manifest_hash' => $manifest->contentHash, 'result' => 'SUCCESS_CURRENT', 'observed_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
            ]));
            $this->store->transaction(static function (StorageTransaction $tx) use ($job, $manifest, $observation, $now, $snapshot): void {
                $row = CandidateQueue::claimedRow($tx, $job);
                // Recheck if concurrent evidence admission changed the derived identity basis.
                if ($tx->state()['generation'] !== $snapshot['generation']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
                $tx->putRecord($observation);
                $tx->set('local_observations', $job['onion'], ['manifest_hash' => $manifest->contentHash, 'observation_hash' => $observation->contentHash, 'observed_at' => $now]);
                $row['state'] = 'VERIFIED'; $row['claim'] = null; $row['lease_until'] = 0; $row['error'] = null; $row['next_attempt'] = $now + 86400 + random_int(0, 3600); $tx->set('candidates', $job['onion'], $row);
            });
            return ['status' => 'VERIFIED', 'onion' => $address->hostname, 'manifest_hash' => $manifest->contentHash];
        } catch (TransportException | ProtocolException $error) {
            if ($error->getMessage() === 'CANDIDATE_CLAIM_LOST') { return ['status' => 'CLAIM_LOST']; }
            $quarantine = !in_array($error->getMessage(), ['TOR_REQUEST_FAILED', 'HTTP_STATUS_REJECTED', 'EVIDENCE_CHANGED_RETRY'], true);
            $queue->fail($job, $now, $error->getMessage(), $quarantine);
            return ['status' => $quarantine ? 'QUARANTINED' : 'BACKOFF', 'error' => $error->getMessage()];
        }
    }
}
