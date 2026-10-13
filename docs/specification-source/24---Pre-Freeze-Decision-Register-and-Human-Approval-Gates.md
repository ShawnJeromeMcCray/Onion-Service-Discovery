# 24 - Pre-Freeze Decision Register and Human Approval Gates

Source: https://docs.google.com/document/d/1_B5uqXfbpNJ73JY1qY7ysIUfQjB5Hy66k7TqkY_f61o/edit?usp=drivesdk

Source modified: 2026-10-12T19:25:34.446Z

---

ONION SERVICE DISCOVERY
PRE-FREEZE DECISION REGISTER AND HUMAN APPROVAL GATES


Purpose
This register lists decisions that are intentionally NOT allowed to remain implicit. The coding agent may research, prototype and recommend them, but must not mark them finally approved where a Human Approval Gate is specified.


Decision states:
UNRESOLVED
RESEARCHED
PROPOSED
NEEDS-HUMAN-REVIEW
APPROVED
REJECTED
SUPERSEDED




CURRENT APPROVAL-STATE RULE — 2026-10-12
No D-XXX decision is APPROVED merely because this document contains a recommendation, baseline or proposed rule.


For the Coding Agent:
- any entry requiring a Human gate/review remains NEEDS-HUMAN-REVIEW until a linked approval record names the decision ID, approver, date and accepted option;
- an entry presenting undecided alternatives with no accepted option is UNRESOLVED;
- “recommended”, “preferred”, “proposed” or “architecture direction” is not approval;
- non-release-blocking means the feature/decision may be absent where specified; it does not mean an unapproved implementation choice is silently authorized;
- repository decision-register state MUST match the evidence/approval record, never inference.


This rule prevents the specification text itself from fabricating Human approval.




D-001 Project License
Question:
Which open-source license governs the project?
Agent work:
compare appropriate permissive/copyleft options, dependency compatibility and project goals.
Human gate:
project owner chooses.
Release blocker:
yes.


D-002 Minimum/Preferred PHP Version
Current baseline:
PHP 8.5 preferred as of October 2026; only supported branches.
Agent work:
validate ecosystem/deployment compatibility, CI matrix and support horizon.
Human gate:
maintainer approval via ADR.
Release blocker:
yes.


D-003 Web Local Search Browser Compatibility Baseline
Question:
Which current Tor Browser desktop and Android versions/security-level behaviors define the supported Web Client baseline?


Agent:
refresh official Tor Browser behavior for JavaScript, storage, CSP and WebCrypto;
run compatibility tests;
document fallback behavior.


Human browser/security gate.
Release blocker:
yes.


D-004 Web Local Search Origin Trust and Server-Fallback Policy
Decisions to freeze:
- WEB-COMBINED-V1 same-origin generic index download;
- WEB-SEPARATED-V1 direct credentialless CORS versus query-independent Client Host proxy reference path;
- no separate extension;
- browser storage optional;
- local-mode query network behavior;
- serving-origin trust limitation wording;
- No-JavaScript server-search endpoint;
- POST-only query submission;
- no query logging;
- no query-triggered remote work.


Human privacy/security/product gate:
yes.


No implementation may weaken local-mode query privacy or silently switch a user to server-search semantics without explicit disclosure.


D-005 Canonical JSON Implementation
Baseline:
RFC 8785 JCS.
Agent:
select PHP/TypeScript implementations or implement small well-tested adapter if unavoidable.
Cross-language vectors.
Human protocol/crypto review.
Release blocker:
yes.


D-006 Signed Envelope Exact Byte Construction
Freeze:
domain prefix encoding
separator
canonical payload inclusion/exclusion
signature field exclusion.
Human crypto review.
Release blocker:
yes.


D-007 Manifest Hash Definition
Freeze exact:
domain prefix;
payload bytes;
hash algorithm SHA-256;
encoded representation.
Human crypto review.
Release blocker:
yes.


D-008 Genesis previous_manifest_hash Representation
Options:
explicit null;
absent field.
Current draft preference:
explicit null.
Agent:
compare JCS/schema simplicity/interoperability.
Human Protocol freeze approval.
Release blocker:
yes.


D-009 Revision Successor Rule
Question:
Must successor revision equal previous+1 or only be greater?
Recommended:
exact +1 for simpler continuity/fork semantics.
Agent:
analyze recovery/offline update operational effects.
Human Protocol approval.
Release blocker:
yes.


D-010 Service Key Rotation Countersignature
Question:
Must new key countersign rotation?
Recommended:
yes if interoperability/operational workflow remains practical.
Agent:
threat analysis and vectors.
Human crypto approval.
Release blocker:
yes.


D-011 Service Key Recovery after Total Key Loss
Protocol v1 recommended decision:
no cryptographic continuity recovery without old key.
A future recovery mechanism would require separate protocol design.
Human approval:
confirm intentionally no social/admin recovery.
Release blocker:
yes because operator docs depend on it.


D-012 Onion Migration Evidence Levels
Freeze:
Identity signature requirement;
old endpoint confirmation semantics;
new endpoint confirmation semantics;
UI labels.
Human protocol/security/UX review.
Release blocker:
yes.


D-013 Node Descriptor Revision/Hash Chain
Question:
Does Node Descriptor use exact revision+previous hash chain analogous to manifests, or simpler signed monotonic revision?
Agent:
analyze equivocation detection vs complexity.
Human protocol review.
Release blocker:
yes before node sync production.


D-014 Snapshot/Chunk Exact Format
Options:
canonical JSON array;
NDJSON of canonical records;
other explicit deterministic format.
Criteria:
streaming, memory, exact hashing, interoperability.
Human protocol review.
Release blocker:
yes.


D-015 Snapshot Partitioning
Freeze deterministic chunk boundary algorithm.
Must not depend on locale/DB insertion order.
Agent benchmark.
Human protocol/operations review.
Release blocker:
yes for scalable production sync.


D-016 Delta Change Model
Question:
append-only event feed vs record-set diff abstraction.
Need stable cursor, retention and snapshot fallback.
Agent prototype.
Human protocol review.
Release blocker:
yes.


D-017 Mixed-Validity Sync Batch Policy
Recommended:
framing/hash failure rejects batch;
individually valid signed records can be independently quarantined/committed only if envelope remains trustworthy.
Freeze exact transaction semantics.
Human security review.
Release blocker:
yes.


D-018 Public Candidate Announcement Endpoint
Architecture direction:
included in the Protocol v1 reference/operator profile as a bounded self-announcement mechanism for explicitly opted-in publishing.


Security semantics:
announcement creates only an untrusted Candidate hint;
remote Node MUST independently fetch and verify the canonical Onion Service Manifest over Tor before Direct Observation/index inclusion.


Required:
strict request limits;
global work budget;
target-centric duplicate suppression/backoff;
per-source caps;
no arbitrary URL;
no synchronous expensive fetch;
Sybil identity rotation must not bypass global limits.


Human product/security approval remains required for final endpoint, limits and default retry policy.
Release blocker:
yes for the plug-and-play publish workflow.


D-019 Automated Crawler
Decision:
core, optional disabled capability, or post-v1?
Recommended:
optional/post-core.
Must not crawl private/non-opt-in indiscriminately.
Human product/privacy review.


D-020 Proof of Work
Architecture direction:
not a Protocol identity/trust primitive.


Optional use:
adaptive admission/work prioritization for candidate-announcement overload only if stress evidence justifies it.


Hard rules:
PoW never proves unique person/operator;
never increases Service trust;
never affects identity/fork truth;
never boosts search ranking;
must include replay protection and bounded verification cost.


Human approval required before enabling any production PoW admission scheme.
Not a release blocker if absent when non-PoW bounded admission passes the abuse gates.


D-021 Moderation in Initial Release
Options:
no moderation implementation, only extension point;
basic signed source support;
bundled default source.
Recommended:
avoid mandatory central moderation.
Human product/policy review.


D-022 Clearnet Identity Proof
Question:
include in v1?
Possible Onion-Location/HTTPS cross-link.
Recommended:
optional and separate from core identity.
Needs outbound/privacy threat review.
Human approval.


D-023 Search Ranking Algorithm
Agent:
design deterministic local text relevance and spam-resistance.
Must keep Trust state separate.
Human UX/product review before stable UI.
Release blocker:
yes for client release.


D-024 Unicode Confusable Strategy
Agent:
select Unicode data/library/version;
define warning behavior, not identity rewrite.
Human security/UX review.
Release blocker:
yes.


D-025 Human Fingerprint Format
Options:
grouped SHA-256 hex/base32.
Must be deterministic and not misleadingly short.
Agent usability/security comparison.
Human UX/security approval.


D-026 Client Source Bootstrap Set
Need:
initial independent Node seeds.
Criteria/public governance.
Do not invent fake production seeds.
Human maintainer must approve actual addresses/operators.
Release blocker:
yes for network-ready release.


D-027 Node Seed Inclusion Policy
Governance doc baseline.
Human maintainers approve first set and process.


D-028 Web Client Distribution and Asset Provenance
Decision:
The Web Local Search client is distributed as static assets with the PHP/reference Node release and MAY also be published as a separately verifiable static bundle.


Required:
- reproducible build;
- asset manifest/hashes;
- SBOM;
- provenance/checksums;
- strict CSP;
- no remote runtime dependency;
- operator self-check against deployed assets.


No browser extension store/signing path is part of the product architecture.


Human release decision:
approve the actual release artifact/signing/provenance mechanism.


Release blocker:
yes.
D-029 Server Packaging
Options:
Composer project/source tarball/container optional.
Agent provide reproducible baseline.
Human operations decision.
Release blocker:
yes for final artifact list.


D-030 Release Signing Mechanism
Select actual maintainable signing mechanism and key custody.
Human security/release gate.
Release blocker:
yes.


D-031 Provenance Infrastructure
Determine CI capabilities for SLSA-aligned attestation.
Human release gate approves actual claim.
Release can proceed with documented lesser provenance only if Definition of Done allows and security reviewer approves; no false level claim.


D-032 CycloneDX Version/Tool
Agent refresh before RC.
Human not necessarily required unless tool introduces major dependency; release evidence records version.


D-033 OWASP ASVS Applicability Level/Matrix
Use current ASVS 5.0.0 baseline at drafting time, refresh.
Agent maps applicable controls.
Human security reviewer signs matrix, not “certification”.


D-034 Logging Defaults
Need exact retention, webserver format and event fields.
Privacy/security review.
Release blocker:
yes.


D-035 Direct Observation Publication
Question:
Are all node observations public/exported, summarized, or optional?
Privacy/bandwidth implications.
Human protocol/product review.


D-036 Staleness Thresholds
Identity-independent local policy:
recent/temp unreachable/stale/long unseen.
Agent proposes based on usability/load.
Not wire identity.
Human product review.
Can be configurable bounded defaults.


D-037 Default Operator Switches
publish_service default should require explicit opt-in.
mirror/discover/sync defaults need operator UX review.
Recommended:
publishing false until configured;
mirror/discover conservative;
sync only if node role enabled.
Human security/product gate.


D-038 Manifest Auto-Regeneration
Define triggers:
config change
rotation
migration
explicit CLI
No unnecessary periodic revision increments.
Agent design.
Human protocol/ops review.


D-039 Key Backup UX
CLI/docs should make lack of backup visible without exposing private key.
Human ops/security review.


D-040 Storage Retention Defaults
Exact durations/budgets derived from benchmarks.
Human operations/security review before production.


D-041 Rate Limit and Work Budget Defaults
Must be benchmark-derived.
Agent provides stress evidence.
Human security approval.
Release blocker:
yes.


D-042 Tor Stream Isolation Strategy
Agent research current Tor SOCKS specification and deployment practicality.
Decide isolation token/use per work class.
Human Tor/security review.
Release blocker:
yes before real Tor integration production.


D-043 HTTP Compression
Recommended:
disable or strictly expanded-size enforce for verification path.
Agent prototype tests.
Security review.
Release blocker:
yes if compression accepted.


D-044 API CORS Model
Depends on the same-origin Web Local Search architecture and whether any cross-origin browser fetch is introduced.
Agent produce least-privilege design.
Human browser/server security review.


D-045 Public Health/Metrics
Decide whether publicly exposed.
Recommended:
minimal health; detailed metrics local/private.
Ops/security review.


D-046 Container Support
Optional.
Do not make container mandatory.
Human ops decision based target audience.


D-047 Project Branding/Logo
Non-security; human decision.
Must not block protocol implementation.


D-048 Real-World Legal/Policy Review
Technical docs are not legal advice.
Before public production operation by project maintainers, owner decides whether jurisdiction-specific legal review is needed.
AI cannot approve.


D-049 External Audit Provider
Human selection only.
Agent prepares package.
Release gate may distinguish “production beta before independent audit” vs “production stable after audit” only by explicit owner/security policy.
Never mark audit performed without evidence.


D-050 Production Release Approval
Final Human Gate.
Required inputs:
Gate Matrix
Red Team results
Protocol/Crypto review
Web Local Search origin/CSP/query-privacy review
Tor transport review
Supply-chain evidence
known issues
security exceptions
audit status
clean install/backup/restore/rollback evidence.


Approval record includes:
release version
source revision
date
approvers
exceptions
exact security/privacy claims authorized.


Open Decision Workflow
For each D-XXX create ADR/proposal or approval record.
No unresolved decision that affects Protocol bytes, crypto trust, user privacy or release signing may be silently defaulted by the coding agent.


Decision Register Artifact
Maintain docs/governance/decision-register.md with:
ID
title
state
owner
recommended option
ADR/proposal
human reviewer
date
release blocker yes/no.


D-051 Stable Logical Service Identifier
Problem:
The current Service Identity signing key changes during a valid key rotation, so the current key fingerprint cannot be the stable lookup/pin/index identity.


Proposed Protocol v1 rule:
logical_service_id is anchored to the genesis Service Identity public key and remains stable across valid Service-key rotations and Onion migrations.
Recommended wire representation:
the canonical genesis Service Identity public-key encoding (ed25519:<base64url-no-padding>) reused as a logical identity anchor; active records also carry the current key/epoch separately.
A fresh verifier MUST NOT accept a claimed logical_service_id without continuity evidence back to the genesis anchor.


Human Protocol/Crypto review:
required.
Release blocker:
yes.


D-052 Signed Node Checkpoint Chain
Problem:
“same sequence, different root” detects only one equivocation shape. A malicious Node can maintain divergent signed histories unless checkpoints are chained.


Proposed Protocol v1 rule:
production index-exporting Nodes sign checkpoints containing:
Node Identity;
checkpoint sequence;
previous_checkpoint_hash;
active index root;
record count;
Protocol/index format version;
generated timestamp.
Successor sequence is exact +1.
Checkpoint histories are valid only when previous hash links exactly.
Two signed histories from the same Node that are not prefix-compatible are equivocation evidence once both are available.


Genesis previous_checkpoint_hash representation and exact checkpoint-hash byte construction require Protocol/Crypto freeze.


Human Protocol/Security review:
required.
Release blocker:
yes.


D-053 Category Registry v1 Immutability
Problem:
Protocol v1 rejects unknown category IDs. Adding new category IDs later under the same unversioned Manifest field would therefore break old v1 validators.


Proposed rule:
the Protocol Category Registry v1 is immutable after Protocol v1 freeze.
Existing IDs are never repurposed.
A new category after freeze requires an explicitly versioned compatible extension mechanism accepted by old implementations, or a new Protocol version.
No silent additions to categories-v1.json.


Human Protocol/Product review:
required before freeze.
Release blocker:
yes.


D-054 Language Tag Wire Grammar
Problem:
“BCP47-style” is insufficient for interoperable signed bytes and duplicate detection.


Proposed v1 rule:
language values MUST be well-formed RFC 5646/BCP 47 language tags, ASCII only, serialized in lowercase for the Protocol wire form, unique after lowercase canonicalization, and remain within the Protocol length/count limits.
The implementation MUST use one tested grammar/library policy across PHP, TypeScript and the independent validator.


Human Protocol/I18N review:
required before freeze.
Release blocker:
yes.


Release Rule
Any D-XXX marked Release blocker=yes must be APPROVED before Production. NEEDS-HUMAN-REVIEW is not PASS.
