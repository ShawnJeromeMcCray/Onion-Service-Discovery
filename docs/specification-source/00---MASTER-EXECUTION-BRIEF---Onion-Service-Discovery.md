# 00 - MASTER EXECUTION BRIEF - Onion Service Discovery

Source: https://docs.google.com/document/d/1iavEaLZX4iFR0taaQ4jp_O-5mXLeTDPmof4x1prVrkA/edit?usp=drivesdk

Source modified: 2026-10-12T19:33:40.863Z

---

ONION SERVICE DISCOVERY
MASTER EXECUTION BRIEF


Zweck
Dieses Dokument ist die oberste Arbeitsanweisung für einen autonomen KI-Coding-Agenten. Ziel ist eine produktionsreife, sicherheitsorientierte und dezentrale Discovery-Lösung für freiwillig veröffentlichte Tor Onion Services, ohne Tor selbst zu verändern oder neu zu kompilieren.


Projektname
Der öffentliche Name lautet immer vollständig: Onion Service Discovery.
Keine abgekürzte Produkt- oder Protokollbezeichnung verwenden.
Öffentlicher Slug: onion-service-discovery


Nicht verhandelbare Prinzipien
- Teilnahme ist opt-in.
- Ein Betreiber soll nach der Erstkonfiguration keine regelmäßige manuelle Verlängerung benötigen.
- Service-Metadaten werden vom Service selbst veröffentlicht.
- Kein einzelner Server, Betreiber oder Entwickler darf für die Gesamtfunktion zwingend erforderlich sein.
- Suchanfragen werden im Client lokal verarbeitet.
- Die Anwendung greift niemals auf private Tor-Onion-Schlüssel zu.
- Service-Identität und Node-Identität verwenden getrennte Anwendungsschlüssel.
- Fremde Peers gelten grundsätzlich als nicht vertrauenswürdig.
- Kritische Metadaten werden kryptografisch und, soweit möglich, direkt verifiziert.
- Sicherheitsfehler werden fail-closed behandelt.
- Netzwerk- und Speicherressourcen sind strikt begrenzt.
- Logs und Telemetrie dürfen keine Suchbegriffe oder Browserverläufe sammeln.
- Benutzeroberflächen dürfen technische Verifikation niemals als pauschales “sicher” oder “vertrauenswürdig” darstellen.


Primäre Komponenten
1. PHP-Referenznode für bestehende Onion-Service-Webserver.
2. Web Local Search als statischer Referenzclient für Tor Browser auf Desktop und Android; WEB-COMBINED-V1 kann ihn vom Discovery Node ausliefern, WEB-SEPARATED-V1 von einem getrennten/self-hosted Client Host; keine separate Browser-Extension.
3. Versionierte Protokoll- und JSON-Spezifikationen.
4. Conformance- und Security-Test-Suite.
5. Betriebs-, Release-, Incident- und Audit-Dokumentation.


Operator-Funktionen
Jeder Betreiber kann Funktionen unabhängig aktivieren:
- publish_service: den eigenen Service auffindbar machen.
- mirror_directory: Discovery-Daten für andere bereitstellen.
- discover_services: Kandidaten entdecken und kontrolliert verifizieren.
- sync_with_peers: mit anderen Discovery-Nodes synchronisieren.


Arbeitsreihenfolge für den Coding-Agenten
Phase 0: Workspace, Coding-Standards, CI und ADR-Struktur.
Phase 1: Datenmodelle, JSON-Schemas, kanonische Serialisierung und Kryptographie.
Phase 2: Manifest, Revisionen, Identitätskontinuität, Key-Rotation und Service-Migration.
Phase 3: PHP-Storage und lokale CLI.
Phase 4: streng begrenzter Tor-Transport und direkte Verifikation.
Phase 5: read-only Node-API.
Phase 6: Pull-Synchronisierung, Candidate Queue, Rate Limits und Backoff.
Phase 7: Web Local Search Client, clientseitige Protokoll-/Signaturprüfung, bounded Snapshot-/Chunk-Download, lokale Suche und optionaler No-JavaScript Server Search Fallback.
Phase 8: lokale Suche und verständliche Trust-/Warning-UX.
Phase 9: Multi-Node-E2E, adversariale Tests, Fuzzing und Performance.
Phase 10: Hardening, Audit-Vorbereitung und Operator-Dokumentation.
Phase 11: Release Candidate, Clean-Room-Installation und Rollback-Test.
Phase 12: Production Release erst nach allen Quality Gates.


Der Agent beginnt NICHT mit UI oder Netzwerkcode. Zuerst müssen Architecture Decision Records für Laufzeiten, Canonical JSON, Signaturformat, Key Lifecycle, Manifest-Schema, Transport-Isolation, lokalen Index und Synchronisationsmodell erstellt und durch Tests abgesichert werden.


Verbotene Abkürzungen
Keine abgekürzten Produkt- oder Protokollnamen und keine Akronyme. Öffentlich immer Onion Service Discovery ausschreiben.


Definition of Done auf oberster Ebene
- Protokoll ist versioniert, vollständig spezifiziert und durch Conformance Tests abgesichert.
- Referenznode läuft ohne Tor-Modifikation auf einem Standard-Webserver-Stack.
- Betreiber können Publish, Mirror, Discover und Sync unabhängig konfigurieren.
- Client-Suche bleibt lokal.
- Identitätskontinuität, Key-Rotation und Service-Adressmigration sind getestet.
- Fremde Node-Daten können nicht stillschweigend die Identität eines Services überschreiben.
- Eingaben, Requests, Datenbankwachstum und Synchronisierung sind begrenzt.
- Privacy-, Security-, Operator-, Incident- und Release-Dokumente sind vollständig.
- CI, statische Analyse, Dependency-Audit, Secret-Scanning und Tests sind grün.
- Backup, Restore, Migration und Rollback wurden praktisch getestet.
- Offene kritische oder hohe Security Findings blockieren den Release, sofern nicht ausdrücklich durch einen menschlichen Security Owner akzeptiert.
ONION SERVICE DISCOVERY
MASTER EXECUTION BRIEF FÜR EINEN AUTONOMEN KI-CODING-AGENTEN


Status: verbindliche Arbeitsanweisung für die erste produktionsreife Implementierung
Ziel: ein sicherheitsorientiertes, dezentrales Discovery-System für öffentliche Tor Onion Services ohne Änderungen oder Neukompilierung von Tor.
Projektname: Onion Service Discovery. Dieser Name MUSS ausgeschrieben und konsistent verwendet werden. Keine Abkürzung als Produkt- oder Protokollname.


1. MISSION


Baue ein produktionsreifes System, mit dem öffentliche Onion Services freiwillig auffindbar werden können, ohne dass Betreiber regelmäßig manuell registrieren oder verlängern müssen und ohne dass ein einzelner zentraler Suchserver, ein einzelner Registry-Betreiber oder der ursprüngliche Entwickler ein Single Point of Failure ist.


Das System besteht aus zwei primären Referenzimplementierungen:
A) Onion Service Discovery Node: serverseitige PHP-Referenzimplementierung, die in einen bestehenden Onion-Service-Webstack integriert werden kann.
B) Onion Service Discovery Web Local Search Client: statische HTML/CSS/JavaScript-Anwendung. WEB-COMBINED-V1 liefert Code und generische Indexdaten von einem Node-Origin; WEB-SEPARATED-V1 trennt Client Host und Discovery-Data-Nodes gemäß eingefrorenem CORS/Proxy-Profil. Sie läuft ohne separate Extension in Tor Browser auf Desktop und Android. Bei verfügbarem JavaScript sucht WEB-LOCAL lokal im geladenen Index; ohne JavaScript kann ein klar gekennzeichneter serverseitiger Such-Fallback angeboten werden.


Tor selbst wird NICHT geforkt, NICHT neu kompiliert und NICHT modifiziert.


2. NICHT VERHANDELBARE PRODUKTPRINZIPIEN


MUSS:
- Opt-in: Ein Onion Service darf nur in den öffentlichen Discovery-Index gelangen, wenn er dies explizit in seinem eigenen Discovery-Manifest erklärt.
- Zero Renewal: Nach der Erstkonfiguration darf keine periodische manuelle Registrierung erforderlich sein.
- Self-hosted Metadata: Name, Beschreibung, Kategorien, Sprachen und Discovery-Schalter stammen primär vom Onion Service selbst.
- No Central Authority: Kein einzelner Node darf für Funktion, Identität oder Suche erforderlich sein.
- Web Local Search: In der unveränderten Referenzimplementierung werden Suchbegriffe ausschließlich lokal im Browser verarbeitet. Ein bösartiger/kompromittierter Serving Node kann jedoch manipulierten Web-Code ausliefern; diese Origin-Trust-Grenze ist ausdrücklich Teil des Threat Models.
- Tor-only Network Access: Node-zu-Node- und Node-zu-Service-Kommunikation erfolgt für .onion-Ziele ausschließlich über Tor SOCKS mit Remote-Hostname-Auflösung.
- No Tor Key Access: Die Anwendung darf niemals den privaten Hidden-Service-Key von Tor lesen, kopieren, importieren oder als Anwendungsidentität verwenden.
- Separate Keys: Service Identity Key und Node Identity Key sind getrennte Ed25519-Schlüssel.
- Explicit Trust Semantics: UI darf nicht pauschal “trusted”, “safe”, “legit” oder vergleichbare Aussagen anzeigen.
- Untrusted Peers: Daten von Peers sind Hinweise/Behauptungen und werden nicht allein durch Empfang wahr.
- Direct Verification: Kritische Service-Metadaten werden soweit praktikabel direkt beim betreffenden Onion Service verifiziert.
- Privacy by Design: Keine Suchbegriffe in Logs, Node-Requests, Telemetrie oder Fehlermeldungsdiensten.
- Fail Closed: Bei Signaturfehlern, Parsing-Mehrdeutigkeiten, Onion-Mismatch, Hash-Mismatch, Key-Mismatch oder Protokollverletzungen wird ein Datensatz verworfen bzw. quarantänisiert.
- Bounded Resources: Größenlimits, Zeitlimits, Rate Limits, maximale Peerzahlen, maximale Kandidatenzahlen und Backoff sind verpflichtend.
- Secure Defaults: Publish, Mirror und Discover müssen getrennt konfigurierbar sein; riskantere Funktionen dürfen nicht unbemerkt aktiviert werden.
- No Hidden Network Side Effects: Normale Webseitenbesuche des Nutzers dürfen nicht automatisch als Discovery-Signale an Nodes gemeldet werden.
- Reproducibility: Release-Artefakte, Abhängigkeiten, SBOM und Prüfsummen müssen nachvollziehbar sein.


SOLL:
- Stateless/read-only Peer APIs bevorzugen.
- Pull-Synchronisierung gegenüber ungefragtem Push bevorzugen.
- SQLite als einfache Referenzspeicherung; Abstraktion für spätere Backends.
- libsodium für Ed25519 in PHP.
- RFC 8785 JSON Canonicalization Scheme oder eine gleichwertig exakt spezifizierte kanonische Darstellung für signierte JSON-Payloads.
- SHA-256 für Inhalts-/Kettenhashes, sofern kein besser begründeter Standard gewählt wird.
- Merkle-basierte inkrementelle Synchronisierung als spätere oder direkt produktionsfähige Optimierung, sofern die erste korrekte Implementierung dadurch nicht gefährdet wird.


3. ROLLEN


3.1 Normaler Nutzer
- Installiert den Client.
- Lädt signierte/verifizierbare Discovery-Daten von mehreren Nodes.
- Hält einen lokalen Index.
- Sucht ausschließlich lokal.
- Sieht präzise technische Vertrauenssignale und Warnungen.
- Muss niemals selbst Node sein.


3.2 Onion-Service-Betreiber
Kann drei Funktionen unabhängig schalten:
- publish_service: eigener Service darf gefunden werden.
- mirror_directory: Node speichert und liefert Discovery-Daten weiter.
- discover_services: Node darf Kandidaten entdecken/verifizieren und den Index aktualisieren.
Zusätzlich kann sync_with_peers separat steuerbar sein.
Minimaler Betreiberaufwand: Manifest bereitstellen und publish_service aktivieren. Keine manuelle Verlängerung.


3.3 Discovery Node
- Lernt Peers aus konfigurierten Seeds und verifizierten Peerinformationen.
- Synchronisiert über Pull.
- Prüft Kandidaten kontrolliert.
- Speichert direkte Beobachtungen getrennt von fremden Behauptungen.
- Signiert eigene Beobachtungen mit Node Identity Key.
- Darf keine Aussage “Service ist sicher” ableiten.


3.4 Projektentwickler
- Definiert Spezifikation, Conformance Tests, Referenzimplementierungen und Releaseprozesse.
- Betreibt keinen zwingend notwendigen zentralen Dienst.


4. KERN-ARCHITEKTUR


Onion Service
  -> /.well-known/onion-service-discovery.json
  -> Discovery Node(s)
  <-> Discovery Node(s)
  -> Client-Index-Snapshots/Deltas
  -> Web Local Search: Browser-Speicher nur opportunistisch; In-Memory-Suche als Baseline
  -> lokale Suche


Für neue Clients existiert eine kleine eingebettete Seed-Liste. Seed-Nodes sind nur Bootstrap-Hilfen. Nach erfolgreicher Synchronisierung lernt der Client/Node zusätzliche Peers. Der Ausfall aller ursprünglichen Seeds darf bestehende Installationen nicht sofort unbrauchbar machen.


5. MANIFEST-BINDUNG UND IDENTITÄT


Ein Service-Manifest MUSS mindestens enthalten:
- protocol = "Onion Service Discovery"
- protocol_version
- onion
- service_identity_public_key
- revision
- previous_manifest_hash, außer Genesis
- publish_service
- service.name
- service.description optional, aber begrenzt
- languages
- categories
- signature


Das Manifest MUSS direkt von derselben Onion-Adresse abrufbar sein, die im Feld onion steht.
Bei Identitätsprüfung sind HTTP-Redirects auf andere Hosts/Onions verboten.
Die Onion-v3-Adresse selbst bleibt Tor-Identität; die Anwendung führt zusätzlich einen Onion Service Discovery Service Identity Key, ohne Tor-Schlüssel zu berühren.


6. KRYPTOGRAPHISCHE KONTINUITÄT


- Jedes Manifest wird kanonisch serialisiert und mit Service Identity Key signiert.
- revision ist strikt monoton.
- previous_manifest_hash bildet eine nachvollziehbare Kette.
- Ein bereits bekannter Client/Node darf ältere Revisionen nicht als aktuelle Revision übernehmen.
- Gleich hohe Revisionen mit unterschiedlichen Hashes sind ein Fork-Ereignis und müssen sichtbar quarantänisiert werden.
- Service Identity Key Rotation: alter Schlüssel signiert den neuen Schlüssel und die Rotation; optional bestätigt neuer Schlüssel rückwärts.
- Onion-Migration: bisherige Service Identity signiert die neue Onion-Adresse. Wenn alter Onion noch erreichbar ist, soll er die Migration ebenfalls ausliefern.
- Fehlt die Bestätigung der alten Onion-Adresse, darf UI nur “Identity continuity” anzeigen, nicht “vollständig bestätigte Onion-Migration”.


7. NODE-IDENTITÄT


Jeder Mirror/Discovery-Node besitzt einen separaten Ed25519 Node Identity Key.
Node-Key signiert:
- Observations
- Node-Metadaten
- ggf. Snapshot-/Merkle-Roots
Node-Key DARF NICHT Service-Metadaten umschreiben.
Ein Service und sein Node dürfen auf derselben Maschine laufen, Schlüssel bleiben getrennt.


8. DATENTYPEN


Implementiere mindestens:
- ServiceManifest
- ServiceIdentity
- ServiceRevision
- MigrationRecord
- KeyRotationRecord
- Candidate
- DirectObservation
- PeerAssertion
- NodeIdentity
- PeerDescriptor
- IndexEntry
- QuarantineRecord
- ModerationSignal
- SyncState
- Merkle/Chunk metadata sofern aktiviert


Jeder Datentyp erhält JSON Schema, Parser, Validator, Canonicalizer und negative Tests.


9. TRUST-MODELL


Vertrauen wird nicht als ein Score “Wahr/Falsch” modelliert. UI und Datenmodell unterscheiden:
- onion_binding_verified
- manifest_signature_valid
- identity_continuity_valid
- migration_verified
- clearnet_link_verified optional
- first_seen
- last_directly_seen
- last_peer_reported
- established_duration
- selected_observer_confirmations
- fork_detected
- stale/offline state
- community/moderation warnings separat


Die Anzahl beliebiger Nodes ist KEIN zuverlässiger Vertrauensbeweis wegen Sybil-Angriffen.


10. DISCOVERY


Zulässige Discovery-Quellen:
- andere verifizierte Manifest-Dateien, die Kandidaten/Peers nennen;
- bekannte öffentliche Onion-Links, sofern Node-Crawler explizit aktiviert ist;
- manuelle einmalige Kandidaten-Ankündigung;
- Peer-Synchronisierung.


Niemals:
- Kandidat sofort als verifiziert speichern;
- einen normalen Browser-Client dazu zwingen, massenhaft unbekannte Onions zu prüfen;
- unbeschränkt unbekannte Adressen abfragen.


Candidate State Machine:
RECEIVED -> VALIDATED_ADDRESS -> QUEUED -> DIRECT_CHECK_PENDING -> VERIFIED oder REJECTED/BACKOFF/QUARANTINED.


11. SPAM-, DOS- UND MISSBRAUCHSSCHUTZ


Verpflichtend:
- strenge v3 .onion Syntaxvalidierung;
- kein Zugriff auf clearnet/private IP/localhost über Candidate-Felder;
- SSRF-Schutz;
- Remote DNS nur über Tor SOCKS;
- Manifest Maximalgröße z.B. 16 KiB, final in Spezifikation festlegen;
- Anzahl Strings/Listen begrenzen;
- Unicode normalisieren und unsichtbare/gefährliche Zeichen behandeln;
- maximale Redirect-Anzahl für nicht-identitätskritische Requests; für Identitätsbindung 0;
- harte Connect- und Total-Timeouts;
- globale und per-Peer Rate Limits;
- Peer-Budgets;
- Candidate-Budgets;
- exponentielles Backoff mit Jitter;
- Circuit-/Request-Concurrency begrenzen;
- keine Rekursion ohne Depth-Limit;
- keine unendliche Peer-Ausbreitung;
- maximale Datenbankgröße/Retention Policies;
- Quarantäne statt aggressiver Löschung bei Konflikten;
- Replay-Schutz über Revision/Hash/Zeitkontext;
- Schema unknown-field policy explizit definieren;
- Parser darf keine Duplicate-JSON-Keys still akzeptieren, wenn dies Mehrdeutigkeiten erzeugt;
- keine dynamische Codeausführung aus Netzwerkdaten;
- keine Remote-Includes;
- keine Shell-Kommandos aus untrusted Feldern;
- SQL ausschließlich parametrisiert.


Optional nach Threat-Model-Review:
- moderates Proof-of-Work nur für kostenintensive Erstankündigungen, niemals für normale Benutzer-Suche;
- Observer-Sets;
- append-only transparency logs / Merkle consistency proofs.


12. PHP REFERENZNODE


Zielumgebung:
- PHP 8.5 bevorzugt (Stand Oktober 2026); grundsätzlich eine zum Implementierungs- und Releasezeitpunkt aktiv unterstützte PHP-Version. Mindest-/Supportmatrix per ADR festlegen und vor jedem Major Release gegen php.net/supported-versions.php aktualisieren;
- ext-sodium;
- ext-curl;
- selected StorageBackend dependencies only; SQLiteStore requires PDO + pdo_sqlite, FilesystemStore does not;
- Composer mit gelockten Abhängigkeiten;
- nginx oder Apache;
- lokaler Tor SOCKS-Port;
- optional cron/systemd timer, der nur PHP CLI startet, kein eigener Onion Service Discovery Daemon erforderlich.


Logischer Aufbau:
src/Domain/
src/Crypto/
src/Protocol/
src/Validation/
src/TorTransport/
src/Discovery/
src/Sync/
src/Storage/
src/Http/
src/Security/
bin/
public/
config/
tests/
schemas/


Keine Secrets im Webroot.
Dateirechte dokumentieren.
SQLite WAL/locking/recovery bewusst testen.
Migrationen versionieren.


13. NODE HTTP API


API ist möglichst read-only. Mindestens:
GET /onion-service-discovery/v1/node
GET /onion-service-discovery/v1/state
GET /onion-service-discovery/v1/peers
GET /onion-service-discovery/v1/services/{onion}
GET /onion-service-discovery/v1/changes?... oder äquivalente delta-Schnittstelle
GET /onion-service-discovery/v1/snapshot/... sofern Snapshots verwendet werden


Keine unautorisierte Remote-Admin-API.
Administrative Änderungen ausschließlich lokal/CLI oder stark gesichert.
Content-Type strikt.
CORS minimal.
CSP für HTML-Oberflächen.
Security headers.
Cache-Semantik dokumentieren.


14. TOR TRANSPORT


Alle .onion-Requests des Nodes gehen über SOCKS5 mit Hostname-Auflösung durch Tor.
Kein lokaler DNS-Fallback.
Kein Proxy-Bypass.
Keine Proxy-Konfiguration aus Peer-Daten.
Timeouts und maximale Responsegröße gelten schon während Transfer.
Host/Port Whitelist aus Spezifikation.
Clearnet-Proofs, falls unterstützt, sind separate optionale Verifikation mit eigenem Threat Model.


15. BROWSER-CLIENT


Web Local Search Client:
- Web-Plattform-Kompatibilität gemäß aktuell unterstütztem Tor Browser Desktop/Android bei Implementierung;
- minimal notwendige Permissions;
- Node-Liste/Seeds;
- Download von Snapshots/Deltas;
- Signatur-/Hash-/Schema-Prüfung;
- optionales origin-lokales IndexedDB/Cache Storage, ohne Persistenzgarantie;
- lokaler Suchindex;
- Such-UI;
- Detailansicht mit technischen Vertrauenssignalen;
- Warnung bei Identity Change/Fork/Migration;
- TOFU-Pinning für Favoriten oder explizit gespeicherte Identitäten;
- keine Queries an Nodes;
- keine Telemetrie standardmäßig;
- keine automatische Übermittlung der Browsing-Historie;
- keine WebRTC-Abhängigkeit;
- keine Native-Messaging-/EXE-Pflicht.


Client darf niemals suggerieren, dass ein vom besuchten Node ausgelieferter Web Client gegen einen bösartigen Serving Origin automatisch vertrauenswürdig ist. Die Referenzimplementierung sendet lokale Queries nicht, aber ein kompromittierter/maliziöser Node kann modifizierten Code ausliefern.


16. SUCHE UND RANKING


Lokaler Index über name, description, categories, languages, onion und technische Statusfelder.
Ranking darf technische Sicherheitssignale transparent einbeziehen, aber Popularität nicht als Vertrauensbeweis behandeln.
Names sind nicht eindeutig.
Phishing-Schutz:
- ähnlich geschriebene Namen markieren;
- bekannte gepinnte Identität bevorzugt sichtbar machen;
- andere Service-Key/Onion-Kombination deutlich als andere Identität kennzeichnen;
- Unicode confusable analysis;
- keine automatischen Marken-/Eigentumsbehauptungen.


17. MODERATION


Moderation strikt von kryptografischer Identität trennen.
Ein Service kann technisch korrekt identifiziert und gleichzeitig von einer Moderationsliste gemeldet sein.
ModerationSignal enthält Quelle, Provenienz, Kategorie, Zeit und Ablauf/Revision.
Basisprotokoll darf nicht eine einzelne globale Moderationsautorität erzwingen.


18. DATENSCHUTZ


Keine Speicherung von Suchbegriffen, Browser-Historie, personenbezogenen Nutzerprofilen oder unnötigen IP-/Request-Fingerprints.
Node-Logs minimieren, rotieren und standardmäßig sparsam.
Keine Third-Party-Analytics.
Keine externen Fonts/Tracker.
Suche und Rendering laufen im WEB-LOCAL-Modus lokal im Browser. Die ausführbaren HTML/JS/CSS kommen je nach aktivem Deploymentprofil entweder vom Combined Node oder vom getrennten Client Host; genau dieser Serving-Code-Origin ist die dokumentierte Web-Trust-Boundary.


19. TESTANFORDERUNGEN


Verpflichtend:
- Unit Tests;
- Integration Tests;
- Conformance Tests;
- negative Protocol Tests;
- Parser ambiguity tests;
- cryptographic known-answer tests;
- fuzzing für Parser/Canonicalization/API;
- property-based tests für Revision chains/state machines;
- SSRF tests;
- redirect tests;
- SOCKS/DNS-leak tests;
- database corruption/recovery tests;
- migration/key-rotation tests;
- fork tests;
- rate-limit/backoff tests;
- concurrency tests;
- Web-Client Cache/Storage failure tests;
- end-to-end test mit mehreren lokalen Mock-Nodes und simuliertem Tor-Transport;
- dependency vulnerability scanning;
- static analysis;
- PHPStan/Psalm oder vergleichbare strenge Analyse;
- lint/format;
- Web-Client CSP/XSS/static-bundle checks;
- reproducible clean install test.


Kein Release bei kritischen/hohen offenen Security Findings ohne dokumentierte Ausnahme und explizite menschliche Freigabe.


20. CI/CD UND SUPPLY CHAIN


- lockfiles;
- minimal dependencies;
- SBOM;
- SAST;
- secret scanning;
- dependency audit;
- build checks;
- test matrix;
- signed tags/releases;
- SHA-256 checksums;
- release provenance soweit praktikabel;
- keine Build-Secrets im Repository;
- reproducible build documentation;
- Security policy;
- vulnerability disclosure process.


21. DOKUMENTATION


Erstelle README, Architecture, Protocol specification, Threat model, Security design, Operator guide, Node installation, Tor SOCKS configuration guidance, Browser client guide, Developer guide, Conformance guide, Release guide, Incident response, Privacy statement, Changelog, Migration guide, Key backup/rotation guide und FAQ mit präzisen Sicherheitsgrenzen.


22. ARBEITSMETHODE DES CODING-AGENTEN


Für jede Phase:
1. Anforderungen aus allen Spezifikationsdokumenten lesen.
2. Unklarheiten als ADR/Decision/Protocol-Conflict dokumentieren. Bei normativer Wire-/Crypto-/State-Semantik NICHT eigenmächtig entscheiden; betroffenen Pfad blockieren, bis die zuständige Freeze-/Human-Gate-Entscheidung vorliegt.
3. Threat Model aktualisieren, bevor Netzwerk-/Krypto-Funktionalität erweitert wird.
4. Tests zuerst oder parallel schreiben.
5. Kleinste kohärente Implementierung bauen.
6. Static Analysis + Unit + Integration + Security Tests ausführen.
7. Selbst-Code-Review anhand Security Checklist.
8. Dokumentation aktualisieren.
9. Erst danach nächsten Meilenstein beginnen.


Agent darf keine Sicherheitsfunktion kommentarlos weglassen, um einen Test grün zu machen.
Agent darf keine Validierung “vorübergehend” deaktivieren.
Agent darf keine Signatur-/Proxyprüfung im Produktionspfad abschalten.
Agent darf keine Catch-all-Fehlerbehandlung verwenden, die Sicherheitsfehler in Erfolg verwandelt.


23. PHASEN


Phase 0: Workspace, Coding Standards, CI skeleton.
Phase 1: Protocol types, schemas, canonicalization, crypto primitives, known-answer tests.
Phase 2: Service Manifest generation/verification, revision chain, rotation, migration.
Phase 3: PHP storage and local CLI.
Phase 4: Tor transport and direct verification with strict safety controls.
Phase 5: Node HTTP read API and peer descriptors.
Phase 6: Pull sync, candidate queue, rate limiting, backoff.
Phase 7: Browser client index ingestion and cryptographic verification.
Phase 8: Local search, trust UX, pinning, warnings.
Phase 9: Multi-node E2E, adversarial tests, fuzzing, performance.
Phase 10: hardening, audit readiness, operator docs.
Phase 11: release candidate, clean-room deployment test, rollback drill.
Phase 12: production release only after all gates pass.


24. DEFINITION OF DONE – HIGH LEVEL


Produktionsreif bedeutet mindestens:
- Spezifikation versioniert und implementiert.
- Keine unspezifizierten kritischen Wire-Formate.
- Alle Security Invariants automatisiert getestet.
- Referenznode kann ohne Tor-Modifikation auf Standardserver laufen.
- Betreiber kann Publish/Mirror/Discover/Sync unabhängig schalten.
- Client sucht lokal.
- Identity continuity, key rotation und onion migration funktionieren.
- Peer-Lügen können keine fremden Service-Metadaten ohne direkte/kriptografische Verifikation überschreiben.
- Netzwerkinput ist strikt begrenzt.
- SSRF/DNS-leak Schutz getestet.
- Security- und Privacy-Dokumente vollständig.
- CI reproduzierbar grün.
- Dependency/SAST/secret scans sauber oder begründet.
- Backup/Restore und ausgewählte StorageBackend-/Schema-Migrationen getestet.
- RC in isolierter Umgebung frisch installierbar.
- Rollback dokumentiert.
- unabhängige Security Review Findings abgearbeitet.
- Nutzer-UI macht keine falschen Sicherheitsversprechen.


25. NAMENSREGEL


Der sichtbare Projekt-/Protokollname lautet immer:
Onion Service Discovery


Keine abgekürzten Produkt- oder Protokollnamen und keine Akronyme in UI, README-Titeln, Protokollnamen oder Benutzertexten. Öffentlich immer Onion Service Discovery ausschreiben.
Öffentliche Pfade und Slugs verwenden: onion-service-discovery


26. ERSTE ANWEISUNG AN DEN AGENTEN


Beginne NICHT mit UI oder Netzwerkcode.
Beginne mit:
- vollständiger Bestandsaufnahme dieser Spezifikationssammlung;
- ADR-001: Language/Runtime/Versionen;
- ADR-002: Canonical JSON und Signaturformat;
- ADR-003: Service- und Node-Key-Lifecycle;
- ADR-004: Manifest Schema und Duplicate-Key-Policy;
- ADR-005: Tor Transport / SOCKS Isolation;
- ADR-006: Local Index Storage;
- ADR-007: Sync Model und Conflict Resolution;
- Test Harness für Protokoll und Kryptographie.


Erst wenn diese Grundlagen implementiert und getestet sind, darf Netzwerk-Synchronisierung entstehen.


ENDE DES MASTER EXECUTION BRIE


27. AUDIT-PLUS COMPLETENESS STANDARD


Onion Service Discovery is not considered fully designed merely because the Protocol, PHP Node and Web Local Search Client are specified. The project additionally requires:


- formal state modeling and model checking for critical identity/sync semantics;
- an independent language-neutral conformance suite;
- a third-language minimal Protocol validator;
- a reproducible local multi-node Test Network and Chaos Lab;
- deterministic synthetic reference datasets;
- benchmark-derived capacity/resource defaults;
- RFC/IANA/publication readiness;
- WCAG-oriented accessibility and security-human-factors validation;
- operator/developer diagnostic tooling;
- complete record lifecycle and Protocol archaeology;
- architecture rationale and rejected-alternatives register;
- ecosystem adoption and maintainer-succession planning;
- a single public Protocol Handbook;
- a concise Five-Minute Service Operator Contract.


These artifacts are mandatory maturity evidence, not decorative documentation.


28. PROTOCOL STABLE BAR


Protocol Version 1 may be called Stable only when:
- normative bytes/schemas/crypto/state semantics are frozen;
- formal model has no unresolved safety counterexample;
- PHP, TypeScript and independent third-language implementation pass CORE conformance;
- API/sync profiles pass intended interoperability tests;
- RFC-style public Protocol Handbook exists;
- IANA/Well-Known plan is reviewed;
- Human Protocol/Crypto approval is documented.


29. PRODUCTION STABLE BAR


Production Stable additionally requires:
- deterministic Chaos Lab release profile;
- upgrade/backup/restore/failure evidence;
- WCAG 2.2 accessibility review;
- critical warning comprehension tests;
- clean operator/developer walkthroughs;
- measured supported scale;
- synthetic corpus;
- Protocol archaeology/history preservation;
- maintainer succession and no hidden central runtime dependency;
- all Definition-of-Done gates PASS.


30. ELEGANCE REQUIREMENT


Where two designs offer equivalent security, prefer the one that:
- has fewer moving parts;
- has clearer operator consequences;
- is easier to independently implement;
- can be tested offline;
- has fewer permissions/dependencies;
- preserves future compatibility;
- is explainable in one short rationale.


Complexity must be justified, not admired.


31. THIRD-PARTY REVIEW TEST


At maturity, a competent independent reviewer should be able to:
- understand the system from the public Protocol Handbook;
- run the conformance suite;
- reproduce the formal model;
- launch the Test Network;
- inspect the synthetic corpus;
- build from source;
- verify release evidence;
- install a Node;
- explain identity continuity and limitations
without access to undocumented maintainer knowledge.


32. FINAL DESIGN PHILOSOPHY


The project should feel deliberately boring in the best security-engineering sense:
explicit formats;
finite limits;
boring cryptography;
deterministic state transitions;
predictable failure;
small trust assumptions;
local privacy;
transparent limitations;
reproducible evidence;
no magical central service.




33. STORAGE BACKEND FREEDOM
Onion Service Discovery MUST NOT require SQLite or any particular database product.


The reference implementation uses a pluggable StorageBackend contract.


Required baseline adapters:
- FilesystemStore: no SQL/database server required;
- SQLiteStore.


Additional production adapters SHOULD include PostgreSQL and MySQL/MariaDB once their conformance suites pass.


The selected backend is an operator decision. Protocol semantics MUST remain identical across backends.


A publish-only operator MAY operate without a full discovery database when only signed Manifest generation/publication is needed.


34. MALICIOUS NODE ISOLATION
The browser architecture distinguishes:
- Client Host: serves executable Web Local Search code;
- Discovery Node: serves Protocol data only.


A deployment MAY combine both, but the stronger profile separates them.


With an honest Client Host, a malicious Discovery Node cannot alter executable search code and therefore cannot directly force query exfiltration through Node-supplied JavaScript.


WEB-SEPARATED-V1 SHOULD combine valid data from multiple distinct selected Discovery Node identities/source groups, preserve conflicts, reject invalid signatures, track checkpoint ancestry/rollback, and compare signed Node checkpoint chains. Distinct Node keys do not prove independent operators.


No majority vote becomes Service identity truth.


35. FUNDAMENTAL WEB TRUST BOUNDARY
A malicious Client Host can still modify the executable Web Client.


Without a locally installed/native/browser-integrated trust anchor, this cannot be fully eliminated by JavaScript served from that same origin.


The project mitigates this with:
- reproducible static bundles;
- strict CSP;
- no remote code;
- release hashes/provenance;
- multiple Client Hosts that are actually independently operated where this is known/documented; cryptographic host count alone is not proof of operator independence;
- self-hosted Client Host option;
- explicit non-claims.


The strongest browser-only reference deployment is a self-hosted/trusted Client Host plus multiple distinct selected data-node identities/source groups, with known operator diversity represented only where it is actually documented.




36. FINAL SECURITY CLOSURE
The preferred Service Identity key lifecycle is now offline-first.
The public Onion/PHP host SHOULD normally receive only signed Service records, not the long-lived Service private key.


Online signing remains possible only through an explicit SignerProvider security profile.


37. SOFTWARE UPDATE TRUST
Production release trust MUST NOT reduce to “GitHub says this is the latest file”.
Use a mature signed update-metadata design with rollback/freeze protection and protected root trust.


38. FUTURE-PROOFING WITHOUT AMBIGUITY
Protocol v1 stays strict:
Tor v3 addresses;
Ed25519;
SHA-256;
RFC 8785.


Future Tor address/crypto changes create a reviewed new Protocol migration rather than permissive negotiation.


39. PRE-IMPLEMENTATION STATUS
Once the Final Pre-Implementation Closure Audit is PASS, Onion Service Discovery is considered sufficiently specified to begin implementation.


From then on, additional preparation is justified only by concrete evidence or findings.




40. PLUG-AND-PLAY OPERATOR PRINCIPLE
A normal Onion Service operator who only wants to be discoverable must not need to understand the internal peer protocol, storage engine or cryptographic byte format.


The expected journey is:
install;
configure one clear file;
install a signed Manifest;
open the operator's own Onion Service in Tor Browser;
visit /onion-service-discovery/status/;
see truthful progress from local readiness to external observation/searchability.


The dashboard distinguishes:
local configuration;
Tor self-verification;
bootstrap connectivity;
candidate announcement;
external Direct Observation;
actual sampled searchability.


“Connected” and “Discoverable” are never treated as synonyms.


41. SELF-SERVICE DISCOVERY
Once publish is explicitly enabled and the signed Manifest self-verifies, the reference implementation SHOULD perform bounded automatic self-announcement to selected Discovery Nodes.


Remote Nodes never trust the announcement itself; they fetch and verify the Service independently.


No recurring central registration is introduced.


42. PRODUCT QUALITY BAR
The system is not considered polished if an ordinary operator must read protocol documentation just to answer whether their current Service revision can be found.


The status dashboard and CLI must provide the answer and remediation directly.




43. VERIFIABLE DISCOVERABILITY
The product must allow an operator to independently verify that their exact current Service state is present in the actual active index delivered by external Discovery Nodes.


The proof key is:
Service Identity
+ canonical Onion
+ Manifest revision
+ Manifest hash.


A Node's “listed” assertion is not authoritative.


44. SIGNED INDEX STATE
Discovery Nodes sign active-index checkpoints.
The operator/client validates the checkpoint and locally verifies exact membership.


For scalable deployments, a deterministic Merkle root and compact inclusion proof is preferred once its cross-language specification and vectors are frozen.


45. MULTI-SOURCE PROPAGATION EVIDENCE
The dashboard can truthfully report:
“Current revision is included in the signed active index of 3 of 4 checked Discovery Nodes.”


It must never convert that into global network completeness or Service trust.


46. EQUIVOCATION
Conflicting Node-signed checkpoints are retained as cryptographic source-integrity evidence.


This is separate from Service identity forks and does not invalidate valid Service-signed records from other sources.




47. SYBIL-RESISTANT GROWTH
Network growth improves resilience only when additional participants provide independently useful replication/auditing.


The Protocol MUST remain safe if one adversary creates arbitrarily many Onion Services, Service identities, Node identities or witness identities.


Raw count has zero authority.


48. EVIDENCE, NOT VOTING
Service identity truth derives from Service cryptography/direct binding evidence.


Node misconduct derives from locally verifiable signed contradictions/proofs.


Neither is decided by majority vote.


49. BOUNDED PARTICIPATION
Every untrusted growth path has finite local cost:
peer admission;
candidate announcement;
candidate verification;
observation ingestion;
witness/checkpoint gossip.


New identities cannot bypass global work budgets.


50. SEARCH FARM RESILIENCE
Creating thousands of matching Onion Services cannot produce trust or automatically dominate relevance.


Local search may cluster near-identical results for usability while preserving each distinct identity.


51. INDEPENDENCE NON-CLAIM
Different public keys are only distinct identities.


The project does not cryptographically claim that they are distinct humans/operators.




52. CATEGORY REGISTRY
Protocol v1 uses a fixed broad category registry rather than arbitrary category spellings.


adult is an ordinary content category.


Categories describe content/function only and never produce trust/safety/legal status.


Specific descriptors belong in separate tags rather than proliferating core categories.


53. DEFENSIVE PROGRAMMING
All attacker-controlled values are treated as typed untrusted data crossing explicit validation boundaries.


The reference implementation uses:
positive field validation;
hard length/count/depth ceilings;
strict encoding/type rules;
context-safe output encoding;
parameterized/structured sink APIs;
bounded network/storage/CPU work;
fail-closed Protocol state transitions.


54. EXPLOIT-CLASS COVERAGE
Production readiness requires explicit prevention/tests for applicable known classes including injection, XSS, SSRF, path traversal, authorization, CSRF, deserialization, resource exhaustion, race conditions, request/cache ambiguity, supply-chain compromise and exceptional-condition failures.


OWASP/CWE lists are coverage inputs, not a claim that security can be frozen forever.


55. SECURITY EVOLUTION
A new material exploit class found after release triggers:
applicability review;
control/test update;
regression fixture;
security advisory/patch where needed.


The project must remain maintainable rather than claim permanent invulnerability.
