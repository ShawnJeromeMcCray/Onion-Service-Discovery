# 08 - Autonomous AI Coding Agent Master Work Order

Source: https://docs.google.com/document/d/1cULvv2DZXskb5Gg_D2al7biY1xNFUf4OvyWrIQ5DAX0/edit?usp=drivesdk

Source modified: 2026-10-12T12:31:50.778Z

---

ONION SERVICE DISCOVERY
AUTONOMOUS AI CODING AGENT MASTER WORK ORDER


ROLE
Du bist der verantwortliche Principal Software Engineer, Security Engineer, Protocol Engineer, QA Lead und Release Engineer für Onion Service Discovery. Deine Aufgabe ist nicht, einen Prototypen zu erzeugen, sondern ein auditierbares, wartbares, dokumentiertes und produktionsreifes Open-Source-System gemäß den Projektunterlagen zu implementieren.


Du darfst Sicherheitsanforderungen nicht still vereinfachen. Du darfst Spezifikationen nicht durch Annahmen ersetzen, wenn eine Entscheidung dauerhaft Wire Format, Identität, Kryptographie, Privatsphäre oder Datenmigration beeinflusst.


1. VERBINDLICHE LESEREIHENFOLGE
Vor dem ersten produktiven Code vollständig lesen:
1. 00 - MASTER EXECUTION BRIEF - Onion Service Discovery
2. 01 - Definition of Done and Production Readiness Gates
3. 01 - Product Requirements and System Architecture
4. 02 - Protocol Version 1 Specification
5. 03 - Threat Model and Security Architecture
6. 04 - PHP Reference Node Implementation Specification
7. 05 - Web Local Search Client Specification
8. 06 - Test Strategy Conformance and Verification Matrix
9. 07 - Production Operations Release and Incident Runbook
10. dieses Dokument
11. Implementation Backlog and Milestone Checklist, sobald vorhanden.


Nach dem Lesen erstellst du eine kurze “Specification Understanding” im Repository:
- Ziele;
- Nicht-Ziele;
- Komponenten;
- Security Invariants;
- offene Entscheidungen;
- Abhängigkeiten;
- Release Gates.


2. PRIORITÄT DER QUELLEN
Bei Konflikten:
A. Security-Invarianten und explizite MUST/MUSS-Regeln.
B. Protocol Specification.
C. Master Execution Brief.
D. Component Specifications.
E. Architecture.
F. Beispiele/Illustrationen.


Wenn Konflikt nicht eindeutig auflösbar:
- ADR erstellen;
- sicherste rückwärtskompatible Variante wählen;
- Konflikt markieren;
- betroffene Tests hinzufügen.
Keine stille Interpretation.


3. PROJEKTNAME
Öffentlich immer vollständig:
Onion Service Discovery


Keine abgekürzten Produkt- oder Protokollnamen und keine Akronyme verwenden. Öffentlich immer den vollständigen Namen Onion Service Discovery ausschreiben.


Slug:
onion-service-discovery


Interne lokale Variablennamen dürfen angemessen kurz sein, solange öffentliche Artefakte den ausgeschriebenen Namen verwenden.


4. ARBEITSMODUS
Arbeite in kleinen, reviewbaren vertikalen Schritten.
Jeder Schritt:
PLAN
IMPLEMENT
TEST
STATIC ANALYZE
SELF-REVIEW
DOCUMENT
VERIFY
COMMIT/CHANGESUMMARY


Kein riesiger ungetesteter Code-Dump.


5. KEINE PROTOTYP-ABKÜRZUNGEN IM PRODUKTIONSCODE
Verboten:
- TODO als Ersatz für Security Validation;
- “temporarily disable signature verification”;
- hardcoded production secret;
- permissive catch-all;
- ungeprüftes Netzwerk-JSON direkt in Domain;
- beliebige URL Fetch-Funktion;
- unbounded loops/queues/responses;
- remote script loading;
- pauschales “trust peer”;
- last-write-wins für Identität;
- Tests löschen/lockern, um Build grün zu bekommen.


6. INITIALER REPOSITORY-AUFBAU
Erzeuge eine nachvollziehbare Monorepo-Struktur, beispielsweise:


/
  README.md
  SECURITY.md
  PRIVACY.md
  CONTRIBUTING.md
  LICENSE
  CHANGELOG.md
  docs/
    architecture/
    protocol/
    security/
    operations/
    adr/
    conformance/
  protocol/
    schemas/
    test-vectors/
  node-php/
    src/
    bin/
    public/
    config/
    tests/
  web-local-search-client/
    src/
    tests/
  tools/
  .github/ oder CI-provider-spezifisch


Passe Struktur nur mit ADR an, wenn bessere Gründe bestehen.


7. ADR-PFLICHT VOR NETZWERKCODE
Erstelle mindestens:
ADR-001 Runtime- und Toolchain-Versionen
ADR-002 Canonical JSON und Hashing
ADR-003 Signaturformat und Domain Separation
ADR-004 Service Identity Key Lifecycle
ADR-005 Node Identity Key Lifecycle
ADR-006 Manifest Schema und Unknown/Duplicate Field Policy
ADR-007 Tor Transport und Isolation
ADR-008 SQLite Storage/Transactions
ADR-009 Sync State/Delta Model
ADR-010 Web Local Search Client Storage/Cache Strategy and Serving-Origin Trust Boundary
ADR-011 Local Search Approach
ADR-012 Dependency Selection Policy
ADR-013 Release Signing/Provenance
ADR-014 Logging/Telemetry Policy


Jedes ADR:
Context
Decision
Alternatives
Security impact
Privacy impact
Compatibility
Testing impact
Migration impact
Status


8. SECURITY INVARIANTS ALS CODE
Erzeuge eine Datei docs/security/invariants.md und testbare IDs:
SEC-INV-001 Private Tor key never read by application.
SEC-INV-002 Direct manifest host equals manifest onion.
SEC-INV-003 Peer assertion never becomes direct observation without direct verification.
SEC-INV-004 Lower revision never replaces higher valid revision.
SEC-INV-005 Equal revision/different hash is fork.
SEC-INV-006 Key change requires valid rotation proof.
SEC-INV-007 Onion change requires migration proof for continuity.
SEC-INV-008 Search query never leaves client.
SEC-INV-009 Moderation does not rewrite identity.
SEC-INV-010 Resource usage bounded.
Weitere Invarianten aus Threat Model ergänzen.


Jede Invariante erhält Tests oder Review Gate.


9. PHASE 0 — SKELETON
Deliverables:
- Repo Struktur;
- license decision documented;
- language/toolchain pinned;
- formatters;
- lint;
- static analysis;
- test runners;
- CI skeleton;
- security scanning skeleton;
- docs structure;
- ADR template;
- issue/PR templates optional;
- no functional networking yet.


Exit:
Clean checkout => install => lint/test skeleton green.


10. PHASE 1 — PROTOCOL CORE
Implementiere zuerst sprachunabhängige Spezifikation/Testvektoren.
Deliverables:
- JSON Schemas;
- canonicalization vectors;
- key/signature vectors;
- hash vectors;
- valid/invalid manifest corpus;
- error code registry;
- limits table;
- version policy.


Dann PHP und TypeScript Parser/Validator, die dieselben Vektoren bestehen.


Exit:
Cross-implementation bytes/hash/signature behavior identisch.


11. PHASE 2 — IDENTITY
Implementiere:
- ServiceManifest;
- revision chain;
- fork detection;
- key rotation;
- onion migration;
- direct observation;
- Node Descriptor.


Keine Netzwerkabhängigkeit; alles gegen statische Testvektoren.


Exit:
Alle Identity/Revision/Rotation/Migration Conformance Tests grün.


12. PHASE 3 — PHP STORAGE
Implementiere:
- SQLite migrations;
- repositories;
- transactions;
- quarantine;
- candidate store;
- sync state;
- backup-friendly file layout.


Tests:
fresh migrate;
migration repeat;
constraints;
rollback;
corrupt fixture behavior;
locking.


Exit:
Storage kann vollständige Domainhistorie ohne unsafe overwrite abbilden.


13. PHASE 4 — CONFIG UND CLI
Implementiere:
- config schema;
- startup validation;
- key generation;
- manifest generation;
- db migration;
- db verify;
- status;
- offline manifest verification.


Noch kein fremder Netzwerktraffic nötig.


Exit:
Operator kann lokale Instanz sicher initialisieren.


14. PHASE 5 — TOR TRANSPORT
Implementiere zentralen Transport Adapter.
Kein anderer Modulcode darf eigene Onion-Netzwerkrequests starten.


Vor Implementierung Threat Model erneut lesen.


Pflicht:
- strict target validation;
- configured proxy only;
- bounded bytes;
- bounded time;
- bounded redirects;
- typed failure results;
- no silent fallback.


Tests verwenden Mock/Controlled Environment.
Keine Produktionsfreigabe allein aufgrund manueller Erfolgsprobe.


15. PHASE 6 — DIRECT VERIFIER
Pipeline atomar und nachvollziehbar.
Jeder Prüfschritt hat eigenen Result Type.
Bei Fehler:
keine teilweise VERIFIED State Mutation.


Erzeuge Audit/trace-friendly Event IDs ohne sensitive Inhalte.


16. PHASE 7 — PUBLIC NODE API
Read-only zuerst.
OpenAPI oder gleichwertige maschinenlesbare API-Spezifikation erstellen.
Contract Tests.
Keine Remote Administration.


Exit:
Zweiter Testnode kann Descriptor/State/Records lesen.


17. PHASE 8 — PEER SYNC
Pull-basiert.
Implementiere zunächst einfaches korrektes Delta/Snapshot-Verfahren.
Erst danach Optimierungen.


Pflicht:
- staging;
- validation;
- transaction;
- conflict/quarantine;
- sync cursor after commit;
- per-peer budgets;
- backoff.


18. PHASE 9 — DISCOVERY
Candidate ingestion aus klaren Quellen.
Keine unbounded recursive discovery.
Work scheduler.
Fairness/priority policy.
Rate limits.


Exit:
Mass candidate fixture bleibt innerhalb definierter CPU/Memory/DB/Network Budgets.


19. PHASE 10 — WEB LOCAL SEARCH CLIENT CORE
Implementiere:
- statischen HTML/CSS/TypeScript-Build;
- same-origin Snapshot-/Chunk-Loader;
- Protocol-Core-Verifikation;
- In-Memory-Index als Baseline;
- optionales origin-lokales Cache/IndexedDB ohne Persistenzannahme;
- Web Client Build-/Asset-Manifest;
- strikte CSP;
- keine Remote Runtime Dependencies;
- keine Analytics;
- keine Browser-Extension;
- keine Content Scripts;
- keine Query-abhängigen Requests.


Pflicht:
- Network-Instrumentation-Test beweist, dass lokale Suche keinen Querytext überträgt;
- Client funktioniert auch, wenn Browser-Speicher nicht verfügbar ist;
- Android- und Desktop-Tor-Browser-Testprofile;
- Serving-Origin-Trust-Boundary dokumentiert.


20. PHASE 11 — SEARCH, UX UND NO-JAVASCRIPT FALLBACK
Implementiere Local Search:
- Search Index;
- deterministic ranking;
- filters;
- result cards;
- detail view;
- warnings;
- identity fingerprint;
- full Onion display;
- optional origin-local pinning;
- migration history.


Implementiere optionalen Server Search Fallback:
- plain HTML form;
- POST only;
- query not in URL;
- explicit privacy notice;
- no query logging;
- no query-triggered remote work;
- bounded work/results;
- no-store response;
- ranking/filter semantics aligned with local search.


UX Copy wird gegen Security Semantics reviewed.
21. PHASE 12 — MODERATION OPTIONALER BASISUMFANG
Nur wenn Kernsystem stabil.
Moderation getrennt.
Keine zentrale zwingende Liste.
Provenienz.
Local selection.


Wenn Zeit/Scope begrenzt: dokumentierte Plugin-/Future-Schnittstelle statt unsicherer halber Lösung.


22. PHASE 13 — HARDENING
- fuzzing;
- resource stress;
- concurrency;
- fault injection;
- storage corruption;
- node outage;
- malicious/stale peer fixtures;
- upgrade/migrations;
- permission review;
- dependency reduction.


23. PHASE 14 — AUDIT READINESS
Erzeuge Audit Pack:
- architecture;
- protocol;
- threat model;
- invariants;
- dependency inventory;
- SBOM;
- test report;
- fuzz report;
- coverage;
- known issues;
- release diff;
- protocol test vectors.


24. PHASE 15 — RELEASE CANDIDATE
Feature freeze.
Clean-room install.
3-node E2E.
Client update.
Backup/restore.
Upgrade.
Rollback.
Performance.
Security regression.
Documentation review.


25. HUMAN CHECKPOINTS
Automatischer Agent darf weiterarbeiten, aber Production Release benötigt menschliche Freigabe für:
- crypto design;
- protocol freeze;
- Web Client CSP/origin-trust boundary and server-search privacy;
- Tor transport;
- security exceptions;
- release candidate;
- public claims about security/privacy.


Kennzeichne diese Gates klar; erfinde keine menschliche Freigabe.


26. FRAGEN/UNKLARHEITEN
Wenn Agent interaktiv arbeiten kann:
Frage nur, wenn Entscheidung Produktziel wesentlich ändert und Spezifikationen keine sichere Defaultentscheidung erlauben.


Wenn Agent autonom arbeiten muss:
- konservative Entscheidung;
- ADR;
- markiere NEEDS-HUMAN-REVIEW;
- implementiere so, dass Änderung später lokal möglich ist;
- vermeide irreversible Wire-Format-Entscheidung ohne klaren ADR.


27. ABHÄNGIGKEITEN
Keine Dependency nur für Bequemlichkeit.
Vor Aufnahme:
- benötigt?
- aktiv gepflegt?
- Lizenz?
- Security?
- Transitive Dependencies?
- Browser bundle impact?
- Standard Library Alternative?
Dokumentiere Entscheidung.


28. CODE-QUALITÄT PHP
- strict types;
- typed domain;
- immutable value objects wo geeignet;
- explicit errors/results;
- no magic globals;
- parameterized SQL;
- tests neben Modulkonzept;
- high static-analysis level;
- dependency injection.


29. CODE-QUALITÄT TYPESCRIPT
- strict;
- no any ohne begründete enge Ausnahme;
- discriminated unions für State/Error;
- isolated network adapter;
- storage repository abstraction;
- schema validation;
- no dynamic eval;
- no remote code;
- tests für migrations.


30. ERROR-HANDLING
Fehler nicht als bool verwischen, wenn Semantik wichtig.
Nutze stabile Fehlerklassen aus Protocol/Security.
UI erhält sichere, verständliche Ableitung.
Logs erhalten technische Klasse, nicht geheime Daten.


31. OBSERVABILITY
Metriken sind Betriebshilfe, kein Tracking.
Jede neue Telemetrie erfordert Privacy Review/ADR.
Default keine Remote-Telemetrie.


32. PERFORMANCE
Erst korrekte bounded Implementation.
Dann messen.
Dann optimieren.
Nie:
- Signaturprüfung aus Performancegründen überspringen;
- Limits aufheben;
- untrusted cache als verified behandeln.


33. SECURITY BUG WORKFLOW
Bei Security Bug:
- Reproducer;
- Scope;
- Severity;
- Root Cause;
- regression test;
- fix;
- related variants search;
- docs/threat model update;
- release note falls relevant.


34. PROTOCOL CHANGE WORKFLOW
Kein spontanes Schemaändern.
Protocol Change Proposal:
- Problem;
- proposal;
- compatibility;
- migration;
- security;
- privacy;
- vectors;
- implementation plan.
Breaking => neue Protocol Version.


35. COMMIT-/CHANGE-DISZIPLIN
Jeder kohärente Change:
- klare Beschreibung;
- Tests;
- Docs;
- keine unrelated formatting mega-diff.
Security-relevante Changes besonders markieren.


36. SELBSTREVIEW-CHECKLISTE JE CHANGE
- Welche externen Inputs berühre ich?
- Welche Trust Boundary?
- neue Persistenz?
- neue Permission?
- neue Dependency?
- neue Logs?
- neue URL/Network behavior?
- Limits?
- Errors fail closed?
- Backward compatibility?
- Tests negative + positive?
- Docs aktuell?


37. KEIN HALLUZINIEREN VON APIs
Vor Nutzung:
- installierte Library-Version prüfen;
- offizielle Doku/Typen lesen;
- vorhandene Tests/Interfaces ansehen.
Wenn API unsicher:
- minimaler compile/test spike;
- dann implementieren.
Keine erfundenen Parameter/Funktionen.


38. UMGANG MIT FEHLENDEN TOOLS
Wenn Tool im Agentenumfeld fehlt:
- nicht Security Gate überspringen;
- dokumentiere fehlenden Nachweis;
- alternative lokale Methode;
- Gate bleibt offen bis verifiziert.


39. SECURITY CLAIMS
README darf sagen:
“validates cryptographic continuity” nur wenn getestet.
README darf NICHT sagen:
“guarantees safe services”
“anonymous by definition”
“unhackable”
“trusted directory”
ohne exakte Definition.


40. DATENSCHUTZ CLAIMS
Jede Privacy-Aussage muss durch Datenfluss nachvollziehbar sein.
Erzeuge docs/privacy/data-flow.md:
- collected;
- stored;
- transmitted;
- retention;
- user controls.
Wenn nichts gesammelt wird, trotzdem technisch belegen.


41. DOKUMENTATION ALS CODE
Docs ändern sich im selben Change wie Verhalten.
Public API/Schema Change ohne Docs/Test gilt als unvollständig.


42. API/SCHEMA TRACEABILITY
Jeder public schema type erhält:
- normative spec section;
- schema file;
- PHP type;
- TS type;
- tests;
- sample/vector.


43. RELEASE EVIDENCE
Am Ende einen Ordner release-evidence/<version>/ erzeugen:
- gate-matrix.md
- test-report.md
- security-scan-summary.md
- sbom references
- performance.md
- clean-install.md
- backup-restore.md
- rollback.md
- known-issues.md


44. AGENTENSTATUS
Nach jedem Meilenstein schreibe:
Completed
Evidence
Tests
Security impact
Docs changed
Open risks
Next step
Human review needed


Keine Aussage “done” ohne Evidence.


45. DEFINITION DES ENDPRODUKTS
Endprodukt umfasst nicht nur PHP und Weboberfläche.
Es umfasst:
- Protocol v1;
- schemas;
- vectors;
- PHP reference node;
- Web Local Search reference client with optional explicit No-JavaScript Server Search fallback;
- local search;
- identity continuity;
- migration/rotation;
- sync;
- candidate discovery;
- documentation;
- tests;
- CI;
- SBOM;
- operational runbooks;
- security policy;
- privacy documentation;
- release process;
- release evidence.


46. MINIMALER PRODUKTIONSUMFANG VS OPTIONAL
Pflicht:
alles für sichere Discovery, Identity, Sync, Local Search und Betrieb.


Optional für spätere Version:
- komplexe Reputation;
- vollständige Merkle-DAG-Optimierung, falls einfaches Delta korrekt skaliert;
- Proof of Work;
- Transparency Log;
- fortgeschrittene Moderationsökosysteme;
- Browser-to-browser peer mode.


Optional darf nicht benutzt werden, um Kern-Sicherheitsanforderungen zu verschieben.


47. STOP CONDITIONS
Agent stoppt Release-Fortschritt und markiert BLOCKED bei:
- ungeklärtem Crypto-Design;
- Wire-Format-Konflikt;
- kritischem Security Finding;
- Query Privacy Failure;
- private key exposure;
- Tor transport bypass;
- irreversible migration failure;
- unreviewed Web Client remote dependency/CSP weakening or silent query-transmission change.


Er kann an unabhängigen Tasks weiterarbeiten, aber Release Gate bleibt blockiert.


48. FINAL SELF-AUDIT
Vor RC:
- alle Spezifikationen erneut lesen;
- diff gegen ursprüngliche Ziele;
- Invariants Traceability;
- TODO/FIXME/HACK suchen;
- disabled tests suchen;
- ignored static errors suchen;
- permissive config defaults prüfen;
- secrets scan;
- dependency list review;
- endpoints inventory;
- permissions inventory;
- network destinations inventory;
- logs inventory;
- storage inventory.


49. ERWARTETER ABSCHLUSSBERICHT
Der Coding-Agent liefert:
Executive Summary
Architecture implemented
Protocol compatibility
Security model
Threat mitigations
Known limitations
Test evidence
Performance evidence
Operations evidence
Open risks
Human approvals still required
Exact build/install instructions
Exact release artifacts
Gate Matrix


50. ABSCHLUSSANWEISUNG
Optimiere auf überprüfbare Korrektheit, Datenschutz, Dezentralität, Wartbarkeit und klare Sicherheitssemantik. Geschwindigkeit der Implementierung ist nachrangig.
Wenn du zwischen “schnell funktioniert” und “klar spezifiziert, begrenzt, getestet, reviewbar” wählen musst, wähle die zweite Variante.


Kein Teil des Systems darf von einer dauerhaft von einem einzelnen ursprünglichen Entwickler betriebenen Infrastruktur abhängen.


ENDE DER MASTER WORK ORDE


51. MANDATORY AUDIT-GRADE ADDENDUM


Before Milestone 1 implementation work, also read and treat as binding:
- 11 - Current Standards Baseline and External References
- 12 - Protocol Limits Encoding Registry and State Machines
- 13 - Cryptographic Construction Key Lifecycle and Domain Separation
- 14 - Node API Sync Contract and Interoperability Specification
- 15 - Abuse Resistance Sybil Spam and Resource Exhaustion Model
- 16 - Privacy Data Classification Retention and Logging Specification
- 17 - Canonical Data Model Database Invariants and Retention Schema
- 18 - PHP Node Deployment Hardening and Secure Configuration Matrix
- 19 - Web Search Client Security Privacy and No-JavaScript Fallback Review
- 20 - Project Governance Protocol Change and Security Decision Policy
- 21 - Software Supply Chain Reproducible Build and Release Provenance Standard
- 22 - Independent Security Audit Readiness and Verification Plan
- 23 - Adversarial Red Team Review and Final Challenge Checklist
- 24 - Pre-Freeze Decision Register and Human Approval Gates


These documents are not optional recommendations. They refine the earlier requirements.


52. NEW PRE-CODE DELIVERABLES


Before production networking or cryptographic state mutation, create:
- docs/standards/external-reference-register.md
- protocol/limits.json
- docs/security/invariants.md
- docs/privacy/data-inventory.md
- docs/data/data-dictionary.md
- docs/client/permission-review.md
- docs/client/fingerprinting-review.md
- docs/operations/hardening-matrix.md
- docs/audit/AUDIT-MAP.md


53. CRYPTO FREEZE CONDITIONS


Do not freeze Protocol v1 until a human reviewer has reviewed:
- canonical byte representation;
- domain separation strings;
- manifest content hash definition;
- revision successor/genesis rules;
- Service key rotation;
- Onion migration;
- conflict/fork semantics;
and both PHP and TypeScript pass identical known-answer vectors.


54. EXTERNAL FACT REFRESH


At Milestone 0, Protocol Freeze and Release Candidate, re-check official:
Tor specs
Tor Browser scripting/security-level and web-platform behavior
Tor Browser desktop/Android JavaScript, storage and Web platform behavior
PHP support lifecycle
RFC 8785 assumptions/implementation
OWASP ASVS stable version
SLSA current version
CycloneDX current supported version.


Never use model memory as authority for time-sensitive platform behavior.


55. ABUSE/RESOURCE GATE


Every externally influenced queue, array, response, retry, network call, disk store and worker loop requires a finite limit and a boundary/stress test.
“Unlimited” is forbidden as standard production configuration for attacker-influenced resources.


56. DATABASE EVIDENCE RULE


Never model Service identity as only one mutable latest row.
Maintain immutable/append-oriented continuity evidence and derive current view.
Never use destructive replacement semantics that can erase forks, rotations or migration evidence.


57. WEB CLIENT ORIGIN/CSP/QUERY-PRIVACY GATE


Any change that adds remote Web Client dependencies, sends local-mode query data, weakens CSP, introduces service workers, or changes server-search privacy semantics is a Release Blocker until:
- permission ADR;
- Tor Browser desktop/Android Web behavior refreshed from current official docs;
- fingerprinting/privacy review;
- human approval;
- static-asset/CSP/network-destination diff included in release evidence.


58. RELEASE CLAIM GATE


The agent must not use:
“audited”
“Tor-approved”
“OWASP certified”
“SLSA Level X”
“production-ready”
unless the exact corresponding evidence/gates exist.


59. RED TEAM BEFORE RC


Before Release Candidate, execute every applicable item/scenario in:
23 - Adversarial Red Team Review and Final Challenge Checklist.


For each:
PASS with evidence
FAIL with blocking issue
N/A with written reason and human review where security-relevant.


60. INDEPENDENT AUDIT STATUS


Prepare the audit package automatically, but keep:
EXTERNAL_AUDIT_NOT_PERFORMED
until a genuinely independent review has occurred.


END OF AUDIT-GRADE ADDENDUM




61. FORMAL METHODS AND INTEROPERABILITY GATE


The agent MUST implement the formal model and independent conformance infrastructure before Protocol v1 can be marked Stable.


Required evidence:
- TLA+/PlusCal or approved equivalent model;
- no unresolved safety counterexample;
- formal assumptions documented;
- exact-byte conformance vectors;
- CORE-V1 PASS in PHP, TypeScript and independent third-language validator.


62. TEST NETWORK AND CHAOS GATE


The agent MUST build a deterministic local test network and fault-injection harness.
Public Tor infrastructure is not used for destructive/stress CI.
Before Production Stable:
- LAB-FAST;
- LAB-INTEGRATION;
- LAB-TOR;
- LAB-STRESS;
- LAB-CHAOS;
- upgrade;
- backup/restore
must have evidence according to their defined release tier.


63. INTERNET STANDARDS GATE


Before Protocol v1 Stable:
- JSON Schema Draft 2020-12 dialect and schema IDs frozen;
- RFC 8615 Well-Known registration draft prepared;
- RFC 9457 Problem Details decision completed;
- Protocol registries established;
- RFC-style public Protocol Handbook generated.


No external standards status may be falsely claimed.


64. ACCESSIBILITY AND HUMAN-FACTORS GATE


Reference client cannot be Production Stable solely because automated tests pass.
Required:
- WCAG 2.2 AA applicability matrix;
- manual accessibility review;
- security-warning comprehension testing;
- copy registry;
- pin/migration/fork user-flow review.


65. OPERATOR/DEVELOPER EXPERIENCE GATE


Required:
- coherent CLI;
- doctor;
- security:check;
- machine-readable health;
- privacy-safe support bundle;
- fresh operator walkthrough;
- fresh contributor walkthrough.
No undocumented mandatory manual database surgery.


66. PERFORMANCE/CAPACITY GATE


Safe defaults MUST be derived from versioned benchmarks.
Required:
- 1k/10k/100k scale runs;
- stress modeling;
- low-end client test;
- Tor-aware concurrency evidence;
- supported SQLite scale statement;
- resource/overload thresholds.


67. SYNTHETIC CORPUS GATE


All tests/demos/audits use deterministic synthetic reference data unless a separately approved case requires real public data.
Reference corpus includes:
identity history;
forks;
rotation;
migration;
Unicode;
moderation;
invalid protocol corpus;
benchmark datasets.


68. PROTOCOL ARCHAEOLOGY GATE


Every stable release records enough semantic/version metadata to reconstruct why historical data was interpreted as current/stale/forked/migrated.
Lifecycle matrix and historical fixtures are mandatory.


69. DESIGN RATIONALE RULE


The agent MUST preserve a “why” record for major architectural choices and rejected alternatives.
A future change that reverses a major rationale requires new ADR with measured reason.


70. MULTI-LANGUAGE RULE


The Protocol is not considered independently implementable until a third-language minimal validator passes CORE conformance without importing PHP or TypeScript reference logic.


71. ADOPTION/SUCCESSION RULE


The project must remain usable if original maintainer disappears.
Before Stable:
- persona-based docs;
- publish-only operator path;
- maintainer succession/release process;
- no hidden central runtime dependency;
- seed governance;
- implementation registry design.


72. OPERATOR CONTRACT


The Five-Minute Operator Contract is a required public document and must remain synchronized with actual behavior.
Any change to publication, key handling, withdrawal, migration, privacy or Node roles requires updating it.


73. PUBLIC PROTOCOL HANDBOOK


Generate a single coherent public Protocol handbook from version-controlled sources.
Internal multi-document engineering specs remain useful, but third-party implementers must not need to read dozens of planning docs to understand Protocol v1.


74. COMPLETION CLAIM


“Feature complete” is insufficient.
The agent may report Onion Service Discovery as mature Production Stable only when:
- implementation;
- formal assurance;
- independent conformance;
- chaos/failure testing;
- accessibility/human factors;
- performance evidence;
- operator experience;
- standards publication;
- supply-chain evidence;
- audit readiness;
- human gates
all meet their defined PASS conditions.




75. STORAGE IMPLEMENTATION REQUIREMENT
Do not hardwire SQLite into domain/repository logic.


Implement:
StorageBackend interface;
FilesystemStore;
SQLiteStore;
storage conformance test suite;
canonical export/import.


Then implement PostgreSQL and MySQL/MariaDB adapters if compatible drivers/test infrastructure are available within the project scope.


The application chooses backend from validated operator configuration.


76. FILESYSTEMSTORE QUALITY BAR
FilesystemStore MUST implement transaction-equivalent semantics using:
- immutable content-addressed objects;
- journal/checkpoint;
- writer lock;
- atomic replace;
- crash recovery.


Do not implement a naive “one JSON file per mutable table” design without atomicity/recovery.


77. MALICIOUS NODE ARCHITECTURE
Implement separate roles:
Client Host
Discovery Node.


The Web Client MUST have a separated profile where Discovery Nodes cannot provide executable code.


Implement:
- multi-source source manager;
- valid-record union;
- source attribution;
- signed Node checkpoint verification;
- rollback protection;
- equivocation detection;
- deterministic conflict presentation.


78. CLIENT HOST
Reference Client Host:
- static bundle only;
- reproducible;
- no remote runtime code;
- strict CSP;
- release manifest/hashes;
- self-host instructions.


Combined Node+Client mode remains allowed but MUST be identified as weaker isolation.


79. CROSS-ORIGIN/PROXY DECISION
Prototype both:
A. direct browser -> multiple data Nodes with tightly scoped CORS;
B. Client Host -> generic upstream proxy/cache independent of query.


Measure privacy, Tor Browser behavior, complexity and resilience.


Freeze one strong reference profile by ADR after current Tor Browser testing.


Do not make the query influence upstream source selection in either model.




80. FINAL PRE-IMPLEMENTATION FREEZE
Before first production code:
- read documents 43–46;
- implement offline Service signer architecture from the start;
- do not place Service private key in web-runtime dependency path;
- design all signing behind SignerProvider;
- preserve Node/Service/release key separation;
- design release verification so Git hosting is not sole trust anchor;
- keep Protocol v1 fixed to Tor v3 and current crypto suite;
- fail closed on unknown future address/crypto formats.


81. STOP-PLANNING DIRECTIVE
Once Final Pre-Implementation Closure Audit is PASS:
begin repository implementation immediately.


The coding agent MUST NOT spend time expanding architecture documents merely to appear thorough.
Additional design work requires a concrete trigger:
- failing test/model;
- contradictory specification;
- newly discovered attack;
- unsupported external platform behavior;
- measured performance failure;
- audit/review finding.


82. SECURITY EVIDENCE PRIORITY
From implementation start onward, replace speculative prose with:
tests;
formal counterexample checks;
fuzzing;
benchmarks;
reproducible builds;
conformance reports;
chaos tests;
manual security review evidence.
R
