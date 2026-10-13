# 02 - Protocol Version 1 Specification

Source: https://docs.google.com/document/d/1jrJyPDRXFChFXSPe9SgOldeVYOcZ-GTmUzH4WmZW64o/edit?usp=drivesdk

Source modified: 2026-10-12T19:28:58.271Z

---

ONION SERVICE DISCOVERY
PROTOCOL VERSION 1 SPECIFICATION


Status
Normative Arbeitsgrundlage für die Referenzimplementierungen. Die Schlüsselwörter MUSS, DARF NICHT, SOLL, SOLL NICHT und KANN sind normativ zu verstehen.


1. Protokollidentität
Öffentlicher Protokollname:
Onion Service Discovery


Protokollkennung im JSON:
"Onion Service Discovery"


Version:
1


Öffentliche Pfade verwenden den ausgeschriebenen Slug:
onion-service-discovery


2. Grundprinzip
Ein Service veröffentlicht seine eigenen Discovery-Metadaten. Discovery-Nodes beobachten, spiegeln und verifizieren Daten. Clients verarbeiten Indexdaten lokal. Kein einzelner Node ist Autorität für Service-Identität oder Seriosität.


3. Standardpfad des Service-Manifests
Der Referenzpfad lautet:
/.well-known/onion-service-discovery.json


Ein Node, der einen Service direkt verifiziert, MUSS diesen Pfad auf genau der zu prüfenden Onion-Adresse abrufen.


4. Manifest-Grundstruktur
Version 1 MUSS logisch mindestens folgende Felder besitzen:


{
  "protocol": "Onion Service Discovery",
  "protocol_version": 1,
  "onion": "<v3 onion hostname>",
  "service_identity_public_key": "<encoded Ed25519 public key>",
  "revision": <positive integer>,
  "previous_manifest_hash": "<hash or null for genesis>",
  "published_at": "<optional protocol timestamp>",
  "discovery": {
    "publish_service": true|false,
    "mirror_directory": true|false,
    "discover_services": true|false,
    "sync_with_peers": true|false
  },
  "service": {
    "name": "<bounded display name>",
    "description": "<bounded text>",
    "languages": ["<BCP47-like tag>"],
    "categories": ["<bounded category id>"]
  },
  "links": {
    "clearnet": [],
    "related_onion_services": []
  },
  "signature": "<encoded detached signature>"
}


Das finale JSON Schema ist Teil des Protokollpakets und ist gegenüber diesem Beispiel maßgeblich, wenn es präziser ist.


5. Onion-Feld
- MUSS eine gültige Tor v3 Onion-Adresse darstellen.
- MUSS in normalisierter Form gespeichert werden.
- Der direkte Verifier MUSS prüfen, dass das onion-Feld genau dem Host entspricht, gegen den die Verifikation durchgeführt wurde.
- Alias, Weiterleitung oder ähnlich geschriebene Adresse genügt nicht.
- Ein Mismatch führt zu REJECT oder QUARANTINE gemäß Ursache.
- Die Anwendung DARF NICHT versuchen, den privaten Tor-Onion-Schlüssel zu lesen.


6. Service Identity Key
- Ed25519-Anwendungsschlüssel.
- Getrennt vom Tor-Hidden-Service-Schlüssel.
- Öffentlicher Schlüssel steht im Manifest.
- Privater Schlüssel liegt nur beim Service-Betreiber.
- Der Schlüssel signiert den kanonischen signierbaren Manifest-Payload.
- Die genaue Key-Encoding-Form wird einmalig festgelegt und mit Testvektoren veröffentlicht.


7. Kanonische Serialisierung
Vor Signatur und Hashing MUSS eine deterministische Darstellung erzeugt werden.
Bevorzugte Basis: RFC 8785 JSON Canonicalization Scheme, sofern die Implementierung und Interoperabilitätstests dies bestätigen.
Die signaturtragende Eigenschaft selbst wird NICHT in den zu signierenden Payload aufgenommen.
Es MUSS eindeutig dokumentiert sein:
- welche Felder signiert werden;
- wie null behandelt wird;
- wie Zahlen kodiert werden;
- wie Unicode behandelt wird;
- ob optionale fehlende Felder anders als explizites null sind;
- wie unbekannte Felder in zukünftigen Versionen behandelt werden.


8. Hashes
Version 1 verwendet einen dokumentierten kryptografischen Hash für Inhaltsreferenzen. Die Referenzimplementierung soll SHA-256 verwenden, sofern bis zur Implementierung kein stärker begründeter Standard gewählt und per ADR dokumentiert wird.
Hash-Encoding MUSS kanonisch sein.


9. Revisionen
revision:
- positive Ganzzahl;
- startet bei 1;
- steigt bei jeder signifikanten Manifeständerung strikt;
- darf nicht sinken.


previous_manifest_hash:
- bei Revision 1 null;
- bei Revision >1 Hash des vollständigen vorherigen signierbaren/definierten Manifestzustands gemäß Spezifikation.


Regeln:
- höhere gültige Revision kann niedrigere aktuelle Revision ablösen;
- niedrigere Revision nach bekannter höherer Revision ist stale/replay;
- gleiche Revision plus gleicher Hash ist identisch;
- gleiche Revision plus anderer gültiger Inhalt ist FORK;
- Fork wird niemals automatisch durch Zeitstempel entschieden.


10. Discovery-Schalter
publish_service:
true bedeutet ausdrückliche Zustimmung, im öffentlichen Discovery-System aufzutauchen.
false bedeutet, dass Nodes den Service nicht als aktiv öffentlich auffindbar führen sollen. Historische/technische Daten können nach Retention Policy intern für Konflikterkennung verbleiben, dürfen aber nicht als normaler Suchtreffer behandelt werden.


mirror_directory:
Node darf Discovery-Daten speichern und ausliefern.


discover_services:
Node darf Kandidaten selbst prüfen.


sync_with_peers:
Node darf mit anderen Discovery-Nodes synchronisieren.


11. Service-Metadaten
name:
- rein beschreibend;
- nicht eindeutig;
- kein Eigentums- oder Markenbeweis;
- begrenzte Länge;
- Unicode-Regeln und Normalisierung spezifizieren.


description:
- optional;
- streng begrenzte Länge;
- reiner Text in Basisversion;
- kein ungeprüftes HTML.


languages:
- begrenzte Anzahl;
- definierte Tag-Syntax;
- Duplikate entfernen.


categories:
- begrenzte Anzahl;
- Protocol v1 uses the frozen Protocol Category Registry v1; free-form category identifiers are not permitted in the normative category field;
- keine versteckte Vertrauenssemantik.


12. Fremdlinks und Bezüge
clearnet:
- optional;
- niemals automatisch als Identitätsbeweis werten;
- eine gesonderte Proof-Verifikation kann den Status “clearnet link verified” erzeugen.


related_onion_services:
- nur Candidate-Hinweise;
- Aufnahme eines verwandten Service bedeutet nicht Verifikation;
- maximale Anzahl strikt begrenzen.


13. Signaturprüfung
Verifier:
1. parse mit strengem Parser;
Step 2 — Schema validieren;
Step 3 — Onion-Bindung prüfen;
4. signierbaren Payload bilden;
5. kanonisieren;
Step 6 — Signatur mit service_identity_public_key prüfen;
Step 7 — Revision/Hash-Kette gegen bekannten Zustand prüfen;
8. erst danach als direkt verifizierte Manifest-Beobachtung speichern.


Jeder Fehlerzustand erhält einen eindeutigen internen Result Code.


14. Key-Rotation-Record
Für Service-Identity-Key-Wechsel MUSS ein eigener signierter Record existieren.


Logische Felder:
- protocol;
- protocol_version;
- record_type = "service_identity_key_rotation";
- onion;
- old_public_key;
- new_public_key;
- effective_revision;
- previous_state_hash;
- old_key_signature;
- optional new_key_confirmation.


Der alte bekannte Key muss den Übergang autorisieren.
Fehlt dieser Nachweis, ist der neue Key eine neue/abweichende Identität und nicht automatisch Kontinuität.


15. Onion-Migration-Record
Für Wechsel von alter zu neuer Onion-Adresse:


- record_type = "onion_service_migration";
- service_identity_public_key;
- from_onion;
- to_onion;
- effective_revision;
- previous_state_hash;
- service_identity_signature;
- optional confirmation observed from old service;
- optional confirmation observed from new service.


Die UI muss unterscheiden:
A) Service-Identity-Kontinuität kryptografisch bestätigt.
B) Alte Onion hat Migration zusätzlich direkt bestätigt.
C) Neue Onion hat Migration direkt bestätigt.


16. Node Identity
Discovery-Nodes besitzen einen separaten Ed25519 Node Identity Key.


Node Descriptor:
- protocol;
- protocol_version;
- node_identity_public_key;
- node_onion;
- capabilities;
- software implementation name/version;
- supported protocol versions;
- optional contact/policy metadata;
- descriptor revision;
- signature.


Service Key und Node Key sind getrennt, auch wenn Service und Node denselben Host benutzen.


17. Direct Observation
Ein Node kann eine signierte Beobachtung erzeugen:
- record_type = "direct_observation";
- node identity;
- service onion;
- manifest hash;
- service identity public key;
- revision;
- observation result class;
- observed_at;
- optional bounded metadata;
- node signature.


Observation beweist nur: dieser Node behauptet, zu diesem Zeitpunkt diesen Zustand direkt beobachtet zu haben.
Sie beweist nicht Seriosität des Service.


18. Peer Assertion
Daten, die nur von einem Peer stammen und nicht lokal direkt verifiziert sind, werden als PeerAssertion gespeichert.
PeerAssertion darf DirectObservation nicht ersetzen.


19. Candidate
Candidate enthält minimal:
- onion;
- discovery_source_type;
- source identifier/hash;
- first_received;
- retry state;
- validation state.


Candidate darf nicht alle Service-Metadaten als “wahr” vorwegnehmen.


20. Candidate-State-Machine
RECEIVED
-> ADDRESS_VALIDATED
-> QUEUED
-> DIRECT_CHECK_PENDING
-> VERIFIED


Alternative:
-> INVALID
-> BACKOFF
-> QUARANTINED
-> REJECTED


Retry-Regeln werden durch lokale Policy begrenzt.


21. Peer-Discovery
Peers können andere Node Descriptors nennen.
Empfänger:
- prüft Syntax/Signatur;
- speichert unbekannte Nodes zunächst als Kandidaten;
- erzwingt maximale Peerzahlen;
- wählt Peers nicht allein nach Häufigkeit;
- darf Seeds und manuell gesetzte Peers bevorzugt behandeln, ohne sie zu Root Authorities zu machen.


22. Synchronisationsmodell
Version 1 ist Pull-orientiert.


Minimal:
GET node state
Vergleich von state/root/revision
Abruf begrenzter Delta- oder Snapshot-Blöcke
lokale Validierung
lokale Commit-Transaktion


Kein Peer erhält direkten Schreibzugriff auf die Datenbank eines anderen Nodes.


23. Empfohlene HTTP-Ressourcen
GET /onion-service-discovery/v1/node
GET /onion-service-discovery/v1/state
GET /onion-service-discovery/v1/peers
GET /onion-service-discovery/v1/services/{onion}
GET /onion-service-discovery/v1/changes
GET /onion-service-discovery/v1/snapshots/{id}
GET /onion-service-discovery/v1/chunks/{hash}


Exakte Query-Parameter, Limits, Antwortschemata und Fehlercodes müssen vor Implementierung eingefroren werden.


24. API-Grundsätze
- standardmäßig GET/read-only;
- keine generische URL-Abruf-Funktion;
- keine Remote-Admin-Befehle;
- keine beliebigen Dateipfade;
- kein dynamisches Include;
- Antwortgrößen limitiert;
- Content-Type eindeutig;
- Cache-Verhalten spezifiziert;
- Server verrät keine Secrets oder internen Pfade.


25. Snapshot-/Chunk-Modell
Ziel:
Clients und Nodes sollen nicht bei jeder Aktualisierung den gesamten Index laden müssen.


Mögliche Version-1-Struktur:
- signierter Snapshot Descriptor;
- content-addressed Chunks;
- Root Hash;
- sortierte/deterministische Records.


Falls Merkle-Strukturen in Version 1 nicht vollständig umgesetzt werden, MUSS die Datenstruktur so gestaltet werden, dass eine spätere inkrementelle Erweiterung ohne Neudefinition der Service-Identität möglich bleibt.


26. Konfliktregeln
Konflikte werden explizit gespeichert.
Keine stillen Merge-Heuristiken bei:
- unterschiedlichem Service Key;
- Fork gleicher Revision;
- widersprüchlicher Migration;
- ungültiger Rotation;
- Hash-Kettenbruch.


27. Stale-/Liveness-Semantik
Nicht erreichbar ist nicht gleich gelöscht.
Mindestens unterscheiden:
- recently directly seen;
- temporarily unreachable;
- stale;
- long unseen.


Zeitgrenzen sind Policy, nicht Identität.
Historische Zeiten dürfen nicht verwendet werden, um kryptografische Konflikte zu überschreiben.


28. Moderation
Moderation ist separater Record-Typ.
Moderationsdaten dürfen:
- warnen;
- filtern;
- kategorisieren.


Sie dürfen nicht:
- Manifest-Signaturstatus verändern;
- Service-Identity-Kontinuität umschreiben;
- eine einzelne globale Autorität erzwingen.


29. Fehlerklassen
Normative Fehlerfamilien:
PROTOCOL_UNSUPPORTED
SCHEMA_INVALID
ADDRESS_INVALID
ONION_BINDING_MISMATCH
SIGNATURE_INVALID
HASH_MISMATCH
REVISION_STALE
REVISION_FORK
KEY_ROTATION_INVALID
MIGRATION_INVALID
RESPONSE_TOO_LARGE
TIMEOUT
TRANSPORT_FAILURE
RATE_LIMITED
QUARANTINED
INTERNAL_STORAGE_ERROR


Öffentliche API darf weniger Detail liefern, interne Logs/Testresultate sollen präzise Klassen behalten.


30. Größenlimits
Vor Production sind konkrete Limits festzuschreiben für:
- Manifestbytes;
- Name;
- Beschreibung;
- Anzahl Sprachen;
- Anzahl Kategorien;
- Anzahl related services;
- Peer-Liste;
- Delta-Größe;
- Chunk-Größe;
- maximale Records je Antwort;
- maximale JSON-Tiefe.


Limitwerte stehen im JSON Schema bzw. einer normativen Limits-Tabelle.


31. Zeit
Systemzeit darf für Liveness und Backoff genutzt werden.
Systemzeit darf nicht allein über Identitätskonflikte entscheiden.
Zeitstempel müssen ein einziges Format nutzen, z.B. RFC 3339 UTC, sofern in ADR bestätigt.


32. Versionierung
protocol_version 1 ist fest.
Breaking Wire Changes benötigen neue Version.
Ein Node kann mehrere Versionen unterstützen, darf aber nie Felder einer unbekannten Version mit Version-1-Semantik interpretieren.


33. Forward Compatibility
Unknown fields:
- Parser darf sie nur gemäß expliziter Schema-/Versionierungsregel tolerieren;
- signierte unbekannte Felder müssen bei Signaturprüfung korrekt erhalten/berücksichtigt werden;
- Implementierungen dürfen unbekannte Daten nicht beim Weiterreichen versehentlich semantisch verändern.


34. Conformance
Eine Implementation ist Version-1-conform nur wenn:
- alle Pflichtschemas akzeptiert/abgelehnt werden wie Testvektoren;
- Signaturtestvektoren exakt übereinstimmen;
- Revision/Fork/Rotation/Migration State Machines die definierten Tests bestehen;
- Fehlerklassen kompatibel abgebildet werden;
- Netzwerk-/Resource-Limits nicht unter normative Mindestgrenzen fallen.


35. Referenz-Testvektoren
Der Coding-Agent MUSS erzeugen:
- gültiges Genesis Manifest;
- gültige Revision 2;
- manipuliertes Feld ohne neue Signatur;
- falsche Onion-Bindung;
- stale revision;
- forked revision;
- gültige Key Rotation;
- ungültige Rotation;
- gültige Migration;
- ungültige Migration;
- gültiger Node Descriptor;
- manipulierte Observation;
- Boundary-Size-Manifeste;
- Unicode-/Parsing-Grenzfälle.


36. Freeze-Regel
Bevor PHP-Node und Browser-Client gemeinsam integriert werden, müssen folgende Punkte als Protocol v1 Freeze markiert sein:
- Manifest Schema;
- Canonicalization;
- Key Encoding;
- Signature Input;
- Hash Encoding;
- Revision Semantics;
- Rotation/Migration Records;
- Node Descriptor;
- Observation Record;
- grundlegende Sync-Envelope-Schemas.


Änderungen danach benötigen Protocol Change Proposal, Migrationsanalyse und aktualisierte Testvektoren.


37. Category Registry v1
Manifest categories are broad, stable, self-declared content/function identifiers from the frozen Protocol Category Registry v1.
The registry explicitly includes adult.
Category assignment is signed operator metadata, not proof of legality, safety, legitimacy, age verification or external classification.
Moderation/status concepts such as phishing, malware, trusted, verified or official MUST NOT be represented as operator categories.
Unknown Protocol v1 category IDs are rejected.
The v1 registry is immutable after Protocol v1 freeze; post-freeze additions require an explicitly versioned compatible extension mechanism or a new Protocol version.


38. Logical Service Identifier
A logical Service continuity lineage requires an identifier that survives valid Service-key rotation and Onion migration.
Protocol v1 uses logical_service_id anchored to the genesis Service Identity public key, separately from the current Service Identity key/key epoch.
The exact representation is frozen under D-051.
A verifier MUST NOT accept a claimed logical_service_id without continuity evidence back to the genesis anchor.


39. Signed Index Checkpoints
Production index-exporting Nodes publish signed checkpoints according to the Node API/checkpoint specification.
Checkpoint histories are chained and are distinct from Service revision chains.
Exact checkpoint hash/chain bytes are frozen under D-052.


40. Freeze Completeness
Protocol v1 Freeze additionally requires:
- Logical Service Identifier representation and vectors;
- signed checkpoint byte construction and chain semantics;
- Category Registry v1 immutability;
- exact language-tag wire grammar;
- Candidate Announcement endpoint semantics;
- exact active-index record identity tuple.
