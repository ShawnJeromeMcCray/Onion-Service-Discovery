# Deployment and recovery

These instructions describe the executable development implementation. They do
not approve a public production release. Use a staging host until the release
gate matrix passes. The operator supplies an existing Tor v3 Onion Service and
its public `hostname` file. The application never provisions or reads Tor keys.

## Install

1. Provision 64-bit PHP 8.4+ with json, sodium, iconv and curl. SQLite additionally
   needs PDO SQLite. Current executed evidence is PHP 8.5.11; the full supported
   OS/PHP/Tor matrix is not yet certified. Provision a Tor SOCKS listener on
   loopback with remote hostname resolution and stream isolation support.
2. Verify the release artifact with the [offline TUF verifier](../../release-tools/README.md)
   after an authenticated production trust root exists. An unsigned development
   checkout must be identified as such. Never install an artifact on the basis
   of its bundled bootstrap root alone.
3. Put application source at `/opt/onion-service-discovery`, outside the public
   document root. Either extract the verified
   [development runtime package](release-candidate.md#development-runtime-package-d-029),
   which already contains the PHP dependencies and the built browser assets, or
   provision a source checkout: run `composer install --no-dev --no-plugins
   --no-scripts --no-interaction --prefer-dist` in `node-php` with a supported
   Composer and build browser assets using the locked Node toolchain:
   `npm ci --ignore-scripts` and `npm run build` in `web-local-search-client`.
   Preserve bundled notices.
4. Create a dedicated `osd-worker` Node identity and a private
   writable runtime directory. Reference stores require 0700 directories and
   0600 files; give these processes access through the same service UID, never world permissions. Separate web/worker
   UIDs need an isolation/storage-access design compatible with these mode checks;
   the reference does not supply a privilege-separated storage broker. A separate Client Host gets
   its own configuration/runtime and no Node or Service signing key.
5. Copy the appropriate example from `node-php/config`, replace synthetic onions
   and paths, and keep the configuration outside the document root. Set every
   feature explicitly. `server_search` defaults to false. Enable it only on a Node/combined role
   after reviewing the explicit [server-search privacy and deployment contract](server-search.md). Source/onion metadata is intentionally
   public; private paths and credentials are not browser configuration.
6. Run `php node-php/bin/onion-service-discovery config:validate CONFIG` and
   `doctor CONFIG`. Initialize the selected backend with `storage:init BACKEND
   PATH`. A Client Host instead uses `client:init CONFIG` and does not need keys.
7. For a Node, create a separate protected Node key using `key:create node KEY
   --passphrase-file PRIVATE_FILE`, then `node:init CONFIG --passphrase-file
   PRIVATE_FILE`. Protect that file with 0600 and its directory with 0700. The
   signer never takes a passphrase on the command line or in ordinary environment
   variables. Create/sign Service records offline with a different key. The web
   runtime must have no Service-key access. The basic staging pool shares the
   Node service UID for owner-private storage; it does not prove isolation from
   a compromised web process. Review separate container/mount namespaces or a
   signer/storage broker before production when Node-key isolation is required.
8. Publish a snapshot using `node:snapshot CONFIG --passphrase-file PRIVATE_FILE`.
   Add each peer with an independently obtained `--expected-node-key`. There is
   no invented production seed list or implicit trusted peer.
9. Adapt `deployment/nginx/onion-service-discovery.conf` (or the equivalent
   `deployment/apache/onion-service-discovery.conf`) and the FPM pool to your
   host. Only the front controller is executable. Disable request/query logging.
   The web runtime receives only `ONION_SERVICE_DISCOVERY_CONFIG`, not passwords.
   For a distinct Client Host, configure the allowed sources and use the generic
   proxy delivery profile. Configure Tor to forward public port 80 to the
   loopback listener yourself; keep its HiddenServiceDir inaccessible to OSD.
   If the Onion already serves a website, keep it and add only the OSD routes:
   see [existing website](existing-website.md). The templates' controls and
   their gaps are listed in the [hardening matrix](hardening-matrix.md).
10. Verify through the actual Onion with the supported Tor Browser profiles before
    public rollout. Local mocked SOCKS/Chromium tests cannot substitute this step.

A publisher can keep its status page current with `osd-publisher-check.timer`:
every six hours it runs `service:verify --save-status` for the own Onion,
`network:discoverability-check --save-status` for the Nodes listed in
`publisher_nodes` of the configuration (using the served history bundle) and
finally `network:announce … --if-due`, the bounded automatic
announcement of decision D-018: only for a new revision or a missing
observation, at most once per day per Node, with backoff up to 32 days. It
reaches only loopback (the Tor SOCKS listener). Delete the announce line to
announce only by hand.

Scheduler templates are references, not auto-enabled installation. Instantiate
peer jobs only with explicitly configured onions. Direct worker and snapshot
jobs call the bounded `node:work-one`/`node:snapshot` CLI and need an owner-managed
private Node passphrase file. They must not receive Service keys. Schedule work
with finite frequency and jitter, prevent overlapping jobs, and inspect aggregate
`node:status` and exit codes. Systemd examples intentionally suppress default
logs; health collection should retain counters/error codes only. A staging live
Tor deployment and operator acceptance are required before claiming the templates
as a certified production setup.

## Backup and restore

`storage:export` is a **public evidence interchange**, not an operational backup.
It deliberately omits local checks, cursors, operational authority and never-published
Service identities. Local admission sequences are rebuilt for the exported subset.
Use the trusted private backup for a complete backend migration.

For a trusted private backup use:

```sh
php node-php/bin/onion-service-discovery storage:backup filesystem /private/data /private/backup.ndjson
php node-php/bin/onion-service-discovery storage:restore sqlite /private/staging.sqlite /private/backup.ndjson --trusted-private-backup
php node-php/bin/onion-service-discovery storage:check sqlite /private/staging.sqlite
```

Both directions work between FilesystemStore and SQLite. Backup snapshots capture
records and all operational tables consistently; referenced record bytes are
immutable even during later appends. Every record/signature, sequence, object
hash, state shape and archive checksum is validated before admission. The restore
is bounded and staged. If an I/O/admission failure interrupts it, discard the
staging destination. Existing or partially restored stores are refused.

The backup is **private and trusted**. Its SHA-256 detects damage, not malicious
rewrites of unsigned local state. Encrypt/authenticate backups using a separately
managed established backup system, retain multiple generations and restrict
access. Never restore a peer-supplied file as a trusted backup. Signed fork,
rotation/migration evidence, local observations and source high-water marks are
preserved. Storage transaction generation is local and will change on restore.

Back up separately: encrypted Service key, encrypted Node key, production config,
public manifest, export blob directory and persistent TUF verifier state. Stop
snapshot publishers when capturing export blobs with the database so that its
published pointer and derived indexes have their corresponding blobs. Stop all
workers/web processes before cutover. Verify the restored data, owning Node-key
binding and export availability in isolated staging; expired leases can be
reclaimed normally. Missing export blobs fail closed and must be restored or
reconstructed by an explicitly reviewed republish before serving.

An old backup may contain old revision/source/release high-water marks. Compare
with authenticated recent backups, public conflict evidence and separately
preserved verifier state; never silently erase evidence of a higher revision.
Do not run old and restored copies with the same Node key simultaneously: they
could sign different checkpoints at the same generation. Restore to a new path,
retain the old path until validation and switch configuration atomically while
processes are stopped. Re-run doctor, integrity, bounded peer sync and end-to-end
checks before re-enabling schedules. Test this drill regularly; physical
power-loss/disk-full and live deployment restore drills remain open gates.

## Maintenance, rollback and removal

`node:maintain CONFIG` scans at most 256 remote-provenance assertions, expires
seven-day hints and performs a bounded SQLite WAL checkpoint. It never deletes
signed identity/history/fork evidence or source high-water marks. Busy SQLite
readers may keep WAL frames; retry later, do not remove WAL files manually.
Archive exports and stop publishers/web workers before running
`exports:prune CONFIG --workers-stopped`. This removes at most 256 unreferenced
export blobs per call and retains all current chunks and derived indexes. Old
signed checkpoints/identity/fork records remain in the database. The current API
serves only current-checkpoint chunks; older checkpoint bytes for chain proof
remain in record storage. Besides this immediate operator action, `node:maintain`
(the maintenance timer) prunes unreferenced blobs once they are older than one
hour, at most 256 per run; `doctor` reports the export usage. No automatic destruction
of conflict evidence or private keys is provided.

Before upgrading, keep an authenticated full backup and verify the package.
Storage schema 1 rejects unknown versions; no implicit migration or downgrade is
performed. Run new software in staging first. A code rollback may be incompatible
with newer protocol/storage state; never restore an older identity state just to
make old code run. TUF software high-water marks intentionally reject older
releases. Any emergency software recovery needs an authenticated new authorized
release and approved recovery policy, not a bypass switch.

To remove the application, stop/disable schedules, stop its web pool and remove
its web routing/application directory. Leave Tor and operator data/keys intact
until the operator separately chooses their disposition. Publish a signed
withdrawal before removing discovery if desired; removal alone is downtime and
cannot erase copies held by independent Nodes. For a lost or compromised key,
a corrupted store, conflicting peer checkpoints or a leaked passphrase file,
follow the [incident runbook](incident-runbook.md): it preserves evidence first
and names what protocol v1 cannot undo.

Actual disk-full development evidence: `node-php/tests/storage-disk-full.php`
runs only on an explicitly provisioned tiny `/fault` tmpfs, never a normal host
path. It forces kernel ENOSPC on both backends, checks typed CLI errors without
raw warnings, verifies prior records/cursors after reopening, and admits records
after releasing the synthetic padding. SQLite may automatically roll back on
FULL/IOERR; the adapter avoids issuing another rollback when no transaction is
active. This does not replace deployed disk/controller/power-loss drills.

The ENOSPC matrix additionally fills storage after immutable-object writes,
journal PREPARE, journal COMMIT before HEAD publication and SQLite's pre-cursor commit phase.
For filesystem format 2, a failed COMMIT leaves the old state; a durable COMMIT
followed by failed HEAD publication recovers the entire new state, including
its cursor. A transaction error after COMMIT does not promise rollback; perform
explicit recovery and retry idempotently. The test aligns the journal across a
page boundary because a full filesystem can still permit writes inside already
allocated blocks. Record and cursor must agree in every recovered state.
