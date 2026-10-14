# Changelog

## Unreleased

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
  rejected); freshness, ancestry and operator independence stay unevaluated.
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
