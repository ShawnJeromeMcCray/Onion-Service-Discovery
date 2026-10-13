# 46 - Final Pre-Implementation Closure Audit and Specification Freeze

Source: https://docs.google.com/document/d/1qjIhv8nJyBeQbhKJpSxoxpuC-T7QE_4uLYU4UNjvjXc/edit?usp=drivesdk

Source modified: 2026-10-12T19:35:10.569Z

---

ONION SERVICE DISCOVERY
FINAL PRE-IMPLEMENTATION CLOSURE AUDIT AND SPECIFICATION FREEZE


Status
CONSISTENCY RE-AUDIT PASS — IMPLEMENTATION MAY PROCEED; PROTOCOL FREEZE/HUMAN GATES REMAIN OPEN.


A forensic line-by-line audit on 2026-10-12 found and remediated real normative contradictions, stale superseded requirements and mechanically split text caused by later architecture revisions being appended without fully replacing old requirements. The active specification set was then re-read/scanned for the same contradiction and corruption classes.


Repository implementation MAY proceed according to the current dependency order. Protocol v1 Freeze, final crypto-byte/state semantics, Production Stable claims and any semantics controlled by open D-XXX Human Gates remain BLOCKED until those gates are actually approved. This consistency PASS does not approve any Human Gate.


This document is the final planning/consistency gate. Its purpose is to stop infinite speculative architecture work only after all blocking contradictions are actually removed or explicitly assigned to evidence-producing implementation/Human Gate decisions.


1. Principle
No engineering project can be guaranteed vulnerability-free forever.
The preparation goal is instead:
- no known unresolved architectural contradiction;
- no undefined security-critical byte/state semantics;
- explicit threat assumptions;
- bounded residual risk;
- measurable implementation gates;
- recoverability when assumptions fail.


2. Preparation Completion Criteria
The project is PRE-IMPLEMENTATION READY when:
- Master Execution Brief current;
- Protocol v1 draft coherent;
- cryptographic construction complete enough for vectors/review;
- Threat Model current;
- Privacy model current;
- storage abstraction current;
- Web Local Search/Client Host architecture current;
- malicious Node model current;
- operations/supply-chain/audit docs current;
- all open hard decisions appear in Decision Register;
- no superseded architecture is presented as normative.


3. Remaining Decisions That Belong to Implementation Evidence
Some questions SHOULD NOT be invented on paper:
- exact work/rate-limit defaults;
- exact Argon2id memory/time cost;
- optimal snapshot/chunk target size;
- FilesystemStore supported scale;
- direct cross-origin vs query-independent Client Host proxy reference profile;
- precise browser persistence behavior;
- exact search ranking weights.


These require benchmarks/prototypes/tests.


4. Remaining Human Gates
Human approval is still required for:
- license;
- exact Protocol v1 crypto byte construction;
- revision/genesis semantics;
- key-rotation countersignature;
- migration evidence levels;
- release trust root/threshold policy;
- Client Host separated-browser reference profile;
- final Production Stable release.


The AI agent MUST NOT fake these approvals.


5. Security Claims
Forbidden:
unhackable;
perfect security;
cannot be compromised;
anonymous;
safe;
trusted;
official Tor Project product;
audited when no audit occurred.


6. Cryptographic Baseline Review
Current design intentionally uses established primitives:
- Ed25519 for application signatures;
- SHA-256 for application content addressing;
- Tor-defined SHA3-256 checksum for v3 address validation;
- RFC 8785 for deterministic JSON;
- Argon2id + XChaCha20-Poly1305-IETF for protected software key envelopes where used.


No custom cryptographic primitive.


7. High-Value Key Decision
Preferred production Service Identity signing is offline.
Online Service signing is optional lower-assurance/hardened-provider mode.


8. Release Trust Decision
Software distribution gets an explicit update trust root and rollback/freeze protection rather than relying only on Git hosting/TLS.


9. Future Compatibility Decision
Protocol v1 remains strict and simple.
Future Tor address/crypto changes create an explicit Protocol migration, not permissive parsing/negotiation.


10. Architecture Residual Risks
Known and accepted for v1:
- malicious Client Host can alter remotely served Web code;
- Sybil independence cannot be proven globally;
- a fully compromised host can defeat secrets available to that host;
- public decentralized data cannot be globally erased;
- availability/completeness cannot be guaranteed by cryptography;
- endpoint/OS/browser compromise is outside Protocol control.


11. Stop-Planning Rule
After this gate is re-PASSed following the current consistency remediation:
new planning documents are created only when implementation reveals:
- contradiction;
- new threat;
- external standard change;
- measured performance issue;
- audit finding;
- user/operator evidence.


Do not postpone coding for speculative elegance.


12. Implementation First
The next work is:
- repository bootstrap;
- Protocol Core;
- schemas/vectors;
- formal model;
- storage contract;
- FilesystemStore;
- SQLiteStore;
- signer abstraction/offline signing;
- Node transport;
- Web Client;
- conformance/chaos testing.


13. Evidence Over Promises
From this point forward every security claim needs:
code;
test;
benchmark;
formal result;
audit result;
or explicit documented assumption.


14. “Forever” Maintenance
No frozen software can remain secure forever without maintenance.


The design instead ensures:
- versioned Protocol;
- compatibility watch;
- archived schemas/vectors;
- safe deprecation;
- update trust;
- key rotation;
- backend portability;
- maintainer succession.


15. Release Readiness Is Not Preparation Readiness
PRE-IMPLEMENTATION READY:
specifications are sufficient to begin.


PROTOCOL STABLE:
requires implementation, vectors, formal checks and human crypto review.


PRODUCTION STABLE:
requires full gates, adversarial tests, operations evidence and independent review status.


16. Final Closure Checklist
[ ] No mandatory SQLite assumption.
[ ] No separate browser extension dependency.
[ ] Service key offline-signing profile.
[ ] Node signer isolation profile.
[ ] Malicious data-node separation/multi-source strategy.
[ ] Malicious Client Host limitation explicit.
[ ] Strict Tor v3 parser.
[ ] Future Tor/crypto migration policy.
[ ] Secure release/update trust root.
[ ] Supply-chain provenance.
[ ] Conformance suite plan.
[ ] Formal model plan.
[ ] Chaos lab plan.
[ ] Accessibility/human-factor plan.
[ ] Privacy/local-query semantics.
[ ] No-JavaScript disclosure.
[ ] Resource-abuse model.
[ ] Backup/restore/incident runbooks.
[ ] Governance/decision register.
[ ] External audit readiness.
[ ] Known limitations.


17. PASS Meaning
PASS means:
“There is no known reason to delay implementation for additional general planning.”


It does NOT mean:
“There can never be a vulnerability.”


18. Final Direction to Coding Agent
When this closure gate is explicitly re-PASSed after the forensic consistency audit:
START IMPLEMENTATION.




19. 2026-10-12 FORENSIC CONSISTENCY REMEDIATION RESULT
Scope:
- 55 active Google Docs total: the Workspace Index plus 54 required-reading specification documents;
- archived/superseded drafts excluded from normative authority;
- line-by-line/manual review plus repeated targeted machine scans;
- Drive-root hygiene rechecked.


Material corrections completed:
- Workspace reading order corrected to unique 1–54 and current titles.
- Source-authority/conflict rule corrected: the Coding Agent may not invent normative Wire/Crypto/State semantics.
- SQLite changed from global requirement to selected StorageBackend dependency; FilesystemStore remains SQL-free and first-class.
- PHP, DoD, tests, operations, DX, performance, supply-chain and governance language made backend-neutral.
- Service Identity private-key custody made offline/SignerProvider-first; ordinary Web runtime does not require the Service private key.
- stable logical_service_id separated from current rotatable Service Identity key/key epoch.
- pinning, exact identity lookup, index membership and operator discoverability updated to use logical_service_id.
- valid key rotation/migration preserves logical identity while updating key epoch/Onion context only with valid evidence.
- signed Node checkpoint histories changed from same-sequence-only checks to chained previous_checkpoint_hash semantics and non-prefix-compatible-history equivocation detection.
- Category Registry v1 made immutable after Protocol Freeze; post-freeze categories require an explicit compatible versioning mechanism or new Protocol version.
- language-tag wire grammar moved behind explicit D-054 freeze instead of vague “BCP47-style” behavior.
- Candidate Announcement made an explicit bounded candidate-hint flow rather than an optional contradictory afterthought.
- WEB-COMBINED-V1 and WEB-SEPARATED-V1 separated; CORS/CSP/network behavior is profile-specific.
- WEB-LOCAL and SERVER-FALLBACK privacy semantics separated; only WEB-LOCAL has zero query-dependent network traffic.
- browser persistence/pins/cache treated as optional/best-effort rather than guaranteed IndexedDB durability.
- Sybil/source wording corrected so distinct Node keys do not imply distinct human/operators.
- release/audit/browser review changed from obsolete extension-permission language to origin/CSP/network-capability review.
- milestone IDs decoupled from execution order; prerequisite/dependency order added so secure coding, category registry, storage abstraction and key custody precede dependent implementation.
- mechanically corrupted insertions and duplicate appended sections repaired, including split Protocol/Crypto/API/Index-Proof/PHP fragments.


Verification evidence:
- Workspace Index currently contains exactly 54 ordered required-reading entries, 1 through 54, with no missing/duplicate reading-order number; together with the Workspace Index itself this yields 55 active Google Docs.
- My Drive root currently contains only one project-related item: the Onion Service Discovery main folder. No project file/archive draft is outside it.
- repeated stale-pattern scans found no remaining known active requirement that globally mandates SQLite/PDO-SQLite, browser-extension permissions, same-origin-only Web architecture, same-sequence-only checkpoint equivocation, or operator independence from Node key count.
- targeted corruption scan no longer finds the previously observed split fragments such as “Node equi”/“vocation”, “signed c”/“heckpoint root”, “ice opt-in”, “u48. CORS”, or the orphaned PHP acceptance fragment.


AI consistency conclusion:
PASS for the audited cross-document consistency/corruption classes as of 2026-10-12.


Non-claim / remaining gates:
- this is NOT Human Protocol approval;
- this is NOT Human Crypto approval;
- this is NOT an external security audit;
- this is NOT Protocol v1 Freeze;
- this is NOT Production Stable approval;
- all D-XXX states in the Decision Register remain authoritative and none were auto-approved by this remediation;
- D-051 through D-054 and every other release-blocking Human Gate must still be resolved before the corresponding freeze/release claim.
Do not create more speculative architecture documents unless a concrete blocker or new finding requires one


20. POST-PASS FINAL RECONCILIATION — 2026-10-12
After the initial consistency PASS, an additional full mechanical/content reconciliation found and corrected a small number of residual issues.


Additional material corrections:
- stable identity lookup is now explicitly GET /onion-service-discovery/v1/services/by-id/{logical_service_id};
- GET /services/{onion} is explicitly address convenience and resolves/exposes logical_service_id rather than acting as the persistent identity key;
- index inclusion/discoverability, PHP Node, dashboard, conformance tests, Test Strategy and backlog all use the same stable lookup semantics;
- Candidate Announcement was removed from the last remaining “if implemented” abuse-language and is consistently a bounded Protocol v1 candidate-hint flow;
- Decision Register corruption around “release blocker yes/no” was repaired;
- Decision Register now states explicitly that proposed/recommended text never constitutes Human approval;
- residual isolated punctuation/character artifacts introduced by historical Google Docs inserts were removed across the active set;
- remaining Extension-era lifecycle “permission inventory” terminology was replaced with Web Client origin/CSP/network-capability inventory;
- Master Brief Web Client wording was updated so WEB-COMBINED-V1 and WEB-SEPARATED-V1 are both first-class and no longer imply that executable client code must come from a Discovery Node.


Final structural verification:
- actual required-reading Google Docs excluding the Workspace Index: 54;
- Workspace Index required-reading entries: 54;
- reading-order numbers: exactly 1 through 54, no duplicates or gaps;
- every indexed document title exactly matches one active Drive document title;
- project main folder contains the expected active subfolders, including “05 - Web Local Search Client”;
- My Drive root project search returns only the Onion Service Discovery main folder.


Final status:
CONSISTENCY RE-AUDIT PASS remains valid for continued implementation.
This still does NOT approve any D-XXX Human Gate, Protocol v1 Freeze, external audit, Production Stable release, actual production seed set, license, release signing mechanism or final cryptographic byte construction.
