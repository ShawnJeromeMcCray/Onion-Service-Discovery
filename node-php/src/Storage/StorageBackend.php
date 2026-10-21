<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

interface StorageBackend
{
    public function backendName(): string;
    /** Whether the store's format lets bounded retention remove signed records (storage schema 2). */
    public function supportsPruning(): bool;
    /** Mutation callback is trusted application code, never network supplied. */
    public function transaction(callable $mutation): mixed;
    /**
     * @return array<array-key, mixed>
     */
    public function snapshot(): array;
    public function readRecord(string $contentHash): ?string;
    /**
     * At most 256 validated content-hash addresses per bounded read.
     * @param array<array-key, mixed> $contentHashes
     * @return array<array-key, mixed>
     */
    public function readRecords(array $contentHashes): array;
    /**
     * @return array<array-key, mixed>
     */
    public function integrityCheck(): array;
}
