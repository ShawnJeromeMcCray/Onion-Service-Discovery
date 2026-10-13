# 04 - PHP Reference Node Implementation Specification

Source: https://docs.google.com/document/d/1R3I1SbEphWuKyJK4XGx5acf_6Tid3yPpxfqtPDATXBQ/edit?usp=drivesdk

Source modified: 2026-10-12T19:30:16.394Z

---

ONION SERVICE DISCOVERY
PHP REFERENCE NODE IMPLEMENTATION SPECIFICATION


1. Ziel
Die PHP-Referenzimplementierung soll auf einem bestehenden Onion-Service-Webserver betrieben werden können, ohne Tor zu forken oder eine zusätzliche projektspezifische Binärdatei zu benötigen. Ein optionaler Scheduler darf PHP CLI periodisch starten; ein eigener dauerhaft laufender Onion Service Discovery Daemon ist nicht Voraussetzung.


2. Laufzeit
Bei Implementierungsbeginn ist eine aktuell unterstützte, sichere PHP-Version zu wählen und per ADR festzuschreiben.
Pflicht-Extensions für alle Profile:
- sodium;
- curl.


Adapter-spezifisch:
- PDO + pdo_sqlite nur wenn SQLiteStore ausgewählt ist;
- entsprechende Treiber nur wenn ein Server-DB-Adapter ausgewählt ist.
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
  storage:verify
  rotate-service-key
  rotate-node-key
  generate-manifest
  storage:migrate
public/
  index.php
config/
  config.example.php oder gleichwertiges Schema
schemas/
tests/
docs/
var/
  storage/
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


Generator workflow:
- validates metadata/config;
- derives the next candidate revision only under the frozen revision/auto-regeneration policy;
- sets previous hash according to the accepted chain;
- emits canonical unsigned signing payload/artifact;
- obtains the signature through OfflineSigner/selected SignerProvider;
- verifies the completed signed Manifest before installation;
- atomically installs only the verified public Manifest at the served path.


The web request path MUST NOT increment/sign revisions merely because a page is loaded.


11. StorageBackend
Persistence is accessed only through the backend-neutral StorageBackend contract.


Required reference adapters:
- FilesystemStore;
- SQLiteStore.


Additional adapters such as PostgreSQLStore/MySQL/MariaDBStore are supported only when implemented and passing the same StorageBackend conformance suite.


The domain/protocol layer MUST NOT depend on:
- PDO;
- SQL syntax;
- SQLite pragmas;
- table/column names;
- filesystem layout.


Persistent logical entities include:
service identities/epochs
service revisions/manifests
service key rotations
service migrations
direct observations
peer assertions
Nodes/descriptors
peer sync state
candidates/candidate events
quarantine
moderation signals
snapshot/checkpoint/chunk metadata
change/cursor state.


12. Storage Rules
All supported backends provide equivalent Protocol semantics:
- atomic logical state transitions;
- immutable continuity evidence;
- fork preservation;
- cursor advancement only after durable commit;
- bounded iteration/query;
- integrity verification;
- backup/export/restore;
- crash recovery;
- storage schema/version migration.


FilesystemStore:
- content-addressed immutable evidence;
- journal/staged generations;
- committed-generation recovery;
- stable logical_service_id paths;
- no attacker-controlled raw path names.


SQLiteStore:
- foreign keys enabled;
- explicit transactions;
- prepared statements;
- busy/locking policy;
- WAL only after tested decision;
- UNIQUE/constraint mapping equivalent to backend-neutral invariants;
- no destructive INSERT OR REPLACE conflict handling.


No adapter may erase fork/rotation/migration evidence merely to create one mutable latest row.


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
Step 1 — Budget/Rate Limit prüfen.
Step 2 — Well-Known-Manifest abrufen.
Step 3 — Transportresultat klassifizieren.
Step 4 — Body-Größe/Encoding prüfen.
Step 5 — Schema validieren.
Step 6 — Onion-Bindung prüfen.
Step 7 — Signatur prüfen.
Step 8 — Revision/History prüfen.
Step 9 — Rotation/Migration prüfen.
Step 10 — über die StorageBackend-Atomicity-Grenze committen.
Step 11 — DirectObservation erzeugen/signieren.
Step 12 — Queue-Status aktualisieren.


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
storage:migrate
storage:verify
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
selected storage backend;
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


26. CORS / Web Deployment Profiles
WEB-COMBINED-V1 and WEB-SEPARATED-V1 follow the Node API/Web Client profile rules.


Combined/proxy mode needs no broad browser CORS.
Separated direct-fetch mode is allowed only under the frozen D-044 policy and uses public credentialless Protocol data, fixed methods/content types and explicitly configured data-node origins.


No credentialed wildcard CORS. No arbitrary user-selected cross-origin destination. No query-bearing CORS request.


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
- backend-specific compaction/maintenance only under its tested policy;
- selected StorageBackend integrity check;
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
- selected StorageBackend migration/import dry run where possible;
- App deploy;
- storage migrate/import;
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
- StorageBackend crash/recovery;
- config validation;
- filesystem permissions and selected SignerProvider/key-custody checks.


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
Der PHP-Referenznode gilt erst als fertig, wenn ein frischer Server aus der Dokumentation reproduzierbar eingerichtet werden kann, die ausgewählten StorageBackend-/SignerProvider-Abhängigkeiten korrekt geprüft werden, und mindestens zwei kontrollierte Testnodes ohne zentrale Laufzeitinfrastruktur und ohne Modifikation des Tor-Binaries interoperabel synchronisieren können.


Die Abnahme erfordert nicht SQLite, wenn FilesystemStore gewählt ist, und sie erfordert nicht, dass der Service Identity private key auf dem Webserver liegt.


37. SELECTED STORAGE BACKEND AVAILABILITY AND RUNTIME CHECK
No database/storage driver is assumed merely because PHP exists.


At init/startup/doctor/security:check:
- read and validate the selected StorageBackend;
- check only that backend's runtime dependencies/capabilities;
- perform a bounded open/write/commit/integrity self-test appropriate to the adapter where safe;
- report exact remediation.


FilesystemStore:
- no PDO/SQL dependency;
- verify directory ownership/permissions;
- verify tested local-filesystem atomic rename/locking semantics;
- verify journal/generation recovery capability;
- external sqlite3 is irrelevant.


SQLiteStore:
- require PDO + pdo_sqlite;
- verify the documented SQLite capability/version floor;
- verify transaction/foreign-key behavior;
- external sqlite3 CLI is NOT a runtime requirement.


Other adapters:
- require only their selected driver/capabilities.


Missing an UNUSED backend driver is never an error and MUST NOT cause silent backend substitution.


Publish-only operation may use its minimal selected storage/state path and controlled signing workflow without a full mirror/discovery datastore.


38. WEB LOCAL SEARCH SERVING
The PHP reference Node serves the built Web Local Search assets under:
GET /onion-service-discovery/
or the final frozen equivalent.


WEB-COMBINED-V1: the same Node origin serves the static Web Client and generic index/snapshot/chunk data.
WEB-SEPARATED-V1: the PHP package MAY operate as Client Host, data-only Node, or both, according to the frozen direct-fetch/proxy profile.


No browser-extension permission model exists. CORS/CSP/network destinations are profile-specific and must match the reviewed Web Client security specification.


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




42. STORAGE BACKEND PLUG-IN ARCHITECTURE
The PHP reference Node MUST depend on a StorageBackend interface rather than SQLite-specific repository code.


Initial required adapters:
FilesystemStore
SQLiteStore


Optional additional adapters, Production-supported only if implemented and passing the same conformance/recovery suite:
PostgreSQLStore
MySQL/MariaDBStore


Every adapter passes the same storage conformance suite.


43. FILESYSTEMSTORE
FilesystemStore provides a SQL-free deployment.


It uses:
- content-addressed immutable signed records;
- deterministic derived-state generations keyed by stable logical_service_id;
- append-only journal with PREPARE/COMMIT semantics;
- staged generation plus atomic committed-generation pointer/rebuild rule;
- single-writer process lock;
- crash recovery that exposes only previous or fully committed new state;
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
A production index-exporting Node MUST produce signed checkpoint metadata containing at minimum:
Node Identity
sequence/revision
generated timestamp
Protocol version
record count
snapshot root/hash
previous_checkpoint_hash according to the frozen D-052 checkpoint-chain rule.


Same-sequence conflicting checkpoints and non-prefix-compatible signed checkpoint histories are preserved as equivocation evidence.




47. OPERATOR STATUS ROUTES
Reference PHP Node MUST serve:
GET /onion-service-discovery/status/
as a server-rendered safe baseline.


Optional JavaScript MAY enhance refresh but is not required for essential status.


48. STATUS DERIVATION SERVICE
Implement a deterministic StatusService/DiscoverabilityService that derives UI and CLI state from actual evidence.


Inputs:
runtime checks;
validated config;
Manifest state;
Tor self-verification;
bootstrap/peer state;
announcement attempts;
external observation responses;
current searchable/export state;
storage/scheduler health.


UI MUST NOT invent states independently from this service.


49. SELF-ANNOUNCEMENT
Implement a bounded outbound CandidateAnnouncementClient.


It submits only minimal canonical Service identity/location hints.
It never writes trusted remote state.
Receiving Nodes independently direct-verify the target Onion Service.


50. DISCOVERABILITY CHECK
Implement a read-only external check against configured/selected Nodes for the operator's own public Service using stable logical_service_id lookup:
GET /onion-service-discovery/v1/services/by-id/{logical_service_id}


The address convenience route may be cross-checked, but discoverability identity continuity MUST NOT depend on the current Onion or current rotatable Service key alone.


It distinguishes:
announcement accepted;
current revision directly observed;
current revision included in active searchable export;
stale external revision;
conflict.


51. SAFE STATUS OUTPUT
Public status output excludes:
secrets;
private paths;
stack traces;
raw config;
auth tokens;
private keys;
raw quarantine data.


52. FAILURE ISOLATION
Failure of Onion Service Discovery routes/storage SHOULD NOT take down unrelated content on the host Onion Service where practical.




53. SECURE CODING BASELINE
The PHP reference implementation MUST comply with document 51.


Required coding constraints include:
declare(strict_types=1);
strict comparison in security logic;
typed validated domain objects;
no eval;
no untrusted unserialize;
no dynamic include from input;
no shell-string command construction;
parameterized SQL;
canonical safe filesystem paths;
approved TorHttpClient for outbound Onion verification;
bounded SafeJsonDecoder;
context-safe rendering;
explicit exception/failure handling.


54. REQUEST VALIDATION PIPELINE
Every endpoint follows:
method/content-type/body-size gate
-> parse under depth/member/string limits
-> structural schema validation
-> field grammar/range validation
-> semantic validation
-> cryptographic/state-machine validation
-> atomic mutation or read response.


No expensive crypto/network/storage work before cheap structural limits.


55. PHP SECURITY REGRESSION
CI and release testing include:
static analysis;
security lint;
dependency scanning;
secret scanning;
fuzz/property tests for parsers;
path traversal tests;
SQL-injection tests;
SSRF tests;
exception/fault injection;
security header tests.


56. DANGEROUS API REVIEW
Any introduction of:
exec/system/shell_exec/proc_open;
unserialize;
eval;
dynamic include;
general-purpose outbound HTTP client outside approved transport
is a security-review blocker and requires explicit justification/ADR.


57. CATEGORY SOURCE OF TRUTH
PHP configuration validation and Manifest generation consume the machine-readable Category Registry v1 artifact/generated constants.


adult is accepted as a normal content category and has no special server-side execution behavior.
