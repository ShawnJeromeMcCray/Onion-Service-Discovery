# 04 - PHP Reference Node Implementation Specification

Source: https://docs.google.com/document/d/1R3I1SbEphWuKyJK4XGx5acf_6Tid3yPpxfqtPDATXBQ/edit?usp=drivesdk

Source modified: 2026-10-11T07:37:45.748Z

---

ONION SERVICE DISCOVERY
PHP REFERENCE NODE IMPLEMENTATION SPECIFICATION


1. Ziel
Die PHP-Referenzimplementierung soll auf einem bestehenden Onion-Service-Webserver betrieben werden können, ohne Tor zu forken oder eine zusätzliche projektspezifische Binärdatei zu benötigen. Ein optionaler Scheduler darf PHP CLI periodisch starten; ein eigener dauerhaft laufender Onion Service Discovery Daemon ist nicht Voraussetzung.


2. Laufzeit
Bei Implementierungsbeginn ist eine aktuell unterstützte, sichere PHP-Version zu wählen und per ADR festzuschreiben.
Pflicht-Extensions:
- sodium;
- curl;
- PDO SQLite.
Composer wird mit Lockfile verwendet.


3. Projektstruktur
Empfohlene Struktur:


src/
  Domain/
  Protocol/
  Crypto/
  Validation/
  TorTransport/
  Discovery/
  Sync/
  Storage/
  Http/
  Config/
  Security/
  Observability/
bin/
  onion-service-discovery
  sync
  verify-service
  verify-database
  rotate-service-key
  rotate-node-key
  generate-manifest
  migrate-database
public/
  index.php
config/
  config.example.php oder gleichwertiges Schema
schemas/
tests/
docs/
var/
  database/
  cache/
  quarantine/
  logs/


Private Schlüssel und produktive Konfiguration liegen NICHT im Webroot.


4. Coding-Standards
- strict_types=1;
- PSR-4;
- PSR-12 oder klar dokumentierter Formatter;
- final/readonly dort, wo sinnvoll;
- Value Objects statt rohe Strings für OnionAddress, PublicKey, Hash, Revision;
- keine globalen Zustände;
- Dependency Injection;
- keine Service Locator Magie;
- keine dynamischen Includes aus Netzwerkdaten;
- Exceptions typisiert;
- Fehlerklassen auf Protokollfehler abbildbar;
- PHPStan/Psalm auf hohem Strictness-Level.


5. Domain Objects
Mindestens:
OnionAddress
ServiceIdentityKey
NodeIdentityKey
ProtocolVersion
ManifestRevision
ManifestHash
ServiceManifest
KeyRotationRecord
OnionMigrationRecord
NodeDescriptor
DirectObservation
PeerAssertion
Candidate
CandidateState
ServiceState
QuarantineReason
SyncCursor
SnapshotDescriptor
ChunkHash
ModerationSignal


Jedes Value Object validiert seine Invarianten beim Erzeugen.


6. Parser und Schema
Netzwerk-JSON wird zunächst als bounded bytes gelesen.
Danach:
- UTF-8 prüfen;
- Duplicate-Key-Strategie einhalten;
- JSON parse;
- Schema validate;
- Domain Mapping;
- zusätzliche semantische Validation.


Keine Domain-Objekte aus teilweise validierten Arrays erzeugen.


7. Canonicalization
Ein eigener Adapter kapselt die gewählte Canonical-JSON-Implementierung.
API:
canonicalize(array/object payload): string
hashCanonical(payload): ManifestHash


Known-Answer-Tests sind Pflicht.
Die Signatur darf niemals über eine zufällige Standard-json_encode-Ausgabe erzeugt werden.


8. Crypto Service
Schnittstellen logisch:
ServiceManifestSigner
ServiceManifestVerifier
NodeRecordSigner
NodeRecordVerifier
KeyRotationVerifier
MigrationVerifier
HashService


libsodium wird verwendet.
Private Key Material darf nicht in Logs, Exceptions oder Debug Dumps gelangen.


9. Key Storage
Separate Dateien oder sichere Secret-Verwaltung:
service_identity.key
node_identity.key


Pflicht:
- restriktive Dateirechte;
- Pfad außerhalb Webroot;
- Startup-Prüfung auf Lesbarkeit und unerwartete Rechte;
- atomisches Schreiben bei Rotation;
- Backup-Hinweis;
- kein automatisches Überschreiben existierender Keys;
- Key Generation nur über kryptografisch sicheren Zufall.


10. Manifest Generator
CLI/Service kann ein Manifest aus lokaler Konfiguration erzeugen.
Der Betreiber pflegt:
- Onion-Adresse;
- Name;
- Beschreibung;
- Sprachen;
- Kategorien;
- publish/mirror/discover/sync flags;
- optionale Links.


Generator:
- validiert;
- erhöht Revision kontrolliert;
- setzt previous hash;
- signiert;
- schreibt atomisch an den vom Webserver ausgelieferten Ort.


11. Datenbank
SQLite Referenzbackend.
Repository-Abstraktion soll spätere Backends erlauben, ohne Domain/Protocol zu verändern.


Tabellen mindestens:
schema_migrations
service_identities
service_revisions
service_manifests
service_key_rotations
service_migrations
direct_observations
peer_assertions
nodes
node_descriptors
peer_sync_state
candidates
candidate_events
quarantine
moderation_signals
snapshot_metadata
chunk_metadata


12. Datenbankregeln
- Foreign Keys aktivieren;
- pragmas dokumentieren;
- busy timeout;
- WAL nur nach Test/Begründung;
- Transaktionen für zusammengehörige Identitätsupdates;
- UNIQUE constraints für Identität/Revision/Hash;
- keine “INSERT OR REPLACE” Semantik bei Identitätskonflikten;
- Schema Migration nummeriert und idempotent geprüft;
- Backup/Restore getestet.


13. Candidate Queue
Candidate Repository speichert:
- onion;
- source type;
- source reference;
- first_received;
- last_attempt;
- next_attempt;
- attempts;
- priority;
- state;
- last_error_class.


Queue Regeln:
- dedup;
- globale Maximalgröße;
- Maximalzahl pro Source/Peer;
- Backoff;
- Jitter;
- Dead/Quarantine State;
- kein endloses Retry.


14. Tor Transport Adapter
Ein einziger zentraler Transportservice ist für Onion-HTTP zuständig.
Alle Aufrufer benutzen ihn; keine verstreuten direkten cURL-Aufrufe.


Konfiguration:
- SOCKS host lokal;
- SOCKS port;
- connect timeout;
- total timeout;
- response byte limit;
- user agent policy;
- max redirects.


Pflicht:
- Hostname-Auflösung über den Tor-SOCKS-Pfad;
- kein Direktfallback;
- nur erlaubte Schemes/Hosts;
- Zielhost vor Request validiert;
- Identity-Verifikation folgt keinem Hostwechsel;
- Response wird gestreamt und bei Größenlimit abgebrochen.


15. Direct Verification Service
Input: validierter OnionAddress Candidate.
Ablauf:
1. Budget/Rate Limit prüfen.
2. Well-Known-Manifest abrufen.
3. Transportresultat klassifizieren.
4. Body-Größe/Encoding prüfen.
5. Schema validieren.
6. Onion-Bindung prüfen.
7. Signatur prüfen.
8. Revision/History prüfen.
9. Rotation/Migration prüfen.
10. in DB-Transaktion schreiben.
11. DirectObservation erzeugen/signieren.
12. Queue-Status aktualisieren.


Kein Schritt darf einen späteren Prüfpunkt als implizit bestanden markieren.


16. Peer Manager
Verwaltet:
- configured seeds;
- manually pinned peers;
- learned peers;
- peer state;
- failure counts;
- next retry;
- node descriptor hash;
- protocol capabilities.


Peer-Auswahl:
- bounded;
- randomisiert innerhalb Policy;
- keine reine Häufigkeitswertung;
- tote Peers Backoff;
- Seeds nicht als Root Authority behandeln.


17. Sync Engine
Pull-only als Baseline.
Ablauf:
- Peer Descriptor/State laden;
- Version/Capabilities prüfen;
- lokalen und fremden State vergleichen;
- begrenzte Deltas/Chunks holen;
- jeden Record validieren;
- in Staging/Transaction;
- Konflikte quarantänisieren;
- Sync Cursor erst nach erfolgreichem Commit aktualisieren.


Ein fehlerhafter Peer darf keine lokale, bereits validierte Historie löschen.


18. Public HTTP API
Front Controller im public-Verzeichnis.
Kein Framework ist zwingend; wenn Framework eingesetzt wird, muss Abhängigkeit/Angriffsfläche begründet werden.


Empfohlene Ressourcen:
GET /onion-service-discovery/v1/node
GET /onion-service-discovery/v1/state
GET /onion-service-discovery/v1/peers
GET /onion-service-discovery/v1/services/{onion}
GET /onion-service-discovery/v1/changes
GET /onion-service-discovery/v1/snapshots/{id}
GET /onion-service-discovery/v1/chunks/{hash}


19. API-Regeln
- nur definierte Methoden;
- 405 bei falscher Methode;
- strikte Content Types;
- begrenzte Query-Parameter;
- pagination/limit serverseitig gedeckelt;
- keine dynamische URL-Abfrage;
- keine Remote-Admin-API;
- keine Secrets;
- stabile maschinenlesbare Fehlerstruktur;
- keine Stacktraces in Production.


20. Lokale CLI
Die CLI ist der bevorzugte Admin-Pfad.
Kommandos:
status
config:validate
manifest:generate
service:verify <onion>
peer:list
peer:add
peer:remove
sync:run
candidate:list
quarantine:list
db:migrate
db:verify
key:service:generate
key:service:rotate
key:node:generate
key:node:rotate
snapshot:build


Sensitive Aktionen verlangen explizite Bestätigung oder non-interactive Flag für Automation.


21. Scheduler
Empfohlen:
cron oder systemd timer startet kurze CLI-Jobs.
Getrennte Jobs:
- peer sync;
- candidate verification;
- stale recheck;
- snapshot build;
- maintenance.


Locking verhindert parallele identische Jobs.
Jeder Job hat Laufzeit- und Work-Budget.


22. Opportunistische Synchronisierung
Kann als optionale Fallback-Funktion existieren, wenn kein Scheduler verfügbar ist.
Sie darf:
- nicht bei jedem Request ungebremst starten;
- keine Antwortlatenz unkontrolliert erhöhen;
- nur nach Age Threshold + probabilistischer Policy + Lock auslösen.
Scheduler bleibt bevorzugt.


23. Configuration Schema
Konfiguration ist typisiert und validiert.
Mindestens:
project display name;
publish_service;
mirror_directory;
discover_services;
sync_with_peers;
service onion;
service metadata;
paths;
Tor transport;
database;
limits;
timeouts;
peer seeds;
logging;
scheduler policy.


Unknown critical config keys => Fehler.


24. Secure Defaults
- discover_services standardmäßig konservativ;
- admin remote disabled;
- debug disabled;
- minimal logging;
- finite limits überall;
- no external analytics;
- no arbitrary outbound destination;
- no public directory browsing;
- no automatic key replacement.


25. HTTP Security
Für HTML-Seiten:
- CSP;
- no inline remote script dependency soweit möglich;
- output encoding;
- frame restrictions;
- referrer policy;
- cache policy passend zu Inhalt.
API:
- JSON only;
- keine HTML-Fehlerseiten;
- content sniffing verhindern.


26. CORS
Öffentliche API-CORS-Regeln so eng wie praktisch.
Web Local Search uses same-origin Node endpoints; broad browser-extension CORS/permission support is not required. Optional server-search fallback is tested separately.
Nicht pauschal * setzen, wenn es nicht notwendig ist.
Keine Credentials-basierte Cross-Origin-API als Standard.


27. Logging
Structured Logs mit event class.
Nicht loggen:
- private keys;
- raw config secrets;
- Suchbegriffe;
- Browser history.
Untrusted Strings quoten/sanitizen, um Log Injection zu verhindern.


28. Health
Lokaler/öffentlicher Health State:
OK
DEGRADED
READ_ONLY
MAINTENANCE


Health darf keine internen Pfade, Keys, Peer-Details oder Stacktraces offenlegen.


29. Maintenance
Periodische Tasks:
- alte transient peer assertions bereinigen;
- Quarantine nach Policy erhalten;
- DB vacuum nur kontrolliert;
- integrity_check;
- Snapshot cleanup;
- stale peer cleanup;
- log rotation außerhalb App oder dokumentiert.


30. Backup
Backup enthält:
- DB;
- Service Identity Key;
- Node Identity Key;
- produktive Konfiguration;
- separate Dokumentation, welche Daten kritisch sind.


Keys und DB können unterschiedliche Schutz-/Backupstrategien haben.
Restore Drill ist Pflicht.


31. Upgrade
Upgrade-Reihenfolge:
- Backup;
- Version Compatibility Check;
- DB Migration Dry Run falls möglich;
- App deploy;
- migrate;
- self-check;
- health;
- controlled sync.


Fehler => Rollback Runbook.


32. Tests für PHP-Node
- Domain unit tests;
- repositories;
- migration tests;
- canonicalization vectors;
- crypto vectors;
- transport mocks;
- direct verification;
- queue/backoff;
- peer sync;
- API contract;
- CLI;
- concurrency;
- DB recovery;
- config validation;
- permissions/key file checks.


33. Static Analysis
CI:
composer validate
formatter check
PHPStan/Psalm strict
unit
integration
security/conformance
dependency audit
secret scan
SBOM generation


34. Abhängigkeitsprinzip
Jede Runtime-Dependency braucht:
- Zweck;
- Lizenz;
- Maintainer-/Release-Status;
- Security-Historie soweit prüfbar;
- Alternative;
- Grund, warum Standardbibliothek nicht reicht.


35. Produktionsartefakt
Der Agent liefert:
- Composer package/project;
- example configuration;
- migration scripts;
- CLI;
- web entry point;
- schema files;
- systemd/cron Beispiele als Dokumentation;
- nginx und Apache Beispielkonfigurationen;
- install guide;
- upgrade guide;
- backup/restore guide;
- hardening checklist;
- test report.


36. Abnahme
PHP-Node gilt erst als fertig, wenn ein frischer Server aus der Dokumentation reproduzierbar eingerichtet werden k


37. SQLITE AVAILABILITY AND RUNTIME CHECK
SQLite support is NOT assumed to exist merely because PHP exists.


The reference implementation requires:
- PDO extension;
- pdo_sqlite driver;
- an SQLite library version that satisfies the documented minimum capability set.


At startup/init/doctor/security:check:
- verify extension_loaded('pdo');
- verify 'sqlite' exists in PDO::getAvailableDrivers();
- verify database open/transaction/foreign-key behavior;
- report exact remediation when unavailable.


If SQLite support is missing:
- initialization and write-capable Node startup fail clearly;
- no half-functional fallback to flat files;
- no silent database substitution;
- publish-only static Manifest generation MAY remain available only if its workflow does not require the Node database.


The external sqlite3 command-line executable is NOT a runtime requirement.


Storage interfaces remain abstract enough that a future PostgreSQL or other backend can be added without changing Protocol v1 semantics.


38. WEB LOCAL SEARCH SERVING
The PHP reference Node serves the built Web Local Search assets under:
GET /onion-service-discovery/
or the final frozen equivalent.


The Node also serves same-origin generic index/snapshot/chunk endpoints consumed by that page.


No extension-specific CORS or host-permission support is required.


39. OPTIONAL NO-JAVASCRIPT SERVER SEARCH
Optional endpoint:
POST /onion-service-discovery/search/server


Rules:
- public read-only operation;
- query is processed only against local verified/derived index;
- no live Onion fetches;
- no candidate discovery triggered by query;
- no peer sync triggered by query;
- query length/token/result/work limits;
- reference application does not log query;
- response uses no-store;
- HTML safely escapes all query/result fields;
- page clearly states that the query was sent to this Node.


40. SEARCH ENGINE SHARED SEMANTICS
Local Web Search and server fallback implement the same versioned ranking/filter semantics against equivalent index state.


Search relevance remains separate from:
identity validity;
observer count;
moderation;
liveness.


41. STATIC WEB CLIENT DEPLOYMENT
Built assets:
- read-only in production;
- content/version hashed;
- no runtime CDN;
- strict CSP;
- release asset manifest and hashes;
- checked by security:check.


A compromised/malicious serving Node remains capable of altering Web code; this is a documented Web-origin trust boundary rather than a claim solved by PHP.
ann und zwei unabhängige Testnodes synchronisieren können, ohne zentrale Infrastruktur und ohne Modifikation des Tor-Binaries


42. STORAGE BACKEND PLUG-IN ARCHITECTURE
The PHP reference Node MUST depend on a StorageBackend interface rather than SQLite-specific repository code.


Initial required adapters:
FilesystemStore
SQLiteStore


Planned production adapters:
PostgreSQLStore
MySQL/MariaDBStore


Every adapter passes the same storage conformance suite.


43. FILESYSTEMSTORE
FilesystemStore provides a SQL-free deployment.


It uses:
- content-addressed immutable signed records;
- deterministic derived-state files;
- append-only journal;
- atomic temporary write + rename;
- single-writer process lock;
- crash recovery;
- integrity checker;
- canonical backend-neutral export/import.


It MUST NOT store attacker-controlled strings as raw path names.


44. DRIVER-SPECIFIC CHECKS
doctor checks only the selected backend.


Examples:
filesystem -> permissions, atomic rename/lock semantics, disk;
sqlite -> PDO + pdo_sqlite;
postgresql -> selected PHP driver/connection capability;
mysql -> selected PHP driver/transactional capability.


Missing an unused database driver is not an error.


45. CLIENT HOST SUPPORT
The reference Node package MAY serve the static Client Host role, but the roles are separable.


In data-only mode:
- Node API returns no active content;
- public Protocol endpoints use strict schemas/content types;
- CORS is enabled only if the operator deliberately permits separated direct-browser data access;
- no credentials/cookies are used on public data endpoints.


46. SIGNED NODE CHECKPOINTS
The Node SHOULD produce signed snapshot/checkpoint metadata containing at minimum:
Node Identity
sequence/revision
generated timestamp
Protocol version
record count
snapshot root/hash
previous checkpoint hash where defined.


Conflicting signed checkpoints are preserved as equivocation evidence.
.
