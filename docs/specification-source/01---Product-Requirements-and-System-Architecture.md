# 01 - Product Requirements and System Architecture

Source: https://docs.google.com/document/d/1A8nFe3xFiF4w3eYQsAkLoXhFovKb07tXo5rzTfP7YFY/edit?usp=drivesdk

Source modified: 2026-10-12T19:28:02.138Z

---

ONION SERVICE DISCOVERY
PRODUKTANFORDERUNGEN UND SYSTEMARCHITEKTUR


1. Problemstellung
Öffentliche Onion Services sind schwer auffindbar, weil v3-Onion-Adressen kryptografisch stark, aber für Menschen schwer merkbar und nicht intuitiv durchsuchbar sind. Bestehende Linklisten leiden häufig unter veralteten Einträgen, zentraler Kontrolle, manueller Pflege und fehlender Identitätskontinuität.


2. Produktziel
Onion Service Discovery ermöglicht freiwillige, selbstverwaltete Auffindbarkeit. Betreiber veröffentlichen standardisierte Metadaten. Unabhängige Discovery-Nodes spiegeln, beobachten und verifizieren diese Daten. Clients halten einen lokalen Index und führen Suchanfragen lokal aus.


3. Nicht-Ziele
Das Produkt ist ausdrücklich:
- keine allgemeine Volltext-Suchmaschine über beliebige Seiteninhalte;
- kein Ersatz für Tor;
- keine zentrale Namensregistrierungsstelle;
- keine Garantie, dass ein Service seriös, legal oder sicher ist;
- keine globale Moderationsautorität;
- kein System zur Erfassung privater oder nicht freiwillig veröffentlichter Onion Services;
- keine Werbe-/Tracking-Plattform;
- kein Nutzerprofiling-System.


4. Rollen
4.1 Nutzer
Lädt Indexdaten, prüft ihre Integrität, hält einen lokalen Index und sucht lokal.


4.2 Service-Betreiber
Veröffentlicht das Discovery-Manifest. Kann zusätzlich Mirror-, Discovery- und Sync-Funktionen aktivieren.


4.3 Discovery-Node
Synchronisiert Daten, verifiziert Kandidaten kontrolliert, signiert eigene Beobachtungen und stellt read-only Indexdaten bereit.


4.4 Projektmaintainer
Pflegt Spezifikation, Referenzimplementierungen, Testvektoren und Releaseprozesse, ist aber keine zentrale Laufzeitinstanz.


5. Operator-Schalter
publish_service
Eigener Onion Service darf in Onion Service Discovery erscheinen.


mirror_directory
Node darf verifizierte/prüfbare Discovery-Daten lokal halten und anderen bereitstellen.


discover_services
Node darf neue Kandidaten aus zugelassenen Quellen aufnehmen und kontrolliert verifizieren.


sync_with_peers
Node darf Zustände/Deltas von anderen Discovery-Nodes abrufen.


Alle vier Schalter sind unabhängig. Sichere Defaults sind zu dokumentieren.


6. High-Level-Architektur
Service Publisher
-> standardisiertes Manifest
-> Discovery Node
<-> weitere Discovery Nodes
-> signierte oder inhaltsadressierte Indexdaten
-> Web Local Search Client
-> browser-lokaler In-Memory-Index; optional origin-lokales IndexedDB/Cache Storage ohne Persistenzgarantie
-> lokale Suche


7. Trust Boundaries
Boundary A: Tor-Prozess und Anwendung
Die Anwendung darf Tor als Transport verwenden, aber keine privaten Hidden-Service-Schlüssel lesen.


Boundary B: Node und fremder Node
Alle Peer-Daten gelten als potenziell falsch, veraltet oder manipuliert.


Boundary C: Node und fremder Onion Service
Die Zieladresse, der Manifestinhalt, Signaturen und Revisionen müssen zusammenpassen.


Boundary D: Client und Node
Ein Node ist Datenquelle, keine Autorität für Wahrheit oder Seriosität.


Boundary E: UI und Nutzer
Die Oberfläche muss klar zwischen “Identität technisch bestätigt” und “Inhalt vertrauenswürdig” unterscheiden.


8. Zentrale Datenflüsse
8.1 Veröffentlichung
Betreiber konfiguriert Manifest -> Webserver liefert es unter dem festgelegten Well-Known-Pfad -> Node entdeckt Adresse -> Node ruft Manifest kontrolliert über Tor ab -> Node validiert und speichert Beobachtung.


8.2 Synchronisierung
Node A fragt Node B nach State/Root -> bei Gleichheit Ende -> bei Unterschied Delta/Chunks abrufen -> lokal validieren -> Daten einordnen -> direkte Verifikation nur nach Queue-/Budget-Regeln planen.


8.3 Client-Aktualisierung
Web Local Search lädt vom ausgewählten Discovery Node einen generischen Snapshot/Index -> validiert Struktur/Integrität -> baut den Suchindex lokal im Browser auf. Persistenter Browser-Speicher ist optional; mehrere Nodes können durch bewussten Wechsel des Nutzers genutzt werden.


8.4 Suche
Nutzer gibt Suchbegriff ein -> ausschließlich lokaler Index -> Ergebnisliste -> Detailansicht -> optional Navigation zum Onion Service.
Der Suchbegriff wird nicht an Discovery-Nodes übertragen.


9. Datenklassen
Authoritative Self-Published:
- Service Manifest.


Cryptographically Derived:
- Manifest Hash;
- Manifest Signature Status;
- Revision Chain Status;
- Key Rotation Status;
- Migration Status.


Locally Observed:
- first_seen;
- last_directly_seen;
- last_verification_result;
- stale state.


Peer-Reported:
- peer observations;
- peer last-seen information;
- advertised peer descriptors.


User-Local:
- pinned identities;
- favorites;
- selected moderation sources;
- local search settings.


10. Zustandsmodell eines Services
UNKNOWN
CANDIDATE
QUEUED
DIRECT_CHECK_PENDING
VERIFIED_CURRENT
VERIFIED_STALE
TEMPORARILY_UNREACHABLE
LONG_UNSEEN
MIGRATED
IDENTITY_CHANGED
FORKED
QUARANTINED
REJECTED


Übergänge müssen als explizite State Machine modelliert und automatisiert getestet werden.


11. Konsistenzmodell
Eventual Consistency ist zulässig.
Nicht zulässig ist blindes Last-Write-Wins für Identitätsdaten.


Regeln:
- höhere gültige Revision ersetzt niedrigere;
- gleiche Revision plus gleicher Hash ist identisch;
- gleiche Revision plus anderer Hash ist Fork;
- neuer Service-Key ist nur nach gültigem Rotation Record akzeptabel;
- neue Onion-Adresse ist nur als kontinuierliche Identität akzeptabel, wenn der definierte Migrationsnachweis gültig ist;
- Peer-Zeitstempel allein entscheiden niemals Identität.


12. Ausfallverhalten
Node offline:
- andere Nodes funktionieren weiter;
- Client behält den Index mindestens für die aktive Sitzung lokal; persistenter origin-lokaler Cache ist optional.


Service offline:
- Eintrag wird nicht sofort gelöscht;
- last_directly_seen bleibt erhalten;
- Recheck folgt Backoff.


Seed offline:
- andere Seeds und bereits gelernte Peers verwenden.


DB beschädigt:
- Node geht in klaren Degraded-State;
- keine stillen reparierten/vermuteten Identitätsdaten;
- Restore/Resync möglich.


Signatur-/Revisionskonflikt:
- Quarantäne;
- keine stille Überschreibung.


13. Bootstrap
Nodes enthalten Seed-/Peer-Bootstrap; der Web Local Search Client benötigt für die Baseline keinen eigenen Seed-Pool, da er den Index des bewusst besuchten Nodes nutzt.
Seeds:
- sind keine Root Authorities;
- dürfen ausfallen;
- werden nach erfolgreichem Bootstrap durch gelernte Peers ergänzt;
- können durch Softwareupdates ersetzt/ergänzt werden;
- können manuell ergänzt werden.


14. Discovery-Quellen
Zulässig:
- explizite Peer-Informationen;
- verifizierte Service-Manifeste mit optionalen Verweisen;
- öffentliche Kandidaten-Ankündigung;
- optionales Crawling öffentlicher Links durch dedizierte Nodes, sofern separat aktiviert.


Nicht zulässig:
- stilles Melden normaler Browserbesuche;
- automatisches Aufnehmen eines Kandidaten als verifiziert;
- unbegrenzte Kaskaden von Folgeabrufen.


15. Logisches Persistenz-/Storage-Modell auf hoher Ebene
Empfohlene Repositories/Tabellen:
service_identities
service_revisions
service_manifests
key_rotations
service_migrations
direct_observations
peer_assertions
nodes
peer_sync_state
candidates
quarantine
moderation_signals
snapshots_or_chunks
schema_migrations


16. Öffentliche Node-Schnittstellen
Prinzip: read-mostly/read-only.
Die öffentliche API liefert:
- Node Descriptor;
- Protocol/Capability State;
- Peer Descriptors in begrenzter Form;
- Service Records;
- Snapshot/Delta-Metadaten;
- Chunks/Deltas.


Nicht anbieten:
- generische “fetch arbitrary URL”-Funktion;
- Remote-SQL;
- ungeschützte Admin-Funktionen;
- beliebige Remote-Konfigurationsänderungen.


17. Konfiguration
Konfiguration muss schemavalidiert sein.
Kritische unbekannte Optionen führen zu Startup-Fehler.
Secrets und private Anwendungsschlüssel liegen außerhalb des Webroots.


18. Logging und Metriken
Erlaubte technische Metriken können umfassen:
- Candidate Queue Größe;
- Sync-Alter;
- Datenbankgröße;
- Anzahl bekannter Nodes;
- Erfolgs-/Fehlerkategorien;
- Rate-Limit-Drops.


Nicht loggen:
- Suchbegriffe;
- Nutzer-Browserverläufe;
- unnötige identifizierende Browser-/Clientmerkmale sowie queryabhängige Netzwerkmerkmale.


19. Erweiterbarkeit
- explizite Protokollversion;
- Breaking Change => neue Protokollversion;
- keine stillen Semantikänderungen;
- Capability Negotiation nur mit klarer Spezifikation;
- optionale neue Felder dürfen alte Implementierungen nicht zu unsicheren Annahmen bringen.


20. Architektur-Deliverables des Coding-Agenten
Vor Implementierung des Netzwerks muss der Agent im Code-Repository erzeugen:
- Komponentenübersicht;
- Datenflussdiagramm;
- Trust-Boundary-Diagramm;
- State-Machine-Diagramme;
- Datenmodell;
- ADR-Index;
- Abhängigkeitsdiagramm;
- Security-Invariants-Liste.


Jede wesentliche Abweichung von diesem Dokument erfordert ein ADR mit Motivation, Risiken, Alternativen und Auswirkungen 


21. CLIENT ARCHITECTURE DECISION — WEB FIRST
The separate browser extension has been removed from the product architecture.


Primary client:
Web Local Search served directly by participating Discovery Nodes.


Baseline data flow:
User visits selected Node
-> Node serves static Web Client
-> browser downloads generic snapshot/chunks from same origin
-> browser validates/builds in-memory index
-> user queries local index
-> query does not leave the browser in the reference local mode.


Optional fallback:
No-JavaScript Server Search via POST to the selected Node with explicit disclosure that the Node receives the query.


Storage:
Browser persistence is optional; correctness does not depend on IndexedDB surviving across sessions.


Trust boundary:
the serving Node controls Web executable code. A malicious/compromised Node can violate local-query behavior or misrepresent UI. Reproducible static assets, CSP, hashes/provenance, a self-hosted/trusted Client Host option, distinct selected data sources/source groups and public disclosure mitigate/audit this risk but do not create an independent browser trust root.


Mobile:
Android Tor Browser is a first-class supported Web Client target.


22. STORAGE RUNTIME DECISION
SQLiteStore is one required reference adapter alongside FilesystemStore. PHP installations are not assumed to include pdo_sqlite, and the Protocol is not defined by SQLite semantics.


init/doctor/security:check MUST validate only the selected StorageBackend. pdo_sqlite is required and checked only when SQLiteStore is selected; FilesystemStore MUST operate without it.


The Protocol remains storage-backend independent.




23. STORAGE ABSTRACTION
Storage is an implementation concern, not Protocol truth.


Reference architecture:
Protocol Core
-> StorageBackend interface
   -> FilesystemStore
   -> SQLiteStore
   -> PostgreSQLStore
   -> MySQL/MariaDBStore
   -> future adapters.


FilesystemStore is a first-class zero-SQL option with:
content-addressed immutable objects;
atomic replace;
append-only journal;
single-writer locking;
crash recovery;
integrity verification;
bounded supported scale.


24. CLIENT HOST / DATA NODE SEPARATION
The Web Local Search architecture supports two independent roles.


Client Host:
serves audited static HTML/CSS/JavaScript.


Discovery Node:
serves strict Protocol data only.


The browser MAY use a trusted/self-hosted Client Host and retrieve generic index data from several Discovery Nodes.


This reduces the impact of a malicious data Node:
it can censor/omit/stale/equivocate but cannot inject executable code into the separated client.


The Client Host remains a Web trust boundary.


25. MULTI-SOURCE DATA MODEL
The client SHOULD support a union of valid records from multiple configured Nodes.


Rules:
- validate every signed record;
- keep source attribution;
- preserve disagreements;
- track source freshness;
- never use raw source count as identity truth;
- never delete valid evidence merely because one Node omits it.


Node-signed snapshot/checkpoint hashes and future gossip/witness evidence support equivocation detection.
