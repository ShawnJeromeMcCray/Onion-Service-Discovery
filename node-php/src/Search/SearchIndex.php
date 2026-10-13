<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Search;
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Domain\CategoryId;
use OnionServiceDiscovery\Domain\LanguageTag;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageJson;

/** Replaceable local published-data search cache. Never an external inclusion proof.
 * @phpstan-import-type ServiceRow from LocalSearch
 * @phpstan-type Chunk array{hash:string,bytes:int,row_count:int}
 * @phpstan-type Manifest array{format_version:int,checkpoint_hash:string,created_at:string,row_count:int,chunks:list<Chunk>}
 */
final class SearchIndex
{
    public const MAX_BYTES = 16777216;
    public const MAX_ROWS = 10000;
    public const MAX_CHUNKS = 128;
    /** @param list<ServiceRow> $rows */
    public static function build(PublicBlobStore $blobs, array $rows, ValidatedRecord $checkpoint): string
    {
        if (count($rows) > self::MAX_ROWS || $checkpoint->type !== 'snapshot_descriptor') { throw new ProtocolException('SEARCH_WORK_BUDGET_EXHAUSTED'); }
        $chunks = []; $current = []; $bytes = 2; $total = 0;
        foreach ($rows as $row) {
            $size = strlen(StorageJson::encode($row)) + 1;
            if ($bytes + $size > 524288) { self::flush($blobs, $current, $chunks, $total); $bytes = 2; }
            $current[] = $row; $bytes += $size;
        }
        self::flush($blobs, $current, $chunks, $total);
        $wire = StorageJson::encode(['format_version' => 1, 'checkpoint_hash' => $checkpoint->contentHash, 'created_at' => $checkpoint->document()->created_at, 'row_count' => count($rows), 'chunks' => $chunks]);
        $hash = self::digest('index', $wire); $blobs->put($hash, $wire); return $hash;
    }
    /** @param list<ServiceRow> $current
     * @param list<Chunk> $chunks
     */
    private static function flush(PublicBlobStore $blobs, array &$current, array &$chunks, int &$total): void
    {
        if ($current === []) { return; }
        $wire = StorageJson::encode($current); $total += strlen($wire);
        if (strlen($wire) > 524288 || $total > self::MAX_BYTES || count($chunks) >= self::MAX_CHUNKS) { throw new ProtocolException('SEARCH_WORK_BUDGET_EXHAUSTED'); }
        $hash = self::digest('chunk', $wire); $blobs->put($hash, $wire);
        $chunks[] = ['hash' => $hash, 'bytes' => strlen($wire), 'row_count' => count($current)]; $current = [];
    }
    private static function digest(string $type, string $wire): string
    { return 'sha256:' . hash('sha256', 'Onion Service Discovery/server-search-' . $type . "/v1\0" . $wire); }
    private static function blob(PublicBlobStore $blobs, string $hash, string $type): string
    {
        Encoding::hash($hash); $wire = $blobs->get($hash);
        if ($wire === null || self::digest($type, $wire) !== $hash) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        return $wire;
    }
    /** @return Manifest */
    private static function manifest(PublicBlobStore $blobs, string $hash): array
    {
        $wire = self::blob($blobs, $hash, 'index'); $value = StorageJson::decode($wire, 16384);
        if (count($value) !== 5 || ($value['format_version'] ?? null) !== 1 || !is_string($value['checkpoint_hash'] ?? null) || !is_string($value['created_at'] ?? null) || !is_int($value['row_count'] ?? null) || $value['row_count'] < 0 || $value['row_count'] > self::MAX_ROWS || !is_array($value['chunks'] ?? null) || !array_is_list($value['chunks']) || count($value['chunks']) > self::MAX_CHUNKS) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        $rows = 0; $bytes = 0; $seen = [];
        foreach ($value['chunks'] as $chunk) {
            if (!is_array($chunk) || count($chunk) !== 3 || !is_string($chunk['hash'] ?? null) || !is_int($chunk['bytes'] ?? null) || $chunk['bytes'] < 2 || $chunk['bytes'] > 524288 || !is_int($chunk['row_count'] ?? null) || $chunk['row_count'] < 1 || $chunk['row_count'] > self::MAX_ROWS || isset($seen[$chunk['hash']])) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
            Encoding::hash($chunk['hash']); $seen[$chunk['hash']] = true; $rows += $chunk['row_count']; $bytes += $chunk['bytes'];
        }
        if ($rows !== $value['row_count'] || $bytes > self::MAX_BYTES) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        /** @var Manifest $value Validated bounded internal envelope. */
        return $value;
    }
    /** @param array<array-key,mixed> $row
     * @return ServiceRow
     */
    private static function row(array $row): array
    {
        $keys = ['genesisKey','name','description','onion','revision','status','addressConflict','languages','categories','observationClaims','rotationCount','migrationCount','currentHash','searchText'];
        if (count($row) !== count($keys) || array_diff(array_keys($row), $keys) !== []) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        foreach (['genesisKey','name','description','status','searchText'] as $key) { if (!is_string($row[$key])) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); } }
        if (strlen($row['name']) > 4096 || strlen($row['description']) > 4096 || !in_array($row['status'], ['PUBLISHED','WITHDRAWN','FORKED'], true) || !is_bool($row['addressConflict']) || (!is_string($row['onion']) && $row['onion'] !== null) || (!is_string($row['currentHash']) && $row['currentHash'] !== null)) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        Encoding::publicKey($row['genesisKey']);
        if ($row['onion'] !== null) { OnionAddress::parse($row['onion']); }
        if ($row['currentHash'] !== null) { Encoding::hash($row['currentHash']); }
        foreach (['revision','observationClaims','rotationCount','migrationCount'] as $key) { if (!is_int($row[$key]) || $row[$key] < ($key === 'revision' ? 1 : 0) || $row[$key] > ($key === 'revision' ? 9007199254740991 : self::MAX_ROWS)) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); } }
        foreach (['languages','categories'] as $key) {
            if (!is_array($row[$key]) || !array_is_list($row[$key]) || count($row[$key]) > ($key === 'languages' ? 16 : 32)) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
            foreach ($row[$key] as $item) { if (!is_string($item)) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); } if ($key === 'languages') { LanguageTag::parse($item); } elseif (CategoryId::tryFrom($item) === null) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); } }
        }
        /** @var ServiceRow $row Validated internal row, built only from signed public records. */
        return $row;
    }
    /** @return array{rows:list<ServiceRow>,checkpoint_hash:string,created_at:string} */
    public static function load(StorageBackend $store, PublicBlobStore $blobs, RecordVerifier $verifier, \Closure $budget): array
    {
        $budget(); $state = $store->snapshot(); $pointer = $state['tables']['node']['key:server-search-index'] ?? null; $published = $state['tables']['node']['key:published-snapshot'] ?? null;
        if ($pointer === null || $published === null || ($pointer['format_version'] ?? null) !== 1) { throw new ProtocolException('SEARCH_INDEX_UNAVAILABLE'); }
        $manifest = self::manifest($blobs, $pointer['hash']);
        if ($manifest['checkpoint_hash'] !== $published['hash']) { throw new ProtocolException('SEARCH_INDEX_STALE'); }
        $wire = $store->readRecord($published['hash']); if ($wire === null) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        $record = $verifier->verifyWire($wire);
        if ($record->type !== 'snapshot_descriptor' || $record->contentHash !== $manifest['checkpoint_hash'] || $record->document()->created_at !== $manifest['created_at']) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
        $rows = []; $ids = [];
        foreach ($manifest['chunks'] as $chunk) {
            $budget(); $body = self::blob($blobs, $chunk['hash'], 'chunk');
            if (strlen($body) !== $chunk['bytes']) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
            $part = StorageJson::decode($body, 524288);
            if (!array_is_list($part) || count($part) !== $chunk['row_count']) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); }
            foreach ($part as $item) {
                $budget(); if (!is_array($item)) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); } $row = self::row($item);
                if (isset($ids[$row['genesisKey']])) { throw new ProtocolException('SEARCH_INDEX_CORRUPT'); } $ids[$row['genesisKey']] = true; $rows[] = $row;
            }
        }
        $budget(); return ['rows' => $rows, 'checkpoint_hash' => $manifest['checkpoint_hash'], 'created_at' => $manifest['created_at']];
    }
    /** @return list<string> */
    public static function references(PublicBlobStore $blobs, string $hash): array
    { $manifest = self::manifest($blobs, $hash); return [$hash, ...array_column($manifest['chunks'], 'hash')]; }
}
