# Production completion continuation review

Status: development changes tested; the requested Production Stable outcome is
not complete and is not approved.
Date: 2026-10-13
Starting commit: `4fddde667b1fe67a60b14390e71808e55f123905`.

All 55 active Drive titles/IDs/modification times match the repository manifest.
The mandatory source snapshot was re-read. Archive sources were excluded. The
starting commit's actual GitHub CI [run 38380085827](https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/actions/runs/38380085827)
completed successfully; the earlier foundation branch has no unmerged commits.
The clean-checkout local baseline passed all 36 checks before these changes.

## Implemented and reviewable changes

- Complete signed snapshot membership: independently validate the selected Node
  signature, all chunk hashes/bytes/counts/order, every Service signature and
  owner genesis continuity; compare the exact current tuple. Separate historical,
  stale, withdrawn, omitted, forked and conflicting state. Compare the snapshot
  with the owner's existing evidence to detect a fork hidden by a split view.
- Offline `index:verify-inclusion` CLI: no network/store/private-key capability;
  independently selected Node key required; missing/invalid artifacts fail.
- Local peer grouping: durable in both backends; reject malformed groups and
  in-flight sync races; expose concentration/unknown grouping; duplicate Node
  keys cannot inflate group credit. Counts have no identity authority.
- Tor transport: modern SOCKS extension format zero with fresh per-request
  parameters; exact handshake and non-reuse regression tests.
- D-051 owner-approved genesis identity lookup: complete continuity/fork/withdrawal
  evidence by stable key and historical/current Onion address; both backends.
- Actual private Tor network: successful signed manifest, direct observation,
  signed snapshot/membership and peer sync; no external route.
- Signature-verified actual Tor Browser: headless local application rendering,
  worker signature verification, local search without HTTP requests and pins.

## Evidence and limits

[Three-language report](conformance/production-completion-core-report.json):
226 black-box cases per language, zero failure/skip, including 24 new membership
fixtures. [PHP 8.5 unit report](conformance/production-completion-unit-report.json)
and [PHP 8.4 unit report](conformance/production-completion-php84-report.json)
cover 38 suites including PHPStan level 8, storage recovery and the new CLI/group
checks. Reports are executed development evidence, not full revised-specification
acceptance. The controlled Chromium suite has four passing browser tests;
Chromium is not Tor Browser desktop/Android certification.

[Membership model report](conformance/membership-formal-report.json): finite
two-source model passes; accepting a bare Node assertion yields the expected
counterexample. Primitive soundness/complete-validation/state-derivation are
assumptions. The model does not establish freshness or unbounded correctness.

The signed snapshot format itself is the existing development format. The
membership result explicitly reports freshness/ancestry as not evaluated. No
new stable signed wire type, production root, seed set or green external status
is fabricated. Existing historical reports/requirements remain preserved.

## Self review

External inputs: supplied public snapshot/owner history and local group tokens.
Trust boundary: independent local artifact verification; stored local peer policy.
Network: existing Tor adapter only; offline membership/groups make no request.
The explicit publisher announcement makes one bounded fixed-endpoint POST;
its default-off receiver cannot perform network requests or signing.
Web client: no CSP, runtime origin, remote asset or query-flow change.
Dependencies: none added. Secrets/logging: none added.
Persistence: optional peer grouping and default-off receiver configuration;
received hints use the existing bounded queue/admission schema; no native schema/format
migration or signed-record rewrite. Public/group counts grant no trust authority.
Work limits: descriptor-first 10,000-record/32-MiB checks; merged owner/source
evidence remains bounded at 20,000 records. Negative/overflow/tamper tests remain.
Rollback: remove the new CLI/helper/group display; optional peer field is inert
to earlier sync/storage code. No destructive data migration was performed.
Human review: Protocol/Crypto, Tor transport and final release gates remain open.

## Remaining blockers and exact next dependencies

The machine-readable [47-section production matrix](production-readiness.json)
still has no complete production acceptance. The [decision register](governance/decision-register.md)
records owner approval for D-001 and the proposed D-051 genesis-key choice;
independent cryptographic review remains open.

The D-051 owner-approved logical-ID choice has a tested derived lookup endpoint.
Independent D-051 crypto review and D-052 signed active-index checkpoint freeze
remain open before Production Stable. Current tested choice derives the logical anchor from canonical
genesis `ed25519:` encoding and computes the authorized key epoch from complete
continuity evidence. The owner approved that choice on 2026-10-13; independent review is not implied.
The existing complete-snapshot diagnostic is not the missing active-root scheme.

D-018 still controls final bounded announcement endpoint/media type/default
retry policy. The default-off receiver and one-shot operator Tor announcement
are implemented as [PCP-002](protocol/proposals/PCP-002-bounded-announcement-draft.md).
Automated multi-Node self-announcement/external observation/active-index onboarding
remains incomplete. Local source grouping does not implement bounded learned
probation/referral admission/gradual source rotation. The optional No-JavaScript
profile now has scoped development evidence described below; full atomic
requirement traceability, expanded capacity/chaos profiles and
complete secure-coding evidence remain engineering work.

Tor Browser normal/Safer/Safest desktop and Android profiles, public live Tor integration,
independent review/audit, physical/deployed recovery drills, approved real seed
infrastructure, production offline/threshold trust-root custody and final release
authorization need real evidence/owners. TCP grant lists in this environment are
empty; an HTTP-accessible repository does not establish public Tor connectivity.

The owner goal is ongoing. These changes must not be described as a finished,
maintenance-free repository or Production Stable release.

## Subsequent scoped evidence

The stable-ID change passes all 39 suites on both PHP minors:
[PHP 8.5 report](conformance/service-id-api-report.json),
[PHP 8.4 report](conformance/service-id-api-php84-report.json).
The [private Tor report](conformance/private-tor-onion-report.json) records
successful application traffic through real Tor 0.4.9.11 in an isolated
Chutney network. The [Tor Browser report](conformance/tor-browser-local-report.json)
records a verified official 15.0.25 binary with headless local tests in Docker
network none. Neither report certifies public-network operation, Android or
privacy/security-level profiles. Reproduction and precise limits are in
[the lab instructions](../lab/real-tor-cli.md).

The bounded announcement continuation passes all 41 suites on both executed
PHP minors: [8.5](conformance/announcement-report.json) and
[8.4](conformance/announcement-php84-report.json). This includes actual public
HTTP routing and controlled SOCKS/CLI assertions; the fresh real-Tor lab also
passes announcement followed by independent verification. The hints alone
produce no signed record or local observation. No D-018 approval is inferred.

## Optional NoJS search continuation

Implemented default-off explicit HTML POST search over a prepared local cache.
No query-triggered transport, signing or maintenance occurs. Corrupt/stale data
fails closed; an explicit operator rebuild reconstructs derived bytes. Shared
Unicode 17 tables avoid PHP/browser runtime-version drift. Official normalization
fixtures and shared ranking cases cover both implementations. See
[scope and evidence](operations/server-search.md). Public-Tor deployment, process
isolation, accessibility and independent review remain open.

The clean NoJS candidate at `e009e239fe8ccb40b12f21dbb33f20fd07a38c34`
passes two-build byte reproducibility, structure/hash/SBOM validation and all six
packaged runtime smoke checks. See [candidate report](conformance/nojs-candidate-report.json).
A subsequent CLI guard also rejects aliases resolving to known Tor-private-key
filenames before opening input; tests use synthetic files only.

Checkpoint longevity research is recorded in [PCP-003](protocol/proposals/PCP-003-checkpoint-longevity-research.md).
Its isolated RFC 6962 experiment checks 8,515 prefix pairs through 129 opaque leaves
and adversarial proof mutations. This does not validate hidden signed checkpoint
links, establish active-index freshness, or implement an accepted OSD wire format.
[Research scope](conformance/checkpoint-consistency-research-report.json) is separate
from production conformance.

The implemented NoJS/private-key-alias commit `04e5328c49fc0f3102f1ca7148c11558f3194b33`
passes all eight hosted jobs in [run 38476299383](https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/actions/runs/38476299383),
including both PHP minors, formal models, controlled browser, dependency/secret
checks and packaged-candidate validation. The later bounded PHP chain catch-up
passes all 45 local suites on both pinned minors:
[PHP 8.5](conformance/checkpoint-catchup-report.json),
[PHP 8.4](conformance/checkpoint-catchup-php84-report.json). Its accepted anchor
remains unchanged while verifying 35 missed generations; cached ancestors also
obey the per-step 32-record work limit. Browser catch-up remains bounded separately.

The browser now resumes a 35-generation checkpoint gap across explicit bounded
refreshes, using at most 16 session-only verified cursors. Partial work admits no
new source records or anchor. Tests reject a changed target with a skipped link
and keep late first-contact/failed-first-import history claims unproven. PHP
likewise preserves genesis confidence and conservatively normalizes old unversioned
flags. All 45 suites pass on both pinned PHP minors:
[PHP 8.5](conformance/browser-catchup-report.json),
[PHP 8.4](conformance/browser-catchup-php84-report.json); four Chromium browser
checks and the TypeScript sink policy pass. No compact research proof is used
by either runtime, and active-index/onboarding approval remains open.

A signed conflicting ancestor encountered during browser catch-up now quarantines
the source before admission and retains both authenticated records. A bounded
fork reserve remains available after ordinary witness saturation; no old proof
is silently evicted. [Executed scope](conformance/source-witness-report.json)
includes all 246 Node unit tests, typechecking, four Chromium checks and sink policy.
The previous browser/baseline commit `dead657f3aeafbefbb3d8f654acae55060da1988`
passed all hosted jobs in [run 38479421456](https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/actions/runs/38479421456).
