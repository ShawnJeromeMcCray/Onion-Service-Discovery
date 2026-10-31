<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Storage;
use OnionServiceDiscovery\Protocol\RecordVerifier;

/** Trusted private operational backup, distinct from an untrusted public record import. */
final class PrivateBackup
{
    private const MAX_BYTES = 268435456;
    /** Attempts of an export that concurrent maintenance pruning invalidated (EVIDENCE_CHANGED_RETRY after them). */
    public const PRUNE_RETRIES = 3;
    /** @return array<string,mixed> */
    public static function export(StorageBackend $store, string $path): array
    {
        if (in_array(basename($path), ['hs_ed25519_secret_key', 'private_key'], true) || str_contains($path, '://') || is_link($path)) { throw new StorageException('BACKUP_PATH_INVALID'); }
        return self::retryPruned(static fn (): array => self::exportOnce($store, $path));
    }

    /**
     * Runs an export again when node:maintain removed a record of its snapshot meanwhile (prunedSince() threw
     * EVIDENCE_CHANGED_RETRY), at most PRUNE_RETRIES times; the failed attempt has already removed its file.
     * @template T
     * @param callable(): T $export
     * @return T
     */
    public static function retryPruned(callable $export): mixed
    {
        for ($attempt = 1; ; ++$attempt) {
            try { return $export(); }
            catch (StorageException $error) { if ($error->getMessage() !== 'EVIDENCE_CHANGED_RETRY' || $attempt >= self::PRUNE_RETRIES) { throw $error; } }
        }
    }

    /**
     * A record of $state the store no longer returns: EVIDENCE_CHANGED_RETRY when the store moved on (retention
     * pruned it after the snapshot was read), STORAGE_CORRUPT when the same state lost it.
     * @param array<array-key,mixed> $state
     */
    public static function missingRecord(StorageBackend $store, array $state): StorageException
    {
        $now = $store->snapshot();
        return new StorageException($now['generation'] !== $state['generation'] || ($now['pruned_records'] ?? 0) !== ($state['pruned_records'] ?? 0) ? 'EVIDENCE_CHANGED_RETRY' : 'STORAGE_CORRUPT');
    }

    /** @return array<string,mixed> */
    private static function exportOnce(StorageBackend $store, string $path): array
    {
        // A consistent snapshot references immutable objects; later appends cannot change it, pruning can (retried).
        $state = $store->snapshot();
        $header = StorageJson::encode(['private_backup_version' => 1, 'state' => $state]);
        if (strlen($header) > 33554432) { throw new StorageException('BACKUP_BUDGET_EXHAUSTED'); }
        $records = $state['records'];
        uasort($records, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        $mask = umask(0077);
        try { $stream = @fopen($path, 'x+b'); } finally { umask($mask); }
        if ($stream === false) { throw new StorageException('BACKUP_UNAVAILABLE'); }
        $digest = hash_init('sha256'); $bytes = 0;
        try {
            self::write($stream, $header . "\n", $digest, $bytes);
            foreach (array_chunk(array_keys($records), 256) as $batch) {
                $wires = $store->readRecords($batch);
                foreach ($batch as $hash) {
                    $wire = $wires[$hash] ?? null;
                    if ($wire === null) { throw self::missingRecord($store, $state); }
                    if (hash('sha256', $wire) !== $records[$hash]['object_hash']) { throw new StorageException('STORAGE_CORRUPT'); }
                    self::write($stream, $wire . "\n", $digest, $bytes);
                }
            }
            $checksum = hash_final($digest);
            $footer = StorageJson::encode(['sha256' => $checksum, 'record_count' => count($records)]) . "\n";
            if (fwrite($stream, $footer) !== strlen($footer) || !fflush($stream) || !fsync($stream)) { throw new StorageException('BACKUP_UNAVAILABLE'); }
            return ['sha256' => $checksum, 'record_count' => count($records), 'source_generation' => $state['generation'], 'private_operational_state' => true, 'keys_included' => false];
        } catch (\Throwable $error) { @unlink($path); throw $error; }
        finally { fclose($stream); }
    }

    /** Only a trusted owner-controlled private backup may restore local authority.
     * All validation precedes admission. Failure during restore requires discarding staging.
     * @return array<string,mixed>
     */
    public static function restore(StorageBackend $destination, string $path, RecordVerifier $verifier): array
    {
        $initial = $destination->snapshot();
        if ($initial['generation'] !== 0 || $initial['records'] !== []) { throw new StorageException('RESTORE_REQUIRES_EMPTY_STAGING_STORE'); }
        $verified = self::verifiedSpool($path, $verifier);
        $state = $verified['state']; $records = $verified['records']; $checksum = $verified['checksum']; $spool = $verified['spool'];
        try {
            rewind($spool); $batch = []; $spoolBytes = 0; $sequences = array_column($records, 'sequence');
            for ($index = 0; $index < count($records); ++$index) {
                $batch[$sequences[$index]] = $verifier->verifyWire(substr(self::line($spool, 65537, $spoolBytes), 0, -1));
                if (count($batch) === 256 || $index === count($records) - 1) {
                    // Sequences that bounded retention removed at the source stay retired, so every record keeps its own.
                    $destination->transaction(static function (StorageTransaction $tx) use ($batch): void { foreach ($batch as $sequence => $record) { $tx->retireSequences($sequence - $tx->state()['last_sequence'] - 1); $tx->putRecord($record); } }); $batch = [];
                }
            }
            $destination->transaction(static function (StorageTransaction $tx) use ($state): void { $tx->retireSequences($state['last_sequence'] - $tx->state()['last_sequence']); });
            foreach ($state['tables'] as $table => $rows) {
                foreach (array_chunk($rows, 256, true) as $batch) {
                    $destination->transaction(static function (StorageTransaction $tx) use ($table, $batch): void {
                        foreach ($batch as $key => $value) { $tx->set($table, substr($key, 4), $value); }
                    });
                }
            }
            $restored = $destination->snapshot();
            if (StorageJson::encode($restored['records']) !== StorageJson::encode($state['records']) || $restored['last_sequence'] !== $state['last_sequence'] || StorageJson::encode($restored['tables']) !== StorageJson::encode($state['tables'])) { throw new StorageException('BACKUP_RESTORE_MISMATCH'); }
            $destination->integrityCheck();
        } finally { fclose($spool); }
        return ['sha256' => $checksum, 'record_count' => count($records), 'source_generation' => $state['generation'], 'operational_state_restored' => true, 'staging_only' => true, 'keys_included' => false];
    }
    /** Validate a trusted private backup without creating or modifying a destination.
     * @return array<string,mixed>
     */
    public static function verify(string $path, RecordVerifier $verifier): array
    {
        $verified = self::verifiedSpool($path, $verifier);
        fclose($verified['spool']);
        return ['sha256' => $verified['checksum'], 'record_count' => count($verified['records']), 'source_generation' => $verified['state']['generation'], 'private_operational_state' => true, 'destination_modified' => false];
    }
    /** @return array{state:array<array-key,mixed>, records:array<array-key,mixed>, checksum:string, spool:resource} */
    private static function verifiedSpool(string $path, RecordVerifier $verifier): array
    {
        if (in_array(basename($path), ['hs_ed25519_secret_key', 'private_key'], true) || str_contains($path, '://') || is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0 || filesize($path) > self::MAX_BYTES) { throw new StorageException('BACKUP_PATH_INVALID'); }
        $stream = fopen($path, 'rb');
        if ($stream === false) { throw new StorageException('BACKUP_UNAVAILABLE'); }
        $spool = tmpfile();
        if ($spool === false) { fclose($stream); throw new StorageException('BACKUP_UNAVAILABLE'); }
        $digest = hash_init('sha256'); $bytes = 0;
        try {
            $line = self::line($stream, 33554433, $bytes);
            $header = StorageJson::decode(substr($line, 0, -1), 33554432);
            if (count($header) !== 2 || ($header['private_backup_version'] ?? null) !== 1 || !is_array($header['state'] ?? null)) { throw new StorageException('BACKUP_INVALID'); }
            $state = $header['state']; StorageTransaction::validateState($state, new StorageLimits());
            hash_update($digest, $line);
            $records = $state['records'];
            uasort($records, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
            foreach ($records as $hash => $metadata) {
                $line = self::line($stream, 65537, $bytes);
                $record = $verifier->verifyWire(substr($line, 0, -1));
                if ($record->canonicalWire . "\n" !== $line || $record->contentHash !== $hash || hash('sha256', $record->canonicalWire) !== $metadata['object_hash'] || $record->type !== $metadata['record_type']) { throw new StorageException('BACKUP_INVALID'); }
                hash_update($digest, $line);
                if (fwrite($spool, $line) !== strlen($line)) { throw new StorageException('BACKUP_UNAVAILABLE'); }
            }
            $footer = StorageJson::decode(substr(self::line($stream, 1024, $bytes), 0, -1), 1024);
            $checksum = hash_final($digest);
            if (count($footer) !== 2 || ($footer['sha256'] ?? null) !== $checksum || ($footer['record_count'] ?? null) !== count($records) || fgetc($stream) !== false) { throw new StorageException('BACKUP_INVALID'); }
        } catch (\Throwable $error) { fclose($spool); throw $error; }
        finally { fclose($stream); }
        return ['state' => $state, 'records' => $records, 'checksum' => $checksum, 'spool' => $spool];
    }
    /** @param resource $stream */
    private static function write($stream, string $line, \HashContext $digest, int &$bytes): void
    {
        $bytes += strlen($line);
        if ($bytes > self::MAX_BYTES - 1024) { throw new StorageException('BACKUP_BUDGET_EXHAUSTED'); }
        if (fwrite($stream, $line) !== strlen($line)) { throw new StorageException('BACKUP_UNAVAILABLE'); }
        hash_update($digest, $line);
    }
    /** @param resource $stream */
    private static function line($stream, int $maxLine, int &$bytes): string
    {
        if ($maxLine < 1 || $maxLine > 33554433) { throw new StorageException('BACKUP_BUDGET_EXHAUSTED'); }
        $line = fgets($stream, $maxLine + 1);
        if ($line === false || strlen($line) > $maxLine || !str_ends_with($line, "\n")) { throw new StorageException('BACKUP_INVALID'); }
        $bytes += strlen($line);
        if ($bytes > self::MAX_BYTES) { throw new StorageException('BACKUP_BUDGET_EXHAUSTED'); }
        return $line;
    }
}
