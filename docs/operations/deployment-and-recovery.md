# Deployment and recovery

These instructions describe the executable development implementation. They do
not approve a public production release. Use a staging host until the release
gate matrix passes. The operator supplies an existing Tor v3 Onion Service and
its public `hostname` file. The application never provisions or reads Tor keys.

## Install

1. Provision 64-bit PHP 8.3+ (8.5 preferred) with json, sodium, iconv and curl. SQLite additionally
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
6. Run `php node-php/bin/onion-service-discovery config:validate CONFIG`. A
   Client Host then uses `client:init CONFIG` and does not need keys; run
   `doctor CONFIG` afterwards. Until step 7 a Node's `doctor` and `status`
   report `NOT_INITIALIZED` (warning `NODE_NOT_INITIALIZED`, exit 6) with the
   next step; that is expected, not a fault.
7. For a Node, create a separate protected Node key using `key:create node KEY
   --passphrase-file PRIVATE_FILE`, then `node:init CONFIG --passphrase-file
   PRIVATE_FILE`, which creates the selected store (`storage:init BACKEND PATH`
   can create it beforehand) and the export directory; then run `doctor CONFIG`.
   Protect that file with 0600 and its directory with 0700. The
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

Back up separately: encrypted Service key, encrypted Node key and its signing
ledger (`KEY_PATH.signed`, see below), production config,
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

### Restoring an older store: the signing ledger

A Node key must never sign a checkpoint generation or Node descriptor revision it
already published: peers and publishers treat two different records with the same
number as equivocation (`SOURCE_CHECKPOINT_EQUIVOCATION`,
`NODE_DESCRIPTOR_ROLLBACK_OR_FORK`) and quarantine the Node for good. A store
restored from a backup older than the last publication would make the next
`node:snapshot` sign exactly that. So every Node keeps a signing ledger beside its
key, `KEY_PATH.signed` (for example `/etc/onion-service-discovery/node.key.signed`):
an owner-only JSON file naming the newest checkpoint (generation and hash) and Node
descriptor (revision and hash) this key signed and the store committed. It lives in
the key's directory, outside the store and its backups, and is replaced atomically.

- `node:init` and `node:snapshot` compare the ledger with the store before they
  sign. A ledger ahead of the store stops them with `SIGNED_CHAIN_AHEAD_OF_STORE` or
  `SIGNED_DESCRIPTOR_AHEAD_OF_STORE`, and nothing is signed, stored or exported.
  `doctor` fails with `SIGNING_LEDGER_AHEAD_OF_STORE` and `node:status` reports
  `"signing_ledger": "AHEAD_OF_STORE"`.
- A ledger behind the store or missing (the first run after an upgrade, a lost
  file, a crash between the store commit and the ledger write) is raised to the
  store's pointers first; no action is needed (`doctor` shows `BEHIND_STORE` or
  `NOT_PRESENT` meanwhile).
- The guard only works while the ledger is newer than the restored store. When you
  restore a store, keep the Node's current ledger: never copy an older ledger back
  over it together with the store. Restore a ledger from backup only when the
  current one is lost or damaged, and then from the newest copy you have.
- A damaged ledger, a symlink, one open to group or others or one of another key stops
  signing with `SIGNING_LEDGER_INVALID`; it is never replaced silently. Restore it
  from the newest backup kept with the key, or see the
  [incident runbook](incident-runbook.md#6-signing-ledger-ahead-of-the-store-or-damaged).

After restoring an older store, keep the web server and the timers stopped and let
the Node continue its own published chain before it serves anything:

```sh
php node-php/bin/onion-service-discovery doctor /etc/onion-service-discovery/node.json
# signing_ledger: AHEAD_OF_STORE
php node-php/bin/onion-service-discovery node:snapshot /etc/onion-service-discovery/node.json --passphrase-file PRIVATE_FILE --adopt-signed-chain
```

`--adopt-signed-chain` loads the checkpoint the ledger names from the export
directory and, when the ledger's descriptor revision is ahead, the Node descriptor
from the published-state copy beside the exports. It checks that each is exactly
what this key signed (signature, type, key, number and hash), admits it into the
store and points the store at it, then publishes as usual: the next checkpoint is
the ledger's generation plus one and names the adopted checkpoint as its previous
one. Its output has `"adopted_signed_chain": {"checkpoint": "ADOPTED"|"CURRENT",
"descriptor": "ADOPTED"|"CURRENT"}`; running it again changes nothing. The export
pruning of `node:maintain` and `exports:prune` keeps the ledger's checkpoint blob
meanwhile.

Generations between the restored store and the adopted checkpoint are simply
absent from this Node: a peer that already holds the adopted checkpoint continues
normally, a peer that held an older one sees a gap in the chain, and publishers
whose evidence named a missing generation report it as `NOT_EVALUATED`. Records
the Node admitted after the backup are not restored by adoption; peer sync and
direct checks admit them again.

If the needed blob or published-state copy is gone (the export directory was lost
or restored from elsewhere), `--adopt-signed-chain` fails with
`SIGNED_CHAIN_UNRECOVERABLE` and changes nothing. The remaining option is to
restore a newer backup of the store, one taken at or after the ledger's
generation. Never delete or edit the ledger to make signing proceed: that signs a
conflicting generation, and peers quarantine the Node permanently.

## Degraded mode

A Node whose store cannot be read, for example after an interrupted write that
needs recovery or because the store is corrupted, keeps serving its last
published signed snapshot (decision D-045). The snapshot descriptor and its
chunks are content-addressed files in the export directory and verify on their
own. After every publication `node:snapshot` also writes a copy of the published
state there, `.published-state.json`: the state document and the signed Node
descriptor, replaced atomically and never pruned. While the store cannot be read:

- `/onion-service-discovery/v1/node`, `state`, `snapshots/HASH` for the current
  checkpoint (and for older checkpoints still in the export directory) and
  `chunks/HASH` answer exactly as before, with the same headers and limits;
- `peers`, `changes`, service lookups, a checkpoint the export directory no
  longer holds and announcements answer 503 `NODE_DEGRADED_READ_ONLY`; no web
  request opens the store for writing, so nothing recovers it unasked;
- server search answers its 503 page;
- the status page shows the Node's storage as `DEGRADED` with one sentence and no
  details. `status`, `status --check` and `doctor` report the cause and the next
  step, which the [incident runbook](incident-runbook.md#4-store-corruption-or-an-interrupted-write)
  describes.

Every request checks the copy against the signed records again. Without a copy
(before the first `node:snapshot` of this release), with a damaged copy or
export directory, or with a missing or invalid configuration nothing is served:
the routes answer 503 `NODE_UNAVAILABLE` as before and the card shows `ERROR`.
The copy is written only after the store committed the publication, so it never
names a snapshot the store did not publish. If writing it fails, `node:snapshot`
fails and its next run writes it; until then `doctor` warns
`PUBLISHED_STATE_STALE`. Back up the export directory with the store, as
described above. Detailed health stays local: the public page shows only the
state.

## Maintenance, rollback and removal

`node:maintain CONFIG` scans at most 256 remote-provenance assertions, expires
seven-day hints and performs a bounded SQLite WAL checkpoint. It removes at most
256 signed checkpoints per run that nothing needs any more: the Node's own beyond
the newest 256 on its chain, and peer checkpoints that are not a peer's anchor,
pending checkpoint, one of its four newest current ones or conflict evidence
(see cli.md). It never deletes service records, Node descriptors, conflicting
checkpoints or source high-water marks. Pruning needs a store migrated with
`storage:migrate` (filesystem format 3, SQLite schema version 2); before that it
reports `record_pruning: MIGRATION_REQUIRED` and the store keeps growing until
`STORAGE_BUDGET_EXHAUSTED`. Busy SQLite
readers may keep WAL frames; retry later, do not remove WAL files manually.
Archive exports and stop publishers/web workers before running
`exports:prune CONFIG --workers-stopped`. This removes at most 256 unreferenced
export blobs per call and retains all current chunks and derived indexes. The
newest 256 own signed checkpoints remain in the database for chain proof. The
API serves the current checkpoint's chunks and, while their blobs exist, those
of earlier checkpoints, so a peer can finish one that changed meanwhile.
Besides this immediate operator action, `node:maintain` (the maintenance timer)
prunes unreferenced blobs once they are older than one hour and the blobs of
checkpoints that were current within that hour stay, at most 256 per run;
`doctor` reports the export usage and the store's `storage_usage`. No automatic destruction
of conflict evidence or private keys is provided.

Before upgrading, keep an authenticated full backup and verify the package.
Unknown storage versions are refused; no implicit migration or downgrade is
performed. After upgrading to a release with bounded checkpoint retention, stop
the workers, back up, run `storage:migrate BACKEND PATH` once and restart; an
older release then refuses the store, so roll back by restoring that backup. Run new software in staging first. A code rollback may be incompatible
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
