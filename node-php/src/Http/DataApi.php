<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotFormat;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\PublicBlobStore;

/** Read-only public protocol surface; no signing or maintenance dependency. */
final readonly class DataApi
{
    /** @param list<string> $allowedOrigins */
    public function __construct(private StorageBackend $store, private PublicBlobStore $blobs, private RecordVerifier $verifier, private bool $mirror, private array $allowedOrigins = []) {}
    /**
     * @return array<array-key, mixed>
     */
    public function handle(string $method, string $target, ?string $origin = null): array
    {
        $result = $this->dispatch($method, $target);
        if ($method === 'HEAD') { $result['body'] = ''; }
        if ($origin !== null && in_array($origin, $this->allowedOrigins, true)) {
            $result['headers']['Access-Control-Allow-Origin'] = $origin;
            $result['headers']['Vary'] = 'Origin';
        }
        return $result;
    }
    /** @return array<array-key, mixed> */
    private function dispatch(string $method, string $target): array
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'", 'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()'];
        if (!in_array($method, ['GET','HEAD'], true)) { return $this->response(405, ['error' => 'METHOD_NOT_ALLOWED'], $headers + ['Allow' => 'GET, HEAD']); }
        if (strlen($target) > 256 || preg_match('/[\x00-\x20\x7f]/', $target) === 1) { return $this->response(400, ['error' => 'REQUEST_INVALID'], $headers); }
        [$path, $query] = array_pad(explode('?', $target, 2), 2, '');
        try {
            $state = $this->store->snapshot(); $publicHashes = [];
            foreach ($state['tables']['node'] as $key => $row) { if (str_starts_with($key,'key:public-index-')) { foreach ($row['hashes'] as $hash) { $publicHashes[$hash]=true; } } }
            $published = $state['tables']['node']['key:published-snapshot'] ?? null;
            if ($path === '/onion-service-discovery/v1/node' && $query === '') {
                $node = $state['tables']['node']['key:descriptor'] ?? null;
                if ($node === null) { return $this->response(503, ['error' => 'NODE_NOT_INITIALIZED'], $headers); }
                return $this->wireResponse($this->record($node['hash'], 'node_descriptor'), 16384, $headers, $method);
            }
            if (!$this->mirror) { return $this->response(404, ['error' => 'NOT_FOUND'], $headers); }
            if ($path === '/onion-service-discovery/v1/state' && $query === '') {
                if ($published === null) { return $this->response(503, ['error' => 'SNAPSHOT_UNAVAILABLE'], $headers); }
                $checkpoint = $this->verifier->verifyWire($this->record($published['hash'], 'snapshot_descriptor'))->document();
                return $this->response(200, ['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'checkpoint_hash' => $published['hash'], 'generation' => $checkpoint->generation, 'cursor' => $checkpoint->cursor, 'node_identity_public_key' => $checkpoint->node_identity_public_key], $headers, $method);
            }
            if ($path === '/onion-service-discovery/v1/peers' && $query === '') {
                $peers = [];
                foreach ($state['tables']['peers'] as $key => $peer) {
                    if (($peer['state'] ?? null) === 'ACTIVE' && isset($peer['node_identity_public_key'])) { $peers[] = (object) ['onion' => OnionAddress::parse(substr($key, 4))->hostname, 'node_identity_public_key' => $peer['node_identity_public_key']]; }
                    if (count($peers) >= 256) { break; }
                }
                return $this->response(200, ['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'peers' => $peers], $headers, $method);
            }
            if (preg_match('~\A/onion-service-discovery/v1/snapshots/([0-9a-f]{64})\z~D', $path, $match) === 1 && $query === '') {
                return $this->wireResponse($this->record('sha256:' . $match[1], 'snapshot_descriptor'), 65536, $headers, $method);
            }
            if (preg_match('~\A/onion-service-discovery/v1/chunks/([0-9a-f]{64})\z~D', $path, $match) === 1 && $query === '') {
                if ($published === null) { throw new ProtocolException('NOT_FOUND'); }
                $checkpoint = $this->verifier->verifyWire($this->record($published['hash'], 'snapshot_descriptor'))->document(); $expected = null;
                foreach ($checkpoint->chunks as $chunk) { if ($chunk->hash === 'sha256:' . $match[1]) { $expected = $chunk; break; } }
                if ($expected === null) { throw new ProtocolException('NOT_FOUND'); }
                $wire = $this->blobs->get($expected->hash);
                if ($wire === null || strlen($wire) !== $expected->bytes || SnapshotFormat::chunkHash($wire) !== $expected->hash) { throw new ProtocolException('EXPORT_CORRUPT'); }
                return $this->wireResponse($wire, 524288, $headers, $method);
            }
            if ($path === '/onion-service-discovery/v1/changes' && preg_match('/\Aafter=(0|[1-9][0-9]{0,15})\z/D', $query, $match) === 1) {
                $after = (int) $match[1]; if ($after > 9007199254740991 || $after > $state['last_sequence']) { throw new ProtocolException('CURSOR_INVALID'); }
                $entries = array_intersect_key($state['records'], $publicHashes); uasort($entries, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
                $records = []; $cursor = $after; $bytes = 256;
                $selected = [];
                foreach ($entries as $hash => $metadata) {
                    if ($metadata['sequence'] > $after) { $selected[$hash] = $metadata; }
                    if (count($selected) >= 64) { break; }
                }
                $wires = $this->store->readRecords(array_keys($selected));
                foreach ($selected as $hash => $metadata) {
                    $wire = $wires[$hash] ?? null; if ($wire === null) { throw new ProtocolException('STORAGE_CORRUPT'); }
                    $record = $this->verifier->verifyWire($wire);
                    $entry = (object) ['sequence' => $metadata['sequence'], 'record' => $record->document()]; $size = strlen(CanonicalJson::encode($entry)) + 1;
                    if ($bytes + $size > 1048576) { break; }
                    $records[] = $entry; $cursor = $metadata['sequence']; $bytes += $size;
                }
                return $this->response(200, ['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'after' => $after, 'cursor' => $cursor, 'has_more' => $cursor < max(array_column($entries,'sequence') ?: [0]), 'records' => $records], $headers, $method, 1048576);
            }
            if (preg_match('~\A/onion-service-discovery/v1/services/([a-z2-7]{56}\.onion)\z~D', $path, $match) === 1 && $query === '') {
                $onion = OnionAddress::parse($match[1]); $records = [];
                if (($published['service_index_version'] ?? 0) !== 1) { return $this->response(503, ['error' => 'SERVICE_INDEX_NOT_READY'], $headers, $method); }
                $prefix = substr(hash('sha256', $onion->hostname), 0, 2);
                $index = $state['tables']['node']['key:service-index-' . $prefix] ?? null;
                $hashes = [];
                if ($index !== null) {
                    $wire = $this->blobs->get($index['hash']);
                    if ($wire === null || 'sha256:' . hash('sha256', "Onion Service Discovery/service-lookup/v1\0" . $wire) !== $index['hash']) { throw new ProtocolException('STORAGE_CORRUPT'); }
                    $entries = \OnionServiceDiscovery\Storage\StorageJson::decode($wire, 524288);
                    if (($entries['too_large'] ?? false) === true) { throw new ProtocolException('RESPONSE_TOO_LARGE'); }
                    $hashes = $entries[$onion->hostname] ?? [];
                    if (($hashes['too_large'] ?? false) || count($hashes) > 256) { throw new ProtocolException('RESPONSE_TOO_LARGE'); }
                }
                foreach ($this->store->readRecords($hashes) as $hash => $wire) {
                    if (!isset($publicHashes[$hash])) { throw new ProtocolException('STORAGE_CORRUPT'); }
                    $record = $this->verifier->verifyWire($wire);
                    if ($record->contentHash !== $hash) { throw new ProtocolException('STORAGE_CORRUPT'); }
                    $records[] = $record->document();
                }
                if (count($records) !== count($hashes)) { throw new ProtocolException('STORAGE_CORRUPT'); }
                return $this->response(200, ['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'records' => $records], $headers, $method, 32768);
            }
            return $this->response(404, ['error' => 'NOT_FOUND'], $headers);
        } catch (ProtocolException $error) {
            return $this->response($error->getMessage() === 'NOT_FOUND' ? 404 : ($error->getMessage() === 'RESPONSE_TOO_LARGE' ? 413 : 400), ['error' => $error->getMessage()], $headers);
        } catch (\InvalidArgumentException) { return $this->response(400, ['error' => 'REQUEST_INVALID'], $headers); }
    }
    private function record(string $hash, string $type): string
    {
        $wire = $this->store->readRecord($hash); if ($wire === null) { throw new ProtocolException('NOT_FOUND'); }
        $record = $this->verifier->verifyWire($wire); if ($record->type !== $type || $record->contentHash !== $hash) { throw new ProtocolException('STORAGE_CORRUPT'); }
        return $wire;
    }
    /**
     * @param array<array-key, mixed> $headers
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function response(int $status, array $value, array $headers, string $method = 'GET', int $limit = 16384): array
    { return $this->wireResponse(CanonicalJson::encode((object) $value), $limit, $headers, $method, $status); }
    /**
     * @param array<array-key, mixed> $headers
     * @return array<array-key, mixed>
     */
    private function wireResponse(string $wire, int $limit, array $headers, string $method, int $status = 200): array
    {
        if (strlen($wire) > $limit) { throw new ProtocolException('RESPONSE_TOO_LARGE'); }
        return ['status' => $status, 'headers' => $headers + ['Content-Length' => (string) strlen($wire)], 'body' => $method === 'HEAD' ? '' : $wire];
    }
}
