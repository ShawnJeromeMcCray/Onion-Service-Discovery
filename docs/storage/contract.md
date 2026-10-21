# Draft storage contract

Explicitly select `filesystem` or `sqlite`; a missing selected driver fails.
FilesystemStore does not use PDO. Both backends admit only `ValidatedRecord`
objects. Evidence records are immutable, indexed by domain-separated content
hash, and sequenced exactly once. Duplicates are idempotent. Operational rows
are mutable and have a finite table allowlist, global row budget and per-value
budget. Records are removed only by bounded retention (`LocalMaintenance`,
`node:maintain`), only superseded checkpoints, and only in storage schema 2:
the removed record's sequence is retired, never reused, and
`count(records) + pruned_records = last_sequence` keeps the sequence check exact.

A trusted transaction callback updates evidence and operational state together.
Callbacks cannot perform transport, external side effects or nested backend
transactions. Local direct observations require the separate direct-verifier
workflow; importing records never creates them. A callback exception before
the commit point leaves the prior state intact. An exception after a durable
commit means the caller must read back state before deciding whether to retry.

FilesystemStore requires a private local directory and working advisory locks,
atomic same-filesystem rename and file/directory fsync. NFS, SMB and object
storage are unsupported. Immutable content objects precede the checksummed
checkpoint; PREPARE precedes the atomic HEAD replacement. In format 2 the
durable journal COMMIT before HEAD publication is the commit point (format 1:
HEAD replacement), see below. Recovery completes or aborts an unfinished journal entry
according to HEAD. A trailing incomplete journal line may be discarded only
after consistency checks; malformed complete entries fail closed. Checkpoint
cleanup preserves the current and previous checkpoints. The object of a removed
record is deleted only after HEAD names the checkpoint without it, so a crash
leaves at most an unreferenced object; a reader of the previous state treats it
as absent. Format 3 is format 2 that may hold storage schema 2. Journal compaction
preserves the evidence index and sequence high-water mark.

SQLiteStore uses normalized evidence/operational tables, indexed Service/onion
fields, WAL, synchronous FULL, foreign keys and bounded lock waiting. Schema
version 2 adds the `pruned_records` metadata row; a removed record and its
immutable object are deleted in the same transaction. Evidence,
state and cursor share one SQL transaction. Never copy a live database file
without its WAL or use it as a backup. PrivateBackup provides staged complete application-state restore; authenticated
backup custody and actual deployed restore drills remain gates.

Default budgets: 10,000 records, 30,000 total operational rows, 256 new records
and 1,024 operational mutations per transaction, 256 MiB logical stored bytes,
32 MiB state checkpoint, 16 MiB journal, 8 KiB operational value and two-second
lock wait. Logical quotas exclude filesystem block overhead. `node:status` and
`doctor` report their use (`storage_usage`, WARN from 80 %).

Steady state with bounded retention: a Node keeps at most 256 own checkpoints
plus, per peer, its anchor, its pending checkpoint and four recent current ones
(at most 6 x 256 = 1,536 records at the 256-peer maximum, about 512 for peers
in step), and at most one witness row per kept peer checkpoint. What remains
grows only with evidence: service manifests, rotations, migrations, direct
observations and Node descriptors are history and are never pruned, so the
10,000-record budget is a limit on the number of service revisions and
observations a Node can hold (roughly 7,900 with the maximum peer count). The
state stays below its 32 MiB limit up to that budget except when nearly every
record arrived from eight peers: a record entry takes about 210 bytes, its
remote-provenance row with eight sources about 2,570 and its source list about
610, so such a state reaches 32 MiB at about 9,700 records. Both limits show
in `storage_usage`; at either one admission stops with `STORAGE_BUDGET_EXHAUSTED`
while serving continues. Actual disk-full
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
encoding and storage schema versions 1 and 2 are implementation details subject to
explicit migration; signed record bytes are unchanged. A private backup carries
`pruned_records`; a restore retires the same sequences, so every record keeps
its sequence on either backend.

Evidence: shared backend contract tests, concurrent-reader checks, quota
rollback, archive migration and eight abrupt-process-exit fault points. These
tests do not establish physical power-loss behavior or human review approval.


The private backup differs from public interchange: it restores owner-trusted
local observations, quarantine, source cursors/high-water marks and operational
rows. Public interchange excludes never-published Service identities, can rebuild
local admission sequences and restores no operational authority. Empty staging,
whole-archive signature/hash/shape checks and explicit trust are mandatory.
Read-only backend mode rejects transactions, creates no database and performs no
filesystem journal repair. HTTP and doctor use it; explicit storage:check can
recover a pending journal. Filesystem format 2 commits in the journal before HEAD publication. A lagging
HEAD can be completed only for its exactly linked committed successor. PREPARE
alone never becomes current. Format 1 is read/migrated using its historical
semantics and is refused for new writes until explicit storage:migrate.
