# Data inventory

What each role stores, where, for how long and who can see it. Specification 16,
section 47 requires this inventory; the [data flow](data-flow.md) shows the
network side. It describes the reference implementation and the reference
deployment templates. A modified build or deployment can behave differently,
and a malicious serving host can replace the browser code.

Classes (specification 16, section 3): **A** public protocol data, **B**
public-but-operational data, **C** local user data, **D** operator-sensitive
data, **E** security diagnostics. **S** is the separate server-search query
class (section 51).

## Visitor's browser

| Data | Class | Source | Purpose | Stored | Sent to, visible to | Retention | Deletion | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Search query and filters (language, category, adult display) | C | Typed by the visitor | Local search | Page memory only; the query is cut to 256 characters and 16 terms | Nobody: no request, URL, worker command or saved profile carries it | Until the page is left | Leave or reload the page | Chromium browser tests: query network invariance |
| Loaded signed records | A | Nodes (relay or direct), imported bundles, the offline demo | Verification and local search | Worker memory, at most 10,000 records | Nobody | The browser session | Clear index, Forget local data | `client-model.test.mjs`, browser tests |
| Pins: genesis key, saved Onion, anchor and latest hash, highest revision, label (128 characters), conflict flag; at most 256 | C | The visitor's pin action | Continuity warnings | Session memory; `localStorage` key `onion-service-discovery-pins-v1` only with "Remember pins" | Nobody: never sent | Until removed. Tor Browser deletes site data when it closes and on New Identity | Remove local pin, untick "Remember pins", Forget local data | Browser tests: pin restore, forgetting, unavailable storage |
| Nodes the visitor added: Onion and expected key, at most 8 | C | Typed by the visitor | Load extra sources | Session memory; `localStorage` key `onion-service-discovery-sources-v1` only with "Remember the Nodes I add" | The Client Host relay learns each Onion when it is loaded | As pins | Remove this Node, untick the option, Forget local data | `client-host.php`, `visitor-relay-http.php` |
| Source ledgers, continuity witnesses (at most 528 records), walk cursors (at most 16) | A, B | Loaded data | Detect rollback, equivocation and key changes | Worker memory only | Nobody | The browser session | Forget local data; Clear index keeps them | `source-witnesses.test.mjs`, `source-loader.test.mjs` |
| Pin backup `onion-service-discovery-pins.json` (the pin fields above) | C | "Export pins" after a confirmation | A private copy | The visitor's download folder | Whoever receives the file; it can reveal interests | Until the visitor deletes it | The visitor | `client-model.test.mjs` (backup parsing) |
| Server-search query | S | The disclosed HTML form (POST) | Server-rendered results | Request memory on the serving Node only | The serving Node | Not stored or logged by the reference application | — | `server-search-http.php`: POST only, no echo, not in the test server log, no cookie |

No cookies, accounts, installation identifiers, analytics, telemetry or remote
fonts, images or scripts exist in the reference client. Opening a result is
ordinary Tor Browser navigation after an explicit click, without a referrer and
without any report to a Node.

## Client Host

| Data | Class | Source | Purpose | Stored | Sent to, visible to | Retention | Deletion | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Configuration: role, features, source Onions, paths | D (paths), A (source Onions) | The operator | Serving and relaying | Private file (0600) | `config.json` shows the sources, profile and visitor switch, never paths | Until changed | The operator | `client-host.php` |
| Relay requests: Node Onion, fixed endpoint, time | B | Visitors' browsers | Fetch public data over Tor | Request memory only | The Data Node sees a Tor connection from the Client Host, not the visitor | Not kept | — | `client-host.php`, `client-relay.php` |
| Relay budgets `.relay-budget`, `.visitor-relay-budget` | B | Each relay request | Rate limit without identifiers | Export directory: a token count and a time | Nobody | Overwritten on every request | — | `visitor-relay-http.php` (no Node address stored) |

## Discovery Node

| Data | Class | Source | Purpose | Stored | Sent to, visible to | Retention | Deletion | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Signed records: Manifests, rotation and migration proofs, Node descriptors, direct observations, checkpoints | A | Direct checks, peer sync, `storage:admit`, `storage:import` | Continuity, snapshots, search | Store (`storage.path`); snapshot files in the export directory | Public through the data API, snapshots, peers and copies elsewhere | Kept as continuity evidence. A withdrawal stops normal listing | No routine deletion; `exports:prune` removes only unreferenced derived files | Storage and data API tests |
| Candidates: Service Onion, state, attempts, error, sources (`announcement`, `manual` or peer Onions) | B | Announcements, `candidate:add`, peer sync | Direct checks | Store | Local; the status page shows counts per state | Kept; a quarantined one is rechecked after about seven days | No command | `candidate-queue.php`, `candidate-announcement.php` |
| Own observation rows: Onion, Manifest hash, observation hash, times, genesis key | B | Own direct checks | Keep what this Node saw | Store | Local; the signed observation is public | Kept | No command | `direct-verifier.php` |
| Provenance of synced records: source Onion and Node key, checkpoint, times | B | Peer sync | Provenance | Store | Local | Seven days, removed by `node:maintain` | `node:maintain` | `peer-sync.php` |
| Peers: Onion, expected key, state, failures, error, local group; sync anchors | B | `peers:add`, sync | Synchronization | Store | `/onion-service-discovery/v1/peers` publishes active peers' Onions and keys | Kept; `peers:disable` keeps the row | No command | `peer-sync.php`, `peer-source-groups.php` |
| Checkpoint evidence: at most 8 hashes per Node key and generation | E | Peer sync | Equivocation evidence | Store | Local; the signed checkpoints are public | Kept | No command | `peer-sync.php` |
| Request budget `.request-budget` | B | Data API, announcement and search requests | Rate limit without identifiers | Export directory: a token count and a time | Nobody | Overwritten on every request | — | `request-budget.php` |
| Copy of the last published state `.published-state.json`: the state document and the signed Node descriptor | A | `node:snapshot`, `node:init` | Serving the last published snapshot while the store cannot be read (D-045) | Export directory | Public: the same bytes the state and Node routes serve | Replaced by every publication; never pruned | — | `degraded-mode-http.php` |
| Node key and passphrase file | D | The operator | Signing | Owner-only files | Never sent | Until the operator removes them | The operator | `protected-signer.php` |
| Status page facts | A, B | Configuration and store | Operator and visitor information | Not stored; rendered per request | Public: Node Onion and key, storage state, counts by state, record count, snapshot generation and time, last check and sync times; while the store cannot be read only the state `DEGRADED`, the identity and the snapshot generation and time | — | — | `node-status.php`, `degraded-mode-http.php` |

## Publisher

| Data | Class | Source | Purpose | Stored | Sent to, visible to | Retention | Deletion | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Signed Manifest and history bundle | A | Signed offline by the operator | Public discovery | `node-php/public/.well-known/` | Public; Nodes copy them | Copies held by Nodes are kept; a withdrawal stops normal listing | Cannot be erased from independent Nodes | `manifest-installer.php`, `publisher-lifecycle.php` |
| Service key and passphrase | D | The operator | Signing | Offline administrator machine | Never sent | The operator's choice | The operator | `protected-signer.php` |
| Configuration: own Onion, `publisher_nodes` with expected keys, paths | D (paths), A (Onions, keys) | The operator | Checks and announcements | Private file (0600) | Not shown publicly | Until changed | The operator | `configuration.php` |
| Saved evidence `CONFIG_NAME.discoverability.json`: self-check (time, Manifest hash, result); membership (time, Manifest hash, Logical Service ID, revision, key epoch, counts, category per Node); checkpoint anchors (hash, generation, time, SHA-256 of the Node key); announcements (time, result, Manifest hash, attempts, next time) | B | `service:verify`, `network:discoverability-check` and `network:announce` with `--save-status` or `--if-due` | The status page | Beside the configuration, with its group, at most 0640. Per-Node rows are keyed by the SHA-256 of the Node's Onion, never the Onion or key | The status page shows aggregate facts publicly | Each check replaces its section; announcement rows for at most 16 Nodes | Deleting the file forgets all of it at once | `publisher-status.php`, `publisher-membership.php`, `announcement-schedule.php` |

## Logs, backups and support data

| Data | Class | Source | Purpose | Stored | Sent to, visible to | Retention | Deletion | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Web server access logs | B | Requests | — | Off in the templates; an existing Apache website must exclude OSD requests itself | The operator, if enabled | The operator's rotation | The operator | Loopback run (manual) |
| Error logs | E | Web server and PHP | Faults | Web server at `crit` only; PHP and FPM worker output off | The operator | The operator's rotation | The operator | — |
| Timer job output | E | CLI jobs | — | Discarded (`StandardOutput=null`); systemd still records start, stop and exit status | The operator | The journal's retention | The operator | — |
| Private backups (`storage:backup`) | D, B | The operator | Recovery | Operator-chosen path; no keys | Never sent | The operator's choice | The operator | `storage-backup.php` |
| Support bundle (`diagnostics:export`) | E | The operator | Problem reports | Printed only | Whoever the operator sends it to; paths, Onions, keys, passphrases, sources, pins and queries are left out | — | — | `operator-commands.php` |

Every outbound request of a Node, a Client Host or a publisher check goes
through the configured loopback Tor SOCKS listener with a fresh isolation
parameter. There is no direct or clearnet fallback.
