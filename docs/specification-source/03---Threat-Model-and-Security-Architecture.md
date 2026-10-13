# 03 - Threat Model and Security Architecture

Source: https://docs.google.com/document/d/12wZrysCvp9G7pzYsHw8w8rHQ_bxLxGEpQ-58SjaitDo/edit?usp=drivesdk

Source modified: 2026-10-12T19:29:06.451Z

---

ONION SERVICE DISCOVERY
THREAT MODEL UND SECURITY ARCHITECTURE


Zweck
Dieses Dokument beschreibt Angreifer, Schutzgüter, Vertrauensgrenzen, Sicherheitsinvarianten, Fehlermodi und verpflichtende Gegenmaßnahmen. Es ist für Implementierung und Review bindend.


1. Sicherheitsziele
Onion Service Discovery soll:
- Service-Metadaten eindeutig an die tatsächlich angesprochene Onion-Adresse koppeln;
- Anwendungsidentität über Revisionen, Schlüsselwechsel und Onion-Migrationen nachvollziehbar machen;
- Manipulation durch fremde Nodes erkennbar machen;
- Suchanfragen lokal halten;
- zentrale Ausfall- und Kontrollpunkte vermeiden;
- Betreiber nicht dazu zwingen, private Tor-Schlüssel an die Anwendung zu geben;
- Netzwerk-, Speicher- und Rechenressourcen gegen Missbrauch begrenzen;
- falsche Sicherheitsversprechen in der Benutzeroberfläche vermeiden.


2. Nicht garantierte Eigenschaften
Das System garantiert NICHT:
- dass ein technisch verifizierter Service seriös ist;
- dass ein Service frei von Schadsoftware oder Betrug ist;
- dass ein Anzeigename einer Marke/Organisation rechtmäßig gehört;
- dass viele Beobachter unabhängig voneinander sind;
- dass ein momentan offline Service kompromittiert oder endgültig verschwunden ist;
- dass ein Clearnet-Link rechtliche Identität beweist.


3. Schutzgüter
Hoch:
- private Service Identity Keys;
- private Node Identity Keys;
- Integrität von Manifesten und Identitätsketten;
- lokale Nutzer-Pins/Favoriten;
- Suchprivatsphäre;
- Release-Supply-Chain;
- Konfigurationssecrets.


Mittel:
- lokale Discovery-Datenbank;
- Peer-Liste;
- Liveness-/Observation-Historie;
- Moderationsquellen.


4. Angreifermodelle
A. Bösartiger Service-Betreiber
Kann beliebige eigene Metadaten publizieren, Namen imitieren, häufig Identitäten erzeugen und andere Kandidaten empfehlen.


B. Bösartiger Discovery-Node
Kann veraltete, erfundene oder selektiv ausgelieferte Daten senden, Peers verschweigen oder unterschiedliche Clients unterschiedlich bedienen.


C. Sybil-Angreifer
Betreibt viele Nodes oder Services, um Häufigkeit/Mehrheitslogik zu manipulieren.


D. Netzwerk-/Transportfehler
Verbindungen schlagen fehl, werden unterbrochen oder liefern unvollständige Daten.


E. Kompromittierter Webserver
Anwendungscode oder Service Identity Key kann betroffen sein, während der Tor-Hidden-Service-Key getrennt bleiben soll.


F. Kompromittierter Client
Lokale Pins/Index können manipuliert werden; außerhalb des Schutzbereichs des Netzprotokolls, aber Datenintegrität soll Angriffe begrenzen.


G. Supply-Chain-Angreifer
Versucht Dependencies, Build-Pipeline oder Release-Artefakte zu manipulieren.


5. Kerninvarianten
Invariant 1: Die Anwendung liest niemals den privaten Tor Hidden-Service-Key.
Invariant 2: Ein direkt verifiziertes Manifest muss von derselben Onion-Adresse stammen, die es selbst deklariert.
Invariant 3: Peer-Empfang allein erzeugt keinen Status “direkt verifiziert”.
Invariant 4: Alte Revisionen überschreiben niemals automatisch neuere bekannte Revisionen.
Invariant 5: Gleich hohe widersprüchliche Revisionen werden als Fork behandelt.
Invariant 6: Service-Key-Wechsel benötigt kryptografischen Kontinuitätsnachweis.
Invariant 7: Onion-Migration benötigt kryptografischen Kontinuitätsnachweis.
Invariant 8: Suchbegriffe werden nicht an Discovery-Nodes gesendet.
Invariant 9: Moderationsstatus verändert nicht die kryptografische Identitätswahrheit.
Invariant 10: Kein Node ist Root Authority.
Invariant 11: Unbegrenzte Ressourcenverwendung durch Netzwerkinput ist verboten.
Invariant 12: Unsicherheit wird sichtbar dargestellt; sie wird nicht in “verified” umgedeutet.


6. Schlüsseltrennung
Tor Hidden-Service Key:
- ausschließlich Tor;
- niemals Anwendung;
- niemals Export für Onion Service Discovery.


Service Identity Key:
- signiert Manifest, Rotation und Migration;
- private Hälfte nur Betreiber;
- außerhalb Webroot;
- Backup- und Rotation-Runbook.


Node Identity Key:
- signiert Node Descriptor und Observations;
- getrennt vom Service Key;
- Kompromittierung darf Service-Identität nicht automatisch kompromittieren.


7. Manifest-Angriffsfläche
Gefahren:
- manipulierte Felder;
- Mehrdeutigkeiten in JSON;
- Duplicate Keys;
- übergroße Dokumente;
- Unicode-Verwechslung;
- ungültige Schlüssel;
- Signatur über anderes Byteformat als Verifier erwartet;
- Mismatch zwischen Host und onion-Feld.


Pflichtmaßnahmen:
- byte size limit vor vollständiger Verarbeitung;
- strikter Parser;
- Schema;
- Canonicalization-Testvektoren;
- Duplicate-Key-Policy;
- Normalisierung nur dort, wo Spezifikation sie verlangt;
- Signaturprüfung nach Schema-/Binding-Prüfung;
- sichtbarer Name nie als eindeutiger Schlüssel.


8. Replay/Fork-Angriffe
Replay:
Peer liefert alte, aber gültig signierte Revision.
Abwehr:
bekannte höchste gültige Revision und Hash-Kette speichern.


Fork:
zwei unterschiedliche gültig signierte Inhalte mit gleicher Revision.
Abwehr:
beide Beweise erhalten, Status FORKED/QUARANTINED, keine automatische Auflösung per Timestamp.


9. Key-Rotation-Angriffe
Gefahr:
Angreifer präsentiert neuen Schlüssel und behauptet legitime Rotation.
Abwehr:
alte bekannte Identität muss Rotation autorisieren; fehlender Nachweis => Identity Changed, nicht Continuity.


10. Onion-Migrations-Angriffe
Gefahr:
Angreifer behauptet, eine bekannte Identität sei auf seine Onion-Adresse umgezogen.
Abwehr:
Service Identity Signature auf Migration; zusätzliche direkte Bestätigung durch alte/neue Onion soweit vorhanden; UI unterscheidet Kontinuitätsgrade.


11. Peer-Manipulation
Gefahren:
- falsche Peer-Liste;
- selektive Daten;
- stale snapshots;
- erfundene Observations;
- unterschiedliche Sicht für unterschiedliche Clients.


Abwehr:
- Node Descriptor signiert;
- Peer-Daten nur als Assertions;
- mehrere unabhängige Quellen möglich;
- lokale Validierung;
- content hashes;
- optional Transparenz-/Merkle-Mechanismen;
- keine “Mehrheit = Wahrheit”-Regel.


12. Sybil
Viele Nodes dürfen nicht automatisch hohen Vertrauenswert erzeugen.
Pflicht:
- keine lineare Vertrauensgewichtung nach Node-Anzahl;
- Seed/Observer-Quellen transparent;
- technische Direct Verification bevorzugen;
- Nutzer kann ausgewählte Observer getrennt behandeln;
- Reputation, falls später eingeführt, muss eigenes Threat Model erhalten.


13. Candidate-/Resource-Abuse
Gefahr:
Massenhaft Kandidaten zwingen Nodes zu Netzwerk-/CPU-/Storage-Arbeit.
Pflicht:
- maximale Queue;
- maximale Kandidaten je Quelle und Zeit;
- dedup;
- Priorisierung;
- exponentielles Backoff;
- Jitter;
- maximale parallele Verifikationen;
- harte Response-Limits;
- Retention;
- optional zusätzliche Kostenschwelle für Erstankündigungen nach Review.


14. SSRF-/Zielvalidierung
Die Implementierung darf keinen generischen Remote-Fetch-Dienst bilden.
Kandidaten für direkte Onion-Verifikation:
- müssen v3-Onion-Format erfüllen;
- werden in normalisierte Hostform gebracht;
- dürfen keine beliebigen Schemes, lokalen Hostnamen, IP-Literale oder Dateipfade enthalten;
- Proxy-/Transportparameter dürfen niemals aus fremden Daten stammen.


15. Transport-Sicherheitsinvarianten
- .onion über konfigurierte Tor-SOCKS-Schnittstelle;
- Hostname-Auflösung im Tor-Pfad;
- kein stiller Direkt-Fallback;
- harte Connect-/Total-Timeouts;
- begrenzte Responsegröße;
- begrenzte Redirects;
- für Onion-Binding-Verifikation keine Cross-Host-Weiterleitung;
- Fehler = keine Verifikation.


16. Browser-Privatsphäre
Gefahr:
Discovery wird selbst zur Beobachtungsquelle für Nutzerinteressen.
Pflicht:
- Query bleibt lokal;
- Browser meldet nicht automatisch besuchte Onion-Adressen;
- keine Drittanbieter-Telemetrie;
- keine eindeutige Nutzer-ID;
- keine Remote-Fonts/Skripte;
- Netzwerkupdates möglichst unabhängig von konkreten Suchvorgängen planen.


17. Fingerprinting
Eine separat installierte Tor-Browser-Erweiterung kann die Gleichförmigkeit der Nutzerpopulation beeinflussen.
Pflicht:
- Dokumentation weist darauf hin;
- keine Behauptung, dass ein vom Discovery Node ausgelieferter Web Client gegen einen bösartigen Serving Origin automatisch vertrauenswürdig oder query-privat ist;
- minimaler Permission-/Feature-Footprint;
- langfristig Protokoll unabhängig vom konkreten Client halten.


18. UI-/Phishing-Risiken
Gefahren:
- gleicher Anzeigename;
- Unicode Confusables;
- imitierte Beschreibung;
- gefälschte “verified” Wahrnehmung.


Pflicht-UX:
- vollständige Onion-Adresse zugänglich;
- stable Logical Service Identifier and current Service Identity fingerprint/key-epoch details zugänglich;
- gepinnte bekannte Identität sichtbar;
- Abweichung von Pin deutlich warnen;
- technische Labels präzise, z.B. “Manifest direkt von dieser Onion-Adresse geprüft”;
- keine pauschale grüne “SAFE”-Badge;
- ähnliche Namen als potenziell unterschiedliche Identitäten behandeln.


19. Datenbankrisiken
- parametrisierte Queries;
- Transaktionen;
- Schema Constraints;
- Migrationen;
- Crash Recovery;
- Quarantäne;
- maximale Größe;
- Backups;
- Restore-Test;
- keine Netzwerkdaten als SQL/Dateipfad interpretieren.


20. Webserver-Risiken
- Secrets außerhalb Webroot;
- Adminfunktionen lokal/CLI;
- keine Remote-Code- oder Template-Ausführung aus Metadaten;
- Output Encoding;
- CSP für HTML;
- minimale CORS-Policy;
- sichere Header;
- keine Directory Listings von Secrets;
- keine Debug-Seiten in Production.


21. Supply Chain
Pflicht:
- gelockte Dependencies;
- minimale Bibliothekszahl;
- Dependency Scan;
- Secret Scan;
- SBOM;
- reproduzierbare Build-/Package-Dokumentation;
- Release Checksums;
- signierte Releases soweit praktikabel;
- Review von Third-Party Build Actions.


22. Logging
Standardmäßig minimal.
Nicht in Logs:
- Suchbegriffe;
- private Schlüssel;
- Auth-Secrets;
- vollständige Request-Dumps mit sensitiven Headern;
- Browserverlauf.
Fehlerlogs kategorisieren, ohne untrusted Text ungefiltert als strukturierte Logfelder zu übernehmen.


23. Moderationsmissbrauch
Gefahr:
Fake-Reports werden benutzt, um Konkurrenz zu verdrängen.
Abwehr:
- Moderation getrennt von Identity;
- Quelle/Provenienz;
- optional signierte Moderationslisten;
- lokale Auswahl;
- Reports verändern nicht kryptografische Verifikationsfelder.


24. Clearnet-Proofs
Falls unterstützt:
- eigener Status “clearnet_link_verified”;
- HTTPS-Domain bestätigt Onion-Verknüpfung nach definierter Methode;
- bedeutet nur gegenseitige technische Verknüpfung, nicht Seriosität;
- Fehler beeinflusst nicht Onion-eigene Signaturgültigkeit.


25. Availability
Das System soll Ausfall einzelner Nodes tolerieren.
Pflicht:
- mehrere Seeds;
- gelernte Peers;
- lokaler Cache;
- keine zentrale Session;
- Stale-Daten klar markieren statt Index vollständig zu löschen.


26. Incident-Klassen
KEY_COMPROMISE_SERVICE
KEY_COMPROMISE_NODE
PROTOCOL_VULNERABILITY
CLIENT_PRIVACY_BUG
SYNC_INTEGRITY_BUG
SUPPLY_CHAIN_INCIDENT
MALICIOUS_RELEASE
STORAGE_CORRUPTION
SEED_OUTAGE
MASS_SPAM_EVENT


Für jede Klasse muss vor Production ein Runbook existieren.


27. Security-Review-Fragen
Vor jedem Major Release:
- Hat sich eine Trust Boundary geändert?
- Gibt es neue Netzwerkinputs?
- Gibt es neue Parser?
- Gibt es neue kryptografische Semantik?
- Gibt es neue persistente Identifikatoren?
- Gibt es neue Berechtigungen im Browser?
- Gibt es neue Remote-Dependencies?
- Haben sich Limits erhöht?
- Können neue Features Queries/Browsing korrelieren?
- Gibt es neue Adminflächen?


28. Security-Testpflichten
- negative Manifesttests;
- Signaturmanipulation;
- Revision Replay;
- Fork;
- Key Rotation;
- Migration;
- oversized inputs;
- parser ambiguity;
- Unicode display;
- Queue exhaustion;
- timeout behavior;
- rate limits;
- storage corruption;
- dependency/static analysis;
- Web Client CSP/XSS/origin-trust/local-query/server-fallback privacy tests;
- clean install under secure defaults.


29. Security-Ausnahmeprozess
Wenn eine Pflichtmaßnahme temporär nicht umgesetzt werden kann:
- als Security Exception dokumentieren;
- Risiko, Scope und Ablaufdatum nennen;
- Workaround;
- Owner;
- menschliche Freigabe.
Keine stillen Ausnahmen im Code.


30. Erfolgsdefinition
Sicherheitsarchitektur gilt erst als umgesetzt, wenn jede Invariante mindestens einen au


31. WEB LOCAL SEARCH TRUST BOUNDARY — ARCHITECTURE REVISION
The separate Web Local Search client has been removed from Onion Service Discovery.


Primary client:
a static Web Local Search application served by participating Discovery Nodes and usable directly in Tor Browser on desktop and Android.


Security benefit:
- no browser-extension permissions;
- no content scripts;
- no Native Messaging;
- no extra browser-extension fingerprinting delta; Web-origin/browser-feature differences are separately reviewed;
- no extension update/signing channel.


New threat:
the serving Node controls the executable HTML/JavaScript. A malicious or compromised Node can serve code that leaks queries or lies in the UI.


Mandatory mitigations:
- reference client is static and reproducibly built;
- no remote runtime dependencies;
- strict CSP;
- published asset hashes/provenance;
- operator self-check verifies deployed assets;
- local-mode query instrumentation proves the reference build emits no query network traffic;
- multiple distinct Nodes/source groups can serve/reference the client or data; distinct keys do not prove independent operators;
- public documentation states that the serving origin is a trust boundary.


No document may claim that a remote Web page can cryptographically guarantee its own honesty against the origin serving it.


32. NO-JAVASCRIPT SERVER SEARCH THREAT
An optional server-rendered fallback may accept a query when JavaScript is unavailable.


Privacy consequence:
the query is intentionally transmitted to that Node.


Required:
- explicit pre-submit disclosure;
- POST, not query-in-URL;
- no query logging;
- no analytics/search-history;
- finite query/work/result limits;
- no live discovery/network requests triggered by the query;
- results only from local verified/derived index;
- no cookie/account requirement.


33. WEB CLIENT SECURITY TESTS
Replace browser-permission tests with:
- CSP tests;
- XSS/DOM-injection tests;
- malicious metadata rendering tests;
- local-query network instrumentation;
- no remote asset tests;
- result-navigation scheme validation;
- server-search query/logging tests;
- Android/Desktop Tor Browser compatibility;
- storage-unavailable behavior.


34. STORAGE PRIVACY
Browser persistence is optional and origin-local.
The client must remain functional if IndexedDB/Cache Storage is unavailable or cleared.
No unique installation identifier or persistent user account is introduced.
tomatisierten Test oder einen expliziten Review-Gate besitzt und eine Traceability-Matrix von Anforderung -> Code -> Test -> Dokumentation existiert


35. MALICIOUS DISCOVERY NODE — REDUCED TRUST MODEL
Under the separated Client Host architecture, a malicious Discovery Node is treated as an untrusted data source.


It may:
- omit Services;
- replay stale records;
- equivocate;
- flood invalid records;
- bias its own observations;
- refuse service.


It must not be able to:
- forge Service signatures;
- rewrite valid continuity;
- inject executable client code;
- learn the local query from a query-independent data request.


36. CLIENT HOST THREAT
The Client Host is more privileged than a data Node because it supplies executable code.


Compromise can:
- exfiltrate local queries;
- falsify UI;
- disable signature checks;
- alter ranking;
- hide conflicts.


Mitigation:
self-hosting;
reproducible bundles;
asset verification by operators/auditors;
static code;
strict CSP;
no remote runtime dependencies;
independent Client Hosts.


Residual risk remains explicit.


37. MULTI-SOURCE OMISSION DEFENSE
No single data Node controls completeness.


Client/source merger:
- unions independently valid records;
- retains source attribution;
- never removes evidence solely due absence from another Node;
- warns on stale/single-source visibility according to policy.


38. CHECKPOINT EQUIVOCATION
Nodes sign checkpoints/snapshot roots.


Conflicting signed roots from the same Node are evidence of equivocation.


Gossip/witnessing MAY propagate public checkpoint evidence without user-query data.


39. CROSS-ORIGIN PRIVACY REVIEW
Separated direct-browser Node fetches require dedicated Tor Browser testing before release.


Review:
CORS
credentials
Origin/Referer behavior
request partitioning
cache behavior
timing
circuit/isolation implications.


Do not assume generic Firefox behavior equals current Tor Browser behavior.




40. SECURE CODING ATTACK-CLASS COVERAGE
The threat model is implemented through document 51 and a maintained attack-class coverage registry.


Explicit classes include:
XSS/DOM XSS
SQL/command/code injection
path traversal/symlink/TOCTOU
CSRF/authz/IDOR
SSRF/destination confusion
open redirect/header injection
request ambiguity/cache poisoning
deserialization/template injection
prototype pollution/ReDoS
zip/decompression/resource bombs
parser differential/Unicode spoofing
key theft/algorithm confusion/replay/downgrade
supply-chain/update compromise
Sybil/eclipse/equivocation.


41. VALIDATION IS NOT SANITIZATION
Security design uses field-specific positive validation plus context-specific output encoding.


“Remove suspicious characters” is not an accepted general security control.


42. FAIL-CLOSED PARSERS
Malformed, oversized, ambiguous, duplicate-key, unsupported-version or type-confused Protocol input is rejected before it can mutate current state.


43. ADULT CONTENT THREAT SEPARATION
adult is a content category, not a separate trust zone.


No adult-category record may enable richer/less-safe rendering or remote active content.


External content classification/moderation remains separate from Service self-declaration.
