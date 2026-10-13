# 01 - Definition of Done and Production Readiness Gates

Source: https://docs.google.com/document/d/1TBZrr-vBQMdKnz43x06yyx38PoSU1ocKaG5OqxrVWfg/edit?usp=drivesdk

Source modified: 2026-10-12T19:34:20.687Z

---

ONION SERVICE DISCOVERY
DEFINITION OF DONE, QUALITY GATES UND PRODUKTIONSFREIGABE


Zweck
Dieses Dokument definiert objektiv, wann eine Implementierung als produktionsreif gelten darf. “Funktioniert lokal” oder “Demo läuft” ist ausdrücklich nicht ausreichend.


1. RELEASE-KLASSEN
- Prototype: experimentell, keinerlei Produktionsversprechen.
- Alpha: grundlegende Interoperabilität, bekannte Lücken zulässig.
- Beta: vollständige Kernfunktionen, Security-Hardening läuft.
- Release Candidate: Feature Freeze, nur Fehlerbehebung, Audit- und Operations-Gates aktiv.
- Production: alle Pflicht-Gates erfüllt.


2. BLOCKIERENDE GATES
Ein Production Release ist verboten, wenn mindestens eines zutrifft:
- offene Critical/High Security Findings ohne schriftliche menschliche Risikoakzeptanz;
- fehlende oder nicht reproduzierbare Protokollspezifikation;
- unbekannte/unspezifizierte Wire-Formate;
- fehlende Tests für Identitätsbindung, Signaturen, Revision Chain, Key Rotation oder Migration;
- Möglichkeit eines DNS-/Proxy-Bypass für .onion Requests;
- unbeschränkte Netzwerkantworten, Candidate Queue oder Datenbankwachstum;
- Suchbegriffe verlassen den Client;
- private Tor-Schlüssel werden von der Anwendung gelesen;
- Peer-Daten können fremde Service-Metadaten ungeprüft überschreiben;
- Admin-Endpunkte sind remote ohne starke Authentisierung erreichbar;
- Restore/Rollback wurde nie praktisch getestet;
- Dependencies enthalten unbehandelte bekannte kritische Schwachstellen;
- Release-Artefakte sind nicht prüfbar oder stammen aus nicht dokumentiertem Build.


3. FUNKTIONALE ABNAHME
MUSS funktionieren:
- publish_service einzeln aktivierbar;
- mirror_directory einzeln aktivierbar;
- discover_services einzeln aktivierbar;
- sync_with_peers einzeln aktivierbar;
- gültiges Manifest erzeugen und prüfen;
- Onion-Adresse des Manifests stimmt exakt mit Abrufziel überein;
- Signatur prüfen;
- Revisionen monoton verarbeiten;
- alte Revisionen als stale/replay erkennen;
- Fork bei gleicher Revision und anderem Hash erkennen;
- Service Identity Key rotieren;
- Onion-Adresse mit Kontinuitätsnachweis migrieren;
- Candidate Queue mit Backoff;
- direkte Verifikation;
- Peer-Synchronisation per Pull;
- lokaler Client-Index;
- lokale Suche;
- Pinning/TOFU für explizit gespeicherte Identitäten;
- Warnung bei Identitätswechsel;
- Offline/Stale-Zustände ohne sofortige Löschung;
- mehrere Nodes können unabhängig betrieben werden;
- Ausfall eines Nodes zerstört nicht den lokalen Index.


4. PROTOKOLL-GATE
Für jeden öffentlichen Datentyp existiert:
- normative Beschreibung;
- JSON Schema;
- kanonische Serialisierungsregel;
- Größen-/Längenlimits;
- Unknown-field Policy;
- Duplicate-key Policy;
- Versionsverhalten;
- Fehlerverhalten;
- positive Testvektoren;
- negative Testvektoren;
- mindestens ein Interoperabilitätstest.


5. KRYPTOGRAPHIE-GATE
- ausschließlich etablierte Bibliotheken, keine Eigenimplementierung von Ed25519;
- eindeutiges Domain Separation/Context-Design für unterschiedliche Signaturtypen;
- Signatur-Input exakt spezifiziert;
- Testvektoren dokumentiert;
- Schlüsseldateien mit restriktiven Rechten;
- sichere Erzeugung aus CSPRNG;
- Backups/Rotation dokumentiert;
- Service Key und Node Key getrennt;
- private Tor-Keys unzugänglich für Anwendung;
- Timing-/Fehlermeldungsverhalten auf offensichtliche Side Channels überprüft;
- ungültige Schlüsselgrößen/Encoding strikt verworfen.


6. INPUT-VALIDATION-GATE
Alle Netzwerkdaten sind feindlich.
Tests müssen abdecken:
- zu große JSON-Dokumente;
- tiefe Verschachtelung;
- Duplicate Keys;
- falsche Typen;
- Null-Bytes;
- ungültiges UTF-8;
- Unicode Confusables;
- bidirektionale Steuerzeichen;
- extrem lange Strings;
- riesige Arrays;
- ungültige Base64-/Hex-Felder;
- ungültige Onion-Adressen;
- falsche Protokollversion;
- unbekannte Pflichtfelder;
- manipulierte Signatur;
- falscher previous hash;
- future/overflow timestamps, falls Zeitfelder verwendet werden;
- integer overflow/precision loss.


7. NETZWERK-GATE
- .onion-Ziele nur via Tor SOCKS5 Hostname-Auflösung;
- kein System-DNS für .onion;
- kein direkter TCP-Fallback;
- Hostziel aus Netzwerkdaten streng auf zulässige Onion-v3-Adresse begrenzt;
- Identity-Verification-Requests folgen keinen Cross-Host Redirects;
- Connect Timeout;
- Header Timeout soweit verfügbar;
- Total Timeout;
- Max Response Bytes;
- Max Concurrent Requests;
- Max Requests je Peer/Zeitraum;
- Backoff mit Jitter;
- Circuit/Connection Isolation soweit über verfügbaren Tor-SOCKS-Mechanismus realisierbar;
- Tests mit absichtlich kaputtem SOCKS/DNS Setup müssen fail-closed enden.


8. STORAGE-GATE
- backend-neutral StorageBackend contract implemented;
- FilesystemStore and SQLiteStore pass the same black-box atomicity/invariant suite;
- SQL adapters use parameterized statements/prepared statements;
- FilesystemStore uses stable logical_service_id-derived paths, bounded filenames and crash-safe journal/generation recovery;
- storage schema/version migrations are versioned and tested;
- crash/fault injection across every durable commit stage;
- corruption/integrity failure detected for every Production-supported backend;
- backup/export documented;
- restore/import practically tested;
- retention/size/disk limits;
- Quarantine separated from active current/search state;
- backend-specific indexes/constraints tested without becoming Protocol semantics;
- private keys are not stored inside ordinary discovery storage by default;
- missing an unused backend driver is not an error.


9. WEB CLIENT GATE
- no browser-extension permission model;
- no WebRTC dependency;
- no native executable;
- WEB-LOCAL search has zero query-dependent network traffic;
- optional SERVER-FALLBACK is separately disclosed and tested;
- WEB-COMBINED-V1 / WEB-SEPARATED-V1 origin/CORS/CSP behavior is explicit;
- browser persistence is optional/best-effort; in-memory baseline remains functional;
- persistent cache migration tested only if that feature is enabled;
- strict CSP;
- no remote scripts;
- no third-party analytics;
- no external font/CDN dependency;
- safe URL/address rendering;
- full copyable Onion address;
- Unicode/confusable/bidi handling reviewed;
- technical trust/evidence labels semantically correct;
- warning states distinguish identity evidence from content safety/legitimacy;
- no false claim of operator independence from Node key count.


10. SECURITY-TEST-GATE
Mindestens:
- Unit;
- Integration;
- E2E;
- Conformance;
- Fuzzing;
- Property Tests;
- Static Analysis;
- Dependency Audit;
- Secret Scan;
- SSRF Tests;
- Rate Limit Tests;
- Replay/Fork Tests;
- Migration/Rotation Tests;
- parser ambiguity tests;
- corrupted storage tests;
- concurrency/race tests;
- Web-Client CSP/XSS/origin-trust review sowie No-JavaScript-Server-Search-Privacy-Review.


11. PERFORMANCE-GATE
Messbare Budgets festlegen und testen:
- Startzeit Client;
- Index-Load;
- Suchlatenz lokal;
- Speicherverbrauch;
- DB-Größe;
- Delta-Sync;
- maximale CPU pro Verification Batch;
- maximale Netzwerkparallelität.
Performance-Optimierungen dürfen Security-Invariants nicht umgehen.


12. OPERATIONS-GATE
- Installationsanleitung von leerem System getestet;
- Konfigurationsbeispiel;
- sichere Defaultwerte;
- Log Rotation;
- Backup;
- Restore;
- Upgrade;
- Downgrade/Rollback soweit unterstützt;
- Key Rotation Runbook;
- Incident Response;
- Vulnerability Disclosure;
- Changelog;
- Release Notes;
- bekannte Einschränkungen;
- Disaster Recovery.


13. SUPPLY-CHAIN-GATE
- Lockfiles committed;
- SBOM erzeugt;
- Checksums;
- signierte Releases soweit Infrastruktur verfügbar;
- Build- und Testversionen gepinnt oder kontrolliert;
- CI nutzt minimal notwendige Secrets;
- Third-Party Actions/Dependencies versioniert;
- Lizenzprüfung;
- kein unreviewter Download-and-execute Schritt.


14. DOKUMENTATIONS-GATE
Pflichtdokumente:
README, Architecture, Protocol, Security Architecture, Threat Model, Privacy, Node Operator Guide, Client Guide, Developer Guide, API Reference, Data Model, Testing, Conformance, Release, Incident Response, Vulnerability Disclosure, Key Management, Migration, Backup/Restore, FAQ, Changelog, ADR Index.


15. HUMAN REVIEW
Vor Production muss ein Mensch mindestens freigeben:
- Crypto Design;
- Threat Model;
- Web Client Asset/CSP/Origin-Trust and Server-Search Privacy Review;
- Tor Transport;
- Release Supply Chain;
- offene Security Findings;
- Benutzertexte zu Vertrauen/Sicherheit.


16. FINALER RELEASE-BEFEHL FÜR DEN CODING-AGENTEN
Der Agent darf “production-ready” erst melden, wenn er eine Gate-Matrix erzeugt hat, in der jede Pflichtzeile den Status PASS mit Verweis auf Test, Artefakt oder Review besitzt. UNKNOWN gilt als FAIL. SKIPPED gilt als FAIL, außer das Gate ist ausdrücklich als nicht anwendbar begründet und menschlich bestätigt


17. FORMAL ASSURANCE GATE
Production Stable is blocked unless:
- formal state model exists for revision/fork/rotation/migration/sync/pinning;
- no unresolved safety counterexample;
- assumptions documented;
- formal invariants trace to executable tests;
- Human Protocol/Security review completed.


18. INDEPENDENT CONFORMANCE GATE
Required:
- language-independent conformance suite;
- exact-byte vectors;
- PHP CORE pass;
- TypeScript CORE pass;
- independent third-language CORE pass;
- intended NODE/CLIENT profiles pass;
- no required test skipped.


19. CHAOS / FAILURE-INJECTION GATE
Release Candidate evidence includes:
- deterministic multi-node lab;
- network faults;
- process crashes;
- storage failure;
- peer equivocation;
- candidate flood;
- upgrade;
- backup/restore.
Existing verified identity history must remain consistent under tested faults.


20. INTERNET-STANDARDS GATE
Before Stable:
- Well-Known URI registration draft;
- final Well-Known suffix;
- JSON Schema Draft 2020-12 discipline;
- public error-model decision including RFC 9457 evaluation;
- stable registries;
- RFC-style Protocol Handbook;
- accurate IANA Considerations.


No standards-body status may be claimed without actual external status.


21. ACCESSIBILITY / HUMAN-FACTORS GATE
Reference client Production Stable requires:
- WCAG 2.2 Level AA applicability assessment;
- keyboard/screen-reader/zoom review;
- no color-only security semantics;
- critical warning comprehension testing;
- security-copy registry;
- Human UX/Security approval.


22. OPERATOR EXPERIENCE GATE
Required:
- one coherent CLI;
- doctor;
- security:check;
- machine-readable health;
- privacy-safe diagnostics export;
- clean operator walkthrough;
- clean developer bootstrap walkthrough;
- no undocumented manual StorageBackend surgery.


23. PERFORMANCE / CAPACITY GATE
Required:
- versioned benchmark datasets;
- 1k/10k/100k scale evidence;
- stress/1m modeling where feasible;
- low-end client test;
- search/sync/storage/network reports;
- supported-scale statement for each Production-supported StorageBackend; SQLiteStore receives an explicit scale statement when enabled;
- safe limits derived from measurement.


24. SYNTHETIC DATA GATE
Core tests, demos, benchmarks and audits rely on deterministic synthetic reference data.
No real user search/browsing history is needed.
Golden corpus includes forks, rotations, migrations, invalid inputs, Unicode and abuse fixtures.


25. PROTOCOL ARCHAEOLOGY GATE
Required:
- record lifecycle matrix;
- version/semantic metadata;
- historical fixtures;
- preserved evidence through migrations;
- ability to explain prior current/fork/migration decisions without undocumented maintainer memory.


26. MULTI-LANGUAGE / ADOPTION GATE
Required:
- independent Protocol Core validator;
- third-party implementation guide;
- SDK boundaries;
- publish-only operator path;
- seed governance;
- maintainer succession plan;
- Five-Minute Operator Contract;
- no hidden central runtime dependency.


27. FINAL CLAIM RULE
“Production-ready” is forbidden until all original and extended gates are PASS.
“Protocol Version 1 Stable” is forbidden until formal, conformance, standards-publication and Protocol/Crypto human-review gates are PASS.
“Production Stable” additionally requires chaos, accessibility, operator experience, capacity, corpus, archaeology and ecosystem/succession gates.




28. SELECTED STORAGE BACKEND RUNTIME GATE
The PHP reference node MUST verify only the selected StorageBackend's required runtime capabilities.


PASS evidence:
- FilesystemStore operates without PDO/SQLite and validates filesystem permissions/atomicity/locking;
- SQLiteStore, when selected, verifies PDO + pdo_sqlite, the documented SQLite capability/version floor, open/transaction/foreign-key behavior;
- any other selected adapter verifies only its own driver/capabilities;
- a missing UNUSED adapter/driver is not an error;
- no silent backend substitution;
- external sqlite3 CLI is not a runtime requirement for SQLiteStore;
- doctor/status reports the selected backend and exact remediation.


Production documentation MUST NOT imply that any database driver is universally present in PHP.


29. WEB LOCAL SEARCH GATE
PASS evidence:
- no separate browser extension artifact exists;
- direct Web Local Search works in supported Tor Browser desktop and Android;
- reference local mode emits no query-dependent network traffic;
- browser storage absence does not break basic search;
- strict CSP/XSS/no-remote-asset checks pass;
- serving-origin trust limitation documented.


30. NO-JAVASCRIPT SERVER SEARCH GATE
If fallback is enabled:
- explicit privacy disclosure before submit;
- POST, not query URL;
- no query logging;
- no query-driven remote discovery/sync;
- bounded search work/results;
- no-store response;
- accessible plain HTML.




31. STORAGE BACKEND FREEDOM GATE
Production Stable requires:
- backend-neutral StorageBackend contract;
- SQL-free FilesystemStore;
- SQLiteStore;
- black-box backend conformance tests;
- crash/failure injection;
- backend-neutral export/import;
- operator-selectable backend;
- no Protocol semantic dependence on SQL.


Missing SQLite MUST NOT block an operator who selected FilesystemStore.


32. MALICIOUS NODE ISOLATION GATE
Required:
- Client Host role formally distinct from Discovery Node;
- separated profile supported;
- data Nodes provide no active code;
- multi-source valid-record union;
- signed Node checkpoints;
- rollback/equivocation tests;
- malicious Node omission/stale/fabrication scenarios;
- no majority-based identity truth.


33. CLIENT HOST TRUST GATE
Required:
- residual malicious Client Host risk documented;
- reproducible static bundle;
- operator asset verification;
- self-hosted Client Host deployment path;
- no claim that remotely served JavaScript can prove its own honesty against its origin.


34. SEPARATED BROWSER PRIVACY GATE
If direct cross-origin Node fetching is supported:
- current Tor Browser desktop/Android behavior tested;
- no credentials/cookies;
- no query-bearing request;
- no result-triggered prefetch;
- CORS minimized;
- privacy findings recorded.




35. HIGH-ASSURANCE KEY CUSTODY GATE
Required:
- offline Service Identity signing supported and documented as preferred;
- web runtime does not need Service private key in preferred profile;
- SignerProvider abstraction;
- Node signing blast radius separate from Service identity;
- encrypted backup and recovery drill;
- hardened/HSM/external-signer option boundary;
- no false claim that at-rest encryption protects against a fully compromised running host.


36. SECURE RELEASE TRUST GATE
Required before Production Stable:
- explicit release trust root;
- rollback/freeze protection using a mature update metadata framework or reviewed equivalent;
- high-value signing keys offline/threshold-protected;
- release:verify tooling;
- Git hosting not sole trust anchor;
- release-key compromise/recovery drill.


37. FUTURE TOR/CRYPTO COMPATIBILITY GATE
Required:
- Protocol v1 strictly supports current Tor v3 Onion addresses;
- unknown address versions fail closed;
- no algorithm negotiation in v1;
- future address/crypto changes require explicit Protocol migration;
- compatibility matrix refreshed before stable releases;
- downgrade from known newer continuity is prevented.


38. PRE-IMPLEMENTATION FREEZE GATE
Once the final closure audit is PASS:
START IMPLEMENTATION.
Do not create additional speculative architecture requirements unless driven by:
implementation contradiction;
new threat;
external standard change;
benchmark result;
audit finding;
real operator/user evidence.




39. PLUG-AND-PLAY OPERATOR EXPERIENCE GATE
Production Stable requires:
- ready-to-deploy package;
- common publish-only setup with one clear config file plus signed Manifest;
- safe /status/ page;
- Tor Browser Android and desktop usability;
- no SQL requirement for publish-only/default FilesystemStore path;
- exact self-verification;
- bounded automatic announcement;
- external observation check;
- sampled searchability confirmation;
- truthful non-global wording;
- no secret leakage;
- no undocumented registration step.


40. DISCOVERABILITY TRUTH GATE
A green DISCOVERABLE state is forbidden unless backed by external evidence for the current revision.


Peer reachability alone is insufficient.
Local Manifest validity alone is insufficient.
Announcement acceptance alone is insufficient.




41. VERIFIABLE DISCOVERABILITY GATE
Production Stable requires:
- stable logical_service_id frozen under D-051;
- canonical GET /services/by-id/{logical_service_id} lookup plus address convenience lookup resolving logical_service_id;
- valid Service-key rotation and Onion migration preserve logical_service_id;
- exact active Service-state tuple separates stable logical identity from current key epoch/Onion;
- deterministic active index representation;
- Node-signed active-index checkpoint;
- operator/client local membership verification;
- stale revision detection;
- comparison across multiple distinct selected Node identities/source groups without assuming operator independence;
- omission handling;
- checkpoint-chain ancestry/rollback detection under D-052;
- same-sequence and non-prefix-compatible-history equivocation evidence;
- search-export equivalence test.


A simple remote “listed=true” response is not sufficient for the green DISCOVERABLE state.


42. MERKLE PROOF GATE
If compact Merkle inclusion proofs are shipped as a Production Stable capability:
- tree construction is normatively frozen;
- leaf/branch domain separation frozen;
- sort/order/odd-node rules frozen;
- PHP/TypeScript/third-language vectors pass;
- invalid proof fuzzing passes.


If not ready, Protocol v1 may use signed deterministic complete snapshots for membership verification and advertise Merkle proof support only later as an explicit capability.




43. SYBIL-RESISTANCE GATE
Production Stable requires:
- raw Service/Node/witness identity count has zero trust authority;
- bounded active peer/probation sets;
- global and per-source work budgets;
- duplicate-target verification collapse;
- source concentration diagnostics;
- no majority identity/fork decision;
- no observer-count ranking boost;
- valid Sybil-farm stress fixtures;
- search result-flood mitigation without deleting distinct identities;
- objective misbehavior proofs, not accusation voting.


44. ECLIPSE-RESISTANCE GATE
Required:
- multiple bootstrap origins;
- no wholesale peer-table replacement from one source;
- gradual bounded source rotation;
- selected-source persistence;
- one peer cannot fill every source slot via referrals;
- source concentration visible to operator.




45. CATEGORY REGISTRY GATE
Protocol Stable requires:
- one machine-readable Category Registry v1;
- schema/generated constants use the same source;
- adult included;
- categories separated from moderation/trust;
- localization outside signed IDs;
- unknown category behavior frozen;
- category-count rank neutrality;
- conformance vectors.


46. SECURE CODING / EXPLOIT RESISTANCE GATE
Production Stable requires:
- document 51 implemented;
- security coverage matrix;
- ASVS applicability matrix;
- OWASP Top 10 current mapping;
- CWE Top 25 current mapping;
- strict input/range/type/encoding limits;
- context-safe output encoding;
- SSRF/path/SQL/XSS/command-injection controls;
- dangerous API lint/review;
- static analysis/SCA/secret scan;
- fuzz/property/negative tests;
- exceptional-condition/fault injection;
- zero unresolved Critical/High security findings for Production Stable subject to existing audit policy.


47. DEFENSIVE PROGRAMMING GATE
Required:
- typed validated domain boundaries;
- no security-critical PHP loose comparisons;
- no untrusted native deserialization;
- no remote/runtime executable code;
- hard ceilings not operator-disableable;
- fail-closed unsupported versions/ambiguous records;
- verified state survives rejected malicious input.
