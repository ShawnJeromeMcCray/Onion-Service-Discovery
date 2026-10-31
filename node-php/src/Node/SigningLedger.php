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
 * (lost, first run after an upgrade) is raised to the store first. Before each commit the ledger holds
 * the new record as pending (pending_checkpoint, pending_descriptor: present only while set, so a
 * ledger without one is the version 1 file older releases wrote), after the commit as signed. A crash
 * between the two leaves the pending entry: the next signing run adopts it from its export blob when
 * it directly follows the store's head (the commit was lost, or a restored backup predates it), and
 * otherwise refuses as for any record the key signed beyond the store. A damaged ledger or one of
 * another key stops signing (SIGNING_LEDGER_INVALID) and is never replaced silently.
 */
final class SigningLedger
{
    public const MAX_BYTES = 4096;
    /**
     * Own checkpoints --adopt-signed-chain must find below the signed head (exports:prune keeps their blobs): a peer
     * treats a missing parent within PeerSync::SERVED_CHECKPOINTS of the newest checkpoint as a fault, not a gap.
     */
    public const CHAIN_BLOBS = PeerSync::SERVED_CHECKPOINTS;
    /** Checkpoints one adoption admits at most (one transaction, with the descriptor). */
    private const MAX_ADOPTED = 255;
    private const KEYS = ['checkpoint', 'descriptor', 'ledger_version', 'node_identity_public_key'];
    private const EMPTY = ['checkpoint' => null, 'descriptor' => null, 'pending' => ['checkpoint' => null, 'descriptor' => null]];
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
     * The ledger's entries as [generation or revision, content hash], with the pending ones written before
     * their commit, or null while there is no ledger. With $publicKey the ledger must belong to that key.
     * @return array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null, pending: array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null}}|null
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
            $pending = ['checkpoint' => self::entry($fields['pending_checkpoint'] ?? null, 'generation'), 'descriptor' => self::entry($fields['pending_descriptor'] ?? null, 'revision')];
            // A pending entry is written only while it is set, never as null.
            if (array_keys(array_diff_key($fields, ['pending_checkpoint' => 0, 'pending_descriptor' => 0])) !== self::KEYS || (array_key_exists('pending_checkpoint', $fields) && $pending['checkpoint'] === null)
                || (array_key_exists('pending_descriptor', $fields) && $pending['descriptor'] === null) || $fields['ledger_version'] !== 1 || !is_string($fields['node_identity_public_key'])) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
            Encoding::publicKey($fields['node_identity_public_key']);
            if ($publicKey !== null && $fields['node_identity_public_key'] !== $publicKey) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
            return ['checkpoint' => self::entry($fields['checkpoint'], 'generation'), 'descriptor' => self::entry($fields['descriptor'], 'revision'), 'pending' => $pending];
        } catch (\Throwable) { throw new StorageException('SIGNING_LEDGER_INVALID'); }
    }

    /**
     * Before signing: refuses when this key already signed beyond the store's published checkpoint or
     * Node descriptor, adopts a pending record whose commit was lost when it directly follows the store's
     * head (its blob in $blobs), and raises a ledger that is missing or behind to the store. Returns the
     * store state to sign against: $state, or the state after such an adoption.
     * @param array<array-key,mixed> $state The store state the signature will be committed against.
     * @return array<array-key,mixed>
     */
    public function reconcile(string $publicKey, StorageBackend $store, RecordVerifier $verifier, array $state, ?PublicBlobStore $blobs = null): array
    {
        return $this->exclusive(function () use ($publicKey, $store, $verifier, $state, $blobs): array {
            $stored = self::stored($store, $verifier, $state); $ledger = $this->read($publicKey);
            $signed = $ledger ?? self::EMPTY;
            if (self::relation($signed['checkpoint'], $stored['checkpoint']) === 'AHEAD') { throw new StorageException('SIGNED_CHAIN_AHEAD_OF_STORE'); }
            if (self::relation($signed['descriptor'], $stored['descriptor']) === 'AHEAD') { throw new StorageException('SIGNED_DESCRIPTOR_AHEAD_OF_STORE'); }
            foreach (['checkpoint', 'descriptor'] as $kind) {
                $pending = $signed['pending'][$kind];
                // Committed (the store has it) or superseded: the store's pointer below replaces it.
                if ($pending === null || self::relation($pending, $stored[$kind]) !== 'AHEAD') { continue; }
                $record = $blobs === null ? null : self::successor($kind, $pending, $stored[$kind], $blobs, $verifier, $publicKey);
                if ($record === null) { throw new StorageException($kind === 'checkpoint' ? 'SIGNED_CHAIN_AHEAD_OF_STORE' : 'SIGNED_DESCRIPTOR_AHEAD_OF_STORE'); }
                $state = self::admit($store, $state, $kind === 'checkpoint' ? [$record] : [], $kind === 'descriptor' ? $record : null);
                $stored = self::stored($store, $verifier, $state);
            }
            // Nothing is ahead or pending any more, so the store's pointers are the newest this key published.
            $next = ['checkpoint' => $stored['checkpoint'], 'descriptor' => $stored['descriptor'], 'pending' => self::EMPTY['pending']];
            if ($next !== $signed && ($ledger !== null || $stored['checkpoint'] !== null || $stored['descriptor'] !== null)) { $this->write($publicKey, $next); }
            return $state;
        });
    }

    /**
     * Before the store commits a signed checkpoint or Node descriptor (its blob already written): holds it as
     * pending, so a crash before record() still keeps it from being signed over.
     * @param 'checkpoint'|'descriptor' $kind
     */
    public function prepare(string $publicKey, string $kind, int $number, string $hash): void
    {
        $this->exclusive(function () use ($publicKey, $kind, $number, $hash): void {
            $ledger = $this->read($publicKey) ?? self::EMPTY; $ledger['pending'][$kind] = [$number, $hash]; $this->write($publicKey, $ledger);
        });
    }

    /**
     * The commit was refused before it wrote anything (EVIDENCE_CHANGED_RETRY): the pending record was never published.
     * @param 'checkpoint'|'descriptor' $kind
     */
    public function cancel(string $publicKey, string $kind, string $hash): void
    {
        $this->exclusive(function () use ($publicKey, $kind, $hash): void {
            $ledger = $this->read($publicKey);
            if ($ledger === null || ($ledger['pending'][$kind][1] ?? null) !== $hash) { return; }
            $ledger['pending'][$kind] = null; $this->write($publicKey, $ledger);
        });
    }

    /**
     * After the store committed a signed checkpoint or Node descriptor.
     * @param 'checkpoint'|'descriptor' $kind
     */
    public function record(string $publicKey, string $kind, int $number, string $hash): void
    {
        $this->exclusive(function () use ($publicKey, $kind, $number, $hash): void {
            $ledger = $this->read($publicKey) ?? self::EMPTY; $current = $ledger[$kind];
            if ($current !== null && ($current[0] > $number || ($current[0] === $number && $current[1] !== $hash))) { throw new StorageException($kind === 'checkpoint' ? 'SIGNED_CHAIN_AHEAD_OF_STORE' : 'SIGNED_DESCRIPTOR_AHEAD_OF_STORE'); }
            $next = $ledger; $next[$kind] = [$number, $hash];
            if ($ledger['pending'][$kind] === [$number, $hash]) { $next['pending'][$kind] = null; }
            if ($next !== $ledger) { $this->write($publicKey, $next); }
        });
    }

    /**
     * Read-only comparison for doctor and node:status: CONSISTENT, BEHIND_STORE (raised by the next
     * signing run), AHEAD_OF_STORE (the store is older than what the key signed, or lacks a pending record
     * the next signing run adopts), NOT_PRESENT or INVALID.
     * @param array<array-key,mixed> $state
     */
    public function inspect(?string $publicKey, StorageBackend $store, RecordVerifier $verifier, array $state): string
    {
        try { $ledger = $this->read($publicKey); } catch (StorageException) { return 'INVALID'; }
        if ($ledger === null) { return 'NOT_PRESENT'; }
        $stored = self::stored($store, $verifier, $state); $relations = [];
        foreach (['checkpoint', 'descriptor'] as $kind) {
            $relations[] = self::relation($ledger[$kind], $stored[$kind]);
            if ($ledger['pending'][$kind] !== null) { $relations[] = self::relation($ledger['pending'][$kind], $stored[$kind]) === 'AHEAD' ? 'AHEAD' : 'BEHIND'; }
        }
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
     * descriptor (from the published-state copy or its blob) that the ledger names but the store lacks,
     * after checking that this key signed exactly those, and points the store at them. The checkpoints
     * between the store's and the signed one are admitted too, walked back through previous_checkpoint_hash
     * (export blobs; at most 255), so a peer anchored on any of them still proves the chain. The newest
     * CHAIN_BLOBS below the signed one must be found (SIGNED_CHAIN_INCOMPLETE otherwise); older ones that are
     * gone leave a gap peers accept as one. SIGNED_CHAIN_UNRECOVERABLE when the signed record itself is gone.
     * @return array{checkpoint:string,descriptor:string}
     */
    public function adopt(string $publicKey, StorageBackend $store, PublicBlobStore $blobs, RecordVerifier $verifier, ?string $nodeOnion): array
    {
        return $this->exclusive(function () use ($publicKey, $store, $blobs, $verifier, $nodeOnion): array {
            $state = $store->snapshot(); $stored = self::stored($store, $verifier, $state);
            $signed = $this->read($publicKey) ?? self::EMPTY; $chain = []; $descriptor = null;
            $checkpoint = self::head($signed['checkpoint'], $signed['pending']['checkpoint']); $signedDescriptor = self::head($signed['descriptor'], $signed['pending']['descriptor']);
            if ($checkpoint !== null && self::relation($checkpoint, $stored['checkpoint']) === 'AHEAD') { $chain = self::chain($checkpoint, $stored['checkpoint'], $store, $blobs, $verifier, $publicKey); }
            if ($signedDescriptor !== null && self::relation($signedDescriptor, $stored['descriptor']) === 'AHEAD') {
                try { $copy = PublishedState::load($blobs, $verifier, $nodeOnion)?->nodeDescriptor; } catch (StorageException) { $copy = null; }
                try { $wire = $copy?->contentHash === $signedDescriptor[1] ? $copy->canonicalWire : $blobs->get($signedDescriptor[1]); } catch (StorageException) { $wire = null; }
                $descriptor = self::signed($wire, $verifier, $publicKey, 'node_descriptor', $signedDescriptor[1]);
                if ($descriptor->document()->descriptor_revision !== $signedDescriptor[0] || $descriptor->document()->node_onion !== $nodeOnion) { throw new StorageException('SIGNED_CHAIN_UNRECOVERABLE'); }
            }
            if ($chain === [] && $descriptor === null) { return ['checkpoint' => 'CURRENT', 'descriptor' => 'CURRENT']; }
            self::admit($store, $state, $chain, $descriptor);
            return ['checkpoint' => $chain === [] ? 'CURRENT' : 'ADOPTED', 'descriptor' => $descriptor === null ? 'CURRENT' : 'ADOPTED'];
        });
    }

    /**
     * Export blobs exports:prune keeps for --adopt-signed-chain: the newest CHAIN_BLOBS checkpoints of this Node's
     * own chain below the store's published checkpoint and below those the ledger names, and the descriptors the
     * ledger names. Only blobs that exist and hold exactly the record; a damaged ledger keeps only the store's chain.
     * @param array<array-key,mixed> $state
     * @return list<string>
     */
    public function retainedBlobs(StorageBackend $store, PublicBlobStore $blobs, RecordVerifier $verifier, array $state): array
    {
        try { $ledger = $this->read(null) ?? self::EMPTY; } catch (StorageException) { $ledger = self::EMPTY; }
        $keep = []; $seen = [];
        foreach ([$state['tables']['node']['key:published-snapshot']['hash'] ?? null, $ledger['checkpoint'][1] ?? null, $ledger['pending']['checkpoint'][1] ?? null] as $hash) {
            for ($walked = 0; is_string($hash) && !isset($seen[$hash]) && $walked < self::CHAIN_BLOBS; ++$walked) {
                $seen[$hash] = true;
                try {
                    $blob = $blobs->get($hash); $record = $verifier->verifyWire($blob ?? $store->readRecord($hash) ?? throw new StorageException('NOT_FOUND'));
                } catch (StorageException | \InvalidArgumentException) { break; }
                if ($record->type !== 'snapshot_descriptor' || $record->contentHash !== $hash) { break; }
                if ($blob === $record->canonicalWire) { $keep[] = $hash; }
                $hash = $record->document()->previous_checkpoint_hash;
            }
        }
        foreach ([$ledger['descriptor'][1] ?? null, $ledger['pending']['descriptor'][1] ?? null] as $hash) {
            try { if (is_string($hash) && $blobs->get($hash) !== null) { $keep[] = $hash; } } catch (StorageException) {}
        }
        return $keep;
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
     * The signed checkpoint $head and its own ancestors the store lacks, newest first, down to the store's
     * generation. A missing ancestor within CHAIN_BLOBS of $head would leave peers anchored below it a gap
     * they treat as a fault, so it refuses (SIGNED_CHAIN_INCOMPLETE); an older one ends the walk.
     * @param array{int,string} $head
     * @param array{int,string}|null $stored
     * @return list<ValidatedRecord>
     */
    private static function chain(array $head, ?array $stored, StorageBackend $store, PublicBlobStore $blobs, RecordVerifier $verifier, string $publicKey): array
    {
        $record = self::signed(self::wire($head[1], $store, $blobs), $verifier, $publicKey, 'snapshot_descriptor', $head[1]);
        if ($record->document()->generation !== $head[0]) { throw new StorageException('SIGNED_CHAIN_UNRECOVERABLE'); }
        $chain = [$record]; $floor = $stored[0] ?? 0;
        while (count($chain) < self::MAX_ADOPTED && ($document = $record->document())->generation - 1 > $floor) {
            $parent = $document->previous_checkpoint_hash; $wire = is_string($parent) ? self::wire($parent, $store, $blobs) : null;
            if ($wire === null) {
                if ($head[0] - ($document->generation - 1) < self::CHAIN_BLOBS) { throw new StorageException('SIGNED_CHAIN_INCOMPLETE'); }
                break;
            }
            $record = self::signed($wire, $verifier, $publicKey, 'snapshot_descriptor', $parent);
            if ($record->document()->generation !== $document->generation - 1) { throw new StorageException('SIGNED_CHAIN_UNRECOVERABLE'); }
            $chain[] = $record;
        }
        return $chain;
    }

    /**
     * The newest record of a kind the key signed: the pending one when it is beyond the committed one.
     * @param array{int,string}|null $entry
     * @param array{int,string}|null $pending
     * @return array{int,string}|null
     */
    private static function head(?array $entry, ?array $pending): ?array
    {
        return $pending !== null && ($entry === null || $pending[0] > $entry[0]) ? $pending : $entry;
    }

    /** A record from the store, else its export blob, or null. */
    private static function wire(string $hash, StorageBackend $store, PublicBlobStore $blobs): ?string
    {
        try { return $store->readRecord($hash) ?? $blobs->get($hash); } catch (StorageException) { return null; }
    }

    /**
     * A pending record whose blob holds exactly what this key signed and that directly follows the store's
     * record of its kind, or null.
     * @param array{int,string} $pending
     * @param array{int,string}|null $stored
     */
    private static function successor(string $kind, array $pending, ?array $stored, PublicBlobStore $blobs, RecordVerifier $verifier, string $publicKey): ?ValidatedRecord
    {
        try { $record = self::signed($blobs->get($pending[1]), $verifier, $publicKey, $kind === 'checkpoint' ? 'snapshot_descriptor' : 'node_descriptor', $pending[1]); }
        catch (StorageException) { return null; }
        $document = $record->document();
        [$number, $previous] = $kind === 'checkpoint' ? [$document->generation, $document->previous_checkpoint_hash] : [$document->descriptor_revision, $document->previous_descriptor_hash];
        return $number === $pending[0] && $number === ($stored[0] ?? 0) + 1 && $previous === ($stored[1] ?? null) ? $record : null;
    }

    /**
     * Admits signed checkpoints (newest first; the store then points at the first) and a Node descriptor in one
     * transaction over exactly the Node rows of $state, and returns the new state.
     * @param array<array-key,mixed> $state
     * @param list<ValidatedRecord> $checkpoints
     * @return array<array-key,mixed>
     */
    private static function admit(StorageBackend $store, array $state, array $checkpoints, ?ValidatedRecord $descriptor): array
    {
        $store->transaction(static function (StorageTransaction $tx) use ($state, $checkpoints, $descriptor): void {
            if ($tx->state()['tables']['node'] !== $state['tables']['node']) { throw new ProtocolException('EVIDENCE_CHANGED_RETRY'); }
            foreach (array_reverse($checkpoints) as $checkpoint) { $tx->putRecord($checkpoint); }
            if ($checkpoints !== []) {
                // service_index_version 1: the derived indexes still describe the older store, so the next run re-signs and rebuilds them.
                $document = $checkpoints[0]->document();
                $tx->set('node', 'published-snapshot', ['hash' => $checkpoints[0]->contentHash, 'root_hash' => $document->root_hash, 'generation' => $document->generation, 'cursor' => $document->cursor, 'service_index_version' => 1]);
            }
            if ($descriptor !== null) { $tx->putRecord($descriptor); $tx->set('node', 'descriptor', ['hash' => $descriptor->contentHash]); }
        });
        return $store->snapshot();
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
     * renamed over the ledger, then the directory flushed. Pending entries are written only while set.
     * @param array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null, pending: array{checkpoint: array{int,string}|null, descriptor: array{int,string}|null}} $ledger
     */
    private function write(string $publicKey, array $ledger): void
    {
        $parent = dirname($this->path);
        if (str_contains($this->path, '://') || is_link($parent) || !is_dir($parent) || (fileperms($parent) & 0022) !== 0 || is_link($this->path) || (file_exists($this->path) && !is_file($this->path))) { throw new StorageException('SIGNING_LEDGER_UNAVAILABLE'); }
        $entry = static fn (?array $value, string $field): ?array => $value === null ? null : [$field => $value[0], 'hash' => $value[1]];
        $fields = ['ledger_version' => 1, 'node_identity_public_key' => $publicKey, 'checkpoint' => $entry($ledger['checkpoint'], 'generation'), 'descriptor' => $entry($ledger['descriptor'], 'revision')];
        if ($ledger['pending']['checkpoint'] !== null) { $fields['pending_checkpoint'] = $entry($ledger['pending']['checkpoint'], 'generation'); }
        if ($ledger['pending']['descriptor'] !== null) { $fields['pending_descriptor'] = $entry($ledger['pending']['descriptor'], 'revision'); }
        $bytes = StorageJson::encode($fields) . "\n";
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
