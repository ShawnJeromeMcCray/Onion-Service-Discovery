# Changelog

## Unreleased

- Owner decisions of 2026-10-22: D-003 (current Tor Browser desktop and Android at
  Standard and Safer), D-026 (default source list stays empty until Nodes pass the
  drafted admission process in `docs/governance/source-node-admission.md`), D-028,
  D-040 (retention as implemented), D-041 (current budgets as development
  defaults), D-042 (per-request Tor isolation as implemented), D-043 (no HTTP
  compression) and a hard guard against re-signing a generation (D-052).
- D-028: CI builds a reproducible static client bundle
  (`onion-service-discovery-static-client.tar.gz`, profile `static-client-v1`) and
  attests its build provenance for every merge to `main`; `doctor` on a Client Host
  checks the served client files against `build-manifest.json`
  (`CLIENT_ASSETS_MODIFIED`, `CLIENT_NOT_BUILT`).
- Full blocker review (operators, Node core, browser client):
  - `manifest:install` makes `.well-known` world-readable whatever the umask; a
    missing or unreadable Manifest answers 404 `MANIFEST_NOT_INSTALLED`, and a Node
    with announcements off answers 404 instead of 405.
  - Templates and guides use one configuration path per role
    (`/etc/onion-service-discovery/node.json`, `publisher.json`), the Node units may
    write where `init` puts the store, and every command runs as `osd-worker`.
  - `sync:status` lists every peer; `status` works for a Client Host; `doctor` warns
    when the saved self-check failed.
  - Candidate checks resume one hour after a clock rollback instead of stalling; an
    idle queue no longer writes the store, so snapshots and direct checks stop
    aborting with `EVIDENCE_CHANGED_RETRY`; reused export blobs survive pruning; the
    server search index accepts long multi-byte descriptions.
  - Browser client: results are re-rendered only when the data changes (seconds per
    update saved at Tor Browser's Safer level), the network budget grows with the
    declared snapshot size, bundles may hold more than 256 records, reloading a Node
    replaces its old records, the same name under two keys is flagged, and demo and
    real data no longer mix.
- A Discovery Node no longer fills its store until `STORAGE_BUDGET_EXHAUSTED`
  stops it for good. `node:maintain` removes signed checkpoints nothing needs
  any more, at most 256 per run: the Node's own beyond the newest 256 on its
  chain (D-052 walks go at most 32 back) and peer checkpoints that are neither a
  peer's verified anchor, its pending checkpoint, one of the four newest it
  showed as current nor conflict evidence. Peer sync keeps only those, and only
  witness rows above each anchor; the public index names only the Node's own
  checkpoints. Service records, Node descriptors and conflicting checkpoints
  are never removed. Removal needs storage schema 2, which counts removed
  records so the sequence check stays exact: run `storage:migrate BACKEND PATH`
  once (filesystem format 3, SQLite schema version 2; nothing is rewritten, and
  older releases refuse a migrated store). New stores start in it; private
  backups and `storage:migrate-backend` keep every record's sequence.
  `node:status` and `doctor` report `storage_usage` against the record, state
  and row limits; `doctor` warns `STORAGE_BUDGET_NEARLY_EXHAUSTED` from 80 % and
  `STORAGE_PRUNING_MIGRATION_REQUIRED` for a store not yet migrated.
- A peer whose snapshot has more chunks than a sync step fetches and changes
  every few minutes is verified to the end: its pending checkpoint stays
  pending while the peer serves its chunks (the data API now serves the chunks
  of earlier checkpoints while their export blobs exist, and `node:maintain`
  keeps those of checkpoints current within the last hour), a step fetches up
  to four chunks, and sync restarts at the current checkpoint only when the
  older one is no longer served. A peer's checkpoints older than its newest 64
  that it no longer serves leave a chain gap (`chain_gap`, history no longer
  claimed as proven) instead of a quarantine.

- `doctor` and `security:check` reports have a JSON schema
  (`docs/operations/health-report.schema.json`, spec 32 section 10) that the
  tests apply to every report they produce; `status` for a publisher adds the
  Logical Service Identifier, current key, fingerprint and key epoch.
- A Discovery Node whose store cannot be read stays online read-only (owner
  decision D-045): `node:snapshot` keeps a copy of the last published state
  (`.published-state.json`) beside the export blobs, and while the store is
  damaged or awaits recovery the Node serves `node`, `state`, its checkpoints
  and chunks byte-identically from there. Peers, changes, service lookups and
  announcements answer 503 `NODE_DEGRADED_READ_ONLY`, server search pauses,
  the status page and `status` show DEGRADED (spec 47 section 39 names:
  HEALTHY, READ_ONLY, DEGRADED, ERROR) and `doctor` reports `node_health` and
  warns with `PUBLISHED_STATE_STALE` when the copy lags the store. A public
  announcement no longer opens a damaged store for writing.
- Search after a view update reuses normalized text and look-alike forms by
  content: with 10,000 entries the page no longer spends about a second
  normalizing every update. The NoJS server search shows the browser's
  invisible-character and mixed-script notes.
- Look-alike names are flagged (owner decision D-024): the browser compares
  names by their UTS #39 confusable skeleton, built from the Unicode 17.0.0
  `confusables.txt` and the client's own frozen NFD, so the result does not
  depend on the browser's Unicode version. A name that looks like a pinned name
  under another key gets a warning, names of different identities that look
  alike get a note, and pin labels and the pin import preview are checked too.
  Names are never rewritten or hidden. The table adds about 38 KB to
  `client.js`; `tools/generate_confusables.py` rebuilds and checks it against
  the pinned source hash.
- Browser tests fix the verifier's clock through the page: since 2026-10-19
  the aging fixtures made six tests fail.
- The decision register records the owner's decisions of 2026-10-15: D-004
  (origin trust and server fallback as implemented), D-030/D-031 (keyless
  Sigstore signing and provenance through GitHub attestations), D-034 (no logs
  by default), D-037 (default switches as implemented) and D-053/D-054
  (Category Registry v1 frozen, language-tag grammar approved; the registry
  files, generated constants and PCP-001 say so).
- Keys have a human-comparable fingerprint (owner decision D-025): SHA-256 over
  a domain and the raw key, 150 bits as six groups of five Base32 characters.
  The browser shows it for identity keys, pins, the pin import preview, Node
  keys of loaded sources and the keys of Client Host vouched or visitor-added
  Nodes; the status page shows the Service and Node key fingerprints and
  `node:identity` prints `node_key_fingerprint` and puts it in the visitor line.
  Vectors are generated independently in Python and checked in PHP and the
  browser.
- Search results are ranked by text relevance (owner decision D-023) in the
  browser and in the server search alike: an exact normalized name first, then
  names that start with the query, names containing every term and finally
  other matches; ties by the normalized name without leading ASCII punctuation,
  then the identity key. Case variants and a leading "!" no longer move a name
  ahead, and Node or observation counts never rank. Shared fixtures are now
  `local-search-v2.json`. **Upgrade note:** a Node with server search enabled
  reports `SEARCH_INDEX_STALE` until `node:snapshot … --rebuild-derived` builds
  the new index format.
- Publisher Node checks evaluate checkpoint freshness (owner decision D-036): a
  Node counts toward discoverability only while its signed checkpoint is at most
  seven days old and not dated more than five minutes ahead. A Node that
  includes the revision in an older checkpoint is reported as `OUTDATED` or
  `CLOCK_AHEAD`, saved as `outdated_checkpoints`, warned about on the status
  page and no longer makes the Service DISCOVERABLE.
- PHP 8.3 is supported (owner decision D-002: 8.3+, 8.5 preferred), so the
  distribution PHP of Ubuntu 24.04 LTS works. `composer.json` requires `^8.3`,
  Composer's platform check refuses an older PHP, `doctor` passes on 8.3 to 8.5
  and warns on a newer untested minor version, and CI runs every PHP test,
  the protocol conformance suite and the fuzzer on a pinned `php:8.3-cli` image
  (8.3.35) besides 8.4 and 8.5.
- Browser client on slow devices: a record already verified in this session is
  not verified again when another Node serves the same bytes, so loading three
  Nodes costs little more than loading one. The 90-second limit per source now
  counts network time only; signature checking stays bounded by the record and
  byte limits, each loading source shows how many records it has checked, and
  Cancel takes effect within 64 records. Without JIT (Tor Browser's Safer
  level) checking took about twice as long as with it in a local measurement.
- Deployment: the nginx, Apache, PHP-FPM and systemd templates are hardened
  (no server version, header and body limits, no uploads or remote includes,
  no core dumps, empty capability sets, kernel and namespace protections, file
  limits). New snippets add only the OSD routes to an existing website, and
  static examples serve a self-hosted browser client with the same headers as
  the PHP host. A test requests 39 likely secret paths for every role and
  expects refusals without content. Relay paths exist only for the Client Host
  and combined roles: a publisher answered them with 503 instead of 404. New
  documents: the hardening matrix (spec 18 section 55), an incident runbook for
  lost or exposed keys, conflicting peers, damaged stores and leaked passphrase
  files, and the privacy data inventory and data flow; `PRIVACY.md` describes
  the current runtime.
- A Client Host can vouch for the keys of the Nodes it lists: the optional
  `source_node_keys` (written by `init --source ONION KEY`) reaches the browser
  client, which fills in those keys read-only and refuses a source that signs
  with another key from the first load on, instead of trusting first contact in
  every new session.
- Operator CLI: failures print `detail` (where, for example the configuration key
  path and rule; never the value), `meaning` from the error registry and a `help`
  page; `config:validate` names every refused key. `status` works for publishers
  and shows the role, Onion and Node facts; `status --check` treats the new
  waiting states as waiting. `storage:verify` (also `db:verify`, `restore:verify`)
  is read-only and reports a pending recovery instead of repairing it.
  `quarantine:list` includes quarantined peers with their evidence hashes;
  `sync:run CONFIG` steps the peer that is due first. `release:info` identifies
  the build from the package metadata. `doctor` reports export usage and timer
  freshness from stored times; `security:check` adds focused findings with
  remediations (file modes, Tor keys near OSD files, web root contents, packaged
  files changed since packaging). `node:maintain` prunes unreferenced export
  blobs older than one hour, so the export directory no longer fills up.
- Browser client evidence: every result says how many loaded sources include
  its current revision; the details name the sources that supplied it, those
  that still serve an older revision and those that do not list it, the
  current signing key after a rotation, earlier onion addresses, the Manifest
  hash and the Nodes that observed the revision (with the latest observation
  time). Entries supplied only by Nodes later set aside are flagged. Each source
  shows its signed checkpoint generation and age; a Node that signed two
  conflicting records can be reported with a saved evidence bundle. The footer
  and `build-manifest.json` carry the client and protocol version.
- Browser client accessibility and identity safety: without JavaScript only an
  explanation and the server-search option are shown; load results and search
  counts are announced once instead of on every update, and high-risk pin
  changes use an alert; keyboard focus, open details and the result page
  survive updates; repeated controls name their identity; every notice carries
  a marker and a written severity, and text fields reach 3:1 contrast. A result
  with a pinned name under another key is flagged, the pinned identity leads its
  group, invisible characters are reported (also in pin labels and imports),
  keys and onion addresses can be copied, and the privacy wording no longer
  overstates: the page says what a Client Host relay learns. New security copy
  registry (`docs/ux/security-copy-registry.md`) and accessibility notes.
- Publisher Nodes live in the validated configuration: the optional
  `publisher_nodes` list (Onion plus independently obtained Node key, at most 16)
  is checked by `config:validate`, written by `init … --node ONION KEY`, and used
  by `service:membership` and `service:announce` when no `--node` is given;
  `service:verify` checks `node.onion` when no Onion is given. The publisher
  timer therefore needs no environment file, and a typo fails at validation
  instead of silently in a nightly job. `doctor` warns when a publishing
  installation has no Nodes. Explicit `--node` triples keep working.
- Publisher dashboard follows the states of spec 47: `CONNECTING`,
  `ANNOUNCED_PENDING_VERIFICATION` and `EXTERNALLY_OBSERVED` between
  `READY_LOCALLY` and `DISCOVERABLE`, derived from the saved self-check,
  announcements and Node checks. The next action is the most urgent one: a
  failing self-check comes first and names its cause. Transport failures are
  typed: `TOR_PROXY_UNREACHABLE`, `ONION_UNREACHABLE`, `TOR_REQUEST_TIMEOUT`,
  `HTTP_NOT_FOUND`, `HTTP_REDIRECT_REJECTED`, `HTTP_SERVER_ERROR` (workers keep
  treating them as transient). A self-check expires after seven days, one from
  before the installed revision no longer counts, Nodes that contradicted their
  previous checkpoint (D-052) raise a visible warning, a migrated Manifest names
  the new Onion instead of "invalid", and the page links the public Manifest and
  history bundle. `doctor` and `diagnostics:export` report the publication
  (`manifest_state`) and fail on a missing or invalid Manifest. The SOCKS connect
  phase may now use the whole `transport.timeout_ms`, because it includes Tor's
  circuit to the Onion. Both real-Tor lab scripts pass with Tor 0.4.9.
- One service user on a host: the publisher timer now runs as `osd-worker`, the
  same user as the PHP-FPM pool, and the guides run `init` and every check with
  `sudo -u osd-worker`, so the status page can read the evidence the checks save.
  An owner/mode table is in the installation guide; the status page says so when
  saved evidence exists but the web server cannot read it.
- Candidate quarantine is a long backoff, not a permanent exclusion: a Service
  whose Manifest failed verification is checked again after about seven days.
  Transport failures (Tor, HTTP status, media type, size) only back off.
  `candidate:retry CONFIG ONION --acknowledge-quarantine` checks one candidate at
  the next worker run; sources and evidence are kept (`CANDIDATE_NOT_FOUND`,
  `CANDIDATE_BUSY`).
- `exports:prune` no longer refuses the export directory of a Client Host with
  visitor-chosen Nodes (it treated the visitor relay budget file as unsafe).
- The black-box conformance runner sends larger suites in batches of at most 256
  requests and 1 MiB per adapter run instead of refusing them.
- Browser client: saved pins can no longer be wiped by a failed restore (labels
  are cut at code points; nothing is written before pins were restored), pin
  import keeps existing pins and reports skipped conflicts, a crashed verifier
  is reported instead of hanging, cancel marks unstarted sources, and typed codes
  are explained in plain language.
- SQLite: a failed write now rolls back its SQL-started transaction on PHP 8.3
  too, instead of leaving the connection inside an open transaction.
- Checkpoint ancestry in the publisher's Node checks (owner-approved D-052):
  `network:discoverability-check` compares each Node's checkpoint with the one
  saved at the previous `--save-status` check and rejects rollback, equivocation
  and broken chains before downloading chunks (at most 32 predecessors per Node;
  an unreachable predecessor or longer gap is not evaluated). Saved anchors are
  keyed by hashes; a rejected Node keeps its anchor.
- Development runtime package (owner decision D-029): CI builds
  `onion-service-discovery-development-runtime.tar.gz` with the locked PHP runtime
  dependencies and the built client twice from independent installs, compares
  them byte for byte and runs the packaged checks without Composer or network.
  Each merge to main gets verified GitHub provenance and SBOM attestations and a
  90-day workflow artifact, marked as an unsigned development preview. CI now
  also regenerates the PCP-004 history vectors.
- Bounded automatic announcement (owner decision D-018): `network:announce …
  --if-due` in the publisher timer sends a hint only for a new revision or while
  a Node still lacks the current revision, at most once per day per Node, with
  backoff of 1, 2, 4 up to 32 days, and only after a passing self-check of the
  same Manifest within 24 hours. Decisions are reserved under a lock; only hashed
  schedule state is saved. `network:announce` also takes the `--node ONION KEY`
  list; HTTP 429 is reported as `ANNOUNCEMENT_RATE_LIMITED`; the status page shows
  result counts and the next retry. `service:membership` and `history:install`
  read the served PCP-004 history bundle directly, so the timer needs no separate
  history file.
- PCP-004 service history bundle (owner-approved): `manifest:install` maintains
  `/.well-known/onion-service-discovery-history.json`; Nodes fetch it once when a
  Manifest does not connect, so Services stay discoverable after revisions, key
  rotations and Onion migrations. Shared vectors pass in PHP, TypeScript and
  Python (248 cases each); `history:install`/`history:verify`; missing history now
  backs off instead of quarantining. Owner decisions D-018, D-029 and protocol v1
  (D-005–D-017, D-052) recorded.
- `init` now writes Node, combined and Client Host configurations too, with all
  private paths in the configuration's own directory and the next steps printed.
- Status page Node card and `node:identity`; publisher lifecycle walkthrough
  (revision, rotation, migration, proof delivery, withdrawal) executed by a test;
  `--output-dir` drafts and multi-file history; operator contract, configuration
  reference and generated error-code registry.
- Let visitors add their own Nodes: an opt-in Client Host `visitor_sources`
  switch relays visitor-chosen v3 Onions on the four fixed protocol paths under a
  separate budget; the browser requires the expected Node key, verifies as for
  listed sources, stores added Nodes only on request and forgets them with
  Forget local data. Tested in Chromium and through the PHP front controller.
- Add `service:membership`: a publisher checks over Tor whether explicitly
  selected Nodes' complete signed snapshots include its exact current state, with
  one category per Node (included, stale, not present, contradictory, unavailable,
  rejected); freshness and operator independence stay unevaluated (ancestry:
  see the D-052 entry above).
- Let the publisher status page answer “Can this Service be found?” from saved
  `network:discoverability-check --save-status` evidence: DISCOVERABLE needs the
  exact installed revision plus the checked Node's own direct observation;
  conflicts, stale revisions and evidence older than seven days are shown.
- Add the specification's `network:discoverability-check` and `network:announce`
  command names and the canonical `GET /v1/services/by-id/{logical_service_id}` route.
- Status page: plain answer (YES/NOT YET/NO/DEGRADED/PROBLEM), the ten-step
  publication pipeline, role explanations, logical service ID and key epoch;
  `service:verify` and `service:announce` accept `--save-status`. Evidence files
  take the configuration's group so the web runtime can read them.
- Add the specification's missing commands: `storage:verify`, `snapshot:build`,
  `peer:remove`, `key:service:rotate`, an explicit `key:node:rotate` refusal,
  `storage:migrate-backend` and the redacted `diagnostics:export` support bundle.
- Add an Apache httpd 2.4 template (tested with PHP-FPM; it refuses bodies over
  1 KiB because LimitRequestBody does not cover proxied FastCGI requests) and a
  publisher check timer; document the Ubuntu 24.04 PHP 8.3 limitation.
- Point clone instructions at the default branch now that production-completion
  work is merged.

- Add a loopback-only executable visitor demo, actual UI screenshot, role-specific
  installation/publishing/Node guides, troubleshooting and evidence map.
- Exercise fresh-copy Publisher, Node and keyless Client Host walkthroughs in
  runtime and packaged-install checks; add the real demo browser acceptance test.
- Enable and verify private GitHub vulnerability reporting; add sanitized bug-report
  templates and a practical support entry point.
- Reuse frozen Unicode search metadata with mutation-safe weak caching and record
  first-query cost; keep local storage failures out of peer quarantine penalties.

- Add default-off bounded candidate announcement hints and a one-shot Tor
  operator command; accepted hints never claim observation or index inclusion.

- Add owner-approved genesis-key service lookup with complete rotation/migration,
  persistent fork and withdrawal evidence on both storage backends.
- Add successful real private-Tor application tests and signature-verified
  actual Tor Browser local headless checks, with explicit certification limits.

- Add exact-state offline complete-snapshot membership in PHP, TypeScript and
  independent Python, shared adversarial vectors and an operator CLI.
- Add local peer source groups and concentration diagnostics without key-count trust.
- Use Tor SOCKS extension format zero with fresh per-request isolation parameters.

- Read all 55 refreshed source documents and record current requirement/security inventories.
- Add draft shared category registry and RFC 5646 validation in three languages, local filters and grouped result pagination.
- Add publisher-only initialization and a truthful read-only local status page.
- Fix FilesystemStore commit ordering, introduce native format 2 and migrate historical format-1 stores without rewriting evidence.
- Share persistent candidate resource budgets across manual enqueue and PeerSync, cap pinned peers and retain typed SQLite policy errors.
- Add syntax security checks and corruption, process interruption, ENOSPC, quota and privacy regressions.

- Initialize repository structure and import the current specification corpus.
- Record unresolved owner/reviewer decisions and production blockers.
- Add local integrity and credential checks.
- Add strict Tor v3 address and bounded JSON validators in PHP, TypeScript and independent Python.
- Add 66 shared input vectors, strict typechecking and a fail-on-missing-tool harness.
- Dedicate project code under CC0-1.0 per owner authorization.

No application or production release is available.
