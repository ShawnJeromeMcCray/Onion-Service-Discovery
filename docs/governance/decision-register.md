# Decision register

Source: specification document 24. No approval has been inferred from the request to implement the project; every approval below quotes an explicit owner answer. Protocol v1 is owner-frozen as implemented (D-005 to D-017, D-052); independent review and production approval remain open.

D-001: CC0-1.0 selected under the owner’s explicit instruction to allow unrestricted use and relinquish rights. Runtime versions (D-002): PHP 8.3, 8.4 and 8.5, 8.5 preferred (owner decision 2026-10-15).

| ID | Decision | State | Owner | Release blocker | Source recommendation |
| --- | --- | --- | --- | --- | --- |
| D-001 | Project License | APPROVED | Project owner / reviewer | YES | See source decision |
| D-002 | Minimum/Preferred PHP Version | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | YES | PHP 8.3+ supported, 8.5 preferred: 8.3, 8.4 and 8.5 each run the full PHP suite in CI; 8.3 support ends with its upstream security support (end of 2027). Ubuntu 24.04 LTS and Debian 13 work with their distribution PHP. |
| D-003 | Web Local Search Browser Compatibility Baseline | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-004 | Web Local Search Origin Trust and Server-Fallback Policy | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
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
| D-024 | Unicode Confusable Strategy | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-025 | Human Fingerprint Format | OWNER-APPROVED (2026-10-15) | Project owner / reviewer | CONDITIONAL / REVIEW | Grouped Base32 of SHA-256 (`Onion Service Discovery/key-fingerprint/v1`, zero byte, raw key): 150 bits as six groups of five characters; display aid only, full keys stay authoritative. Vectors: `protocol/test-vectors/key-fingerprint-v1.json`. |
| D-026 | Client Source Bootstrap Set | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-027 | Node Seed Inclusion Policy | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-028 | Web Client Distribution and Asset Provenance | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-029 | Server Packaging | OWNER-APPROVED (2026-10-14) | Project owner / reviewer | YES | Development runtime package (vendor/ and built client) per merge to main, with CI provenance, marked unsigned development preview; signed stable releases still need D-030/D-031. |
| D-030 | Release Signing Mechanism | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-031 | Provenance Infrastructure | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-032 | CycloneDX Version/Tool | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-033 | OWASP ASVS Applicability Level/Matrix | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-034 | Logging Defaults | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-035 | Direct Observation Publication | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-036 | Staleness Thresholds | PARTIALLY OWNER-APPROVED | Project owner / reviewer | CONDITIONAL / REVIEW | Owner approved 7 days for publisher discoverability evidence (2026-10-14) and 7 days for the signed checkpoint of a Node that counts toward a publisher's discoverability (2026-10-15; 5 minutes of clock tolerance as in the browser); other local staleness thresholds remain open. |
| D-037 | Default Operator Switches | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | publishing false until configured; mirror/discover conservative; sync only if node role enabled. |
| D-038 | Manifest Auto-Regeneration | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-039 | Key Backup UX | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-040 | Database Retention Defaults | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-041 | Rate Limit and Work Budget Defaults | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-042 | Tor Stream Isolation Strategy | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-043 | HTTP Compression | UNRESOLVED | Project owner / reviewer | YES | disable or strictly expanded-size enforce for verification path. |
| D-044 | API CORS Model | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-045 | Public Health/Metrics | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | minimal health; detailed metrics local/private. Ops/security review. |
| D-046 | Container Support | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-047 | Project Branding/Logo | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-048 | Real-World Legal/Policy Review | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-049 | External Audit Provider | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-050 | Production Release Approval | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |

The refreshed source decision register adds four release blockers; D-051 and D-052 are now owner-approved, D-053 and D-054 remain open:

| ID | Decision | State | Owner | Release blocker | Source recommendation |
| --- | --- | --- | --- | --- | --- |
| D-051 | Stable Logical Service Identifier | OWNER-APPROVED / CRYPTO-REVIEW-OPEN | Human Protocol/Crypto reviewer | YES (independent review) | Owner approved canonical genesis public-key encoding on 2026-10-13; claimed identifiers need full continuity evidence. |
| D-052 | Signed Node Checkpoint Chain | OWNER-APPROVED v1 (2026-10-14) / CRYPTO-REVIEW-OPEN | Human Protocol/Security reviewer | YES | As implemented: generation exactly +1, `previous_checkpoint_hash` chain, `null` for generation 1; equivocation stays sticky. |
| D-053 | Category Registry v1 Immutability | PROPOSED / NEEDS-HUMAN-REVIEW | Human Protocol/Product reviewer | YES | Frozen IDs cannot change or silently expand; extensions need explicit versioning. |
| D-054 | Language Tag Wire Grammar | PROPOSED / NEEDS-HUMAN-REVIEW | Human Protocol/I18N reviewer | YES | One tested RFC 5646 grammar, lowercase ASCII wire form and canonical uniqueness are proposed. |

D-052 is owner-approved as implemented (see the second set of 2026-10-14 decisions); D-053 and D-054 remain source recommendations, not approvals. D-001 remains approved by the owner's earlier explicit CC0 instruction.

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

