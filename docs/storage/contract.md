# Draft storage contract

Explicitly select `filesystem` or `sqlite`; a missing selected driver fails.
FilesystemStore does not use PDO. Both backends admit only `ValidatedRecord`
objects. Evidence records are immutable, indexed by domain-separated content
hash, and sequenced exactly once. Duplicates are idempotent. Operational rows
are mutable and have a finite table allowlist, global row budget and per-value
budget. Records cannot be removed through this API.

A trusted transaction callback updates evidence and operational state together.
Callbacks cannot perform transport, external side effects or nested backend
transactions. Local direct observations require the separate direct-verifier
workflow; importing records never creates them. A callback exception before
the commit point leaves the prior state intact. An exception after a durable
commit means the caller must read back state before deciding whether to retry.

FilesystemStore requires a private local directory and working advisory locks,
atomic same-filesystem rename and file/directory fsync. NFS, SMB and object
storage are unsupported. Immutable content objects precede the checksummed
checkpoint; PREPARE precedes the atomic HEAD replacement. HEAD replacement is
the commit point. Recovery completes or aborts an unfinished journal entry
according to HEAD. A trailing incomplete journal line may be discarded only
after consistency checks; malformed complete entries fail closed. Checkpoint
cleanup preserves the current and previous checkpoints. Journal compaction
preserves the evidence index and sequence high-water mark.

SQLiteStore uses normalized evidence/operational tables, indexed Service/onion
fields, WAL, synchronous FULL, foreign keys and bounded lock waiting. Evidence,
state and cursor share one SQL transaction. Never copy a live database file
without its WAL or use it as a backup. Native operational backup/restore tooling
remains an implementation obligation.

Default budgets: 10,000 records, 10,000 total operational rows, 256 new records
and 1,024 operational mutations per transaction, 256 MiB logical stored bytes,
32 MiB state checkpoint, 16 MiB journal, 8 KiB operational value and two-second
lock wait. Logical quotas exclude filesystem block overhead. Actual disk-full
and physical power-loss tests remain open.

`storage:export` writes a versioned NDJSON public-evidence archive with a
SHA-256 footer, preserving admission order. `storage:import` verifies the full
archive and all record signatures before any admission into an empty staging
backend. It imports in bounded transactions and never changes an active store
path. If an admission fails, discard or inspect the staging store; it must not
be activated. Local observations, cursors, private keys and configuration are
excluded, so this archive is not a complete operational backup. Imported
records retain forks and permit identity replay across backends.

The internal `StorageJson` encoding is not a signed protocol format. It uses
sorted ASCII keys, integer-only values, and encodes empty maps as arrays. This
encoding and storage schema version 1 are implementation details subject to
explicit migration; signed record bytes are unchanged.

Evidence: shared backend contract tests, concurrent-reader checks, quota
rollback, archive migration and eight abrupt-process-exit fault points. These
tests do not establish physical power-loss behavior or human review approval.
