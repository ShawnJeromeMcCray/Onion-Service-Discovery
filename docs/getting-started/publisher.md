# Publish your existing Onion Service

This walkthrough creates and installs a genesis Manifest and verifies local
publication. It does not promise automatic admission to public Discovery Nodes.
Provision [PHP dependencies](installation.md) first. Your existing v3 Onion is
managed by Tor; the Service identity key created here is a separate application
key. Work on a staging copy until the release gates pass.

## 1. Record your public Onion

Copy only the existing service's public `hostname` to an administrator-controlled
file named `hostname`. Its content is one canonical 56-character v3 address plus
`.onion`, optionally followed by a newline. Do not grant OSD access to Tor's
private key directory. In the following commands, replace the absolute paths and
`YOUR_CANONICAL_V3_ADDRESS.onion` with your real values.

## 2. Create and back up a Service key offline

On your separate administrator machine, in an owner-private directory:

```sh
umask 077
mkdir -p /absolute/private/osd-service
```

Create `/absolute/private/osd-service/passphrase` with your password manager or
another private input method. Give it mode 0600; do not put the passphrase in a
shell command, ordinary environment variable, Git or an issue. Keep an encrypted
key backup and the passphrase separately. Then run from the repository root:

```sh
php node-php/bin/onion-service-discovery key:create service /absolute/private/osd-service/service.key --passphrase-file /absolute/private/osd-service/passphrase > /absolute/private/osd-service/key-result.json
```

Expect JSON `status: PASS` and `public_key: ed25519:...`. This output is the public
key, not the encrypted key file. Copy that public-key string for the next step.
Key creation refuses an existing destination. Test your encrypted backup before
relying on it; see [offline key custody](../signing/offline.md).

## 3. Describe the service and sign its first Manifest

Create `/absolute/private/osd-service/metadata.json`:

```json
{
  "service": {
    "name": "My public service",
    "description": "A concise description of the public service.",
    "languages": ["en"],
    "categories": []
  },
  "discovery": {
    "publish_service": true,
    "mirror_directory": false,
    "discover_services": false,
    "sync_with_peers": false
  }
}
```

This explicitly opts the metadata into public discovery. Use only metadata you
intend to publish. Category identifiers come from the
[versioned registry](../../protocol/registries/categories-v1.json); language tags
use the draft lowercase RFC 5646 grammar. Empty categories are permitted.
With your real Onion and the **public** Service key string:

```sh
php node-php/bin/onion-service-discovery manifest:init YOUR_CANONICAL_V3_ADDRESS.onion ed25519:YOUR_SERVICE_PUBLIC_KEY /absolute/private/osd-service/metadata.json > /absolute/private/osd-service/draft.json
php node-php/bin/onion-service-discovery record:sign service /absolute/private/osd-service/service.key /absolute/private/osd-service/draft.json --passphrase-file /absolute/private/osd-service/passphrase > /absolute/private/osd-service/manifest.json
php node-php/bin/onion-service-discovery manifest:verify /absolute/private/osd-service/manifest.json
```

The placeholder public key must be replaced with the exact generated string.
Expect successful verification of revision 1. A draft alone is not a signed
publication. Retain every signed revision and continuity proof on the administrator
machine; later offline membership checks need the complete owner history.

## 4. Install only public records on the web host

Transfer the signed `manifest.json` and public `hostname` to the staging web
host. Never transfer the Service key or its passphrase. Create a private
configuration directory owned by the service user of the PHP-FPM pool that will
serve OSD (the templates use `osd-worker`), then run from that host's repository
root:

```sh
sudo install -d -m 0700 -o osd-worker -g osd /absolute/private/osd-publisher
sudo -u osd-worker php node-php/bin/onion-service-discovery init /absolute/private/osd-publisher/config.json YOUR_CANONICAL_V3_ADDRESS.onion --publish
php node-php/bin/onion-service-discovery manifest:install /absolute/public-transfer/manifest.json /absolute/public-transfer/hostname /absolute/app/Onion-Service-Discovery/node-php/public
sudo -u osd-worker php node-php/bin/onion-service-discovery publisher:status /absolute/private/osd-publisher/config.json
```

Run `init`, the status command and every later check as that same user: the web
server must be able to read the configuration and the evidence the checks save
([owners and modes](installation.md#files-and-permissions)). Run
`manifest:install` as the user that may write the checkout's `node-php/public`;
it writes world-readable public files only, and later installs need the same user.

`init` creates configuration only; expected status is `CONFIGURED`. Installation
verifies the signature and exact public-hostname binding before atomic replacement;
expect `INSTALLED`. The status command reads the Manifest under this checkout's
`node-php/public`, so install into that exact directory. Expect `READY_LOCALLY`
and `manifest.state: SIGNATURE_VALID`; external discoverability remains unconfirmed.

Configure the front controller with `ONION_SERVICE_DISCOVERY_CONFIG` pointing to
this private JSON file, following [web serving](installation.md#web-serving-and-operations).
Open these routes through your actual Onion:

- `/.well-known/onion-service-discovery.json`: signed public Manifest.
- `/onion-service-discovery/status/`: read-only status, usable without JavaScript.

Do not replace the rest of your existing website with the OSD front controller.
Integrate these routes with its existing routing, keeping configuration and keys
outside the document root. The reference templates assume a dedicated OSD listener.

## 5. Verify over Tor and check selected Nodes

Set the actual loopback SOCKS port in the private configuration. Explicitly fetch
and verify your own published Manifest through Tor:

```sh
sudo -u osd-worker php node-php/bin/onion-service-discovery service:verify /absolute/private/osd-publisher/config.json YOUR_CANONICAL_V3_ADDRESS.onion
```

Expect `SIGNATURE_AND_BINDING_VALID`. This establishes the fetched signature and
Onion binding, not full owner continuity or inclusion at another Node.
A cooperating Discovery Node can explicitly admit your address and run its direct
verification worker. A Node that enabled the bounded announcement endpoint
accepts a hint: `network:announce CONFIG SIGNED_MANIFEST NODE_ONION` returns
`HINT_RECEIVED_UNCONFIRMED`, never proof of inclusion; the Node still fetches and
verifies your Manifest itself.

Then check, in one bounded command, whether your exact current signed state is
included at the Nodes you selected. Obtain each Node's Onion and its expected
Node public key through a channel you chose independently; never copy them from
the Node's own response. On the administrator machine, collect your complete
owner history (every signed revision and continuity proof) in
`history.json` as `{"records": [ ... ]}`. On the web host the history bundle
that `manifest:install` maintains,
`node-php/public/.well-known/onion-service-discovery-history.json`, is accepted
directly, and for a Service that has only its genesis Manifest, the signed
`manifest.json` itself:

```sh
sudo -u osd-worker php node-php/bin/onion-service-discovery service:membership /absolute/private/osd-publisher/config.json node-php/public/.well-known/onion-service-discovery-history.json \
  --node FIRST_NODE.onion ed25519:FIRST_NODE_PUBLIC_KEY \
  --node SECOND_NODE.onion ed25519:SECOND_NODE_PUBLIC_KEY \
  --node THIRD_NODE.onion ed25519:THIRD_NODE_PUBLIC_KEY
```

The history contains only public signed records; it never needs the Service key.
For every Node, the command fetches the current signed checkpoint and all its
chunks through Tor (fresh SOCKS isolation per request), verifies them under the
key you supplied and compares the complete snapshot with your own history. It
writes nothing locally. Each Node is reported in exactly one category:

| Category | Meaning |
| --- | --- |
| `included` | The verified snapshot contains your exact current Manifest hash |
| `stale` | It contains an older revision of your identity |
| `not_present` | The complete verified snapshot does not contain your identity |
| `contradictory` | It contains a conflicting or forked state for your identity |
| `unavailable` | Tor/HTTP failure; nothing was verified |
| `rejected` | Wrong Node key, corrupt chunk, broken binding, exhausted work budget, or a checkpoint that does not descend from the one saved at your previous check |

Overall status `CURRENT_STATE_INCLUDED_AT_ALL_SELECTED` exits 0; partial or no
confirmation exits 6 with the same JSON.

Checkpoint ancestry is checked across your checks (decision D-052): each Node
signs its checkpoints as one chain, generation by generation. With
`--save-status` the command remembers the checkpoint every Node served; at the
next check the new checkpoint must descend from it, otherwise the Node is
`rejected` with `SOURCE_CHECKPOINT_ROLLBACK`, `SOURCE_CHECKPOINT_EQUIVOCATION`
or `SOURCE_CHECKPOINT_CHAIN_BROKEN` and keeps that state until you act. Each
Node row shows `checkpoint_consistency`: `BASELINE` (first check of this Node
key), `CONSISTENT`, or `NOT_EVALUATED` when a predecessor was unreachable or the
gap exceeds 32 checkpoints. The checkpoint age is shown for your judgement only:
`freshness` remains `NOT_EVALUATED` because no freshness threshold is approved
yet, and `operator_independence` is always `NOT_ESTABLISHED`: several keys may
belong to one operator. Membership is evidence about those Nodes' current
snapshots, not a promise that browsers will load them. See the
[acceptance scenario and remaining work](project-status.md#end-to-end-acceptance-scenario).

Add `--save-status` to the same command to show the result on your status page
(`network:discoverability-check` is an equivalent name). Aggregate counts, each
Node's category and the public checkpoint it served are stored beside your
private configuration, keyed by hashes; Node addresses and keys are not.
Afterwards `/onion-service-discovery/status/` answers the main question
directly, for example *DISCOVERABLE — current revision 1 verified in the signed
snapshots of 2 of 3 checked Discovery Nodes*. DISCOVERABLE requires that a
checked Node includes your exact installed revision **and** exported its own
direct observation of it. Rerun the check after every new revision; evidence for
an older revision is ignored, and evidence older than seven days shows
`DISCOVERABLE_DEGRADED`.

For a complete status page also save the self-check and announcement results:

```sh
sudo -u osd-worker php node-php/bin/onion-service-discovery service:verify /absolute/private/osd-publisher/config.json YOUR_CANONICAL_V3_ADDRESS.onion --save-status
sudo -u osd-worker php node-php/bin/onion-service-discovery network:announce /absolute/private/osd-publisher/config.json /absolute/public-transfer/manifest.json \
  --node FIRST_NODE.onion ed25519:FIRST_NODE_PUBLIC_KEY --node SECOND_NODE.onion ed25519:SECOND_NODE_PUBLIC_KEY --save-status
```

The page then lists ten steps (installation, configuration, identity, Manifest,
self-verification, bootstrap connectivity, announcement, external observation,
searchable state, redundancy/freshness), each PASS, PENDING, WARN, FAIL or
NOT_APPLICABLE, and answers YES, NOT YET, NO, DEGRADED or PROBLEM at the top.
The state below the answer tells where a new revision stands:

| State | Answer | Meaning |
| --- | --- | --- |
| `READY_LOCALLY` | NOT YET | A valid Manifest is installed; no self-check through your Onion has passed yet |
| `CONNECTING` | NOT YET | The self-check passed; no Node has accepted an announcement or included it yet |
| `ANNOUNCED_PENDING_VERIFICATION` | NOT YET | A Node accepted the announcement of this revision and will verify it itself |
| `EXTERNALLY_OBSERVED` | NOT YET | A checked Node holds your Service, but an older revision or without its own observation |
| `DISCOVERABLE` | YES | A checked Node includes this revision and observed it directly |
| `DISCOVERABLE_DEGRADED` | DEGRADED | As above, but the evidence is older than seven days |
| `IDENTITY_CONFLICT` | PROBLEM | A checked Node holds a conflicting signed state for your identity |
| `CONFIGURED_NOT_PUBLISHED`, `WITHDRAWN`, `ERROR` | NO | Publishing is off, the Manifest withdraws the Service, or it is invalid |

The next action names the most urgent step. A failing self-check comes first
and names its cause: the local Tor SOCKS listener (`TOR_PROXY_UNREACHABLE`), the
Onion behind Tor (`ONION_UNREACHABLE`, `TOR_REQUEST_TIMEOUT`), or your web server
(`HTTP_NOT_FOUND`, `HTTP_REDIRECT_REJECTED`, `HTTP_SERVER_ERROR`). A **Warning**
section appears when a checked Node contradicted the checkpoint it served at your
previous check. `doctor CONFIG` reports the same publication facts.
To keep it current and announce automatically, adapt
`deployment/systemd/osd-publisher-check.service`, put your Onion and Node list
into `/etc/onion-service-discovery/publisher-check.env`:

```sh
OSD_ONION=YOUR_CANONICAL_V3_ADDRESS.onion
OSD_NODES=--node FIRST_NODE.onion ed25519:FIRST_NODE_PUBLIC_KEY --node SECOND_NODE.onion ed25519:SECOND_NODE_PUBLIC_KEY
```

and enable `osd-publisher-check.timer`. Every six hours it verifies your own
Onion, checks the Nodes and then runs `network:announce … --if-due`: a Node gets
a hint only for a new revision or while it still lacks your current revision,
at most once per day per Node, with backoff of 1, 2, 4 up to 32 days (decision
D-018). After a new revision it announces by itself once your Onion serves it.
Delete the announce line from the unit to announce only by hand. The status page
shows the results and the next retry.

To verify a snapshot you already downloaded, without network access, use
[offline exact-state membership](../operations/snapshot-membership.md).

## Updates, key rotation, migration and withdrawal

Every change is a new signed revision of your current Manifest. Work on the
administrator machine, transfer only the signed public files, and keep every
signed revision and proof: your complete history is what lets others verify
continuity. The commands below are exercised by `node-php/tests/publisher-lifecycle.php`.
Run them from the repository root after `C="php node-php/bin/onion-service-discovery"`.
Numbered files keep each revision: `manifest-1.json` is the genesis `manifest.json`
from step 3, `service.key` and `passphrase` are your current Service key files.

### Change metadata

```sh
$C manifest:revise manifest-1.json metadata-2.json > draft-2.json
$C record:sign service service.key draft-2.json --passphrase-file passphrase > manifest-2.json
$C manifest:install manifest-2.json hostname /absolute/app/Onion-Service-Discovery/node-php/public
```

An unchanged description needs no renewal. Never sign two different Manifests
with the same revision number: that is a fork, and it stays visible.

### Rotate your Service key

Create the new key first (`key:create service new.key --passphrase-file new-passphrase`),
back it up and use the `public_key` it prints as `NEW_PUBLIC_KEY`. The old key authorizes the change and the new key confirms it:

```sh
mkdir rotation
$C manifest:rotate manifest-2.json ed25519:NEW_PUBLIC_KEY --output-dir rotation
$C record:sign service service.key rotation/rotation-draft.json --passphrase-file passphrase > rotation/rotation-old.json
$C record:sign service new.key rotation/rotation-old.json --passphrase-file new-passphrase --rotation-confirmation > rotation.json
$C record:sign service new.key rotation/successor-draft.json --passphrase-file new-passphrase > manifest-3.json
$C manifest:install manifest-3.json hostname /absolute/app/Onion-Service-Discovery/node-php/public --rotation rotation.json
```

`--output-dir` writes the two drafts as separate owner-private files and never
overwrites existing ones. Installation refuses a new key without its proof.

### Move to a new Onion address

Configure Tor for the new address first and copy its public `hostname`. The
current key signs both the migration proof and the successor:

```sh
mkdir migration
$C manifest:migrate manifest-3.json NEW_CANONICAL_V3_ADDRESS.onion --output-dir migration
$C record:sign service new.key migration/migration-draft.json --passphrase-file new-passphrase > migration.json
$C record:sign service new.key migration/successor-draft.json --passphrase-file new-passphrase > manifest-4.json
$C manifest:install manifest-4.json NEW_HOSTNAME_DIRECTORY/hostname /absolute/app/Onion-Service-Discovery/node-php/public --migration migration.json
```

Update `node.onion` in your publisher configuration to the new address.

### How Nodes learn your history

`manifest:install` also maintains `/.well-known/onion-service-discovery-history.json`
beside your Manifest ([PCP-004](../protocol/proposals/PCP-004-continuity-bundle-draft.md)):
every signed revision, rotation and migration proof, written only when it
connects to the installed Manifest (`"history": "CONNECTED"` in its output).
A Node that meets your Service for the first time after any revision, or after a
rotation or migration, fetches this file once and keeps only records whose own
signatures prove your continuity. Check what Nodes will see:

```sh
$C history:verify /absolute/app/Onion-Service-Discovery/node-php/public
```

Installations made before this feature, or a history built elsewhere, are
rebuilt once from your signed files, in any order:

```sh
$C history:install /absolute/app/Onion-Service-Discovery/node-php/public manifest-1.json manifest-2.json rotation.json manifest-3.json
```

`service:verify` reports `history_bundle` as Nodes would see it over Tor. As a
fallback, a Node operator can still admit a proof file directly with
`storage:admit BACKEND STORE_PATH rotation.json`.

### Withdraw

```sh
$C manifest:withdraw manifest-4.json > draft-5.json
$C record:sign service new.key draft-5.json --passphrase-file new-passphrase > manifest-5.json
$C manifest:install manifest-5.json hostname /absolute/app/Onion-Service-Discovery/node-php/public
```

Withdrawal removes your Service from normal results. Independent Nodes may keep
historical public records; global deletion cannot be guaranteed.

### Check discoverability with your full history

Pass all signed files in order; no hand-made JSON is needed:

```sh
$C network:discoverability-check /absolute/private/osd-publisher/config.json \
  manifest-1.json manifest-2.json rotation.json manifest-3.json migration.json manifest-4.json \
  --node NODE.onion ed25519:NODE_KEY --save-status
```

On the web host the served history bundle holds the same records, which is what
`osd-publisher-check.timer` uses:

```sh
$C network:discoverability-check /absolute/private/osd-publisher/config.json \
  /absolute/app/Onion-Service-Discovery/node-php/public/.well-known/onion-service-discovery-history.json \
  --node NODE.onion ed25519:NODE_KEY --save-status
```
