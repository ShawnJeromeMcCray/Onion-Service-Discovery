# 17 - Canonical Data Model Storage Invariants and Retention Schema

Source: https://docs.google.com/document/d/1ACK4gDp3EB-f8wROvB5tspAzdlEyk4mwF6HcuNHX5dQ/edit?usp=drivesdk

Source modified: 2026-10-12T18:17:13.397Z

---

ONION SERVICE DISCOVERY
CANONICAL DATA MODEL, STORAGE INVARIANTS AND RETENTION SCHEMA


1. Purpose
Define the logical persistent model independently of any database or storage engine. FilesystemStore and SQLiteStore are required reference backends; PostgreSQL/MySQL/MariaDB adapters may also be supported. The model preserves identity history and conflicts rather than overwriting them.


2. Core Rule
Persistent storage is an evidence store plus derived current view.
Immutable evidence and derived status are distinct.
Never implement identity history as one mutable “services” row containing only the latest values.


3. Entities
service_identity
service_revision
service_manifest
service_key_rotation
onion_migration
direct_observation
peer_assertion
node_identity
node_descriptor
peer_relationship
peer_sync_state
candidate
candidate_source
quarantine_event
moderation_source
moderation_signal
snapshot
snapshot_chunk
schema_migration
maintenance_state


4. service_identity
Fields:
id internal stable DB id
service_public_key canonical encoded or raw bytes
created_at_local
first_seen_at_local
current_key_state
current_onion nullable/derived
status derived/cache
created_by evidence source


Uniqueness:
Service public key unique for one application identity epoch/continuity model.
If rotations are modeled as continuity across keys, use a stable logical identity id plus key epochs.


5. Recommended Identity/Key-Epoch Model
service_identities:
logical_service_id UUID/internal integer
genesis_public_key
first_seen
status


service_identity_keys:
logical_service_id
public_key
key_epoch integer
valid_from_revision
valid_to_revision nullable
rotation_record_hash nullable


This allows K1 -> K2 continuity without pretending K2 equals K1 cryptographically.


6. service_revision
Fields:
logical_service_id
revision
manifest_hash
service_key_epoch
onion
previous_manifest_hash
published_at protocol timestamp if present
first_seen_local
current_state enum
raw canonical manifest reference/content


Unique:
(logical_service_id, revision, manifest_hash)
Additionally enforce conflict detection:
same logical identity + revision can have multiple hashes only as explicit fork evidence, never as one overwritten row.


7. service_manifest
Option A:
store canonical JSON bytes/content-addressed by manifest_hash.
Fields:
manifest_hash primary key
canonical_payload
signature
wire_document optional bounded
parsed protocol version


If raw wire document is retained, cap and classify it.
Canonical payload is sufficient for protocol evidence if signature and required envelope fields are preserved according to spec.


8. Current View
Create derived query/view or materialized status table:
service_current_view
logical_service_id
selected_current_revision nullable
selected_manifest_hash nullable
current_onion nullable
identity_state
liveness_state
discoverability_state
last_direct_observation
last_peer_report
fork flag
migration state


Rules selecting current view are deterministic application logic, not SQL “latest timestamp wins”.


9. Fork Evidence
forks:
logical_service_id
revision
hash_a
hash_b
detected_at
sources
resolution_state


Never delete branch merely after selecting one for display.
Resolution, if future protocol permits, needs explicit evidence.


10. Key Rotation
service_key_rotations:
record_hash
logical_service_id
old_key
new_key
old_epoch
new_epoch
effective_revision
previous_state_hash
old_signature
new_confirmation optional
validation_state
first_seen


Unique record hash.
Conflicting rotations preserved.


11. Onion Migration
onion_migrations:
record_hash
logical_service_id
from_onion
to_onion
effective_revision
service_key
signature
old_endpoint_confirmation_state
new_endpoint_confirmation_state
validation_state
first_seen


Migration history is a chain/graph evidence, not a mutable address field.


12. Onion Address History
service_onion_bindings:
logical_service_id
onion
from_revision
to_revision nullable
binding_evidence_hash
state
first_seen
last_directly_seen


Supports historical display and migration.


13. Direct Observation
direct_observations:
observation_hash/id
observer_node_id
service logical id or onion candidate
onion
manifest_hash
service_public_key
revision
result_class
observed_at_claimed
record_signature
received_at_local


For local node’s own observation, distinguish local-direct from imported signed observation.


14. Imported Node Observation
An observation signed by another Node remains source=node X.
Do not relabel as local observation.


15. Peer Assertion
peer_assertions:
source_node_id
assertion type
subject
record hash/reference
claimed time
received time
expiry/retention
validation status


Shorter retention than identity chain.


16. Node Identity
nodes:
node_identity_key
first_seen
state
pinned flag
source provenance


node_descriptors:
node key
descriptor revision
descriptor hash
node onion
capabilities
software info
signature
first/last seen
validation state


Conflicting descriptor same revision = node equivocation evidence.


17. Peer Relationship
peer_relationships:
node key
discovery source
configured/pinned/learned
priority class
state
failure count
next retry
last success
last failure class


Do not store “trust_score” as opaque global truth.


18. Peer Sync State
peer_sync_state:
node key
protocol version
last state/root
cursor
last successful sync
last attempt
backoff
bytes last run
records last run
failure class


Cursor update only after successful commit.


19. Candidate
candidates:
canonical onion
state
first received
last updated
next attempt
attempt count
priority
last error class
last direct attempt


One logical current Candidate row per Onion plus separate source rows.


20. Candidate Source
candidate_sources:
candidate onion
source type
source node/service/hash
first seen
last seen
bounded count
No unlimited duplicate source rows.


21. Quarantine
quarantine_events:
event id
record type
subject hash/onion
reason code
source
first seen
last seen
count
bounded evidence reference
review state


Raw attacker payload optional and bounded separately.


22. Moderation
moderation_sources:
source public key/id
selected/default status local
descriptor metadata


moderation_signals:
source
subject service identity/onion
category
issued time
expires
record hash
signature
validation
No columns that overwrite identity validity.


23. Snapshot
snapshots:
snapshot id
node identity
root hash
generation
record count
created at
signature
status


snapshot_chunks:
snapshot id
chunk ordinal or deterministic key range
content hash
byte size
record count
local cache path/blob ref
verified boolean


24. Change Log
For delta sync reference implementation, maintain append-only logical change sequence:
changes:
sequence integer local monotonic
change type
record type
record hash
subject id
created_at_local


Sequence is node-local sync mechanism, not global identity truth.


25. Stable Cursor
Cursor may encode/sign or opaque-map to change sequence.
Cursor expiry when underlying retained delta window pruned.
Snapshot recovers.


26. Schema Migrations
schema_migrations:
version
name
applied_at
checksum of migration file
application version


A changed migration file after release is forbidden; add a new migration.


27. Maintenance State
maintenance_state:
job name
last start
last finish
last success
lease/lock info
cursor/progress bounded
No secret material.


28. Referential Integrity
Every backend MUST enforce equivalent referential/invariant semantics. SQLiteStore enables foreign_keys; SQL-free FilesystemStore enforces the same invariants in the StorageBackend implementation and conformance suite.
Use explicit cascade only for rebuildable child cache.
Do not cascade-delete cryptographic evidence from mutable current-view deletion.


29. Transactions
Required for:
manifest + revision + current view update
rotation + key epoch + first new manifest
migration + address binding
sync batch + cursor
schema migration


30. Upsert Rule
UPSERT acceptable only where conflict target and semantics are fully safe, e.g. updating last_seen for same immutable hash.
Never INSERT OR REPLACE identity/revision evidence because replacement can delete/confuse linked evidence.


31. Immutable Content
Manifest canonical content keyed by hash is immutable.
If same hash maps to different bytes:
fatal storage/integrity condition.


32. Hash Verification on Read
Security-critical stored canonical blobs SHOULD be hash-verified when imported/restore checked and optionally on diagnostic integrity scan.
Not necessary every ordinary display if DB integrity and immutable API layer preserve performance.


33. Timestamps
Separate:
protocol claimed time
local received time
local directly observed time
Do not conflate.


All local timestamps UTC.
DB stores canonical text or integer milliseconds only if exact semantics documented; JS-interoperable signed fields remain protocol format.


34. Status Derivation
Identity state:
VALID
FORKED
IDENTITY_CHANGED
QUARANTINED
MIGRATED etc.


Liveness state:
RECENT
TEMP_UNREACHABLE
STALE
LONG_UNSEEN


Discoverability:
PUBLISHED
WITHDRAWN
UNKNOWN


Keep axes separate.


35. Withdrawal
publish_service=false:
record verified as new revision.
Current discoverability state becomes WITHDRAWN.
History retained.
Default search view excludes withdrawn after policy.
No global deletion claim.


36. Manifest Disappearance
404/unreachable is liveness evidence, not cryptographic withdrawal.
Do not infer publish=false.


37. Data Retention Tiers
Tier 1 continuity evidence:
service identities, key epochs, accepted revisions, rotation/migration/fork evidence.
Long-lived.


Tier 2 operational evidence:
direct observations.
Configurable months/compact history.


Tier 3 transient:
peer assertions, failed candidates, stale peer health.
Shorter finite retention.


Tier 4 cache:
snapshot chunks, derived search index.
Rebuildable.


38. Storage Size Budget
Operator config:
soft warning bytes
hard operational budget
cache budget
quarantine budget
transient-history budget


At soft threshold:
cleanup rebuildable tiers.
At hard threshold:
pause discovery/admissions before deleting Tier 1 evidence.


39. Vacuum
VACUUM scheduling separate maintenance.
Must account disk temporary space.
Not run on request path.


40. WAL
If WAL enabled:
document backup semantics
checkpoint strategy
file permissions
restore all relevant files safely.
Test crash/recovery.


41. Backup
Use the selected backend's documented consistent backup/checkpoint method. FilesystemStore uses an atomic checkpoint/barrier; SQLiteStore uses a SQLite-safe backup method; server databases use their supported consistent backup mechanisms.
Never copy live mutable storage naively when it can produce an inconsistent snapshot; use the selected backend's tested consistency mechanism.


42. Integrity Check
Maintenance/restore:
PRAGMA integrity_check or appropriate verified mechanism.
Failure => DEGRADED/READ_ONLY; do not silently rebuild identity history.


43. Corruption Recovery
If cache tables corrupt but continuity DB valid, rebuild cache.
If continuity evidence corrupt:
restore backup or re-sync/re-verify with explicit loss warning.
Never silently fabricate chain.


44. Indexes
At minimum evaluate indexes:
manifest_hash
public key
logical_service_id + revision
current onion
candidate state + next_attempt
peer state + next_retry
change sequence
moderation subject
last seen


Benchmark query plans.


45. Full-Text Search
Server database does not need public text search.
Client local index handles search.
If node has admin diagnostics search, separate from public API.


46. Optional Browser Persistence Mapping
Client stores a subset/derived model:
services current
identity history required for pin/migration
node sources
sync state
pins
moderation signals
settings


Client need not store full peer-assertion history.


47. Client Cache Rebuild
Public index stores are rebuildable.
Pins/settings private stores separated so rebuild cache cannot delete them.


48. DB Serialization Boundary
Repositories accept typed Domain objects.
No raw SQL assembly from network strings.
Prepared statements.
Column constraints.


49. Raw JSON
Do not use raw JSON blob as sole database model.
Persist key searchable/invariant fields plus canonical content hash/blob.


50. Schema Ownership
One migration layer only.
No two independent migration systems may mutate the same storage schema/backend state concurrently.


51. Read-Only Mode
If migration/integrity issue:
public API can serve last known verified state in READ_ONLY when safe.
Disable writes/sync/discovery.
Health reflects.


52. Multi-Process Locking
PHP-FPM + CLI jobs may access the selected storage backend only through its concurrency/locking contract.
Test lock contention.
Keep write transactions short.
Scheduler lock prevents duplicate long sync job.


53. Atomic Manifest File Write
For locally published manifest:
write temp file
fsync/close as practical
atomic rename on same filesystem
permissions
avoid partially served document.


54. Key/File-DB Consistency
Startup self-check:
configured Onion
Service key public key
latest locally generated manifest
DB continuity
must agree.
Mismatch => fail safe and operator error.


55. Migration Dry Run
Before destructive/large StorageBackend/schema migration:
backup
preflight disk
version check
atomic transaction/checkpoint according to selected backend
post integrity.


56. Data Model Diagram
Agent MUST create ER diagram and lifecycle sequence diagrams.
Each relationship annotated with:
immutable/mutable
retention tier
source/trust class.


57. Data Dictionary
Maintain docs/data/data-dictionary.md:
table
column
type
nullable
source
security meaning
retention
index
PII/privacy classification.


58. Test Requirements
constraint tests
fork persistence
rotation atomicity
migration atomicity
sync rollback
cursor correctness
candidate dedup
quarantine budget
disk full
lock contention
backup/restore
schema upgrade
current-view derivation properties.


59. Acceptance
Data model passes when:
identity evidence cannot be erased by routine current-state update;
forks/conflicts persist;
rotations/migrations are atomic;
transient abuse cannot force deletion of continuity evidence;
restore reproduces same current view and hashes;
PHP and client derived models agree on Protoco


67. LOGICAL SERVICE IDENTIFIER INVARIANT
The backend-local row identifier is never a Protocol identity.


All continuity-bearing entities MUST reference logical_service_id separately from the current Service Identity key.


A valid key rotation:
- keeps logical_service_id unchanged;
- changes key_epoch/current Service Identity key only after valid transition evidence.


A valid Onion migration:
- keeps logical_service_id unchanged;
- changes current Onion only after valid migration evidence.


68. DERIVED STATE AUTHORITY
Immutable validated Protocol evidence is authoritative.
Derived current views, search indexes and backend-specific indexes are rebuildable materializations.


A backend import MUST validate/recompute derived Protocol-relevant state rather than trusting exported derived rows blindly.


69. PUBLICATION VS DISCOVERABILITY
Do not overload one state variable.


PublicationState describes Service-signed intent:
PUBLISHED | WITHDRAWN.


DiscoverabilityEvidence describes sampled external evidence:
UNKNOWN | EXTERNALLY_OBSERVED | INDEX_INCLUDED | MULTI_SOURCE_INCLUDED | DEGRADED.


Liveness remains orthogonal.
l v1 states.
