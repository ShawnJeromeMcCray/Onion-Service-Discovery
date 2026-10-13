# Decision register

Source: specification document 24. No approval has been inferred from the request to implement the project. Technical prototypes may proceed; production and protocol approval remain blocked.

D-001: CC0-1.0 selected under the owner’s explicit instruction to allow unrestricted use and relinquish rights. Runtime versions and exact protocol choices are not yet frozen.

| ID | Decision | State | Owner | Release blocker | Source recommendation |
| --- | --- | --- | --- | --- | --- |
| D-001 | Project License | APPROVED | Project owner / reviewer | YES | See source decision |
| D-002 | Minimum/Preferred PHP Version | UNRESOLVED | Project owner / reviewer | YES | PHP 8.5 preferred as of October 2026; only supported branches. |
| D-003 | Web Local Search Browser Compatibility Baseline | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-004 | Web Local Search Origin Trust and Server-Fallback Policy | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-005 | Canonical JSON Implementation | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-006 | Signed Envelope Exact Byte Construction | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-007 | Manifest Hash Definition | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-008 | Genesis previous_manifest_hash Representation | UNRESOLVED | Project owner / reviewer | YES | explicit null. |
| D-009 | Revision Successor Rule | UNRESOLVED | Project owner / reviewer | YES | exact +1 for simpler continuity/fork semantics. |
| D-010 | Service Key Rotation Countersignature | UNRESOLVED | Project owner / reviewer | YES | yes if interoperability/operational workflow remains practical. |
| D-011 | Service Key Recovery after Total Key Loss | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-012 | Onion Migration Evidence Levels | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-013 | Node Descriptor Revision/Hash Chain | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-014 | Snapshot/Chunk Exact Format | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-015 | Snapshot Partitioning | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-016 | Delta Change Model | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-017 | Mixed-Validity Sync Batch Policy | UNRESOLVED | Project owner / reviewer | YES | framing/hash failure rejects batch; individually valid signed records can be independently quarantined/committed only if envelope remains trustworthy. Freeze exact transaction semantics. |
| D-018 | Public Candidate Announcement Endpoint | UNRESOLVED | Project owner / reviewer | YES for plug-and-play publish workflow | Now included as a bounded candidate-hint flow; final endpoint, limits and retry policy need approval. |
| D-019 | Automated Crawler | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | optional/post-core. Must not crawl private/non-opt-in indiscriminately. |
| D-020 | Proof of Work | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | not Protocol v1 core. Only reconsider after measured abuse pressure. |
| D-021 | Moderation in Initial Release | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | avoid mandatory central moderation. |
| D-022 | Clearnet Identity Proof | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | optional and separate from core identity. Needs outbound/privacy threat review. |
| D-023 | Search Ranking Algorithm | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-024 | Unicode Confusable Strategy | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-025 | Human Fingerprint Format | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-026 | Client Source Bootstrap Set | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-027 | Node Seed Inclusion Policy | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-028 | Web Client Distribution and Asset Provenance | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-029 | Server Packaging | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-030 | Release Signing Mechanism | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-031 | Provenance Infrastructure | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-032 | CycloneDX Version/Tool | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-033 | OWASP ASVS Applicability Level/Matrix | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-034 | Logging Defaults | UNRESOLVED | Project owner / reviewer | YES | See source decision |
| D-035 | Direct Observation Publication | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
| D-036 | Staleness Thresholds | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | See source decision |
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

The refreshed source decision register adds four unresolved release blockers:

| ID | Decision | State | Owner | Release blocker | Source recommendation |
| --- | --- | --- | --- | --- | --- |
| D-051 | Stable Logical Service Identifier | OWNER-APPROVED / CRYPTO-REVIEW-OPEN | Human Protocol/Crypto reviewer | YES (independent review) | Owner approved canonical genesis public-key encoding on 2026-10-13; claimed identifiers need full continuity evidence. |
| D-052 | Signed Node Checkpoint Chain | UNRESOLVED | Human Protocol/Security reviewer | YES | Exact successor +1, chained previous hash and non-prefix-compatible-history evidence; exact bytes/genesis representation need freeze. |
| D-053 | Category Registry v1 Immutability | PROPOSED / NEEDS-HUMAN-REVIEW | Human Protocol/Product reviewer | YES | Frozen IDs cannot change or silently expand; extensions need explicit versioning. |
| D-054 | Language Tag Wire Grammar | PROPOSED / NEEDS-HUMAN-REVIEW | Human Protocol/I18N reviewer | YES | One tested RFC 5646 grammar, lowercase ASCII wire form and canonical uniqueness are proposed. |

D-052 through D-054 remain source recommendations, not approvals. D-001 remains approved by the owner's earlier explicit CC0 instruction.

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
