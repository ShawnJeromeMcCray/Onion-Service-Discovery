<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Storage;
use OnionServiceDiscovery\Protocol\RecordVerifier;

/** Portable public-evidence archive. Local observations/cursors/secrets are never imported. */
final class RecordArchive
{
    /**
     * @return array<array-key, mixed>
     */
    public static function export(StorageBackend $store, string $path, ?RecordVerifier $verifier = null): array
    {
        if (in_array(basename($path), ['hs_ed25519_secret_key', 'private_key'], true) || str_contains($path, '://')) { throw new StorageException('ARCHIVE_PATH_INVALID'); }
        $snapshot = $store->snapshot();
        $verifier ??= new RecordVerifier(dirname(__DIR__, 3) . '/protocol/schemas');
        $validated = [];
        foreach (array_chunk(array_keys($snapshot['records']), 256) as $batch) {
            foreach ($store->readRecords($batch) as $hash => $wire) {
                $record = $verifier->verifyWire($wire);
                if ($record->contentHash !== $hash) { throw new StorageException('STORAGE_CORRUPT'); }
                $validated[] = $record;
            }
        }
        if (count($validated) !== count($snapshot['records'])) { throw new StorageException('STORAGE_CORRUPT'); }
        $public = \OnionServiceDiscovery\Node\PublicEvidencePolicy::select($validated);
        foreach ($validated as $record) { if ($record->type === 'snapshot_descriptor') { $public[$record->contentHash] = true; } }
        $records = array_intersect_key($snapshot['records'], $public);
        uasort($records, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        $mask = umask(0077);
        try { $stream = @fopen($path, 'x+b'); } finally { umask($mask); }
        if ($stream === false) { throw new StorageException('ARCHIVE_UNAVAILABLE'); }
        $digest = hash_init('sha256');
        try {
            self::write($stream, StorageJson::encode(['archive_version' => 1, 'kind' => 'public-record-evidence', 'record_count' => count($records)]) . "\n", $digest);
            foreach (array_chunk(array_keys($records), 256) as $batch) {
                $wires = $store->readRecords($batch);
                foreach ($batch as $hash) {
                    $wire = $wires[$hash] ?? null;
                    if ($wire === null || hash('sha256', $wire) !== $records[$hash]['object_hash']) { throw new StorageException('STORAGE_CORRUPT'); }
                    self::write($stream, $wire . "\n", $digest);
                }
            }
            $checksum = hash_final($digest);
            $footer = StorageJson::encode(['sha256' => $checksum, 'record_count' => count($records)]) . "\n";
            if (fwrite($stream, $footer) !== strlen($footer) || !fflush($stream) || !fsync($stream)) { throw new StorageException('ARCHIVE_UNAVAILABLE'); }
            return ['record_count' => count($records), 'sha256' => $checksum];
        } catch (\Throwable $error) { @unlink($path); throw $error; }
        finally { fclose($stream); }
    }
    /** Verify the entire archive before admitting records in bounded batches.
     * Destination must be a fresh staging backend. Cutover is a separate operator action.
     */
    /**
     * @return array<array-key, mixed>
     */
    public static function import(StorageBackend $destination, string $path, RecordVerifier $verifier, int $maxRecords = 10000, int $maxBytes = 268435456): array
    {
        if ($maxRecords < 1 || $maxRecords > 1000000 || $maxBytes < 1 || $maxBytes > 1099511627776 || in_array(basename($path), ['hs_ed25519_secret_key', 'private_key'], true) || str_contains($path, '://') || is_link($path) || !is_file($path)) { throw new StorageException('ARCHIVE_PATH_INVALID'); }
        $initial = $destination->snapshot();
        if ($initial['generation'] !== 0 || $initial['records'] !== []) { throw new StorageException('IMPORT_REQUIRES_EMPTY_STAGING_STORE'); }
        $stream = fopen($path, 'rb');
        if ($stream === false) { throw new StorageException('ARCHIVE_UNAVAILABLE'); }
        $spool = tmpfile();
        if ($spool === false) { fclose($stream); throw new StorageException('ARCHIVE_UNAVAILABLE'); }
        $count = 0; $seen = []; $readBytes = 0; $digest = hash_init('sha256');
        try {
            $line = self::line($stream, 1024, $readBytes, $maxBytes);
            $header = StorageJson::decode(substr($line, 0, -1), 1024);
            if (count($header) !== 3 || ($header['archive_version'] ?? null) !== 1 || ($header['kind'] ?? null) !== 'public-record-evidence' || !is_int($header['record_count'] ?? null) || $header['record_count'] < 0 || $header['record_count'] > $maxRecords) { throw new StorageException('ARCHIVE_INVALID'); }
            hash_update($digest, $line);
            for ($index = 0; $index < $header['record_count']; ++$index) {
                $line = self::line($stream, 65537, $readBytes, $maxBytes);
                $record = $verifier->verifyWire(substr($line, 0, -1));
                if ($record->canonicalWire . "\n" !== $line || isset($seen[$record->contentHash])) { throw new StorageException('ARCHIVE_INVALID'); }
                $seen[$record->contentHash] = true; ++$count; hash_update($digest, $line);
                if (fwrite($spool, $line) !== strlen($line)) { throw new StorageException('ARCHIVE_UNAVAILABLE'); }
            }
            $footer = StorageJson::decode(substr(self::line($stream, 1024, $readBytes, $maxBytes), 0, -1), 1024);
            $checksum = hash_final($digest);
            if (count($footer) !== 2 || ($footer['sha256'] ?? null) !== $checksum || ($footer['record_count'] ?? null) !== $count || fgetc($stream) !== false) { throw new StorageException('ARCHIVE_INVALID'); }
        } catch (\Throwable $error) { fclose($spool); throw $error; } finally { fclose($stream); }
        try {
            rewind($spool);
            $batch = []; $spoolBytes = 0;
            for ($index = 0; $index < $count; ++$index) {
                $batch[] = $verifier->verifyWire(substr(self::line($spool, 65537, $spoolBytes, $maxBytes), 0, -1));
                if (count($batch) === 256 || $index === $count - 1) {
                    $destination->transaction(static function (StorageTransaction $tx) use ($batch): void { foreach ($batch as $record) { $tx->putRecord($record); } });
                    $batch = [];
                }
            }
            $destination->integrityCheck();
        } finally { fclose($spool); }
        return ['record_count' => $count, 'sha256' => $checksum, 'operational_state_restored' => false];
    }
    /** @param resource $stream */
    private static function write($stream, string $bytes, \HashContext $digest): void
    {
        if (fwrite($stream, $bytes) !== strlen($bytes)) { throw new StorageException('ARCHIVE_UNAVAILABLE'); }
        hash_update($digest, $bytes);
    }
    /** @param resource $stream */
    private static function line($stream, int $maxLine, int &$readBytes, int $maxBytes): string
    {
        if ($maxLine < 1 || $maxLine > 65537) { throw new StorageException('ARCHIVE_BUDGET_EXHAUSTED'); }
        $line = fgets($stream, $maxLine + 1);
        if ($line === false || strlen($line) > $maxLine || !str_ends_with($line, "\n")) { throw new StorageException('ARCHIVE_INVALID'); }
        $readBytes += strlen($line);
        if ($readBytes > $maxBytes) { throw new StorageException('ARCHIVE_BUDGET_EXHAUSTED'); }
        return $line;
    }
}
