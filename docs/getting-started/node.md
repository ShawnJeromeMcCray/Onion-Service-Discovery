# Run a Discovery Node in staging

A Node collects signed public evidence, checks services directly over Tor and
publishes its own signed snapshots. It is separate from a Publisher and from a
Client Host. Install the [runtime prerequisites](installation.md) first and
provide an existing Tor v3 Onion and loopback SOCKS listener. No public seed
network is supplied. These steps are a staging workflow, not production approval.

## 1. Prepare one configuration

From the repository root, as the dedicated service user:

```sh
umask 077
mkdir -p /absolute/private/osd-node
cp node-php/config/node.example.json /absolute/private/osd-node/config.json
```

Edit `config.json`:

| Field | Set to |
| --- | --- |
| `role` | `node` |
| `storage.backend` | `filesystem`, or deliberately choose `sqlite` |
| `storage.path` | `/absolute/private/osd-node/data` (filesystem) or `/absolute/private/osd-node/data.sqlite` |
| `export_path` | `/absolute/private/osd-node/exports` |
| `node.onion` | Your real canonical v3 Node Onion; replace the synthetic example |
| `node.key_path` | `/absolute/private/osd-node/node.key` |
| `features.mirror_directory` | `true` to serve directory snapshots |
| `features.discover_services` | `true` to explicitly admit and directly check Service candidates |
| `features.sync_with_peers` | `false` until you choose pinned peers; then `true` |
| `features.publish_service` | `false` unless separately publishing this Node as a Service |
| `features.accept_announcements` | `false` unless deliberately enabling the bounded draft endpoint |
| `features.server_search` | `false` unless deliberately enabling the separate NoJS contract |
| `transport.proxy_port` | Your actual loopback Tor SOCKS port, normally 9050 |
| `sources`, `allowed_client_origins` | Empty for a Node-only same-origin deployment |

Paths in the configuration must be actual absolute owner-private paths.
The configuration has no passwords. The Node uses its own application signing
key, never a Tor private key or the Publisher's Service key.

## 2. Initialize identity and the first snapshot

Create an owner-private 0600 passphrase file using a private input method, as
explained in [key custody](../signing/offline.md). Then run:

```sh
php node-php/bin/onion-service-discovery config:validate /absolute/private/osd-node/config.json
php node-php/bin/onion-service-discovery key:create node /absolute/private/osd-node/node.key --passphrase-file /absolute/private/osd-node/passphrase
php node-php/bin/onion-service-discovery node:init /absolute/private/osd-node/config.json --passphrase-file /absolute/private/osd-node/passphrase
php node-php/bin/onion-service-discovery node:snapshot /absolute/private/osd-node/config.json --passphrase-file /absolute/private/osd-node/passphrase
php node-php/bin/onion-service-discovery node:status /absolute/private/osd-node/config.json
```

Keep the public key printed by key creation for independent source pinning.
`node:init` initializes the selected store/export directory and signs the Node
descriptor. Repeating it with unchanged capabilities does not invent a revision.
The first snapshot has no discovered Services until evidence is admitted. An empty
valid directory is not proof that discovery work has happened.

Adapt FPM/nginx and existing Tor forwarding using
[the installation guide](installation.md#web-serving-and-operations). Public Node
routes live under `/onion-service-discovery/v1/`, including `node`, `state`,
`snapshots/<checkpoint-hex>` and `chunks/<chunk-hex>`. Node-only mode does not supply
a full Client Host; run a separate Client Host, or deliberately use the combined
example with a reviewed multi-worker relay deployment.

## 3. Directly check a cooperating Publisher

Use a real opted-in Publisher with a signed Manifest at its Well-Known endpoint:

```sh
php node-php/bin/onion-service-discovery candidate:add /absolute/private/osd-node/config.json PUBLISHER_V3_ADDRESS.onion
php node-php/bin/onion-service-discovery node:work-one /absolute/private/osd-node/config.json --passphrase-file /absolute/private/osd-node/passphrase
php node-php/bin/onion-service-discovery node:snapshot /absolute/private/osd-node/config.json --passphrase-file /absolute/private/osd-node/passphrase
```

Replace the address placeholder. On a successful direct check expect `VERIFIED`;
failed verification or incomplete continuity does not become an active identity.
A received hint is unconfirmed until direct verification. The bounded worker does
one unit of work; it is not a continuously running daemon. Inspect `candidate:list`
and `quarantine:list` for queue/error state. Do not erase conflict evidence to
force a service into the index.

## 4. Select and synchronize peers explicitly

Obtain a peer Onion and expected Node key independently; never infer its key from
an untrusted directory response. Enable `features.sync_with_peers`, then run:

```sh
php node-php/bin/onion-service-discovery peers:add /absolute/private/osd-node/config.json PEER_V3_ADDRESS.onion --expected-node-key ed25519:PEER_PUBLIC_KEY
php node-php/bin/onion-service-discovery sync:peer-step /absolute/private/osd-node/config.json PEER_V3_ADDRESS.onion
php node-php/bin/onion-service-discovery sync:status /absolute/private/osd-node/config.json
```

Expect `CURRENT` only after complete bounded verification. `PROGRESS` requires
another scheduled step after the reported peer retry time; it does not activate a
partial snapshot. Each step limits network requests and ancestor work. A long
absence resumes ancestry across steps. A late first contact does not prove all
history back to genesis. Imported observations remain remote claims, not your
Node's own direct checks. Local storage failures back off without blaming peers.

`peers:group CONFIG ONION GROUP` records your local source-family judgment;
several keys still do not prove independent operators.

## 5. Operate and verify

Use the [systemd templates](../../deployment/systemd) as host-specific references,
not automatic installation. Schedule bounded direct checks, snapshot publication,
peer steps and maintenance without overlaps; retain error codes/counters, not
queries or raw network inputs. Back up data and keys separately and test restore
before enabling unattended work. See [deployment and recovery](../operations/deployment-and-recovery.md).

Default capacity is 10,000 immutable records, including history and observations,
not 10,000 active Services. Long-term growth and higher tiers remain open. Read
[measured capacity](../performance/README.md) before choosing a workload.

Hand a selected snapshot and its chunks to the Publisher for an independent
[exact-current-state membership check](../operations/snapshot-membership.md).
A Node's assertion that it indexed a service is not that proof. Public Tor,
independent-source acceptance and production isolation still require evidence
beyond this staging installation.
