# Incident runbook

What to do when a key, a store or a peer goes wrong. It follows specification 40,
section 13 and specification 07, sections 26 to 28, and uses only commands that
exist today. Commands are run from the application directory as
`php node-php/bin/onion-service-discovery COMMAND`, on the web host as the
service user (`sudo -u osd-worker …`). `CONFIG` is your private configuration
file, `BACKEND PATH` your store (`filesystem` or `sqlite` and its path).

Protocol v1 has no revocation and no recovery key. Some outcomes cannot be
undone; each section says which.

## Every incident: first steps

1. Do not delete, rotate or rewrite anything yet.
2. Stop the jobs that sign or announce. Publisher: `systemctl stop
   osd-publisher-check.timer`. Node: `systemctl stop osd-snapshot.timer
   osd-direct-check.timer` and every `osd-peer-sync@….timer`. Let running jobs
   finish.
3. Record the facts: `release:info`, the exact error codes, `status CONFIG
   --check`, `doctor CONFIG` and `diagnostics:export CONFIG`. The last one leaves
   out paths, Onion addresses, keys and passphrases; review it before you share it.
4. Preserve the evidence named in each section. Keep it private: a private
   backup holds operational state.
5. Tell those affected. There is no central authority or revocation list: reach
   peers, publishers, Client Host operators and visitors through channels they
   already trust.

## 1. Service key compromised or lost

Your Service Identity key signs every revision of your Manifest. Your Logical
Service Identifier is the genesis public key.

**Contain and preserve.**

1. `systemctl disable --now osd-publisher-check.timer`. This stops the self-check
   and the automatic announcement. Do not announce by hand.
2. Keep copies of the installed Manifest and history bundle
   (`node-php/public/.well-known/onion-service-discovery.json` and
   `onion-service-discovery-history.json`), of every signed revision, rotation
   and migration file you have, and of `CONFIG_NAME.discoverability.json` next
   to your configuration.
3. Record `publisher:status CONFIG` and `history:verify node-php/public`.
4. Check what your Nodes hold without changing the saved evidence:
   `network:discoverability-check CONFIG
   node-php/public/.well-known/onion-service-discovery-history.json`, without
   `--save-status`. A `contradictory` Node holds a signed state for your
   identity that conflicts with yours, for example a fork.

**Decide.**

| Situation | What you can do | Result |
| --- | --- | --- |
| You still hold the key; no competing state is visible | [Rotate](../getting-started/publisher.md#rotate-your-service-key) to a new key (`manifest:rotate`, signed by the old and the new key) | Continuity moves to the new key; pins stay valid. The old key can still sign a competing revision or rotation at or before the rotation point. Anyone who sees both then sees a fork. |
| You still hold the key and want to stop discovery | [Withdraw](../getting-started/publisher.md#withdraw) (`manifest:withdraw`, signed by the current key) | Nodes stop listing the Service as published; copies of earlier revisions remain. A competing revision is still possible. |
| You still hold the key and the Onion must change too | [Migrate](../getting-started/publisher.md#move-to-a-new-onion-address) (`manifest:migrate`, signed by the current key) | Continuity moves to the new Onion. A competing transition is still possible. |
| The key is lost | Nothing for this identity: rotation, withdrawal, revisions and migration all need its signature | The last published state stays as it is wherever it was copied. |
| A competing signed state exists | Nothing: protocol v1 never resolves a fork | Nodes and clients show the identity as forked, without a current Manifest or visit link. |

In the last two cases the only way forward is a new identity: create a new
Service key, sign a new genesis with `manifest:init`, preferably for a new
Onion, and tell your visitors through a channel they trust. Do not present it as
the same Service. A new identity on the old Onion is a poor choice: a Node that
already observed your old identity at that Onion refuses to observe another one
there (`IDENTITY_CHANGED`, `node-php/src/Node/DirectVerifier.php:73`). It keeps
that verdict; a recheck about a week later gives the same result. This follows
from the code and was not exercised against a running Node.

**What visitors with a pin see** (browser client):

| Your action | Pin status shown |
| --- | --- |
| Valid rotation | "Signed continuity matches your pin" |
| Migration | "Authorized onion migration from your saved context" |
| Withdrawal | "Publication withdrawn; identity history retained", no visit link |
| A competing signed revision or transition appears | "Fork conflict retained — no current branch selected", no visit link. The pin keeps this state. |
| New identity on the same Onion | The pin stays on the old identity. Both results warn that different identities claim this Onion. If the old records are not loaded: "Another key claims the saved onion; pin preserved". |
| New identity on a new Onion | The pin stays on the old identity, unchanged. |

## 2. Node key compromised or lost

Protocol v1 has no Node key rotation. The supported path is a **new Node
identity: a new key, on a new Onion, with a new store**. The behavior below was
checked with the CLI in a scratch directory on a development host.

**Not possible:**

- `key:node:rotate` fails with `NODE_KEY_ROTATION_UNSUPPORTED`.
- A new key or a new Onion on the existing store: `node:init` fails with
  `NODE_IDENTITY_CHANGE_REQUIRES_MIGRATION`, `node:snapshot` and `node:work-one`
  with `NODE_IDENTITY_CHANGED`. A private backup (`storage:backup`,
  `storage:restore`) and `storage:migrate-backend` carry the old binding and get
  the same refusal.
- Keeping the old Onion with a new key: a peer that pinned the Onion gets
  `PEER_KEY_CHANGE_REQUIRES_EXPLICIT_RESET` from `peers:add`, also after
  `peers:disable`. There is no reset command.
- Revoking the old key: its descriptor, checkpoints and observations remain
  valid signatures wherever they were copied. No command marks observations from
  the compromise period as untrusted. Every peer, publisher, Client Host and
  visitor must act separately.

**Steps:**

1. Stop the old Node: `systemctl disable --now osd-snapshot.timer
   osd-direct-check.timer osd-maintenance.timer` and every
   `osd-peer-sync@….timer`. If the host itself is compromised, take the old
   Onion offline: its Tor key on the same host is compromised too.
2. Preserve: `node:identity OLD_CONFIG` (the old Onion and key, for your
   announcement), `storage:backup BACKEND OLD_PATH PRIVATE_FILE`, `candidate:list
   OLD_CONFIG` (the Services to queue again), `peers:list OLD_CONFIG` and
   `diagnostics:export OLD_CONFIG`. Keep the old key file offline as evidence;
   never use it again.
3. Configure a new Onion in Tor (a new HiddenServiceDir) and note its public
   hostname.
4. Write the new configuration, without a store yet: `init NEW_CONFIG
   NEW.onion --role node` (or `--role combined`).
5. Optional: carry public records over, **before** `node:init`.
   `storage:export BACKEND OLD_PATH ARCHIVE` writes the public evidence: published
   Service records with their continuity proofs, all Node descriptors, direct
   observations of published Manifests and all checkpoints. `storage:import
   BACKEND NEW_PATH ARCHIVE` verifies every record and needs an empty store
   (later it refuses with `IMPORT_REQUIRES_EMPTY_STAGING_STORE`). Candidates,
   peers, sync anchors and your own observations are not carried. The new Node's
   snapshots then also contain the old Node's descriptor and its observations, as
   signed claims of the old key. After a compromise, export from a state you
   trust: restore a backup from before the compromise into a staging path with
   `storage:restore` and export from there, or skip this step and let the new
   Node learn from its peers.
6. Create the key at the new configuration's `node.key_path` (NEW_KEY), with a
   new passphrase file, and initialize: `key:create node NEW_KEY
   --passphrase-file NEW_PASSPHRASE_FILE`, `node:init NEW_CONFIG
   --passphrase-file NEW_PASSPHRASE_FILE`, `node:snapshot NEW_CONFIG
   --passphrase-file NEW_PASSPHRASE_FILE`, then `node:identity NEW_CONFIG`.
7. Queue the Services again: `candidate:add NEW_CONFIG ONION` for each Onion from
   step 2, or let peer sync queue them (`discover_services`). Until the new Node
   has checked a Service itself, publishers' checks do not count it as
   discoverable there.
8. Add your peers: `peers:add NEW_CONFIG PEER.onion --expected-node-key KEY`.
9. Tell others, through channels they trust:
   - peers run `peers:add CONFIG NEW.onion --expected-node-key NEW_KEY` and
     `peers:disable CONFIG OLD.onion`. They must not run `peers:retry` for the old
     Onion: it returns a disabled peer to `CANDIDATE`;
   - publishers replace the old Node in `publisher_nodes` (or in their `--node`
     arguments) with the new Onion and key and run `config:validate`. Their next
     check with `--save-status` replaces the saved results;
   - Client Host operators replace the old Onion in `sources`;
   - visitors who added the old Node remove it and add the new one with its key.
10. Start the new Node's timers. Keep the old store and key offline as long as
    you need them as evidence.

## 3. Conflicting checkpoints from a peer

**Signals.** `sync:peer-step` returns `QUARANTINED` with
`SOURCE_CHECKPOINT_EQUIVOCATION` (two checkpoints for one generation),
`SOURCE_CHECKPOINT_ROLLBACK`, `SOURCE_CHECKPOINT_CHAIN_BROKEN`,
`NODE_DESCRIPTOR_ROLLBACK_OR_FORK` or `PEER_BINDING_MISMATCH`. `peers:list
CONFIG` shows the peer's state and error; `doctor CONFIG` counts quarantined
peers. Publishers see `SOURCE_CHECKPOINT_…` codes for a checked Node, with a
warning on their status page; browser visitors see the source set aside.
`quarantine:list CONFIG` lists such peers (`"kind":"peer"`) with the hashes of
the conflicting checkpoints kept as evidence, next to Service candidates whose
direct check failed (`"kind":"candidate"`, for example `IDENTITY_FORKED` or
`IDENTITY_CHANGED`).

**What is kept.** Both signed checkpoints are stored as records. The store keeps
up to eight checkpoint hashes per Node key and generation, and the last accepted
checkpoint of each peer (`sync:status CONFIG` shows its generation).

1. Do not retry yet. Record `peers:list CONFIG` and `sync:status CONFIG`.
2. Preserve: `storage:backup BACKEND PATH PRIVATE_FILE` keeps records and
   operational state, including the checkpoint evidence. `storage:export BACKEND
   PATH ARCHIVE` writes the signed checkpoints as public evidence you can give to
   the peer's operator; it contains all public records, not only these two.
3. Ask the peer's operator. A Node that restored an old backup or ran two copies
   with one key has broken its chain for good: it must start over as a new Node
   identity (section 2). Add that identity with `peers:add` and keep the old one
   disabled with `peers:disable`.
4. Use `peers:retry CONFIG ONION --acknowledge-quarantine` only after the cause
   is fixed. It returns the peer to `CANDIDATE` and clears its failure count. It
   keeps the accepted checkpoint and all evidence: a peer whose chain still does
   not descend from that checkpoint is quarantined again at the next step. A
   retry cannot accept the other branch.

If peers or publishers report this about your own Node, stop
`osd-snapshot.timer` and follow section 4 or section 2.

## 4. Store corruption or an interrupted write

**Interrupted write** (power loss, killed job, full disk). The filesystem store
recovers the next time a writer opens it: it cuts a partial journal line, aborts
an unfinished transaction and completes a committed one whose HEAD update was
lost. Until then the read-only web routes answer 503, and `doctor` reports
`STORAGE_RECOVERY_REQUIRED`. SQLite recovers its own journal.

1. Free disk space if it is full. `storage:verify BACKEND PATH` looks without
   changing anything: it reports `STORAGE_RECOVERY_REQUIRED` instead of
   completing the write.
2. Stop the timers and run `storage:check BACKEND PATH`: the explicit recovery
   plus a hash check of every record.
3. Run `doctor CONFIG`, then start the timers again.

**Corruption** (`storage:check` fails with `STORAGE_CORRUPT`, exit 4).

1. Stop the timers and the web pool. Do not edit, move or delete files inside
   the store; keep it as evidence.
2. Check the newest private backup: `backup:verify PRIVATE_FILE`.
3. Restore it into a new, empty path (an existing path is refused):
   `storage:restore BACKEND NEW_STAGING_PATH PRIVATE_FILE
   --trusted-private-backup`, then `storage:check BACKEND NEW_STAGING_PATH`.
4. Compare generations. Point a copy of the configuration at the staging path
   and run `status COPY --check`: the Node card shows `snapshot_generation`.
   Compare it with the newest generation this Node published: a peer's
   `sync:status` shows the generation it accepted from you.
5. Same generation: switch the configuration to the restored path while all
   processes are stopped, run `doctor`, start the timers. If export files are
   missing, `node:snapshot CONFIG --passphrase-file FILE --rebuild-derived`
   rebuilds them and signs the next generation.
6. Lower generation: do not run `node:snapshot` with this key again. The next
   snapshot would sign a generation number that peers already saw with other
   content; peers would quarantine your Node and publishers would reject it.
   Start a new Node identity (section 2) and carry the restored public records
   over with `storage:export` from the staging path.

## 5. A passphrase file leaked

A passphrase file unlocks one key file (Argon2id key derivation,
XChaCha20-Poly1305). Whoever holds both can sign with that key.

1. Find every copy of the matching key file: the host, backups, old disks,
   support uploads.
2. If any copy may have been exposed as well, treat it as a key compromise:
   section 1 for the Service key, section 2 for the Node key.
3. If not: there is no command to protect an existing key file with a new
   passphrase. The leaked passphrase keeps working for that key file and every
   copy of it, including backups. Keep the key file and its backups under
   tighter control, and plan the change to a new key: a rotation for the Service
   key (section 1), a new Node identity for the Node key (section 2).
4. Store the new passphrase only in an owner-only (0600) file or as a systemd
   credential, never in a command line or an ordinary environment variable. The
   timers read it through `LoadCredential=` when they start.

## Related

[Operator contract](operator-contract.md), [deployment and
recovery](deployment-and-recovery.md), [CLI](cli.md), [error
codes](error-codes.md), [offline signing](../signing/offline.md),
[troubleshooting](../getting-started/troubleshooting.md).
