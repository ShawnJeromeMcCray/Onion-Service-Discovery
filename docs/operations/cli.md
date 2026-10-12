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
The web front controller also opens a read-only store; an HTTP visit cannot
trigger operational transactions or journal repair. HEAD's validated immutable
checkpoint remains the filesystem commit point, including before journal repair.

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
backup:create=storage:backup, db:verify/restore:verify=storage:check.
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
4 storage/signing failure or failed diagnostic; 6 diagnostic WARN/degraded;
8 normalized internal error. Transport workers can return structured BACKOFF,
QUARANTINED or PROGRESS states with a successful command exit: monitoring must
inspect the JSON status, not interpret exit 0 as a healthy current source.
Unexpected exceptions never print key material or raw traces in CLI output.

Full operator walkthrough, supported deployed runtime matrix, live Tor checks,
privilege separation, automatic authenticated backup custody and incident drills
remain release gates. The reference systemd/nginx/FPM files are staging templates;
no installer silently enables them or modifies the operator's Tor service.
