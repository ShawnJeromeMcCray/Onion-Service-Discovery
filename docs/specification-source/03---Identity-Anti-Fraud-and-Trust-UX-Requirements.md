# 03 - Identity Anti-Fraud and Trust UX Requirements

Source: https://docs.google.com/document/d/1vfn7j1WMWNBm3DA1KIsRhU8zyGtm_FdeyCtybmI1mCQ/edit?usp=drivesdk

Source modified: 2026-10-11T04:32:24.953Z

---

ONION SERVICE DISCOVERY
IDENTITY, ANTI-FRAUD UND TRUST UX REQUIREMENTS


1. Ziel
Dieses Dokument verhindert, dass Onion Service Discovery durch technisch korrekte, aber irreführende “Verified”-Darstellung selbst Phishing/Betrug erleichtert. Technische Identität, historische Kontinuität, externe Verknüpfung, Moderation und inhaltliches Vertrauen werden strikt getrennt.


2. Grundsatz
Onion Service Discovery kann technisch beweisen oder beobachten:
- welche Onion-Adresse angesprochen wurde;
- welches Manifest dort ausgeliefert wurde;
- ob dessen Application-Signatur gültig ist;
- ob dieselbe Service Identity wie zuvor verwendet wird;
- ob ein adressbezogener Migrationsnachweis vorliegt;
- wann ein Zustand beobachtet wurde.


Es kann NICHT allein beweisen:
- dass eine Organisation rechtmäßig den angezeigten Namen trägt;
- dass der Service keine Betrugsabsicht hat;
- dass Inhalte sicher/legal/richtig sind;
- dass ein Betreiber dieselbe natürliche/juristische Person wie eine Clearnet-Marke ist.


3. Eindeutige technische Identität
Interner Identitätsschlüssel ist NICHT der Anzeigename.
Mindestens verwenden:
- Onion Address;
- Service Identity Public Key;
- Manifest History.


Name ist nur Metadatum.


4. Onion Binding
Direct Verification:
A. Ziel-Onion vor Request streng validieren.
B. Manifest vom standardisierten Pfad auf genau diesem Host abrufen.
C. Manifest-onion muss exakt zum Ziel passen.
D. Signatur muss zum Service Identity Public Key passen.
E. Nur dann onion_binding_verified=true.


Redirect auf anderen Host darf diese Eigenschaft nicht erzeugen.


5. Service Identity Continuity
Beim ersten gültigen Manifest:
- Service Identity wird lokal als erste bekannte Application Identity gespeichert.


Später:
- gleicher Key plus gültige Revision Chain => continuity;
- anderer Key ohne Rotation => identity changed;
- anderer Key mit gültiger Rotation => continuity after key rotation.


6. TOFU/Pins
Explizites Nutzer-Pinning:
- Nutzer speichert Service Identity Key und Onion;
- Pin ist lokaler Vertrauensanker für Wiedererkennung, nicht globale Beglaubigung;
- Peers dürfen Pin nicht ersetzen;
- neuer Key ohne Rotation erzeugt Warnung;
- neue Onion ohne Migration erzeugt Warnung.


7. Fingerprint
Client zeigt einen menschenvergleichbaren Fingerprint des Service Identity Public Key.
Anforderungen:
- deterministisch;
- ausreichend lang gegen triviale Kollision/Verwechslung;
- Copy full fingerprint;
- kein Ersatz für vollständigen Key in technischen Details.


Optionales Identicon darf nur Zusatz sein.


8. Namensduplikate
Mehrere Services dürfen denselben name-Wert haben.
UI:
- keine automatische “official” Kennzeichnung;
- Identity/Onion unterscheiden;
- bei identischem/ähnlichem Namen mehrere Ergebnisse klar getrennt darstellen.


9. Unicode Anti-Impersonation
Bewerte:
- Unicode normalization;
- confusable detection;
- bidirectional control characters;
- invisible characters;
- mixed scripts.


UI kann Warnhinweis zeigen:
“Name enthält Zeichen, die mit anderen Schreibweisen verwechselt werden können.”


Nie still den Namen in einen anderen sichtbaren Namen umwandeln, ohne Original zugänglich zu machen.


10. Onion-Adresse
Die vollständige Onion-Adresse:
- muss in Detailansicht sichtbar sein;
- muss kopierbar sein;
- darf nicht durch Anzeigename ersetzt/versteckt werden;
- kann in Karten gekürzt werden, solange eindeutige Detailansicht möglich.


11. Migration
Gültige Migration hat mindestens Application-Identity-Nachweis.
UI unterscheidet:
- Identity continuity confirmed;
- old onion directly confirmed migration;
- new onion directly confirmed migration.


Nicht sagen:
“Verified migration” ohne Definition.


12. Key Rotation
UI sollte reguläre Schlüsselrotation nicht wie Phishing darstellen, wenn Rotation gemäß Protokoll gültig ist.
Details:
old fingerprint
new fingerprint
effective revision
continuity valid


13. Fork
Bei zwei gültig signierten, unterschiedlichen Manifesten gleicher Revision:
- Service Status FORKED;
- beide Evidenzen erhalten;
- kein “neueste Uhrzeit gewinnt”;
- Treffer erhält Warnung;
- Pin nicht automatisch ändern.


14. Clearnet-Verknüpfung
Optional kann ein Service eine HTTPS-Domain nennen.
Eine separate Prüfung kann Status erzeugen:
“Clearnet domain linked/verified according to defined proof”.


Dieser Status:
- ist separat;
- ändert nicht Onion-Signatur;
- ist kein generelles Seriositätssiegel;
- muss Proof-Methoden im Protocol/ADR definieren.


15. Onion-Location
Wenn eine Clearnet-Seite ihren Onion Service über etablierte Onion-Location-Mechanismen bestätigt und die Verifikation erfolgreich ist, kann dies als zusätzliche technische Cross-Link-Evidenz dargestellt werden.
Keine rechtliche Identitätsgarantie daraus ableiten.


16. Observer-Signale
Mehrere Nodes können Observations liefern.
UI darf anzeigen:
“von ausgewählten Beobachtungsquellen gesehen”.


Nicht:
“20 Nodes = 20 unabhängige Organisationen”.
Sybil-Risiko erklären.


17. Zeit-/Historien-Signale
Nützliche technische Informationen:
first seen
last directly seen
duration observed
revision history length


Diese Signale sind Hinweise auf Historie, kein Beweis gegen Betrug.


18. Moderation/Reports
Moderation getrennt.
Beispiel:
Identity:
- manifest signature valid
- continuity valid


Community/Moderation:
- phishing warning from Source A


Ein Report darf Identity-Felder nicht in “invalid” verwandeln.


19. Report Provenance
Falls Moderationsquellen implementiert:
- source identifier;
- signature/provenance;
- category;
- issued time;
- expiry/revision;
- evidence URL/metadata optional.


Client entscheidet lokal, welchen Quellen er folgt.


20. Keine globale Blacklist als Protokollwahrheit
Basisprotokoll bleibt technisch neutral bezüglich Inhaltsmoderation.
Installationen können Listen verwenden.
Keine einzelne Maintainer-Liste darf zwingend für Identity-Verifikation sein.


21. UX-Farben
Warnstatus nicht nur durch Farbe.
Icons + Text + zugängliche Beschreibung.
Grün darf nicht automatisch “safe” bedeuten.
Bevorzugt neutrale technische Statussprache.


22. Verbotene UI-Begriffe ohne separate Definition
SAFE
TRUSTED
LEGIT
OFFICIAL
SCAM-FREE
GUARANTEED
ANONYMOUS
SECURE


Wenn ein Begriff später verwendet wird, muss er exakt definierte Evidenz und Human UX Review besitzen.


23. Empfohlene technische Labels
“Onion binding checked”
“Manifest signature valid”
“Known service identity”
“Identity continuity confirmed”
“Key rotation confirmed”
“Address migration confirmed”
“Recently directly observed”
“Long time since direct observation”
“Identity conflict detected”
“Different identity than your saved pin”


Lokalisierungen müssen dieselbe Semantik erhalten.


24. Favoriten
Favorite und Pin sind getrennte Konzepte möglich:
Favorite = Komfort.
Pin = Identitätswiedererkennung.
UI darf Favorite nicht automatisch als Security Pin behandeln, außer bewusst kombiniert und erklärt.


25. Search Result De-Dup
Nicht nur nach Name deduplizieren.
Primäre technische Gruppierung nach Service Identity/Onion-/Migration-Historie.
Zwei identische Namen mit verschiedenen Identitäten bleiben getrennt.


26. Historische Onion-Adressen
Nach bestätigter Migration:
- alte Adresse historisch anzeigen;
- neue aktuelle Adresse;
- nicht automatisch alte Adresse als aktuellen Navigate-Link verwenden;
- Migration chain speichern.


27. Compromised Identity
Falls Service Identity Key als kompromittiert gemeldet/erkannt wird, braucht es keine erfundene automatische Heilung.
Status:
needs attention / compromised evidence according to incident process.
Recovery Semantik muss Protokoll/Incident Procedure definieren.


28. Similarity Warnings
Optional:
lokaler Vergleich mit gepinnten/bekannten Namen.
Wenn neue Identität sehr ähnlichen Namen wie Pin nutzt:
“Similar name, different identity.”
Keine automatische Betrugsbeschuldigung.


29. Externe Logos/Bilder
Basisversion sollte keine Remote-Bilder für Treffer laden.
Gründe:
- Tracking;
- UI impersonation;
- Datenmenge.
Wenn später Icons erlaubt werden: content-addressed, sanitized, size/type-limited, privacy-reviewed.


30. HTML in Metadaten
Name/description sind Plaintext.
Kein Manifest-HTML direkt rendern.
Links strukturiert validieren.
UI escaped output.


31. Links
Externe Links in Description nicht automatisch anklickbar machen, sofern nicht strukturiert und validiert.
Onion-Navigation erfordert bewusste Nutzeraktion.


32. Phishing-Testmatrix
- echter gepinnter Service;
- gleicher Name anderer Key;
- confusable name anderer Key;
- gleiche Description anderer Key;
- falsche Migration;
- neue Onion ohne Proof;
- gültige Migration;
- gültige Key Rotation;
- Fork;
- stale copy;
- malicious peer supplies fake metadata;
Erwartete UI für jeden Fall als Snapshot/Component Test festhalten.


33. Anti-Fraud Traceability
Für jedes sichtbare Badge/Label dokumentieren:
- Field/State source;
- Validation algorithm;
- Security meaning;
- Non-meaning;
- tests;
- UI copy;
- accessibility label.


34. Benutzererklärung
Eine zentrale Hilfe erklärt:
“Onion Service Discovery bestätigt technische Identität und Kontinuität, nicht die Vertrauenswürdigkeit von Inhalten oder Betreibern.”


35. Operator-Identität
Node-Betreiber und Service-Betreiber können verschieden sein.
Node, der einen Service beobachtet, darf sich nicht als Betreiber dieses Service darstellen.


36. Keine bezahlte Vertrauenspriorisierung
Kernprotokoll und Reference Client dürfen keine bezahlte Platzierung als technisches Vertrauenssignal integrieren.
Falls wirtschaftliche Modelle später existieren, strikte Trennung von Search Relevance/Ads und Identity erforderlich.


37. Auditability
UI-Status muss auf zugrunde liegende technische Evidenz zurückführbar sein.
Detailansicht soll bei Bedarf zeigen:
- manifest hash;
- revision;
- service key fingerprint;
- observation timestamps;
- migration/rotation record IDs;
ohne Nutzer mit Rohdaten in Standardansicht zu überfordern.


38. Fertig-Gate
Anti-Fraud gilt als implementiert, wenn:
- Namen nie Identität ersetzen;
- Pins nicht still überschrieben werden;
- alle Identity-Wechsel Warnpfade besitzen;
- Rotation/Migration korrekt unterschieden werden;
- Fork sichtbar ist;
- Unicode/Confusable-Risiken getestet sind;
- Moderation getrennt ist;
- kein UI-Label mehr verspricht als technisch bewiesen;
- Security/UX Review durchgeführt ist.
