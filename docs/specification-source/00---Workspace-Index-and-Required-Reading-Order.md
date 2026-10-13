# 00 - Workspace Index and Required Reading Order

Source: https://docs.google.com/document/d/1jEGUdIe17knsWwzHhIRo5LyBwnVj8Czfy5zkFxs-SSw/edit?usp=drivesdk

Source modified: 2026-10-12T19:27:04.726Z

---

ONION SERVICE DISCOVERY
WORKSPACE INDEX UND VERBINDLICHE LESEREIHENFOLGE


Zweck
Dieser Drive-Ordner ist das vollständige Spezifikations- und Arbeitsauftragspaket für einen KI-Coding-Agenten. Der Agent soll nicht mit Code beginnen, bevor die Pflichtdokumente gelesen und die grundlegenden ADRs erstellt wurden.


HAUPTORDNER
Onion Service Discovery


ORDNERSTRUKTUR
00 - Start Here
01 - Product and Architecture
02 - Protocol Specification
03 - Security and Threat Model
04 - PHP Reference Node
05 - Web Local Search Client
06 - Testing and Verification
07 - Operations and Release
08 - AI Coding Agent Instructions


VERBINDLICHE LESEREIHENFOLGE


1. 00 - MASTER EXECUTION BRIEF - Onion Service Discovery
Rolle:
Oberster Projektauftrag, Mission, nicht verhandelbare Grundsätze, Komponenten, Phasen und High-Level-Definition-of-Done.
URL:
https://docs.google.com/document/d/1iavEaLZX4iFR0taaQ4jp_O-5mXLeTDPmof4x1prVrkA


2. 01 - Definition of Done and Production Readiness Gates
Rolle:
Verbindliche Release-Gates. Definiert, wann “production-ready” gesagt werden darf.
URL:
https://docs.google.com/document/d/1TBZrr-vBQMdKnz43x06yyx38PoSU1ocKaG5OqxrVWfg


3. 01 - Product Requirements and System Architecture
Rolle:
Ziele, Nicht-Ziele, Rollen, Komponenten, Trust Boundaries, Datenflüsse, Zustandsmodell, Ausfallverhalten.
URL:
https://docs.google.com/document/d/1A8nFe3xFiF4w3eYQsAkLoXhFovKb07tXo5rzTfP7YFY


4. 02 - Protocol Version 1 Specification
Rolle:
Normative Protokollregeln, Manifest, Identity, Revisionen, Rotation, Migration, Nodes, Observations, Sync, Fehlerklassen, Limits und Conformance.
URL:
https://docs.google.com/document/d/1jrJyPDRXFChFXSPe9SgOldeVYOcZ-GTmUzH4WmZW64o


5. 03 - Threat Model and Security Architecture
Rolle:
Angreifermodelle, Schutzgüter, Security Invariants, Trust Boundaries, Ressourcenmissbrauch, Privacy, Supply Chain und Incident-Klassen.
URL:
https://docs.google.com/document/d/12wZrysCvp9G7pzYsHw8w8rHQ_bxLxGEpQ-58SjaitDo


6. 03 - Identity Anti-Fraud and Trust UX Requirements
Rolle:
Identitätskopplung, TOFU/Pins, Phishing-/Impersonation-Schutz, Unicode-Verwechslungen, Migration/Rotation, Trust-Labels und Moderationsgrenzen.
URL:
https://docs.google.com/document/d/1vfn7j1WMWNBm3DA1KIsRhU8zyGtm_FdeyCtybmI1mCQ


7. 04 - PHP Reference Node Implementation Specification
Rolle:
Serverseitige Referenzarchitektur, Module, DB, CLI, Tor-Transportadapter, Candidate Queue, Peer Sync, HTTP API, Betrieb und sichere Defaults.
URL:
https://docs.google.com/document/d/1R3I1SbEphWuKyJK4XGx5acf_6Tid3yPpxfqtPDATXBQ


8. 05 - Web Local Search Client Specification
Rolle:
Web-Deploymentprofile (WEB-COMBINED-V1 / WEB-SEPARATED-V1), CSP/CORS/Netzwerkgrenzen, optionale Browser-Persistenz, lokale Suche, Pinning, UX, Datenschutz, Tests und Releaseanforderungen.
URL:
https://docs.google.com/document/d/1UH4HobtYVGVG10nTY8De4C7X-uFIGha1-Cc4XgMigtw


9. 06 - Test Strategy Conformance and Verification Matrix
Rolle:
Gemeinsame Testvektoren, Unit/Integration/E2E, Fuzzing, Property Tests, Query-Privacy, Recovery, Upgrade und Release-Testbericht.
URL:
https://docs.google.com/document/d/13ceVELv_g4BJHeAldwPbPQ6MJPo_NJCGrqUNg2qkMeU


10. 07 - Production Operations Release and Incident Runbook
Rolle:
Installation, Dateirechte, Backups, Restore, Upgrade, Rollback, Release/SBOM/Supply Chain und Incident Response.
URL:
https://docs.google.com/document/d/1t4pj8ohdbf9nAi9JwFuFxkVQK6IsSHCcjIC7VVTcFsg


11. 08 - Autonomous AI Coding Agent Master Work Order
Rolle:
Direkter Arbeitsauftrag an den Coding-Agenten: Arbeitsmodus, Phasen, ADR-Pflichten, Coding-Regeln, Human Gates, Selbstreview, Release Evidence.
URL:
https://docs.google.com/document/d/1cULvv2DZXskb5Gg_D2al7biY1xNFUf4OvyWrIQ5DAX0


12. 09 - Implementation Backlog and Milestone Checklist
Rolle:
Operativer 38-Milestone-Backlog vom Specification Ingestion bis Post-Release mit Exit Criteria und Evidence-Pflicht.
URL:
https://docs.google.com/document/d/1Hwzdbl4RUEkUJkTDqkc61nHu-Jjnux5vMJYvGoNhVR0


WIE EIN CODING-AGENT GESTARTET WERDEN SOLL


Auftrag:
“Lies den gesamten Google-Drive-Ordner Onion Service Discovery in der im Dokument ‘00 - Workspace Index and Required Reading Order’ angegebenen Reihenfolge. Behandle die Dokumente als verbindliche Produktspezifikation. Beginne mit Milestone 0 des Backlogs. Schreibe noch keinen produktiven Netzwerkcode, bevor alle erforderlichen ADRs, Protocol-v1-Testvektoren und Security Invariants erstellt und geprüft sind. Arbeite Milestone für Milestone. Jeder Abschluss benötigt Evidence, Tests, Self-Review und aktualisierte Dokumentation. Melde ein Release niemals als production-ready, solange die Definition-of-Done-Gates nicht nachweislich PASS sind. Erfinde keine menschliche Freigabe; markiere Human Gates als offen, bis ein Mensch sie bestätigt.”


AUTORITÄTSREGEL
Bei Konflikten:
1. explizite Security Invariants und MUST/MUSS-Anforderungen;
2. Protocol Version 1 Specification;
3. Master Execution Brief;
4. komponentenspezifische Implementierungsspezifikationen;
5. Product Architecture;
6. illustrative Beispiele.


Ein echter Konflikt wird durch ADR dokumentiert und nicht still interpretiert.


NAMENSREGEL
Der öffentliche Name lautet immer vollständig:
Onion Service Discovery


Keine abgekürzte Produkt-/Protokollbezeichnung in Nutzeroberflächen, README-Titeln oder Spezifikationsnamen.
Slug:
onion-service-discovery


ERWARTETER ENDZUSTAND
Der Auftrag endet nicht bei “Code kompiliert”.
Gefordert ist ein vollständiges produktionsfähiges Paket:
- Protocol v1;
- Schemas;
- Conformance/Test Vectors;
- PHP Reference Node;
- Web Local Search Reference Client with explicit No-JavaScript Server Search fallback;
- lokale Suche;
- Identitätskontinuität;
- Key Rotation;
- Onion Migration;
- Peer-Sync;
- bounded Discovery;
- Anti-Fraud/Trust UX;
- CI;
- Tests/Fuzzing;
- SBOM;
- Security/Privacy-Dokumentation;
- Operator Guides;
- Backup/Restore;
- Upgrade/Rollback;
- Incident Runbooks;
- Release Evidence;
- vollständige Gate Matrix.


WICHTIGE PRODUKTGRENZE
Onion Service Discovery bestätigt technische Identität/Kontinuität und unterstützt Auffindbarkeit. Es garantiert nicht, dass Inhalte oder Betreiber vertrauenswürdig sind.


DIESER INDEX IST DIE EINSTIEGSSEITE DES PROJEKTAUFTRAGS


13. 10 - Required Engineering Review and Release Templates
Rolle:
Verbindliche Vorlagen für ADRs, Protocol Change Proposals, Security Exceptions, Change Self Reviews, Milestone Reports, Incident Reports, Dependency-/Permission-Reviews, Privacy-Inventar, Release-Gate-Matrix und Production Handoff.
URL:
https://docs.google.com/document/d/1gQrpvdulg1TMiiAHo6TPGQvhNdH5Qw99ByS-SA-3sLg




MANDATORY ADDENDUM — AUDIT-GRADE EXPANSION


The following documents are mandatory and part of the same specification set. They close protocol, security, standards, governance and audit gaps that would otherwise be left to implementation discretion.


14. 11 - Current Standards Baseline and External References
Role:
Authoritative external-reference baseline and mandatory refresh policy for Tor specifications, Tor Browser Web behavior, PHP support, RFC 8785, OWASP ASVS, SLSA and CycloneDX.
URL:
https://docs.google.com/document/d/1UEeaBpx6Wnnjujk5dzELF3rxYgDc0Ci2eLomxzc6_10


15. 12 - Protocol Limits Encoding Registry and State Machines
Role:
Normative byte/field limits, exact encodings, integer interoperability limits, error registry, candidate/service/peer/sync state machines and work-budget principles.
URL:
https://docs.google.com/document/d/1-IqYDKd5RLl3bVUA5BjKoe94bUohVXpGBKtFQIn_Rec


16. 13 - Cryptographic Construction Key Lifecycle and Domain Separation
Role:
Normative signed-byte design, domain separation, manifest hashes, key generation/storage/rotation, Onion migration, conflict semantics and cross-language crypto test vectors.
URL:
https://docs.google.com/document/d/1YlAsPNdAwUmY6EjJDSRSEuo0pmuYSol84gwxezAmqlI


17. 14 - Node API Sync Contract and Interoperability Specification
Role:
Normative read API, state/delta/snapshot/chunk semantics, cursors, batching, conflict application, cache behavior and interoperability fixtures.
URL:
https://docs.google.com/document/d/1vcK0HW3newL3tgIDHdrI8oieSANKXRvB42tvosn3HoY


18. 15 - Abuse Resistance Sybil Spam and Resource Exhaustion Model
Role:
Mandatory candidate/peer/work quotas, retry/backoff, Sybil constraints, spam ranking considerations, disk/CPU/network bounds and overload behavior.
URL:
https://docs.google.com/document/d/1Kg3MOLH7urZ3i6UHTa0Hte3AnZOC8BwgvFBOJHhj7P8


19. 16 - Privacy Data Classification Retention and Logging Specification
Role:
Normative data inventory principles, query privacy, click/pin/favorite handling, logging/retention, diagnostics, withdrawal limitations and privacy tests.
URL:
https://docs.google.com/document/d/1afBIjZ14dkuQdu857tUjSvX-aANQmxJa7GjyzSBhFAI


20. 17 - Canonical Data Model Storage Invariants and Retention Schema
Role:
Logical evidence/current-view model, key epochs, forks, migrations, observations, candidates, snapshots, DB transactions, retention tiers and SQLite recovery invariants.
URL:
https://docs.google.com/document/d/1ACK4gDp3EB-f8wROvB5tspAzdlEyk4mwF6HcuNHX5dQ


21. 18 - PHP Node Deployment Hardening and Secure Configuration Matrix
Role:
Production PHP-FPM/webserver/filesystem/Tor-SOCKS hardening, key/DB permissions, scheduler, firewall defense-in-depth, secure defaults and deployment self-checks.
URL:
https://docs.google.com/document/d/1p07E8Y31GQY-_LQQon8f1mmXthZVVkmYDDsWji13CLA


22. 19 - Web Search Client Security Privacy and No-JavaScript Fallback Review
Role:
Mandatory Web Local Search origin-trust, CSP/XSS, no-remote-asset, local-query privacy and No-JavaScript server-search review.
URL:
https://docs.google.com/document/d/1_1LTqsSS_402Jyb0IUvZo4y8ZG5geePELgeaJdDwx2I


23. 20 - Project Governance Protocol Change and Security Decision Policy
Role:
ADRs, protocol proposals, breaking-version rules, seed governance, human/AI authority boundaries, emergency changes and security-exception governance.
URL:
https://docs.google.com/document/d/1e9oZYaeJIP8SzlMrXk7f5G6YVvmFSQucQd3lvZx3aj8


24. 21 - Software Supply Chain Reproducible Build and Release Provenance Standard
Role:
Lockfiles, dependency governance, SBOM, SLSA-aligned provenance, reproducible clean builds, artifact signing/hashes, CI least privilege and release archive.
URL:
https://docs.google.com/document/d/1xG4VpHZZhoW_pbdNipxqTXT4OUb9oJ20P7OqPeFyoj8


25. 22 - Independent Security Audit Readiness and Verification Plan
Role:
External audit package, ASVS mapping, independent build/testing, crypto/Tor/browser/privacy review scope and audit-finding remediation.
URL:
https://docs.google.com/document/d/1_ZF_9UOwtv3FpkVypW5dnXzIYkmJnQz5Kx7Jpn5MKXg


26. 23 - Adversarial Red Team Review and Final Challenge Checklist
Role:
Hostile final review designed to falsify identity, privacy, decentralization, parsing, crypto, network, storage, extension, abuse, operations and supply-chain claims.
URL:
https://docs.google.com/document/d/17A6V9fEYKNRMeS7beT7a7yMt6wbpBsjIDWzWhZzAMjE


ADDITIONAL FOLDERS


09 - Standards Governance and Compliance
https://drive.google.com/drive/folders/1VJhGttx6xlVyKHW3RK8HSyifgCd16WU5


10 - Audit and Acceptance
https://drive.google.com/drive/folders/1oNWsZedXgnYPjgMJupFaqpCgNufRBIOz


UPDATED AGENT RULE


All documents 1 through 25 above are mandatory reading. The coding agent MUST NOT interpret documents 11–23 as optional future work. They refine and constrain the earlier documents. Where a later document makes an earlier generic requirement more precise without contradicting it, the later precision controls. Genuine contradictions must be recorded and resolved through ADR/Protocol Change Proposal before implementation.


Before writing network or cryptographic production code, the agent MUST specifically complete:
- standards refresh register;
- protocol limits registry;
- crypto/domain-separation ADRs and vectors;
- API/sync contract freeze draft;
- abuse work-budget configuration;
- privacy data inventory;
- canonical data model;
- Web Local Search storage/origin-trust/CSP/server-search privacy ADR;
- deployment hardening matrix.


Before Release Candidate, it MUST complete:
- supply-chain evidence;
- governance self-audit;
- independent-audit readiness package;
- full adversarial red-team checklist with evidence/status.




27. 24 - Pre-Freeze Decision Register and Human Approval Gates
Role:
Explicit register of every decision that must not remain implicit: license, runtime baseline, Web Local Search browser compatibility/origin trust/server-search privacy, exact crypto bytes, genesis/revision rules, rotation/migration evidence, snapshot/delta format, seed set, release signing, rate limits, Tor isolation and final production approval.
URL:
https://docs.google.com/document/d/1_B5uqXfbpNJ73JY1qY7ysIUfQjB5Hy66k7TqkY_f61o


FINAL READING RULE
This decision register is mandatory before Protocol Freeze. Any item marked release blocker must be APPROVED, not merely PROPOSED or NEEDS-HUMAN-REVIEW, before Production.




28. 25 - Normative Glossary Terminology and Security Semantics
Role:
Single project-wide definitions for Onion address, Service Identity, verification, observations, forks, migration, trust wording, decentralization, privacy and production-readiness semantics.
URL:
https://docs.google.com/document/d/19HdzJKytmIt44-HrWAsnmMLtblsuBJjWpiRk08qxxvU


29. 26 - Known Limitations Non-Claims and Future Research Boundary
Role:
Explicit limitations and non-claims covering trust, human identity, completeness, Sybil resistance, deletion, timing analysis, Web-serving-origin trust, key loss, endpoint compromise and future features that must stay outside Protocol v1 without separate review.
URL:
https://docs.google.com/document/d/1CJo71y488nBv81WM-6GVfQ5rf_K9F7hn4llcDy9VBfY


FINAL COMPLETENESS RULE
The agent must copy the normative glossary and known-limitations document into the implementation repository and keep them synchronized with behavior. Any new security claim or security-semantic term requires review against both documents.




AUDIT-PLUS EXPANSION — FORMAL METHODS, INTEROPERABILITY, TOOLING AND ADOPTION


The following documents are mandatory and extend the required reading order after document 26.


30. 27 - Formal Protocol Model and Model-Checking Verification Plan
Role:
Formal state model for revisions, forks, rotations, migrations, candidate verification, sync commit ordering, pins and liveness separation.
URL:
https://docs.google.com/document/d/1ZexdGAgo746df46SRwJOttMmpXq94QydK2I-LsFIUzs


31. 28 - Independent Conformance Suite and Certification Harness
Role:
Language-independent black-box and exact-byte interoperability suite with CORE/NODE/CLIENT/FULL profiles and reproducible reports.
URL:
https://docs.google.com/document/d/1fasTLWHCM9T2D2EiwUBf8iyzYUxGoT84pjI0Ed6lnHA


32. 29 - Reproducible Test Network Chaos Lab and Failure Injection Specification
Role:
One-command local multi-node test world, controlled Tor/mock profiles, malicious personas, crash/fault injection, stress tests and release evidence.
URL:
https://docs.google.com/document/d/19eXxSqAUNN3zU523KW8deC8O1pJfyTCYy-chRgZ_8FQ


33. 30 - Internet Standards IANA Registration and RFC Publication Plan
Role:
RFC 8615 Well-Known registration readiness, RFC 9457 error model, JSON Schema Draft 2020-12 discipline, registries and public standards publication path.
URL:
https://docs.google.com/document/d/1BtOUvrmldMUcytfqO29GIx6AnCkKaw9ooS9qWgFeNm0


34. 31 - Accessibility Security UX and Human-Factors Validation Standard
Role:
WCAG 2.2 AA target, accessible warnings, security-copy discipline, comprehension testing, Unicode/confusable UX and human-factors acceptance.
URL:
https://docs.google.com/document/d/1p4czVA3MQXFxkOjSCejdA6xam_U7S78NmkDu41b3Smw


35. 32 - Operator Developer Experience and Diagnostic Command Standard
Role:
Production CLI, doctor/security checks, machine-readable health, support bundle, dangerous-command safeguards and contributor bootstrap.
URL:
https://docs.google.com/document/d/1rkfhjdiLlpoHDKmhtJCaq_pbxuXSflGZPkdFARNsTqI


36. 33 - Capacity Performance Scalability and Benchmarking Model
Role:
1k/10k/100k/1m synthetic scale tiers, Tor-aware concurrency, search/sync/storage budgets, low-end device testing and benchmark-derived defaults.
URL:
https://docs.google.com/document/d/1uOqAkyTQTtpcw_UFK2tgD9uwAnsx7U-ANiYoYLHEgFw


37. 34 - Synthetic Reference Dataset Fixtures and Golden Corpus Standard
Role:
Deterministic public synthetic datasets, invalid corpus, identity/fork/migration stories, benchmark fixtures and no real user/service data.
URL:
https://docs.google.com/document/d/1bma0eBbVdyjw1uvGD7mnN0GEKZngTRS9npaNaxQOx8c


38. 35 - Record Lifecycle Matrix and Protocol Archaeology Standard
Role:
Complete lifecycle/retention matrix and reproducibility of historical interpretation across software, Protocol, schemas, ranking and migrations.
URL:
https://docs.google.com/document/d/1i5kPoX4xFZ2eGyoGGmNl6GGy_jCaB8VS1KiR4pvEH8A


39. 36 - Design Rationale Alternatives and Rejected Architectures
Role:
Why no central search, registry, majority trust, blockchain or initial DHT; why PHP, SQLite, local search, separate keys, pull sync and preserved forks.
URL:
https://docs.google.com/document/d/11YY3OH0OnJtRHf3JkTgidTDigN2GgdSybtmBFjTx7Gs


40. 37 - Independent Minimal Validator SDK and Multi-Language Adoption Strategy
Role:
Third-language independent Protocol Core, SDK boundaries, misuse-resistant APIs, multi-language compatibility and community implementation rules.
URL:
https://docs.google.com/document/d/1OilZe0tPQL3vjsIWgW-5ZNYhXJkmLeUAk6zWsf88eeM


41. 38 - Ecosystem Adoption Integration and Long-Term Evolution Strategy
Role:
Publish-only adoption, Node ecosystem, alternative clients, maintainer succession, seed governance, Protocol maturity and no central runtime dependency.
URL:
https://docs.google.com/document/d/1NbPjqAkr5s0MiM5lCU98zgFDwaa30PoUlpiM5OodmiE


42. 39 - Public Protocol Handbook RFC-Style Publication Blueprint
Role:
Single public implementer-facing Protocol document structure, editorial rules, normative appendices, security/privacy/IANA sections and public-review process.
URL:
https://docs.google.com/document/d/1ko-SYPTrgJCA-HfyHzYl_ibNK5HSQwZPa1aInXD_MKI


43. 40 - Five-Minute Onion Service Operator Contract and Consequence Summary
Role:
Concise operator-facing contract explaining what becomes public, key backup, withdrawal, migration, downtime, Node roles and technical verification limits.
URL:
https://docs.google.com/document/d/1ZiRFTgUwz0zlacA_HU3LVqFIqxIpH39JWTkVqeK935k


NEW FOLDERS


11 - Formal Methods and Interoperability
https://drive.google.com/drive/folders/17MgPwclCmPaxYDw1gGxhd1KpIWlxBtid


12 - Test Network Developer Experience and Tooling
https://drive.google.com/drive/folders/1aQM4YjsyaIT-9ajkkag9PY37_yoTeI7-


13 - UX Accessibility and Adoption
https://drive.google.com/drive/folders/1t3w4OcYwizoYa1D3LQUDRApt2KY5bcuB


MANDATORY READING RULE
Documents 27 through 40 are not optional future ideas. They define the evidence, interoperability, human-factors and long-term-maintenance bar required before the project can claim mature production readiness.


Before Protocol v1 Stable:
- formal model clean;
- CORE conformance passes PHP, TypeScript and an independent third-language validator;
- standards/IANA plan reviewed;
- exact Protocol Handbook generated;
- lifecycle matrix complete.


Before Production Stable:
- Chaos Lab release profile passes;
- WCAG/security-UX gates pass;
- operator/developer walkthroughs pass;
- benchmark-derived safe defaults published;
- synthetic corpus published;
- maintainer succession/adoption requirements documented.




44. 41 - Pluggable Storage Backends and SQL-Free Persistence Standard
Role:
Makes persistence an operator-selectable implementation detail instead of a Protocol requirement. Defines FilesystemStore, SQLiteStore, PostgreSQL/MySQL/MariaDB adapter expectations, backend-neutral atomicity, crash recovery, canonical export/import and SQL-free operation.
URL:
https://docs.google.com/document/d/1tOvXkoRp3bXi95xOi2PqLGIXatdhLXVmDDRN2jThIXY


45. 42 - Malicious Node Isolation Client Host and Multi-Source Integrity Architecture
Role:
Separates executable Client Host from data-only Discovery Nodes, defines multi-source union, signed checkpoints, rollback/equivocation detection, self-hosted Client Host and the exact residual trust boundary of remotely served browser code.
URL:
https://docs.google.com/document/d/1FtNkCQMShutKcLzRwVA-LBUS7XuCa_GFCPqgRDty4PY


ARCHITECTURE REVISION — STORAGE
SQLite is no longer a project-wide requirement.
The operator selects a conformant storage backend.
FilesystemStore is the required no-SQL baseline.
SQLiteStore remains a supported embedded option.
PostgreSQL and MySQL/MariaDB are production adapter targets subject to conformance.


ARCHITECTURE REVISION — MALICIOUS NODE
The stronger browser profile separates:
Client Host = executable static UI
Discovery Node = data only.


A single malicious data Node must not control the entire discovery view.
Reference client supports multiple sources, valid-record union, source attribution, signed checkpoint comparison, rollback protection and equivocation evidence.


Residual limitation:
a malicious Client Host can still alter Web code.
Strongest browser-only profile:
self-hosted/trusted Client Host + multiple distinct selected Discovery Node identities/source groups; operator independence is claimed only when actually documented.




46. 43 - High-Assurance Key Custody Offline Signing and Signer Provider Standard
Role:
Makes the high-value Service Identity key offline by default, defines signer-provider isolation, encrypted software-key envelopes, HSM/TPM/KMS/external-signer options, backup recovery, and compromise containment.
URL:
https://docs.google.com/document/d/1jZ238Q7f5v2aFBnvR4HN-005fcW4e_ZhCtdZ7XvXpgU


47. 44 - Secure Release Trust Root Update Rollback and Compromise Recovery Standard
Role:
Adds an explicit software-release trust root, TUF-style rollback/freeze protection, offline/threshold release roles, artifact verification and release-key compromise recovery.
URL:
https://docs.google.com/document/d/19x6FUX2YoWs7IFA2cbKDVt7gNf7wB3FAklcYmz7hlII


48. 45 - Tor Compatibility Crypto Agility and Protocol Migration Policy
Role:
Defines strict Tor v3 support today, fail-closed unknown Onion versions, no v1 algorithm negotiation, Protocol-v2 migration rules and compatibility monitoring for Tor/crypto/browser/runtime changes.
URL:
https://docs.google.com/document/d/17rs4a64M6yk-wcGqooM9k71ACj77NdBJQnsWDWtvGTQ


49. 46 - Final Pre-Implementation Closure Audit and Specification Freeze
Role:
Final planning gate. Once PASS, general architecture planning stops and implementation begins. New planning artifacts are created only for concrete findings, external changes, audit issues or measured problems.
URL:
https://docs.google.com/document/d/1qjIhv8nJyBeQbhKJpSxoxpuC-T7QE_4uLYU4UNjvjXc


FINAL PRE-IMPLEMENTATION RULE
After document 46 is satisfied, the project is PRE-IMPLEMENTATION READY.
This status means there is no known reason to delay coding for more general planning.
It does not claim vulnerability-free software.




50. 47 - Plug-and-Play Operator Dashboard Self-Service Onboarding and Discoverability UX Standard
Role:
Defines the simplest operator path: one clear config, signed Manifest, own-Onion status dashboard, self-verification, bounded self-announcement, external observation/searchability evidence, truthful discoverability states and mobile-first remediation UX.
URL:
https://docs.google.com/document/d/1KFC2Iah1WcHepCbuF0Gcm1eCqMvMiOFSRm0ADXvKGHk


FINAL OPERATOR-EXPERIENCE RULE
The normal operator who only wants to be found must not need to understand peer protocols, databases or cryptographic internals.


They should be able to configure, open their own Onion Service status page and answer immediately:
Is publishing enabled?
Is my Manifest valid?
Can the network reach it?
Have external Nodes observed it?
Is my current revision searchable?
What should I do next?




51. 48 - Verifiable Discoverability Index Inclusion and Checkpoint Proof Standard
Role:
Defines how an operator proves that their exact current Service Identity, Onion, revision and Manifest hash are actually present in a Node's signed active search index. Includes deterministic identity lookup, signed checkpoints, stale/omission handling, rollback/equivocation detection, multi-source comparison and optional compact Merkle inclusion proofs.
URL:
https://docs.google.com/document/d/16JxH-eAjGOsSVAl79okb2YgW_7qBjIziXJq7PIge-eI


DISCOVERABILITY EVIDENCE RULE
The operator dashboard MUST derive the green DISCOVERABLE state from actual locally verified index membership evidence for the exact current Service tuple.


A Node's simple status claim is not enough.
Ordinary text-search ranking is not used as the proof mechanism.




52. 49 - Sybil-Resistant Identity Quantity Neutrality Source Diversity and Admission Control Standard
Role:
Prevents cheap mass-created Onion/Service/Node/Witness identities from becoming authority. Defines zero trust weight for raw identity counts, bounded peer/source admission, source grouping, eclipse resistance, target-centric deduplication, search-farm defenses, objective misbehavior proofs and the strict boundary that optional PoW is admission effort only.
URL:
https://docs.google.com/document/d/1S9XHh_lmhPrau9oI2xRFro-JVchVKeXE6XZGqHvMKXA


SYBIL HARD RULE
More cryptographic identities do not mean more independent actors.
More identities alone MUST NOT create more trust, ranking authority, identity authority or moderation authority.




53. 50 - Protocol Category Registry v1 Tags Localization and Content Classification Standard
Role:
Freezes the Protocol v1 broad category vocabulary, including adult, separates categories from moderation/trust, defines localization and future tags, and requires a single machine-readable registry source for schemas/code.
URL:
https://docs.google.com/document/d/1OkWgB_tMUtBJ8iZXqyqBFfiIKwQcyXyFURtxijNNZhw


54. 51 - Secure Coding Defensive Programming Input Output Validation and Exploit Resistance Standard
Role:
Defines mandatory secure coding construction rules, validation/output encoding, PHP/browser/network/filesystem/SQL hardening, bounded resource handling, dangerous API policy, fuzz/property/mutation testing and OWASP ASVS/Top 10/CWE coverage.
URL:
https://docs.google.com/document/d/101_LkUsgBbt09iSmKrk9vXIzJPccscBUCeBILyAWGJA


CATEGORY RULE
Protocol categories are now a versioned registry, not arbitrary free text.
adult is explicitly included.


SECURE CODING RULE
General feature implementation MUST use the constrained security primitives and coverage/test requirements in document 51.
