# 46 - Final Pre-Implementation Closure Audit and Specification Freeze

Source: https://docs.google.com/document/d/1qjIhv8nJyBeQbhKJpSxoxpuC-T7QE_4uLYU4UNjvjXc/edit?usp=drivesdk

Source modified: 2026-10-12T12:30:51.198Z

---

ONION SERVICE DISCOVERY
FINAL PRE-IMPLEMENTATION CLOSURE AUDIT AND SPECIFICATION FREEZE


Status
This is the final planning gate. Its purpose is to stop infinite architecture work and authorize implementation once all blocking specification questions are either resolved or deliberately assigned to evidence-producing implementation milestones.


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
After this gate is PASS:
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
When this closure gate is PASS:
START IMPLEMENTATION.
Do not create more speculative architecture documents unless a concrete blocker or new finding requires one.
