# Operator CLI contract

Entry point: `php node-php/bin/onion-service-discovery help`; `help COMMAND` shows
that command's syntax and whether it makes Tor requests, writes local state,
saves status evidence or reads a private key. All output is JSON;
`--json` and `--no-interaction` are accepted. No command waits for an interactive
confirmation. A failure prints `{"status":"FAIL","error":CODE}` plus `detail`
(where it happened, for example `transport.timeout_ms: must be an integer from 100
to 60000`; never the value itself), `meaning` (the plain-language entry of the
[error registry](error-codes.md)) and `help` (the page to read). Explicit commands/flags choose side effects, roles and trusted
backup handling; publication, sync, server search and announcement acceptance
default false (`init --role node|combined` enables mirroring and direct checks). Private signing
passphrases come only from owner-private files or service-manager credentials.
Do not send passphrases in command arguments or ordinary environment variables.

Read-only commands include config:validate, doctor, security:check, node:status,
release:info, storage:verify, candidate:list, quarantine:list and sync:status.
Doctor checks the actual selected store without repair, reports pending recovery,
and never probes Tor or decrypts keys. It reports `manifest_state` and the
publication for a publishing installation, `export_usage` (blob files and bytes
against the 4,096-file and 256 MiB export limits; WARN from 80 %) and
`scheduler_state` derived from stored times: a published checkpoint older than
two days (`SNAPSHOT_TIMER_STALE`) or publisher checks older than 36 hours
(`PUBLISHER_TIMER_STALE`) mean the timer stopped. For a Node it reports
`node_health`, the storage state its status page shows (`HEALTHY`, `READ_ONLY`,
`DEGRADED`, `ERROR` or `NOT_INITIALIZED`), with a `next_action` when the store
needs attention, and warns `PUBLISHED_STATE_STALE` when the export directory's
copy of the published state, which [degraded mode](deployment-and-recovery.md#degraded-mode)
serves, does not match the store. Unsupported/unobserved checks
are explicitly marked: Tor connectivity, backup freshness, deployed headers and
independent security/release review are not inferred from local configuration.
`security:check` adds focused findings, each with a remediation: configuration
file and directory modes, Tor secret keys next to OSD files, unexpected files in
`node-php/public`, packaged files changed since packaging (against
`release/FILE-MANIFEST.json` when present) and the absence of remote
administration. Both reports follow the
[health report schema](health-report.schema.json) (spec 32 section 10); every
field is listed there, so monitoring can validate them and no private value can
appear unreviewed.

`status CONFIG` (alias of `node:status`) reports the role, the own Onion and the
roles in use: for a publisher the publication state, the Logical Service
Identifier, current key, its fingerprint and key epoch, and the next action; for a Node
its store counts plus identity, snapshot generation and last direct check and
sync. While the store cannot be read it fails with the store's error code (exit
4) and adds the Node card and a `next_action` from the
[incident runbook](incident-runbook.md#4-store-corruption-or-an-interrupted-write).
With a readable store, `status CONFIG` and `doctor CONFIG` also report
`signing_ledger`, the Node key's signing ledger compared with the store:
`CONSISTENT`, `BEHIND_STORE` or `NOT_PRESENT` (the next signing run raises or
writes it), `AHEAD_OF_STORE` (the store is older than what the key signed: `doctor`
fails with `SIGNING_LEDGER_AHEAD_OF_STORE`, `status` exits 6, and `next_action`
names `--adopt-signed-chain`), `INVALID` (signing stops), `NOT_CHECKED` or
`NOT_APPLICABLE`; see [signed chain](#signed-chain-after-a-restore).
`status CONFIG --check` prints the full status page facts and exits 0 when
nothing needs attention, 6 while waiting or degraded, 4 on an error or conflict
and while the Node's store cannot be read. Use separate
transport/browser tests and operator acceptance to establish those properties.
The ordinary data/status web routes open a read-only store and do not repair
journals; while it cannot be read they serve the last published snapshot
([degraded mode](deployment-and-recovery.md#degraded-mode), D-045). The
default-off experimental announcement route alone opens the selected writable
store for bounded queue admission, and only after a read-only opening
succeeded, so a damaged store is never recovered by a web request; it has no
signer or outbound transport capability. Filesystem format 2 uses the journal COMMIT as durable authority before HEAD.
Read-only opening refuses unresolved recovery rather than repairing it.

`storage:check` is a separate explicit integrity/recovery command; it completes
an interrupted write. On a filesystem store it also deletes object files that no
checkpoint file names any more (left by a crash during a `node:maintain` prune)
and reports them as `integrity.orphaned_objects_removed`. `storage:backup`,
`storage:export` and `storage:migrate-backend` start again (three attempts) when
`node:maintain` prunes a record they were copying, then fail with
`EVIDENCE_CHANGED_RETRY`; at the store's byte budget a pruning transaction still
commits, and `storage_usage` in `doctor` and `node:status` includes
`store_bytes` and `max_store_bytes` (WARN from 80 %). `storage:verify BACKEND PATH` (also `db:verify` and
`restore:verify`) opens the store read-only and never repairs, migrates or
changes the SQLite journal mode: a pending recovery is reported as
`STORAGE_RECOVERY_REQUIRED` with exit 6.
`storage:backup`/`backup:create` captures trusted private records and operational
state; `backup:verify FILE` validates it without restoring. `storage:restore`
requires a fresh staging path and `--trusted-private-backup`. A Node whose
restored store is older than its last publication continues with
`node:snapshot --adopt-signed-chain` ([signed chain](#signed-chain-after-a-restore)).
`db:migrate BACKEND PATH` reports the storage schema and whether record pruning
is enabled, without mutation; there is no invented migration from an unknown
schema. Future migrations need explicit code, fixtures and review.

Aliases: status=node:status, peer:list=peers:list, peer:add=peers:add,
peer:disable=peers:disable, manifest:show=protocol:inspect,
backup:create=storage:backup, db:verify/restore:verify=storage:verify,
manifest:generate=manifest:init and sync:run=sync:peer-step.
The key:service:generate/key:node:generate commands select the corresponding
key:create role; they require the same private passphrase-file flag.
The alias retains the underlying command's argument contract. `release:info`
reports development status, the machine-readable gate matrix and the build:
`SOURCE_CHECKOUT`, or for a package the `source_commit`, profile and SHA-256 of
`release/BUILD-METADATA.json`, `SBOM.cdx.json` and `FILE-MANIFEST.json` to compare
with the GitHub build provenance. It is not a stable release assertion. Release verification is the separate mature Python TUF tool.

Manifest commands prepare genesis/revision/withdrawal/rotation/migration drafts,
then record:sign signs only an allowlisted role/domain. Rotation collects old/new
signatures and its first new-key manifest; manifest:install verifies hostname,
continuity/proofs and atomic installation. No-change revisions preserve exact
existing signed bytes. A Service key is kept offline by default. Node initialization
checks the stored descriptor identity. Snapshot/direct CLI workers also require
that initialized Node key/Onion binding, rejecting an unnoticed key replacement.
Changing a Node identity means a new explicitly pinned source; the v1 protocol
has no Service-style Node key-rotation proof. Do not simulate one by editing rows.

List commands show at most 256 entries and indicate truncation. `peers:list
CONFIG` shows every configured peer with its state, failures and next attempt.
`sync:run CONFIG` without a peer performs one bounded step for the peer that is
due first (`NOTHING_DUE` with the next due time otherwise), so one timer can serve
every peer; `sync:run CONFIG ONION` still steps one named peer.
`quarantine:list CONFIG` lists quarantined candidates and quarantined peers; a
peer row carries the hashes of the conflicting signed checkpoints kept as
evidence.
`peers:retry CONFIG ONION --acknowledge-quarantine` returns a quarantined or
disabled peer to `CANDIDATE` for the next sync step; quarantine is explicit and
source high-water marks/conflicts are not erased by retry.
`candidate:retry CONFIG ONION --acknowledge-quarantine` queues one quarantined or
backed-off Service candidate for the next direct check (state `QUEUED`, attempts
reset); its sources and all stored identity and conflict evidence are kept. It
refuses a candidate that a worker is checking right now (`CANDIDATE_BUSY`). Without
it, a candidate quarantined by a failed verification is checked again
automatically after about seven days.

Protocol tools for implementers, all offline: `protocol:inspect FILE
[--expected-onion ONION]` and `protocol:hash FILE` verify any signed record
(optionally its Onion binding) and print its type and content hash;
`protocol:canonicalize FILE` prints the RFC 8785 canonical bytes of a JSON
document as hex; `protocol:apply-sequence FILE` derives the identity state of a
`{"records": [...]}` sequence (at most 256 records) exactly as a Node would.
`node:maintain` expires bounded remote hints and checkpoints SQLite WAL.
It also removes signed checkpoints nothing needs any more, at most 256 per run
(`signed_records_removed`): the Node's own checkpoints beyond the newest 256 on
its chain (publishers and peers walk at most 32 back, D-052), and peer
checkpoints that are neither a peer's verified anchor, its pending checkpoint,
one of the four newest it showed as current nor named by conflict evidence.
Service records, Node descriptors and conflicting checkpoints are never removed,
and witness rows below a peer's anchor go too (`checkpoint_witnesses_removed`).
Run it from the maintenance timer, at least daily; with snapshots every 15
minutes, hourly keeps the store smallest.
`node:maintain` also prunes export blobs that the current published snapshot and
the derived indexes do not reference, once they are older than one hour (a
running snapshot may be writing newer ones), at most 256 per run, so the export
directory no longer fills up until snapshots fail with `EXPORT_BUDGET_EXHAUSTED`.
The blobs of checkpoints that were current within that hour stay as well: a peer
that started on one of them can finish it, as the data API serves the chunks of
an earlier checkpoint while their blobs exist.
`node:status CONFIG` and `doctor` report `storage_usage`: signed records,
removed records, state bytes and operational rows against their limits (10,000
records, 32 MiB state, 30,000 rows); `doctor` warns
`STORAGE_BUDGET_NEARLY_EXHAUSTED` from 80 %.
`exports:prune --workers-stopped` is the immediate operator-only action; it removes
at most 256 unreferenced derived blobs, not signed identity/fork records.
No command executes a downloaded software artifact.

Exit codes ([all error codes](error-codes.md)): 0 successful command; 2 usage; 3 input/config/protocol validation;
4 storage/signing failure or failed diagnostic; 5 direct transport failure;
6 diagnostic WARN/degraded;
8 normalized internal error. Transport workers can return structured BACKOFF,
QUARANTINED or PROGRESS states with a successful command exit: monitoring must
inspect the JSON status, not interpret exit 0 as a healthy current source.
Unexpected exceptions never print key material or raw traces in CLI output.

Full operator walkthrough, supported deployed runtime matrix, live Tor checks,
privilege separation, automatic authenticated backup custody and incident drills
remain release gates. The reference systemd/nginx/FPM files are staging templates;
no installer silently enables them or modifies the operator's Tor service.

`service:verify CONFIG [ONION]` (without ONION: `node.onion` of the configuration) makes two bounded fixed-path requests through the
configured Tor SOCKS transport: it checks the manifest signature and exact Onion
binding, then reports the served PCP-004 history bundle as `history_bundle`
(`CONNECTED`, `INCOMPLETE`, `MISSING`, `NOT_SERVED_GENESIS_NEEDS_NONE` or
`INVALID_<code>`). It neither opens/mutates the store nor loads a signing key. Its explicit
`NOT_DERIVED` identity state means it does not establish continuity or replace a
pin. `SIGNATURE_AND_BINDING_VALID` is not a safety/content or lasting uptime claim.
Use the normal candidate worker for stored continuity checks and observations.

An unprovisioned source package returns typed `RUNTIME_DEPENDENCIES_MISSING`
with exit 3 and no raw include warning. Use the development runtime package,
which bundles them, or install the locked Composer runtime dependencies before
invoking application commands.

`init CONFIG [ONION] --role node|combined|client-host [--sqlite] [--source ONION]... [--visitor-sources]`
writes the other roles the same way: all private paths inside the configuration's
own 0700 directory, Node roles with mirroring and direct checks on and peer sync
off, and role-specific next steps in `next_action`.

`init CONFIG ONION [--publish]` creates a publisher-only configuration in an
existing private directory, refuses overwrite, creates no keys/storage/Manifest,
and makes no network request. `publisher:status CONFIG` is read-only and uses
the public status page's fact derivation. See publish-only.md for consequences.

`storage:migrate filesystem PATH` explicitly migrates native format 1 or 2 to 3.
Stop workers and make a tested backup with the historical release before migrating.
Legacy HEAD-published transactions retain their old interpretation; format-1
stores refuse new writes until migration. Format 3 is format 2 that may also hold
storage schema 2: the count of signed records that bounded retention removed
(`pruned_records`, so the number of records plus removed ones is always the
highest sequence). `storage:migrate sqlite PATH` migrates SQLite schema
version 1 to 2 in one transaction by adding that counter. Neither rewrites a
signed record or an operational row. A store that is not migrated keeps working
but keeps every checkpoint: `node:maintain` reports `record_pruning:
MIGRATION_REQUIRED` and `doctor` warns `STORAGE_PRUNING_MIGRATION_REQUIRED`. New
stores are created in the current format. Releases before this change refuse a
migrated store with `STORAGE_VERSION_UNSUPPORTED`; keep the pre-migration backup
for a rollback.

`index:verify-inclusion CHECKPOINT CHUNKS_DIRECTORY OWN_HISTORY EXPECTED_NODE_KEY`
verifies exact current owner-state membership offline in a complete signed draft
snapshot, including genesis continuity through rotation/migration. It evaluates
neither freshness nor checkpoint ancestry. See [the contract](snapshot-membership.md).

`service:membership CONFIG OWN_HISTORY [--node NODE_ONION EXPECTED_NODE_KEY]...`
(without `--node`: the `publisher_nodes` of the configuration, otherwise
`PUBLISHER_NODES_NOT_CONFIGURED`) fetches, for each of at most 16 explicitly
selected Nodes, its current state,
signed checkpoint and every chunk through the configured Tor SOCKS transport,
then applies the same exact-state membership verifier as `index:verify-inclusion`.
OWN_HISTORY is `{"records": [...]}`, the PCP-004 history bundle that
`manifest:install` maintains (`.well-known/onion-service-discovery-history.json`
in the webroot) or a single signed record; several files may be given. Each
source is reported once as included, stale, not_present, contradictory,
unavailable or rejected; mere acknowledgements are never counted. It mutates no
store and loads no signing key. Exit 0 only when every selected Node includes the
current state in a fresh checkpoint, otherwise 6. A checkpoint is fresh for seven
days by the local clock (D-036); an included Node whose checkpoint is older, or
dated more than five minutes ahead, is reported with `checkpoint_freshness`
`OUTDATED` or `CLOCK_AHEAD`, counted in `included_with_outdated_checkpoint` and
not counted as current. Checkpoint ancestry (D-052) is compared with the
checkpoints saved at the previous `--save-status` check: rollback, equivocation
or a broken chain rejects that Node (`SOURCE_CHECKPOINT_*`) before any chunk is
downloaded; at most 32 predecessors are fetched per Node, and an unreachable
predecessor or longer gap is `NOT_EVALUATED`, never a rejection. Operator
independence stays `NOT_ESTABLISHED`. Not usable with the client-host role.
`network:discoverability-check` is the specification's name for the same command.
With `--save-status` it also writes aggregate counts, each Node's category and
the checkpoint hash and generation it served, under SHA-256 hashes of the Node
address and key (never addresses or keys)
atomically to `CONFIG_NAME.discoverability.json` beside the configuration, with
the configuration file's group and at most its read permissions, so the web
runtime that reads the configuration can also read the evidence.
`service:verify CONFIG OWN_ONION --save-status` records the self-check result
(also typed failures such as `TOR_PROXY_UNREACHABLE`, `ONION_UNREACHABLE` or
`HTTP_NOT_FOUND`, each with its own remedy on the status page; another Onion is refused with
`SELF_VERIFICATION_ONION_MISMATCH`), and `service:announce … --save-status`
records each Node's acknowledgement under a SHA-256 hash of its address, with
the announced revision, the consecutive attempts and the earliest automatic
retry. `publisher:status` and the public
`/onion-service-discovery/status/` page then report `DISCOVERABLE` only when at
least one checked Node both includes the exact installed revision and exported
its own direct observation of it, `IDENTITY_CONFLICT` when a checked Node holds a
conflicting state, and `DISCOVERABLE_DEGRADED` when that evidence is older than
seven days. The seven-day age is the owner-approved local threshold (D-036);
evidence for another revision is ignored. `network:announce` is an alias of
`service:announce`.

The status page derives one plain answer (YES, NOT YET, NO, DEGRADED or PROBLEM)
and the ten-step pipeline of specification 47 section 11 from these facts only.

## Specification command names and support commands

- `snapshot:build` = `node:snapshot`;
  `peer:remove` = `peers:disable` (the peer is deactivated, its evidence and
  high-water marks are preserved); `key:service:rotate` = `manifest:rotate`.
- `key:node:rotate` always fails with `NODE_KEY_ROTATION_UNSUPPORTED` (exit 3):
  protocol v1 has no Node key-rotation proof. Create a new Node identity, a new
  key on a new Onion with a new store, and have peers pin it explicitly; see the
  [incident runbook](incident-runbook.md#2-node-key-compromised-or-lost).
- `storage:migrate-backend FROM_BACKEND FROM_PATH TO_BACKEND NEW_STAGING_PATH`
  copies records and operational state through the private backup format into a
  fresh staging store, opens the source read-only and removes its temporary
  carrier. Stop workers, run `storage:check` on the new path, then switch the
  configuration. An existing destination is refused.
- `status CONFIG --check` runs the same read-only derivation as the status page
  (answer, ten-step pipeline, Node card) for monitoring: exit 0 when nothing
  needs attention, 6 while waiting for external evidence, when it is older than
  seven days or before `node:init`, 4 for an invalid Manifest, an identity
  conflict or an unreadable store (also while the Node serves its last published
  snapshot in degraded mode; the card's `next_action` names the next step).
  `status CONFIG` without `--check` keeps the Node counts.
- `history:verify WEBROOT` checks offline whether the served history bundle
  connects to the installed Manifest (`CONNECTED`; `MISSING`/`INCOMPLETE` exit 6).
  `history:install WEBROOT SIGNED_RECORD...` rebuilds it from the owner's signed
  files (bundles or single records) under the publisher lock; it refuses with
  `HISTORY_INCOMPLETE` unless the result connects. `manifest:install` maintains
  the bundle automatically and reports `history` ([PCP-004](../protocol/proposals/PCP-004-continuity-bundle-draft.md)).
- `node:identity CONFIG` prints the verified Node Onion and Node public key from
  the signed descriptor, its `node_key_fingerprint` (D-025), and ready-to-use
  lines for `--node`, `peers:add` and visitors. Node and combined roles only; it fails with `NODE_NOT_INITIALIZED`
  before `node:init`.
- `manifest:rotate … --output-dir DIR` and `manifest:migrate … --output-dir DIR`
  write `rotation-draft.json`/`migration-draft.json` and `successor-draft.json`
  as owner-private files (never overwriting) instead of one combined JSON object.
- `network:discoverability-check` accepts several history files before the first
  `--node`: each is a `{"records": [...]}` file, the served PCP-004 history bundle
  or a single signed record.
- `diagnostics:export CONFIG` prints a privacy-reviewed support bundle: versions,
  extensions, role/features and `doctor` results. Paths, Onion addresses, keys,
  passphrases, sources, pins and queries are excluded and additionally redacted.
  Review the output before attaching it to a report.

`peers:group CONFIG ONION GROUP|--clear` records local source-family policy.
Diagnostics expose concentration and unknown/inconsistent grouping without
inferring independent operators or trust from key count. See [source groups](source-groups.md).

## Untrusted announcement: explicit and bounded automatic

`service:announce CONFIG SIGNED_MANIFEST_FILE NODE_ONION` requires explicit
local publication and a signed, opted-in manifest bound to the configured
Service Onion. It makes one fixed-path Tor POST with fresh SOCKS isolation; it
reads no signing key and mutates no local store. The exact response is
`HINT_RECEIVED_UNCONFIRMED`; both direct observation and membership remain
`NOT_CONFIRMED`. Redirect, malformed acknowledgement and bare inclusion
assertions fail; HTTP 429 fails as `ANNOUNCEMENT_RATE_LIMITED`.

`service:announce CONFIG SIGNED_MANIFEST_FILE [--node ONION KEY]...`
takes the same Node list as `network:discoverability-check` (at most 16; without
`--node` the `publisher_nodes` of the configuration) and
announces to each Node in turn; one failing Node does not stop the others. The
JSON lists one row per Node and counts `accepted`, `rate_limited`, `unreachable`
and `other_failure`; exit 0 when every hint was received, otherwise 6.

With `--if-due` the same command is the bounded automatic announcement of
decision D-018 (owner-approved 2026-10-14). It first requires a saved
`service:verify --save-status` result for exactly this Manifest from the last 24
hours (otherwise `SELF_VERIFICATION_REQUIRED`, exit 6, nothing sent). Then each
Node is announced only when it is due:

| Reason | Decision |
| --- | --- |
| `INITIAL` | Never announced to this Node: sent |
| `NEW_REVISION` | The last hint announced another revision: sent |
| `MISSING_OBSERVATION` | Same revision, the Node still lacks it and the backoff has elapsed: sent |
| `ALREADY_INCLUDED` | Saved membership shows this Node includes the current revision: skipped |
| `NODE_EVIDENCE_NEEDS_REVIEW` | Saved membership is `contradictory` or `rejected`; a hint cannot fix that: skipped |
| `DAILY_LIMIT` | Less than 24 hours since the last hint to this Node, also for a new revision: skipped |
| `BACKOFF` | Waiting 1, 2, 4, 8, 16, then 32 days after consecutive hints for one revision: skipped |

A due Node is reserved under a lock on the configuration file before the POST,
so two runs at the same time cannot both announce; a reservation whose run dies
still counts as an attempt (`ANNOUNCEMENT_STARTED`). `--if-due` always saves its
results, so the schedule survives restarts. Explicit announcements without
`--if-due` ignore the schedule but, when saved, count toward the daily limit.
A received hint never stops the schedule; only saved membership evidence does.
`network:announce` is an alias. Receiving Nodes must explicitly enable both
discovery and the optional announcement switch; see
[PCP-002](../protocol/proposals/PCP-002-bounded-announcement-draft.md).

## Explicit derived-export reconstruction

`node:snapshot CONFIG --passphrase-file PRIVATE_FILE --rebuild-derived`
reconstructs derived exports from verified records and signs a new generation.
Ordinary publication fails on corrupted exports. See [server search](server-search.md)
for cache preparation, limits and query privacy.

## Signed chain after a restore

`node:init` and `node:snapshot` never sign a checkpoint generation or Node
descriptor revision the Node key already signed. The key's signing ledger,
`KEY_PATH.signed` (owner-only, beside the key, written atomically after each
committed publication), names the newest of each. When the store is behind it,
for example after `storage:restore` of an older backup, both commands fail with
`SIGNED_CHAIN_AHEAD_OF_STORE` or `SIGNED_DESCRIPTOR_AHEAD_OF_STORE` (exit 4) and
sign nothing. A missing or older ledger is raised to the store automatically; a
damaged one or one of another key fails with `SIGNING_LEDGER_INVALID` and is
never replaced.

`node:snapshot CONFIG --passphrase-file PRIVATE_FILE --adopt-signed-chain` takes
the checkpoint the ledger names from the export directory and, if the ledger's
descriptor revision is ahead, the Node descriptor from the published-state copy,
verifies that this key signed exactly those, points the store at them and
publishes: the new checkpoint is the ledger's generation plus one. The own
checkpoints between the store's generation and the ledger's are admitted from the
export directory too, so peers anchored on any of them follow; one of the newest
64 that is gone fails with `SIGNED_CHAIN_INCOMPLETE` and changes nothing. The
output adds `adopted_signed_chain` (`ADOPTED` or `CURRENT` for `checkpoint` and
`descriptor`). A signed record that is gone fails with
`SIGNED_CHAIN_UNRECOVERABLE`; restore a newer backup then. It combines with `--rebuild-derived`. See
[deployment and recovery](deployment-and-recovery.md#restoring-an-older-store-the-signing-ledger).

`sync:peer-step` may return `PROGRESS` with phase `CHECKPOINT_CHAIN` after a long
absence. Run another scheduled bounded step after the peer retry time. This
preserves the previous accepted cursor until full ancestry verifies; do not
clear the peer ledger to bypass a history failure.
