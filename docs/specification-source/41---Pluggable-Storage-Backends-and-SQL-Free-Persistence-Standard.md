# 41 - Pluggable Storage Backends and SQL-Free Persistence Standard

Source: https://docs.google.com/document/d/1tOvXkoRp3bXi95xOi2PqLGIXatdhLXVmDDRN2jThIXY/edit?usp=drivesdk

Source modified: 2026-10-11T07:36:28.762Z

---

ONION SERVICE DISCOVERY
PLUGGABLE STORAGE BACKENDS AND SQL-FREE PERSISTENCE STANDARD


Status
Binding architecture standard for reference implementations.


1. Core Decision
Onion Service Discovery MUST NOT require a specific database product.


The Protocol is storage-backend independent.


Operators choose a storage backend appropriate to their environment.


2. Required Reference Backends
The reference implementation SHOULD provide at minimum:
- FilesystemStore — no SQL/database server required;
- SQLiteStore — simple embedded SQL backend.


Production-grade additional adapters SHOULD be supported when implemented and tested:
- PostgreSQLStore;
- MySQL/MariaDBStore.


Community adapters MAY exist for other systems, but they do not alter Protocol semantics.


3. No Hidden Database Dependency
Core Protocol logic MUST NOT import PDO, SQLite, PostgreSQL or MySQL-specific APIs directly.


All persistence goes through a stable StorageBackend contract.


4. StorageBackend Contract
The abstraction MUST expose behavior, not SQL concepts.


Required semantic operations include:
- store immutable signed record by content hash;
- read immutable record by content hash;
- atomically update current derived state for an identity;
- append/read ordered local change events;
- store/read peer, candidate, observation and moderation state;
- bounded iteration/pagination;
- atomic batch/transaction boundary;
- consistency/integrity check;
- backup/export;
- schema/storage-version metadata;
- migration capability;
- writer coordination.


5. Atomicity Contract
Every backend MUST provide equivalent application semantics for:
- record admission;
- revision advancement;
- fork preservation;
- rotation/migration state transition;
- sync cursor advancement.


A sync cursor MUST NOT advance unless the corresponding state mutation is durably committed.


6. FilesystemStore Purpose
FilesystemStore exists for:
- zero-SQL deployment;
- minimal hosting environments;
- small publish/mirror deployments;
- development/test;
- operators who deliberately do not want SQL.


It MUST NOT be a loose collection of ad-hoc JSON files.


7. FilesystemStore Layout
Recommended deterministic layout:


data/
  storage-version
  objects/
    sha256/
      ab/
        <full-hash>.json
  identities/
    <service-identity-fingerprint>/
      current.json
      history.jsonl
      state.json
  nodes/
  observations/
  peers/
  candidates/
  moderation/
  changes/
    segment-000001.log
  snapshots/
  locks/
  tmp/


Exact layout is implementation detail, but the safety properties below are normative.


8. Content-Addressed Immutable Objects
Signed Protocol records are stored once under their content hash.


Rules:
- immutable after successful write;
- hash verified before acceptance;
- existing hash with different bytes = integrity failure;
- atomic temporary-file -> fsync -> rename pattern where supported.


9. Derived Mutable State
Mutable current views are derived from immutable evidence.


Examples:
- current Service revision;
- selected Onion binding;
- liveness;
- candidate status;
- peer health.


Derived state can be rebuilt from durable evidence where practical.


10. Filesystem Atomic Writes
For single-file state updates:
- write to temporary file in same filesystem;
- flush;
- fsync where supported;
- atomic rename;
- fsync parent directory where platform permits.


Never overwrite critical state in-place without atomic replacement.


11. Filesystem Journal
Multi-record state transitions use an append-only local journal/change log.


A transaction-like operation:
1. validate all input;
2. acquire writer lock;
3. write immutable objects;
4. append PREPARE record;
5. atomically update derived state;
6. append COMMIT record;
7. release lock.


Recovery:
incomplete PREPARE without COMMIT is detected and either rolled back/rebuilt according to documented recovery rules.


12. Writer Model
FilesystemStore baseline:
single logical writer.


Multiple PHP-FPM workers coordinate through a process-safe lock.


Readers may operate concurrently against immutable objects/current snapshots.


13. Locking
Reference implementation may use flock on supported local filesystems.


Important:
network/distributed filesystems have differing lock/rename semantics.


FilesystemStore support is limited to tested local filesystem semantics unless a specific remote filesystem is explicitly certified.


14. Filesystem Integrity
doctor/db-or-storage-verify checks:
- storage version;
- object hash paths;
- object bytes vs hash;
- JSON/schema validation where applicable;
- journal sequence;
- committed transaction consistency;
- current-view references;
- orphan temp files;
- invalid duplicate state;
- disk permissions.


15. Filesystem Compaction
Journal/history compaction MAY reduce rebuild cost.


Compaction MUST:
- preserve immutable evidence required by retention policy;
- write a new checkpoint atomically;
- never silently discard fork/continuity evidence;
- keep a verifiable checkpoint hash.


16. Filesystem Backup
A consistent backup is created under a storage read/write barrier or checkpoint boundary.


Backup includes:
- storage-version;
- immutable objects required by policy;
- derived/current state or rebuild metadata;
- journals/checkpoints;
- config metadata excluding secrets unless operator explicitly chooses protected secret backup.


17. Filesystem Scale Limit
FilesystemStore MUST publish a tested supported scale.


Do not claim it is appropriate for arbitrarily large public mirror Nodes.


If benchmark thresholds are exceeded:
doctor warns;
documentation recommends SQLite/PostgreSQL/MySQL depending workload.


18. SQLiteStore
SQLite remains a convenient reference option, not a requirement.


Runtime requirement applies only when operator selects SQLiteStore:
- PDO;
- pdo_sqlite;
- supported SQLite capability set.


19. PostgreSQLStore
When implemented:
- use prepared statements;
- explicit transactions;
- compatible uniqueness/invariant constraints;
- migration tests;
- connection/pool bounds;
- no Protocol-semantic differences from other stores.


20. MySQL/MariaDBStore
When implemented:
- InnoDB/transactional semantics required;
- strict SQL mode;
- explicit utf8mb4 where textual operational fields exist;
- prepared statements;
- migration/invariant tests;
- no reliance on silent coercion.


21. Backend Selection
Configuration example concept:


storage:
  backend: filesystem | sqlite | postgresql | mysql


Backend-specific parameters are isolated under that backend namespace.


22. Default Selection
Installer MAY recommend a backend based on environment.


It MUST NOT silently switch backend after initial installation.


The selected backend is recorded explicitly.


23. Publish-Only Mode
Publish-only Service operators may not need the full Node index database.


A minimal publish-only installation MAY:
- keep Service Identity key outside webroot;
- keep local Manifest source/config;
- generate signed Manifest;
- serve static Well-Known Manifest;
without initializing a mirror/discovery database.


24. Migration Between Backends
Provide explicit:
storage:export
storage:import
storage:migrate-backend


Migration uses a backend-neutral canonical export format.


25. Backend-Neutral Export
Export contains:
- immutable Protocol records;
- derived-state version;
- peer/candidate/observation operational records as selected;
- cursor/change state;
- metadata and hashes.


It MUST NOT expose private keys by default.


26. Migration Verification
Before cutover:
- export hash;
- record counts;
- identity current states;
- fork states;
- sync cursor state;
- observation counts;
- moderation state;
are compared.


27. Backend Conformance Suite
Every storage adapter MUST pass the same black-box StorageBackend conformance tests.


Test categories:
atomicity
crash recovery
duplicate writes
concurrency
fork preservation
cursor commit ordering
backup/restore
migration
corruption handling
limit enforcement.


28. Failure Injection
Tests kill the process:
- after immutable object write;
- after PREPARE;
- during derived-state update;
- before/after COMMIT;
- during backend migration.


On restart, invariants MUST hold.


29. No Semantic Backend Drift
Given the same validated event stream and configuration, all conformant backends MUST derive the same Protocol-relevant current state.


30. Search Independence
Search behavior/ranking consumes backend-neutral read models.


No SQL-specific ranking semantics may become normative.


31. Query Safety
SQL adapters:
prepared statements only for untrusted values.


Filesystem adapter:
strict canonical path construction;
never use untrusted text as raw path;
hash/fingerprint derived filenames only;
no path traversal.


32. File Permissions
FilesystemStore:
data directory not web-accessible;
private operational state not under public document root;
least privilege;
umask documented;
secret keys stored separately.


33. Disk Exhaustion
All backends:
soft/hard disk thresholds;
bounded queues;
degraded/read-only mode;
no partial continuity corruption.


34. Configuration Validation
init/doctor:
- validates selected adapter availability;
- validates driver/extension only for selected backend;
- tests atomic operations;
- tests permissions;
- reports exact remediation.


35. No SQL Mode Guarantee
FilesystemStore MUST operate without:
- SQLite;
- PostgreSQL;
- MySQL/MariaDB;
- external database service;
- sqlite3 CLI.


It can still require normal PHP filesystem/JSON/crypto capabilities.


36. Optional Embedded Non-SQL Engines
Future adapters such as LMDB/RocksDB-like systems MAY be considered.


They are not baseline because they often require extra native extensions/libraries.


37. Operator UX
Installer presents:
Filesystem — zero SQL dependency, simpler small deployment, lower scale.
SQLite — simple embedded database, good general default.
PostgreSQL — stronger larger-node/concurrent operational profile.
MySQL/MariaDB — common hosted/server environment.


Exact recommendation is evidence-driven by benchmarks.


38. Storage Capability Descriptor
doctor --json exposes:
backend
backend_version
storage_schema_version
transaction_model
supported_scale_profile
integrity_state
backup_state.


No secrets.


39. Storage Versioning
Storage schema/version is separate from Protocol version.


A backend migration may occur without Protocol wire change.


40. Backup Portability
Canonical export format is the long-term portability layer.


Backend-native backup remains useful for fast restore but is not the only escape path.


41. Removal of SQLite Global Requirement
Any existing requirement stating that Onion Service Discovery as a whole requires SQLite is superseded.


Correct statement:
SQLite is one supported reference storage backend.


42. Acceptance
Storage architecture PASS requires:
- backend-neutral persistence contract;
- FilesystemStore implemented and crash-tested;
- SQLiteStore implemented and contract-tested;
- adapter availability checked only when selected;
- canonical export/import;
- backend migration test;
- no Protocol semantics tied to SQL;
- no hidden external database dependency in FilesystemStore.
