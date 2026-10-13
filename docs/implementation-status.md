# Implementation and release status

The baseline repository contained only a one-line README. The implementation is
on `implementation/repository-foundation`; incremental PRs #1–#6 have been merged
by the repository owner. Subsequent development continues on that branch. The project now contains
an executable development Node, offline Service signer, separate Client Host,
locally verifying/searching browser client and mature offline release verifier.
There is no approved production release.

The next continuation adds offline exact-state membership in complete signed
draft snapshots in PHP/TypeScript/independent Python, including a split-view fork
against the owner's known history. The 24 new shared cases bring the black-box
suite to 226 cases per language. It does not evaluate freshness/checkpoint ancestry
or complete the new active-index checkpoint/onboarding workflow. Local peer
source grouping now exposes concentration without identity-count trust; gradual
learned-source admission remains open. Tor transport uses explicit format-zero
SOCKS isolation. See [continuation evidence](production-completion-review.md).

The 2026-10-13 refresh found 55 active sources: 43 updated, five added, seven
unchanged. The full updated corpus has been read in the mandatory order.
Implementation alignment remains partial. Historical reports describe the earlier draft. Current reports cover specific
updated draft behavior, without full revised-specification conformance. See
[source refresh](specification-refresh.md).

The earlier 50-source baseline was read and imported with
observed links, source modification times and SHA-256 hashes. REVIEWED means
implementation-agent review, not human approval. Later specifications require
FilesystemStore alongside optional SQLite, separate executable hosting roles,
offline Service signing and established release trust-root verification.
D-001 CC0-1.0 is approved under the owner's unrestricted-use instruction; other
required decisions/reviews remain unresolved.

## Executed development evidence

- 202 independent black-box cases pass per PHP/TypeScript/Python, no failures or
  skips, including exact signed bytes, absent optional metadata, strict Ed25519
  edge cases and identity conflict/rotation/migration sequences.
- The shared unit/integration harness has 36 passing checks on digest-pinned
  PHP 8.5.11 and PHP 8.4.26, including PHPStan level 8, both storage backends,
  read-only diagnostics, staged private backup, quotas, journal-first commit recovery and explicit historical-format migration.
  Separate actual ENOSPC checks pass on isolated 2 MiB tmpfs on both PHP minors
  and both backends, preserving record/cursor and resuming after space restoration.
- The seeded differential malformed-input run rejects 1,024 inputs per language
  with matching error classifications. This is smoke fuzzing, not exhaustive
  or coverage-guided assurance.
- Four controlled Chromium browser tests verify signed three-source import,
  query isolation, text-only hostile metadata, withdrawal, local pin handling,
  blocked profile storage, local category/adult filters and continued local search after a failed refresh.
  They are not Tor Browser desktop/Android evidence.
- The finite TLA+ safety model passes both network outcomes and rejects a
  deliberate rollback mutation. Human model review and unbounded/liveness
  claims remain outside this evidence.
- Fourteen synthetic TUF tests exercise artifact/threshold/expiry/rollback,
  root rotation, bootstrap/clock and path boundaries. No production keys/root
  or human release authorization is inferred.
- S1000 measurements use 1,000 fictional Services, two revisions and five Node
  claims each (7,005 records). Both backends, signed snapshots, indexed service
  lookup and browser verification/search algorithms are measured. This is not
  a live five-Node Tor or mobile benchmark. See the capacity limitations.
- npm, Composer and pip-audit currently report no known advisories in the
  inspected locks. Gitleaks finds no secrets in the inspected committed history.
  Advisory scans and secret detection do not replace independent security review.

Reports and exact scope are linked in [release-gates.json](release-gates.json).
GitHub run [38324913078](https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/actions/runs/38324913078)
passed all eight expanded jobs for commit `7ee3170`, including both PHP minors,
actual initial ENOSPC, dependency scans, Git-history secret detection, packaged
runtime smoke and exact-identity attestation verification. Later durable-phase
ENOSPC and unprovisioned-package diagnostic checks extend that evidence; their
exact latest CI run must be checked separately.

## Available implementation

Current additions include publisher-only setup and safe server-rendered status,
a draft 38-category registry and precise lowercase RFC 5646 validation in three
languages, local filters and result-group pagination, persistent candidate
admission/work budgets shared with PeerSync, bounded pinned peers, and syntax
sink checks. Ten thousand rotated sources cannot reset the tested global quota.
A separate 100,001-row presentation test preserves all grouped result identities;
it does not measure verified signature import or live-network capacity.

FilesystemStore format 2 obeys durable journal COMMIT before HEAD. Explicit
format-1 migration preserves old accepted records/cursors, including interrupted
legacy commits, and rejects corrupted objects before modification. See ADR 004.
Category/language tightening has no automatic continuity-preserving migration;
PCP-001 remains a release blocker. Security inventories cover 345 ASVS requirements
and current OWASP/CWE classes but still require applicability/evidence review.

Strict bounded parsing/canonicalization, six signed-record schemas and mature
crypto feed immutable verified records and deterministic identity graphs. The
required FilesystemStore and SQLiteStore share transaction, archive/backup and
fault tests. Role-restricted encrypted signing, zero-renewal revision preparation,
withdrawal, countersigned rotation, migration and atomic manifest installation
are executable. Tor-only transport, periodic candidates, local direct checks,
resumable peer snapshots, remote provenance retention and signed public exports
preserve local-vs-remote authority. Never-published identities are excluded from
public exports; trusted private backups retain their owner-controlled state.

The browser bundles all code/dependencies locally, supports a generic Client Host
proxy or explicitly configured direct CORS profile, keeps queries local, preserves
pin/conflict high-water marks and warns about potentially confusing Unicode.
No silent server search, account, analytics or third-party assets are enabled.
CLI diagnostics do not repair state or decrypt keys; explicit recovery,
maintenance, export pruning and staged restore have separate commands. Staging
systemd/nginx/FPM templates and operator runbooks are included.

Reproducible unsigned source/browser candidates include a dependency inventory,
file manifest, checksums and explicitly unattested source/hash provenance. The
established TUF verifier never executes artifacts. Authenticated GitHub provenance and SBOM bundles were locally verified against
the exact workflow, commit and development ref, including wrong-ref/commit
rejection. The local build hash matches the distinct GitHub-hosted builder; see
[the scoped report](conformance/ci-attestation-report.json). Production root/key
custody, hardened release-builder policy and stable signing are still open.

## Remaining release gates

Required independent protocol/crypto/model/security and human UX review; actual
Tor Browser desktop/Android and live Tor tests; deployed privilege separation,
physical power-loss/disk-full and authenticated restore/incident drills;
operational-heavy multi-peer soak, broader capacity/profile evidence; independent
release-builder review, production provenance acceptance policy, license review and production trust
root/threshold ceremony. No verified production seed infrastructure is invented.

The 318 extracted candidate requirements now have partial evidence pointers for
283 blocks. These pointers are review starting points, not exhaustive MUST-level
coverage or acceptance of the whole source section. Detailed traceability and
standards/freeze review remain open. See the [draft public handbook](protocol/handbook.md),
[operator runbook](operations/deployment-and-recovery.md), [CLI contract](operations/cli.md)
and [well-known registration preparation](standards/well-known-registration-draft.md).
IANA submission, RFC publication, external audit, stable tags and production
approval have not occurred. Documentation alone does not close a gate.
