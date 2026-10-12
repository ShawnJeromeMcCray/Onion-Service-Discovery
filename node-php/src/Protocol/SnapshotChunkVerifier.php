<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Protocol;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Validation\StrictJson;

final readonly class SnapshotChunkVerifier
{
    public function __construct(private RecordVerifier $verifier) {}
    /** @return array{records:list<ValidatedRecord>,last_hash:?string} */
    public function verify(string $wire, ValidatedRecord $checkpoint, int $index, ?string $previousHash = null): array
    {
        if ($checkpoint->type !== 'snapshot_descriptor' || $index < 0) { throw new ProtocolException('SCHEMA_INVALID'); }
        $metadata = $checkpoint->document()->chunks[$index] ?? null;
        if ($metadata === null || strlen($wire) !== $metadata->bytes || SnapshotFormat::chunkHash($wire) !== $metadata->hash) { throw new ProtocolException('SNAPSHOT_CHUNK_HASH_INVALID'); }
        $array = StrictJson::parseValue($wire, 524288);
        if (!is_array($array) || !array_is_list($array) || count($array) !== $metadata->record_count || CanonicalJson::encode($array) !== $wire) { throw new ProtocolException('SCHEMA_INVALID'); }
        $records = []; $last = $previousHash;
        foreach ($array as $document) {
            if (!$document instanceof \stdClass) { throw new ProtocolException('SCHEMA_INVALID'); }
            $record = $this->verifier->verify($document);
            if ($record->type === 'snapshot_descriptor' || ($last !== null && strcmp($last, $record->contentHash) >= 0)) { throw new ProtocolException('SNAPSHOT_RECORD_ORDER_INVALID'); }
            $last = $record->contentHash; $records[] = $record;
        }
        return ['records' => $records, 'last_hash' => $last];
    }
}
