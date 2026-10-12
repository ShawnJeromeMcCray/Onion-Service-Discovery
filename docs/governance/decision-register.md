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
| D-018 | Public Candidate Announcement Endpoint | UNRESOLVED | Project owner / reviewer | CONDITIONAL / REVIEW | omit from first core release unless strong need; manual/out-of-band candidate seeding plus service graph/peer propagation can work. If included, new abuse spec/limits. |
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
