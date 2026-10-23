<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;

use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageJson;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Validation\StrictJson;

/**
 * The newest snapshot checkpoint and Node descriptor this Node key signed and published, kept in a
 * private file beside the key (KEY_PATH.signed), outside the store and its backups. A store restored
 * from an older backup would otherwise make the Node sign a generation or descriptor revision it
 * already published with other content, which peers and publishers treat as equivocation and never
 * forgive. Before signing, the store must have reached what the ledger names: a ledger ahead of the
 * store refuses (SIGNED_CHAIN_AHEAD_OF_STORE, SIGNED_DESCRIPTOR_AHEAD_OF_STORE) until
 * node:snapshot --adopt-signed-chain continues from the signed records; a ledger behind the store
 * (lost, first run after an upgrade, a crash after the commit) is raised to the store first. The
 * ledger is written only after the store committed, so a record that was signed but never committed,
 * and so never published, is never held against the Node. A damaged ledger or one of another key
 * stops signing (SIGNING_LEDGER_INVALID) and is never replaced silently.
 */
final class SigningLedger
{
    public const MAX_BYTES = 4096;
    private const KEYS = ['checkpoint', 'descriptor', 'ledger_version', 'node_identity_public_key'];
    private int $depth = 0;
    /** @var resource|null */
    private $lock = null;

    private function __construct(public readonly string $path, private readonly string $keyPath) {}

    public static function forKey(string $keyPath): self { return new self($keyPath . '.signed', $keyPath); }

    /**
     * Runs $work while this process alone may sign with the key: an advisory lock on the key file,
     * reentrant within this object, so concurrent node:init and node:snapshot runs sign one at a time.
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function exclusive(callable $work): mixed
    {
        if ($this->depth === 0) {
            $lock = is_link($this->keyPath) ? false : @fopen($this->keyPath, 'rb');
            if ($lock === false) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
            if (!flock($lock, LOCK_EX)) { fclose($lock); throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
            $this->lock = $lock;
        }
        ++$this->depth;
        try { return $work(); }
        finally { if (--$this->depth === 0 && $this->lock !== null) { flock($this->lock, LOCK_UN); fclose($this->lock); $this->lock = null; } }
    }

    /**
     * The ledger's entries as [generation or revision, content hash], or null while there is no ledger.
     * With $publicKey the ledger must belong to that key.
     * @return array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null}|null
     */
    public function read(?string $publicKey): ?array
    {
        clearstatcache(true, $this->path);
        if (is_link($this->path)) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
        if (!file_exists($this->path)) { return null; }
        if (!is_file($this->path) || (fileperms($this->path) & 0077) !== 0 || filesize($this->path) > self::MAX_BYTES) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
        $bytes = @file_get_contents($this->path, false, null, 0, self::MAX_BYTES + 1);
        if ($bytes === false) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
        try {
            $fields = get_object_vars(StrictJson::parse($bytes, self::MAX_BYTES)); ksort($fields, SORT_STRING);
            if (array_keys($fields) !== self::KEYS || $fields['ledger_version'] !== 1 || !is_string($fields['node_identity_public_key'])) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
            Encoding::publicKey($fields['node_identity_public_key']);
            if ($publicKey !== null && $fields['node_identity_public_key'] !== $publicKey) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
            return ['checkpoint' => self::entry($fields['checkpoint'], 'generation'), 'descriptor' => self::entry($fields['descriptor'], 'revision')];
        } catch (\Throwable) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
    }

    /**
     * Before signing: refuses when this key already signed beyond the store's published checkpoint or
     * Node descriptor, and raises a ledger that is missing or behind to the store.
     * @param array<array-key,mixed> $state The store state the signature will be committed against.
     */
    public function reconcile(string $publicKey, StorageBackend $store, RecordVerifier $verifier, array $state): void
    {
        $this->exclusive(function () use ($publicKey, $store, $verifier, $state): void {
            $stored = self::stored($store, $verifier, $state); $ledger = $this->read($publicKey);
            $signed = $ledger ?? ['checkpoint' => null, 'descriptor' => null];
            if (self::relation($signed['checkpoint'], $stored['checkpoint']) === 'AHEAD') { throw new StorageException('SIGNED_CHAIN_AHEAD_OF_STORE'); }
            if (self::relation($signed['descriptor'], $stored['descriptor']) === 'AHEAD') { throw new StorageException('SIGNED_DESCRIPTOR_AHEAD_OF_STORE'); }
            // Neither entry is ahead, so the store's pointers are the newest this key published.
            if ($signed !== $stored && ($ledger !== null || $stored['checkpoint'] !== null || $stored['descriptor'] !== null)) { $this->write($publicKey, $stored); }
        });
    }

    /**
     * After the store committed a signed checkpoint or Node descriptor.
     * @param 'checkpoint'|'descriptor' $kind
     */
    public function record(string $publicKey, string $kind, int $number, string $hash): void
    {
        $this->exclusive(function () use ($publicKey, $kind, $number, $hash): void {
            $ledger = $this->read($publicKey) ?? ['checkpoint' => null, 'descriptor' => null]; $current = $ledger[$kind];
            if ($current !== null && ($current[0] > $number || ($current[0] === $number && $current[1] !== $hash))) { throw new StorageException($kind === 'checkpoint' ? 'SIGNED_CHAIN_AHEAD_OF_STORE' : 'SIGNED_DESCRIPTOR_AHEAD_OF_STORE'); }
            if ($current === [$number, $hash]) { return; }
            $ledger[$kind] = [$number, $hash]; $this->write($publicKey, $ledger);
        });
    }

    /**
     * Read-only comparison for doctor and node:status: CONSISTENT, BEHIND_STORE (raised by the next
     * signing run), AHEAD_OF_STORE (the store is older than what the key signed), NOT_PRESENT or INVALID.
     * @param array<array-key,mixed> $state
     */
    public function inspect(?string $publicKey, StorageBackend $store, RecordVerifier $verifier, array $state): string
    {
        try { $ledger = $this->read($publicKey); } catch (StorageException) { return 'INVALID'; }
        if ($ledger === null) { return 'NOT_PRESENT'; }
        $stored = self::stored($store, $verifier, $state);
        $relations = [self::relation($ledger['checkpoint'], $stored['checkpoint']), self::relation($ledger['descriptor'], $stored['descriptor'])];
        return in_array('AHEAD', $relations, true) ? 'AHEAD_OF_STORE' : (in_array('BEHIND', $relations, true) ? 'BEHIND_STORE' : 'CONSISTENT');
    }

    /** The operator's next step for a ledger state that needs one. */
    public static function nextAction(string $ledgerState): ?string
    {
        return match ($ledgerState) {
            'AHEAD_OF_STORE' => 'The store is older than what this Node key already signed (a restored backup?), so signing stops to avoid equivocation. With the timers and the web server stopped, run node:snapshot CONFIG --passphrase-file FILE --adopt-signed-chain; see docs/operations/deployment-and-recovery.md.',
            'INVALID' => 'The signing ledger beside the Node key is damaged, unsafe or belongs to another key, so signing stops. Restore it from the copy kept with the key, or follow docs/operations/incident-runbook.md.',
            default => null,
        };
    }

    /**
     * node:snapshot --adopt-signed-chain: admits the checkpoint (from the export directory) and the Node
     * descriptor (from the published-state copy) that the ledger names but the store lacks, after checking
     * that this key signed exactly those, and points the store at them. The next snapshot then continues at
     * the ledger's generation plus one; the generations between the restored store and the adopted one stay
     * absent. SIGNED_CHAIN_UNRECOVERABLE when a needed record is gone.
     * @return array{checkpoint:string,descriptor:string}
     */
    public function adopt(string $publicKey, StorageBackend $store, PublicBlobStore $blobs, RecordVerifier $verifier, ?string $nodeOnion): array
    {
        return $this->exclusive(function () use ($publicKey, $store, $blobs, $verifier, $nodeOnion): array {
            $state = $store->snapshot(); $stored = self::stored($store, $verifier, $state);
            $signed = $this->read($publicKey) ?? ['checkpoint' => null, 'descriptor' => null]; $checkpoint = null; $descriptor = null;
            if ($signed['checkpoint'] !== null && self::relation($signed['checkpoint'], $stored['checkpoint']) === 'AHEAD') {
                try { $wire = $blobs->get($signed['checkpoint'][1]); } catch (StorageException) { $wire = null; }
                $checkpoint = self::signed($wire, $verifier, $publicKey, 'snapshot_descriptor', $signed['checkpoint'][1]);
                if ($checkpoint->document()->generation !== $signed['checkpoint'][0]) { throw new StorageException('SIGNED_CHAIN_UNRECOVERABLE'); }
            }
            if ($signed['descriptor'] !== null && self::relation($signed['descriptor'], $stored['descriptor']) === 'AHEAD') {
                try { $wire = PublishedState::load($blobs, $verifier, $nodeOnion)?->nodeDescriptor?->canonicalWire; } catch (StorageException) { $wire = null; }
                $descriptor = self::signed($wire, $verifier, $publicKey, 'node_descriptor', $signed['descriptor'][1]);
                if ($descriptor->document()->descriptor_revision !== $signed['descriptor'][0] || $descriptor->document()->node_onion !== $nodeOnion) { throw new StorageException('SIGNED_CHAIN_UNRECOVERABLE'); }
            }
            if ($checkpoint === null && $descriptor === null) { return ['checkpoint' => 'CURRENT', 'descriptor' => 'CURRENT']; }
            $store->transaction(static function (StorageTransaction $tx) use ($state, $checkpoint, $descriptor): void {
                if ($tx->state()['tables']['node'] !== $state['tables']['node']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
                if ($checkpoint !== null) {
                    // service_index_version 1: the derived indexes still describe the restored store, so the next run re-signs and rebuilds them.
                    $document = $checkpoint->document(); $tx->putRecord($checkpoint);
                    $tx->set('node', 'published-snapshot', ['hash' => $checkpoint->contentHash, 'root_hash' => $document->root_hash, 'generation' => $document->generation, 'cursor' => $document->cursor, 'service_index_version' => 1]);
                }
                if ($descriptor !== null) { $tx->putRecord($descriptor); $tx->set('node', 'descriptor', ['hash' => $descriptor->contentHash]); }
            });
            return ['checkpoint' => $checkpoint === null ? 'CURRENT' : 'ADOPTED', 'descriptor' => $descriptor === null ? 'CURRENT' : 'ADOPTED'];
        });
    }

    /**
     * The store's published checkpoint and Node descriptor as [generation or revision, content hash].
     * @param array<array-key,mixed> $state
     * @return array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null}
     */
    private static function stored(StorageBackend $store, RecordVerifier $verifier, array $state): array
    {
        $published = $state['tables']['node']['key:published-snapshot'] ?? null; $pointer = $state['tables']['node']['key:descriptor'] ?? null; $descriptor = null;
        if ($published !== null && (!is_int($published['generation'] ?? null) || !is_string($published['hash'] ?? null))) { throw new StorageException('STORAGE_CORRUPT'); }
        if ($pointer !== null) {
            $wire = $store->readRecord((string) $pointer['hash']); $record = $wire === null ? null : $verifier->verifyWire($wire);
            if ($record === null || $record->type !== 'node_descriptor' || $record->contentHash !== $pointer['hash']) { throw new StorageException('STORAGE_CORRUPT'); }
            $descriptor = [(int) $record->document()->descriptor_revision, $record->contentHash];
        }
        return ['checkpoint' => $published === null ? null : [$published['generation'], $published['hash']], 'descriptor' => $descriptor];
    }

    /**
     * AHEAD when the key signed beyond the store (a higher number, or the same one with another hash),
     * BEHIND when the store is newer, otherwise CONSISTENT.
     * @param array{int,string}|null $signed
     * @param array{int,string}|null $stored
     */
    private static function relation(?array $signed, ?array $stored): string
    {
        if ($signed === null) { return $stored === null ? 'CONSISTENT' : 'BEHIND'; }
        if ($stored === null || $signed[0] > $stored[0] || ($signed[0] === $stored[0] && $signed[1] !== $stored[1])) { return 'AHEAD'; }
        return $signed[0] < $stored[0] ? 'BEHIND' : 'CONSISTENT';
    }

    /** @return array{int,string}|null */
    private static function entry(mixed $value, string $field): ?array
    {
        if ($value === null) { return null; }
        if (!$value instanceof \stdClass) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
        $fields = get_object_vars($value); ksort($fields, SORT_STRING); $expected = [$field, 'hash']; sort($expected, SORT_STRING);
        if (array_keys($fields) !== $expected || !is_int($fields[$field]) || $fields[$field] < 1 || !is_string($fields['hash']) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $fields['hash']) !== 1) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
        return [$fields[$field], $fields['hash']];
    }

    /** A record the ledger names, checked to be exactly what this key signed. */
    private static function signed(?string $wire, RecordVerifier $verifier, string $publicKey, string $type, string $hash): ValidatedRecord
    {
        try { $record = $wire === null ? null : $verifier->verifyWire($wire); } catch (\InvalidArgumentException) { $record = null; }
        if ($record === null || $record->type !== $type || $record->contentHash !== $hash || $record->canonicalWire !== $wire || $record->document()->node_identity_public_key !== $publicKey) { throw new StorageException('SIGNED_CHAIN_UNRECOVERABLE'); }
        return $record;
    }

    /**
     * Replaces the ledger atomically: an owner-only temporary file in the key directory, flushed to disk,
     * renamed over the ledger, then the directory flushed.
     * @param array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null} $ledger
     */
    private function write(string $publicKey, array $ledger): void
    {
        $parent = dirname($this->path);
        if (str_contains($this->path, '://') || is_link($parent) || !is_dir($parent) || (fileperms($parent) & 0022) !== 0 || is_link($this->path) || (file_exists($this->path) && !is_file($this->path))) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
        $entry = static fn (?array $value, string $field): ?array => $value === null ? null : [$field => $value[0], 'hash' => $value[1]];
        $bytes = StorageJson::encode(['ledger_version' => 1, 'node_identity_public_key' => $publicKey, 'checkpoint' => $entry($ledger['checkpoint'], 'generation'), 'descriptor' => $entry($ledger['descriptor'], 'revision')]) . "\n";
        $temporary = $this->path . '.tmp-' . bin2hex(random_bytes(8));
        $previousMask = umask(0077);
        try { $stream = @fopen($temporary, 'xb'); } finally { umask($previousMask); }
        if ($stream === false) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
        try {
            $written = chmod($temporary, 0600) && fwrite($stream, $bytes) === strlen($bytes) && fflush($stream) && fsync($stream);
            fclose($stream); $stream = null;
            if (!$written || !rename($temporary, $this->path)) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
            $directory = @fopen($parent, 'r'); if ($directory === false) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
            try { if (!fsync($directory)) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); } } finally { fclose($directory); }
        } finally { if ($stream !== null) { fclose($stream); } if (is_file($temporary)) { @unlink($temporary); } }
    }
}
