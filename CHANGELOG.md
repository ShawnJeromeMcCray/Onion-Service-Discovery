# Changelog

## Unreleased

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
