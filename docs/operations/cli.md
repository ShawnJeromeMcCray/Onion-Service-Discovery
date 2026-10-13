# Operator CLI contract

Entry point: `php node-php/bin/onion-service-discovery help`. All output is JSON;
`--json` and `--no-interaction` are accepted. No command waits for an interactive
confirmation. Explicit commands/flags choose side effects, roles and trusted
backup handling; publication/discovery/sync flags default false. Private signing
passphrases come only from owner-private files or service-manager credentials.
Do not send passphrases in command arguments or ordinary environment variables.

Read-only commands include config:validate, doctor, security:check, node:status,
release:info, candidate:list, quarantine:list and sync:status. Doctor checks the
actual selected store without repair, reports pending recovery, and never
probes Tor or decrypts keys. Unsupported/unobserved checks are explicitly marked:
Tor connectivity, scheduler, backup freshness, deployed headers and independent
security/release review are not inferred from local configuration. Use separate
transport/browser tests and operator acceptance to establish those properties.
The ordinary data/status web routes open a read-only store and do not repair
journals. The default-off experimental announcement route alone opens the
selected writable store for bounded queue admission; it has no signer or
outbound transport capability. Filesystem format 2 uses the journal COMMIT as durable authority before HEAD.
Read-only opening refuses unresolved recovery rather than repairing it.

`storage:check` is a separate explicit integrity/recovery command.
`storage:backup`/`backup:create` captures trusted private records and operational
state; `backup:verify FILE` validates it without restoring. `storage:restore`
requires a fresh staging path and `--trusted-private-backup`.
`restore:verify`/`db:verify` use the backend/path integrity command.
`db:migrate BACKEND PATH` checks current schema 1 without mutation; there is no
invented migration from an unknown schema. Future migrations need explicit code,
fixtures and review.

Aliases: status=node:status, peer:list=peers:list, peer:add=peers:add,
peer:disable=peers:disable, manifest:show=protocol:inspect,
backup:create=storage:backup, db:verify/restore:verify=storage:check,
manifest:generate=manifest:init and sync:run=sync:peer-step.
The key:service:generate/key:node:generate commands select the corresponding
key:create role; they require the same private passphrase-file flag.
The alias retains the underlying command's argument contract. `release:info`
reports development status and the machine-readable gate matrix, not a stable
release assertion. Release verification is the separate mature Python TUF tool.

Manifest commands prepare genesis/revision/withdrawal/rotation/migration drafts,
then record:sign signs only an allowlisted role/domain. Rotation collects old/new
signatures and its first new-key manifest; manifest:install verifies hostname,
continuity/proofs and atomic installation. No-change revisions preserve exact
existing signed bytes. A Service key is kept offline by default. Node initialization
checks the stored descriptor identity. Snapshot/direct CLI workers also require
that initialized Node key/Onion binding, rejecting an unnoticed key replacement.
Changing a Node identity means a new explicitly pinned source; the v1 protocol
has no Service-style Node key-rotation proof. Do not simulate one by editing rows.

List commands show at most 256 entries and indicate truncation. Quarantine is
explicit and source high-water marks/conflicts are not erased by retry.
`node:maintain` expires bounded remote hints and checkpoints SQLite WAL.
`exports:prune --workers-stopped` is an operator-only quiescent action; it removes
at most 256 unreferenced derived blobs, not signed identity/fork records.
No command executes a downloaded software artifact.

Exit codes: 0 successful command; 2 usage; 3 input/config/protocol validation;
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

`service:verify CONFIG ONION` makes one bounded fixed-path request through the
configured Tor SOCKS transport and checks the manifest signature and exact Onion
binding. It neither opens/mutates the store nor loads a signing key. Its explicit
`NOT_DERIVED` identity state means it does not establish continuity or replace a
pin. `SIGNATURE_AND_BINDING_VALID` is not a safety/content or lasting uptime claim.
Use the normal candidate worker for stored continuity checks and observations.

An unprovisioned source package returns typed `RUNTIME_DEPENDENCIES_MISSING`
with exit 3 and no raw include warning. Install the locked Composer runtime
dependencies before invoking application commands.

`init CONFIG ONION [--publish]` creates a publisher-only configuration in an
existing private directory, refuses overwrite, creates no keys/storage/Manifest,
and makes no network request. `publisher:status CONFIG` is read-only and uses
the public status page's fact derivation. See publish-only.md for consequences.

`storage:migrate filesystem PATH` explicitly migrates native format 1 to 2.
Stop workers and make a tested backup with the historical release before migrating.
Legacy HEAD-published transactions retain their old interpretation; new writes
are refused until migration. Logical storage schema remains 1 and signed records
are not rewritten. `storage:migrate sqlite PATH` checks the unchanged schema.

`index:verify-inclusion CHECKPOINT CHUNKS_DIRECTORY OWN_HISTORY EXPECTED_NODE_KEY`
verifies exact current owner-state membership offline in a complete signed draft
snapshot, including genesis continuity through rotation/migration. It evaluates
neither freshness nor checkpoint ancestry. See [the contract](snapshot-membership.md).

`peers:group CONFIG ONION GROUP|--clear` records local source-family policy.
Diagnostics expose concentration and unknown/inconsistent grouping without
inferring independent operators or trust from key count. See [source groups](source-groups.md).

## One-shot untrusted announcement

`service:announce CONFIG SIGNED_MANIFEST_FILE NODE_ONION` requires explicit
local publication and a signed, opted-in manifest bound to the configured
Service Onion. It makes one fixed-path Tor POST with fresh SOCKS isolation; it
reads no signing key and mutates no local store. The exact response is
`HINT_RECEIVED_UNCONFIRMED`; both direct observation and membership remain
`NOT_CONFIRMED`. There is no automatic retry loop. Redirect, rate-limit,
malformed acknowledgement and bare inclusion assertions fail. This is the
[PCP-002 draft](../protocol/proposals/PCP-002-bounded-announcement-draft.md),
subject to D-018 human endpoint/limits/retry freeze. Receiving Nodes must
explicitly enable both discovery and the optional announcement switch.
