# 06 - Test Strategy Conformance and Verification Matrix

Source: https://docs.google.com/document/d/13ceVELv_g4BJHeAldwPbPQ6MJPo_NJCGrqUNg2qkMeU/edit?usp=drivesdk

Source modified: 2026-10-11T04:29:06.971Z

---

ONION SERVICE DISCOVERY
TEST STRATEGY, CONFORMANCE UND VERIFICATION MATRIX


1. Ziel
Tests sind Teil der Spezifikation. Eine Funktion ohne automatisierten Nachweis gilt nicht als fertig. Das Testsystem muss sowohl korrekte Funktion als auch bewusst fehlerhafte, widersprüchliche und ressourcenintensive Eingaben prüfen.


2. Testpyramide
Unit Tests:
kleine deterministische Domain-/Parser-/Crypto-/State-Machine-Einheiten.


Component Tests:
Storage, HTTP, Transport Adapter, Sync Engine, IndexedDB.


Integration Tests:
mehrere Komponenten mit realen Datenbanken und simulierten Netzwerkgrenzen.


Conformance Tests:
gemeinsame Vektoren für PHP und Browser.


End-to-End:
mehrere Nodes plus Client in reproduzierbarer Testumgebung.


Adversarial/Security:
negative Inputs, Konflikte, Größenlimits, Rate-Limits, Recovery.


Performance:
definierte Budgets.


3. Gemeinsames Testvektor-Repository
Ein Verzeichnis protocol-test-vectors ist Quelle der Wahrheit.
Enthält:
valid/
invalid/
edge/
crypto/
revision/
rotation/
migration/
node/
sync/
unicode/


Jede Datei besitzt expected-result Metadaten.


4. Manifest Testvektoren
Pflichtfälle:
- Genesis Manifest gültig;
- Revision 2 gültig;
- maximale erlaubte Feldlängen;
- minimale gültige Felder;
- falscher protocol name;
- unbekannte protocol version;
- fehlendes Pflichtfeld;
- falscher Datentyp;
- ungültige Onion-Adresse;
- onion mismatch;
- ungültiger public key;
- ungültige signature;
- manipuliertes Feld nach Signatur;
- falscher previous hash;
- Duplicate Keys;
- ungültige Unicode-/Encodingfälle;
- zu großes Manifest.


5. Canonicalization Tests
Gleicher semantischer signierbarer Payload muss in PHP und Client exakt dieselben Canonical Bytes ergeben.
Testfälle:
- unterschiedliche Property-Reihenfolge;
- Unicode;
- escaped characters;
- leere Arrays/Objekte;
- Zahlen nur soweit Schema erlaubt;
- null vs absent;
- verschachtelte Objekte.


6. Crypto Known-Answer Tests
Für feste Testkeys:
- public key;
- payload;
- canonical bytes;
- hash;
- signature;
- verification result.
Keine privaten Produktionskeys in Testdaten.


7. Revision Chain Tests
- 1 -> 2 -> 3;
- 3 gefolgt von 2 => stale;
- Revision 3 gleicher Hash => duplicate;
- Revision 3 anderer Hash => fork;
- Revision mit falschem previous hash;
- sehr große/ungültige revision;
- fehlende Genesis-Regel.


8. Key Rotation Tests
- gültige Rotation;
- falscher old key;
- manipulierte new key;
- falsche effective revision;
- wiederholte Rotation;
- Rotation nach Fork;
- neuer Key ohne Nachweis => identity changed.


9. Migration Tests
- gültige Service-Identity-Signatur auf neue Onion;
- alte Onion zusätzlich bestätigt;
- neue Onion bestätigt;
- nur Identity Continuity vorhanden;
- falsche Ziel-Onion;
- manipulierte Migration;
- widersprüchliche zwei Migrationen;
- Migration nach Key Rotation.


10. Candidate State Machine
Property Tests:
- keine ungültigen Zustandsübergänge;
- VERIFIED nur nach erfolgreicher direkter Prüfung;
- BACKOFF terminiert nicht in engem Retry-Loop;
- QUARANTINED wird nicht automatisch aktiv;
- Retry counter/next attempt monoton plausibel.


11. Peer Tests
- gültiger Node Descriptor;
- ungültige Node-Signatur;
- unbekannte Version;
- zu viele advertised peers;
- Duplikate;
- tote Peers;
- wechselnde Descriptor Revision;
- mehrere Peers liefern widersprüchliche Assertions.


12. Sync Tests
- gleicher State => kein Delta;
- Delta mit neuen Records;
- Delta mit stale Records;
- Delta mit Fork;
- Abbruch mitten im Transfer;
- invalid chunk/hash;
- lokale Transaktion muss bei Batchfehler konsistent bleiben;
- Sync Cursor erst nach erfolgreichem Commit;
- ein bösartiger/fehlerhafter Peer löscht keine validierte lokale Historie.


13. Transport Tests
Mit austauschbarem Transport Adapter:
- success;
- connection timeout;
- total timeout;
- truncated body;
- oversized body;
- wrong content type;
- redirect same host soweit erlaubt;
- redirect other host bei Identity Check => reject;
- proxy unavailable;
- kein definierter Proxy => fail entsprechend Konfiguration;
- keine versteckte Direktverbindung im Test.


14. Zielvalidierungs-Tests
- gültige v3 Onion;
- uppercase/normalization gemäß Spezifikation;
- ungültige Länge;
- ungültiges Alphabet;
- URL mit credentials;
- fremdes Scheme;
- IP literal;
- localhost;
- zusätzlicher Port, wenn nicht erlaubt;
- path injection.
Erwartung: nur spezifikationskonforme Ziele erreichen Transport Adapter.


15. Ressourcen-Tests
- Candidate Queue Maximum;
- Peer Maximum;
- Manifest bytes;
- Delta bytes;
- records per response;
- JSON depth;
- concurrent verification budget;
- DB size threshold;
- Retention cleanup.
Jedes Limit besitzt Boundary Tests: limit-1, limit, limit+1.


16. Datenbanktests
- fresh migrate;
- migrate jede unterstützte Vorversion;
- doppelte Migration;
- crash simulation;
- lock contention;
- transaction rollback;
- corrupt test DB;
- backup;
- restore;
- integrity verification;
- uniqueness constraints;
- quarantine persistence.


17. PHP API Contract Tests
Für jeden Endpoint:
- success schema;
- wrong method;
- malformed path;
- unknown resource;
- limit bounds;
- content type;
- no stacktrace;
- response size;
- cache headers;
- no secret fields.


18. PHP CLI Tests
- config validate;
- manifest generate;
- verify service gegen Mock;
- sync dry/simulated;
- db migrate;
- db verify;
- key generation safety;
- refusal to overwrite key;
- exit codes;
- noninteractive behavior.


19. Client Storage Tests
- fresh IndexedDB;
- upgrade from every supported schema;
- interrupted migration;
- index rebuild;
- preserve pins on cache rebuild;
- full reset;
- corrupted records;
- duplicate records;
- merge conflict.


20. Search Tests
- exact name;
- description;
- categories;
- languages;
- onion fragment soweit vorgesehen;
- Unicode;
- empty query;
- very long query bounded;
- deterministic ordering;
- Search Score getrennt von Trust State.


21. Query Privacy Tests
Instrumentiere alle Netzwerkadapter.
Während lokaler Suche:
- keine Query in URL;
- keine Query im Body;
- keine Query in Headers;
- keine queryabhängigen direkten Requests.
Test mit mehreren deutlich verschiedenen Suchbegriffen.


22. Pinning Tests
- Pin erstellen;
- Neustart;
- Pin erhalten;
- gleicher Name anderer Key => warning;
- gleicher Key neue Onion mit gültiger Migration => migration state;
- neue Onion ohne Nachweis => identity change;
- Peer-Mehrheit darf Pin nicht ersetzen.


23. UI Security Tests
- lange Namen;
- bidirektionale Zeichen;
- confusable names;
- sehr lange Onion;
- HTML-like text bleibt Text;
- warning accessibility;
- vollständige Identität erreichbar;
- keine SAFE/TRUSTED falschen Labels.


24. Moderation Tests
- moderierter technisch verifizierter Service bleibt kryptografisch verifiziert, erhält aber getrennte Warnung;
- ungültige Moderationsquelle wird ignoriert/quarantänisiert;
- Entfernen einer Moderationsquelle verändert keine Identity History.


25. Multi-Node E2E
Mindestens 3 Nodes:
A besitzt initial Service X.
B besitzt Service Y.
C startet leer.
Nach Sync:
- Eventual Consistency gemäß Policy;
- Identitäten unverändert;
- Konflikte sichtbar;
- Ausfall von A verhindert B/C nicht vollständig.


26. Offline E2E
Client mit bestehendem Index:
- alle Nodes offline;
- Suche funktioniert;
- UI zeigt Indexalter;
- keine Datenlöschung.


27. Upgrade E2E
- alte produktnahe Version installieren;
- reale Testdaten;
- Upgrade;
- DB/IndexedDB Migration;
- Sync;
- Suche;
- Pins;
- Rollback soweit unterstützt.


28. Fuzzing
Targets:
- JSON Parser Wrapper;
- canonicalization adapter;
- Manifest semantic validator;
- Node Descriptor validator;
- sync envelopes;
- URL/Onion parsing.
Fuzz-Crashes oder unbounded behavior sind Release Blocker bis triagiert.


29. Property-Based Testing
Properties:
- canonicalization deterministisch;
- hash deterministisch;
- valid sign/verify roundtrip;
- revision state machine keine verbotenen Übergänge;
- candidate state machine keine VERIFIED ohne verification event;
- merge idempotent;
- identical snapshot apply idempotent;
- limits bleiben invariant.


30. Static Analysis
PHP:
strenge Typanalyse, dead code, dependency audit.
TypeScript:
strict mode, lint, typecheck, dependency audit.
Gemeinsam:
secret scan, license scan, SBOM.


31. Security Regression Suite
Jeder gefundene Security Bug erhält vor Fix oder gleichzeitig einen Regression Test mit anonymisiertem Minimalfall.
Kein Security Fix nur als Kommentar/Dokumentation.


32. Performance Benchmarks
Definiere realistische Indexgrößen:
small
medium
large
stress


Messe:
- import/update;
- search P50/P95;
- memory;
- storage;
- Node sync delta;
- verification throughput unter Budget.


33. Reproduzierbarkeit
CI und lokale Entwickleranleitung müssen denselben Testvektorbestand verwenden.
Randomized Tests loggen reproduzierbaren Seed bei Fehler.


34. Testdaten-Sicherheit
Keine echten privaten Schlüssel.
Keine sensitiven realen Nutzer-/Service-Daten erforderlich.
Öffentliche bekannte Adressen nur wenn zwingend; bevorzugt synthetische/offline Vektoren.


35. Traceability Matrix
Für jede normative Anforderung:
Requirement ID
Dokument/Abschnitt
Code Modul
Test IDs
Status
Review


Coding-Agent hält Matrix aktuell.


36. Release Test Report
Für jeden RC:
- Git commit/version;
- Toolchain versions;
- Unit count/result;
- Integration;
- Conformance;
- E2E;
- fuzz duration/result;
- static analysis;
- dependency audit;
- SBOM;
- performance;
- known issues;
- security exceptions;
- reviewer.


37. Exit Criteria
Eine Phase darf erst abgeschlossen werden, wenn:
- definierte Tests existieren;
- alle Pflichtfälle grün sind;
- neue öffentliche Semantik dokumentiert ist;
- Traceability aktualisiert;
- keine unerklärten skipped tests;
- keine flakiness als “normal” akzeptiert.


38. Testphilosophie für den KI-Coding-Agenten
Der Agent darf Tests nicht abschwächen, löschen oder mit pauschalen Mocks umgehen, nur um CI grün zu bekommen.
Bei einem scheiternden Sicherheitstest:
1. Root Cause ermitteln.
2. Spezifikation prüfen.
3. minimalen reproduzierbaren Fall sichern.
4. Code korrigieren.
5. Regression Test behalten.
6. verwandte Invarianten prüfen.
