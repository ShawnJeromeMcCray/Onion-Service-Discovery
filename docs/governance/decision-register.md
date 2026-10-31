# Decision register

Source: specification document 24. No approval has been inferred from the request to implement the project; every approval below quotes an explicit owner answer. Protocol v1 is owner-frozen as implemented (D-005 to D-017, D-052); independent review and production approval remain open.

D-001: CC0-1.0 selected under the owner’s explicit instruction to allow unrestricted use and relinquish rights. Runtime versions (D-002): PHP 8.3, 8.4 and 8.5, 8.5 preferred (owner decision 2026-10-15).

| ID | Decision | State | Owner | Release blocker | Source recommendation |
| --- | --- | --- | --- | --- | --- |
| D-001 | Project License | APPROVED | Project owner / reviewer | YES | See source decision |
| D-002 | Minimum/Preferred PHP Version | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | YES | PHP 8.3+ supported, 8.5 preferred: 8.3, 8.4 and 8.5 each run the full PHP suite in CI; 8.3 support ends with its upstream security support (end of 2027). Ubuntu 24.04 LTS and Debian 13 work with their distribution PHP. |
| D-003 | Web Local Search Browser Compatibility Baseline | OWNER-APPROVED (2026-10-22) / DEVICE-TESTS-OPEN | Project owner / reviewer | YES (device tests) | Current stable Tor Browser desktop and Android at Standard and Safer, current Firefox and Chromium; at Safest the page explains the limit and the optional NoJS search is the fallback; older ESR releases are not promised. See [supported browsers](../browser/client-contract.md#supported-browsers-d-003). |
| D-004 | Web Local Search Origin Trust and Server-Fallback Policy | OWNER-APPROVED AS IMPLEMENTED (2026-10-15) | Project owner / reviewer | CONDITIONAL / REVIEW | Combined host: same-origin generic index download; separated host: query-independent Client Host relay or direct credentialless CORS; no extension; browser storage optional; local search sends no request; the page's serving-origin trust wording; NoJS server search opt-in per Node, POST only, no query logging, no query-triggered remote work. |
| D-005 | Canonical JSON Implementation | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: RFC 8785 serialization over the integer-only JSON subset ([ADR 002](../adr/002-integer-json-and-signed-byte-construction.md)). |
| D-006 | Signed Envelope Exact Byte Construction | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: `Onion Service Discovery/<domain>/v1`, one zero byte, canonical payload without the declared signature slots (ADR 002). |
| D-007 | Manifest Hash Definition | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: SHA-256 over `Onion Service Discovery/<domain>-hash/v1`, zero byte, canonical payload; `sha256:` plus lowercase hex. |
| D-008 | Genesis previous_manifest_hash Representation | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: explicit null. |
| D-009 | Revision Successor Rule | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: exact +1 with the predecessor content hash. |
| D-010 | Service Key Rotation Countersignature | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: old key signs, new key countersigns; committed only with its first valid successor. |
| D-011 | Service Key Recovery after Total Key Loss | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: no cryptographic continuity recovery without the old key; no social/admin recovery. |
| D-012 | Onion Migration Evidence Levels | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: a migration proof signed by the current Service key is required; direct old/new endpoint confirmations are not part of v1. |
| D-013 | Node Descriptor Revision/Hash Chain | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: `descriptor_revision` +1 with `previous_descriptor_hash` chain. |
| D-014 | Snapshot/Chunk Exact Format | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: canonical JSON record arrays per chunk, globally ordered by content hash ([ADR 003](../adr/003-strict-ed25519-points-and-snapshot-bytes.md)). |
| D-015 | Snapshot Partitioning | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: deterministic boundaries in content-hash order, at most 256 records and 512 KiB per chunk. |
| D-016 | Delta Change Model | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: append-only per-Node sequence feed `changes?after=` with complete-snapshot fallback. |
| D-017 | Mixed-Validity Sync Batch Policy | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Project owner / reviewer | YES | As implemented: framing/hash failure rejects the batch; individually valid signed records remain evidence, never a completed source view. |
| D-018 | Public Candidate Announcement Endpoint | OWNER-APPROVED (2026-10-14) | Project owner / reviewer | YES for plug-and-play publish workflow | PCP-002 endpoint and limits accepted; bounded automatic re-announcement only on a new revision or missing observation, exponential backoff, at most once per day per Node. |
| D-019 | Automated Crawler | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | optional/post-core. Must not crawl private/non-opt-in indiscriminately. |
| D-020 | Proof of Work | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | not Protocol v1 core. Only reconsider after measured abuse pressure. |
| D-021 | Moderation in Initial Release | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | avoid mandatory central moderation. |
| D-022 | Clearnet Identity Proof | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | optional and separate from core identity. Needs outbound/privacy threat review. |
| D-023 | Search Ranking Algorithm | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | YES | Deterministic text relevance, identical in browser and server search: exact normalized name, name prefix, every term in the name, other match; ties by normalized name without leading ASCII punctuation, then identity key; Node, observation or source counts never rank. Fixtures: `protocol/test-vectors/local-search-v2.json`. |
| D-024 | Unicode Confusable Strategy | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | YES | UTS #39 skeletons from Unicode 17.0.0 confusables.txt (ICU release-78.3, SHA-256 pinned in `protocol/registries/confusables-v1.provenance.json`) over the client's frozen Unicode 17 NFD: a result whose name looks like a pinned name or another entry's name under a different key is flagged; names are never rewritten or hidden. The server search shows the browser's direction-control, invisible-character and mixed-script notes but no skeleton comparison. |
| D-025 | Human Fingerprint Format | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | CONDITIONAL / REVIEW | Grouped Base32 of SHA-256 (`Onion Service Discovery/key-fingerprint/v1`, zero byte, raw key): 150 bits as six groups of five characters; display aid only, full keys stay authoritative. Vectors: `protocol/test-vectors/key-fingerprint-v1.json`. |
| D-026 | Client Source Bootstrap Set | OWNER-APPROVED PROCESS (2026-10-22) / LIST EMPTY | Project owner / maintainers | YES for network-ready release | The default source list stays empty until Nodes pass the [admission process](source-node-admission.md); no invented seeds, operator consent required. |
| D-027 | Node Seed Inclusion Policy | DRAFTED (2026-10-22) / OWNER CONFIRMATION OPEN | Project owner / maintainers | CONDITIONAL / REVIEW | Draft criteria, independence rule (at least three operators, none above one third), two-channel key exchange and removal rules in [source-node-admission.md](source-node-admission.md). |
| D-028 | Web Client Distribution and Asset Provenance | OWNER-APPROVED (2026-10-22) | Project owner / reviewer | YES | Client ships in the runtime package and as a separate reproducible static bundle with GitHub build-provenance attestation (keyless Sigstore, D-030/D-031) for every merge to main; `doctor` checks served files against `build-manifest.json`. See [static client bundle](../operations/release-candidate.md#static-client-bundle-d-028). |
| D-029 | Server Packaging | OWNER-APPROVED (2026-10-14) | Project owner / reviewer | YES | Development runtime package (vendor/ and built client) per merge to main, with CI provenance, marked unsigned development preview; signed stable releases still need D-030/D-031. |
| D-030 | Release Signing Mechanism | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | YES | Keyless Sigstore signing through GitHub Actions OIDC: GitHub build-provenance and SBOM attestations in the public transparency log, verified with `gh attestation verify ARTIFACT --repo ShawnJeromeMcCray/Onion-Service-Discovery`. No long-lived release key; trust rests on the repository's GitHub identity and workflow. |
| D-031 | Provenance Infrastructure | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | CONDITIONAL / REVIEW | GitHub artifact attestations (build provenance and CycloneDX SBOM) from the digest-pinned workflow; no SLSA level is claimed beyond what the attestation states. |
| D-032 | CycloneDX Version/Tool | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-033 | OWASP ASVS Applicability Level/Matrix | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-034 | Logging Defaults | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | YES | No logs by default: job output and web errors are discarded (systemd output to null, FPM `log_errors` off, no access logs for OSD routes). Failures surface through `doctor`, `status`, the status page and saved publisher evidence. Operators who enable logging do so locally and keep queries, onion addresses, IP addresses and keys out of it. |
| D-035 | Direct Observation Publication | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-036 | Staleness Thresholds | PARTIALLY OWNER-APPROVED | Project owner / reviewer | CONDITIONAL / REVIEW | Owner approved 7 days for publisher discoverability evidence (2026-10-14) and 7 days for the signed checkpoint of a Node that counts toward a publisher's discoverability (2026-10-15; 5 minutes of clock tolerance as in the browser); other local staleness thresholds remain open. |
| D-037 | Default Operator Switches | OWNER-APPROVED AS IMPLEMENTED (2026-10-15) | Project owner / reviewer | CONDITIONAL / REVIEW | Publishing only with `init --publish`; mirroring and discovery only for the node and combined roles; peer sync, server search, announcement intake and visitor-chosen Nodes stay off until explicitly enabled. |
| D-038 | Manifest Auto-Regeneration | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-039 | Key Backup UX | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-040 | Storage Retention Defaults | OWNER-APPROVED (2026-10-22) | Project owner / reviewer | CONDITIONAL / REVIEW | As implemented: `node:maintain` keeps the Node's newest 256 own checkpoints, each peer's anchor, pending checkpoint and newest four witnesses, and every conflicting checkpoint; superseded checkpoints are removed. Service history (Manifests, proofs, observations) is never removed. See [storage contract](../storage/contract.md). |
| D-041 | Rate Limit and Work Budget Defaults | OWNER-APPROVED AS DEVELOPMENT DEFAULT (2026-10-22) / BENCHMARKS OPEN | Project owner / reviewer | YES (final values) | The current values ([candidate budgets](../operations/candidate-budgets.md), request budget burst 20 at 2 per second, relay and visitor-relay budgets) apply until load tests with real Tor hosts confirm or change them. |
| D-042 | Tor Stream Isolation Strategy | OWNER-APPROVED (2026-10-22) / TOR-REVIEW-OPEN | Project owner / reviewer | YES (independent Tor review) | As implemented: Tor SOCKS extended format 0 with a fresh random isolation value per request, so no two requests share a circuit ([ADR 005](../adr/005-tor-socks-format-zero.md)). |
| D-043 | HTTP Compression | OWNER-APPROVED (2026-10-22) | Project owner / reviewer | YES | As implemented: verification paths request and accept only `identity`; any other `Content-Encoding` is refused (`CONTENT_ENCODING_REJECTED`) in the PHP transport and the browser client. |
| D-044 | API CORS Model | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-045 | Public Health/Metrics | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | CONDITIONAL / REVIEW | Minimal public health, detailed diagnostics local only. A Node whose store cannot be read keeps serving its last published signed snapshot (node, state, checkpoint and chunks from `.published-state.json` and the export blobs, re-verified on every read); the status page shows DEGRADED; store-backed routes and announcements answer 503 `NODE_DEGRADED_READ_ONLY`; server search pauses. |
| D-046 | Container Support | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-047 | Project Branding/Logo | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-048 | Real-World Legal/Policy Review | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-049 | External Audit Provider | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-050 | Production Release Approval | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |

The refreshed source decision register adds four release blockers; all four are owner-approved (D-051 and D-052 on 2026-10-13/14, D-053 and D-054 on 2026-10-15), and their independent reviews remain open:

| ID | Decision | State | Owner | Release blocker | Source recommendation |
| --- | --- | --- | --- | --- | --- |
| D-051 | Stable Logical Service Identifier | OWNER-APPROVED / CRYPTO-REVIEW-OPEN | Human Protocol/Crypto reviewer | YES (independent review) | Owner approved canonical genesis public-key encoding on 2026-10-13; claimed identifiers need full continuity evidence. |
| D-052 | Signed Node Checkpoint Chain | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Human Protocol/Security reviewer | YES | As implemented: generation exactly +1, `previous_checkpoint_hash` chain, `null` for generation 1; equivocation stays sticky. Owner decision 2026-10-22: a hard guard keeps a Node from signing a generation or descriptor revision it already signed (for example after restoring an old backup); see the [restore procedure](../operations/deployment-and-recovery.md). |
| D-053 | Category Registry v1 Immutability | OWNER-APPROVED (2026-10-15) / INDEPENDENT REVIEW OPEN | Human Protocol/Product reviewer | YES | The 38 v1 IDs are frozen: they cannot change or silently expand; extensions need explicit versioning. |
| D-054 | Language Tag Wire Grammar | OWNER-APPROVED (2026-10-15) / INDEPENDENT REVIEW OPEN | Human Protocol/I18N reviewer | YES | One tested RFC 5646 grammar, lowercase ASCII wire form (at most 35 characters, 16 entries) and canonical uniqueness. |

D-052 is owner-approved as implemented (see the second set of 2026-10-14 decisions); D-053 and D-054 are owner-approved as proposed (2026-10-15); their independent Protocol/Product and I18N reviews remain open. D-001 remains approved by the owner's earlier explicit CC0 instruction.

PCP-001 records the current category/language implementation experiment and stricter
draft acceptance. Tests do not approve these freeze decisions.

Continuation evidence: D-051 has executable genesis-anchor/current-key/key-epoch
comparison in the offline complete-snapshot verifier, with shared rotation and
migration fixtures. The owner approved this proposed D-051 choice in the continuation conversation on
2026-10-13 ("Ja, dann mach weiter"). This approval accepts the canonical
`ed25519:<base64url-no-padding>` genesis key as the immutable logical service ID;
the current signing key and epoch remain separate. It does not attest to an
independent cryptographic review. The derived by-ID endpoint now preserves
rotation, migration, withdrawal and fork evidence on both storage backends. D-042 now has the format-zero transport implementation and
[ADR 005](../adr/005-tor-socks-format-zero.md); actual deployed Tor and human
review remain open. No other decision approval is inferred.

D-018 now has the executable default-off receiver, the explicit publisher Tor
POST and the owner-approved bounded automatic re-announcement (`--if-due` in the
publisher timer) in [PCP-002](../protocol/proposals/PCP-002-bounded-announcement-draft.md).
A received hint has no signature/observation/index authority; anonymous caller
identity rotation cannot increase its shared source/global budget. Independent
security review of the endpoint and limits remains open.

## Owner decisions on 2026-10-14

Recorded from the owner's explicit answers in the continuation conversation
("Englisch bleibt Hauptsprache. 7 Tage ist gut. Das Besucher eigene nodes
eintragen gerne einbauen."). No other approval is inferred from them.

- **D-036 (partial):** saved publisher discoverability evidence older than
  **7 days** is shown as `DISCOVERABLE_DEGRADED`
  (`PublisherStatus::EVIDENCE_DEGRADED_AFTER_SECONDS`). Browser source freshness
  and the remaining staleness thresholds stay open.
- **Interface language:** English remains the only reference interface language
  (browser client, status page, CLI messages). Category localization files stay
  data for third-party clients; no translated UI is planned for now.
- **Visitor-chosen Nodes:** the reference client lets visitors add Nodes they
  chose, with a required expected Node key, through the opt-in Client Host
  `visitor_sources` relay (default off). This is local source-selection policy
  (specs 42 sections 13/25, 49 section 13); it does not decide D-026 (bootstrap
  set), D-027 (seed policy) or D-044 (cross-origin CORS model).

## Owner decisions on 2026-10-14 (second set)

Recorded from the owner's answers to explicit questions in the continuation
conversation. Each answer selected one listed option:

- **Proof transport (new PCP-004):** "Entwurf + Umsetzung": publishers serve a
  bounded, signature-verified continuity bundle at a Well-Known path and Nodes
  fetch it only when a Manifest's history is incomplete. See
  [PCP-004](../protocol/proposals/PCP-004-continuity-bundle-draft.md).
- **D-018:** "Begrenzt automatisch": the bounded announcement endpoint is
  accepted, and the publisher may re-announce automatically only on a new
  revision or a missing observation, with exponential backoff and at most once
  per day per Node.
- **D-029:** "Entwicklungspaket je Merge": CI builds a reproducible runtime
  package with PHP dependencies and the built client for each merge to main,
  with provenance, marked as an unsigned development preview. Implemented as the
  `development-runtime-package` CI job and `build_release_candidate.py --profile
  runtime`; see [release candidate](../operations/release-candidate.md#development-runtime-package-d-029).
- **Protocol v1 (D-005 to D-017, D-052):** "Jetzt annehmen": the implemented and
  tested choices listed in the table are frozen as protocol v1. This is the
  owner's approval, not an independent cryptographic review, which stays open;
  incompatible changes now need a new protocol version or an explicit owner
  decision. The recommendation offered with the question was to wait for a
  review; the owner chose to accept now.

## Owner decisions on 2026-10-15

Recorded from the owner's answers to explicit questions in the continuation
conversation. Each answer selected one listed option; the quoted text is the
option the owner chose:

- **D-002:** "8.3+, 8.5 bevorzugt": PHP 8.3, 8.4 and 8.5 are supported, 8.5 is
  preferred; CI runs all three.
- **D-036 (rest):** a Node checkpoint counts toward a publisher's discoverability
  only while it is at most 7 days old (300 s clock tolerance).
- **D-034:** "Nichts (wie bisher)": no request or query logging.
- **D-045:** "Letzten Snapshot weiter ausliefern": a Node whose store cannot be
  read keeps serving its last published snapshot read-only.
- **D-023:** "Textrelevanz-Stufen": ranking version 2, text-relevance tiers only.
- **D-024:** "UTS-#39-Warnungen": look-alike name warnings from UTS #39 skeletons.
- **D-025:** "Gruppierter Base32-Code": six groups of five Base32 characters.
- **D-004:** "Wie umgesetzt freigeben".
- **D-030/D-031:** "Sigstore keyless (GitHub)".
- **D-037:** "Wie umgesetzt freigeben".
- **D-053/D-054:** "Beides wie vorgeschlagen".

## Owner decisions on 2026-10-22

Recorded from the owner's answers to explicit questions in the continuation
conversation. Each answer selected one listed option; the quoted text is the
option the owner chose:

- **Generation guard (D-052):** "Harter Schutz": a Node never signs a checkpoint
  generation or descriptor revision it already signed (signing ledger beside the
  key; `--adopt-signed-chain` to continue after restoring an old store).
- **D-040:** "Wie umgesetzt freigeben": retention as implemented.
- **D-041:** "Als Entwicklungs-Standard freigeben": the current budgets apply until
  load tests with real Tor hosts confirm or change them.
- **D-042:** "Wie umgesetzt freigeben": per-request Tor SOCKS isolation.
- **D-003:** "Aktueller Tor Browser": current stable Tor Browser desktop and
  Android at Standard and Safer; Safest falls back to the NoJS search.
- **D-028:** "Paket + separates Bundle": the client ships in the runtime package
  and as a separately attested static bundle.
- **D-043:** "Keine Kompression".
- **D-026:** "Aufnahmeprozess festlegen": the default source list stays empty until
  Nodes pass the admission process. The process text itself (D-027) is a draft
  awaiting the owner's confirmation.
