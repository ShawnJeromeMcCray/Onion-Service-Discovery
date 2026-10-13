# 07 - Production Operations Release and Incident Runbook

Source: https://docs.google.com/document/d/1t4pj8ohdbf9nAi9JwFuFxkVQK6IsSHCcjIC7VVTcFsg/edit?usp=drivesdk

Source modified: 2026-10-12T14:45:14.714Z

---

ONION SERVICE DISCOVERY
PRODUCTION OPERATIONS, RELEASE UND INCIDENT RUNBOOK


1. Ziel
Dieses Dokument definiert Betrieb, Deployment, Upgrade, Backup, Restore, Release, Supply Chain und Incident Response. Eine Software ist nicht produktionsreif, wenn nur der Quellcode funktioniert.


2. Unterstützte Betriebsform
Referenznode:
- bestehender Linux-Server;
- bestehender Tor Onion Service;
- nginx oder Apache;
- PHP-FPM;
- lokaler Tor-SOCKS-Zugang;
- ausgewählter StorageBackend + dessen Laufzeitabhängigkeiten;
- optional cron/systemd timer für kurze PHP-CLI-Jobs.


Keine Modifikation oder Neukompilierung von Tor ist erforderlich.


3. Installationsprinzip
Eine Clean-Room-Installation muss allein anhand der Dokumentation möglich sein.


Installationsdokument MUSS enthalten:
- unterstützte OS-/PHP-Versionen;
- Systemvoraussetzungen;
- PHP Extensions;
- Tor Voraussetzung;
- Webserver-Konfiguration;
- Dateisystemlayout;
- Benutzer/Gruppe;
- Dateirechte;
- Konfigurationsdatei;
- Schlüsselgenerierung;
- Datenbankinitialisierung;
- Scheduler;
- Self-Check;
- Health Check;
- erste Manifestgenerierung;
- optional Node-Funktion aktivieren.


4. Dateisystemtrennung
Beispiel:
application code: read-only für Webprozess soweit möglich
public/: Webroot
config/: außerhalb public
keys/: außerhalb public, restriktiv
var/storage/: schreibbar nur für den minimal erforderlichen Service-/Maintenance-Kontext des ausgewählten StorageBackends
var/cache/: begrenzt
var/quarantine/: begrenzt
logs/: nach Systempolicy


5. Betriebsbenutzer
Anwendung läuft nicht als root.
Webserver und Maintenance-Jobs verwenden minimal notwendige Rechte.
Schlüssel dürfen nicht für unnötige Benutzer lesbar sein.


6. Konfigurationsmanagement
- Beispielkonfiguration im Repository ohne Secrets;
- produktive Konfiguration außerhalb Versionskontrolle;
- Startup Validation;
- sichere Defaultwerte;
- jede produktive Konfigurationsoption dokumentiert;
- Versionshinweise bei deprecations;
- kein Secret in URL/Log.


7. Key Operations
Service Identity, Node Identity und Release-Trust keys are separate trust domains. Preferred Service Identity key custody is offline/isolated; Node key is normally online.


Runbooks:
- initial generation;
- backup;
- restore;
- planned rotation;
- emergency rotation;
- suspected compromise;
- revocation/continuity communication soweit Protokoll ermöglicht.


Key Generation darf bestehende Schlüssel nicht still überschreiben.


8. Backup Scope
Mindestens bewerten:
A. Service Identity Private Key — kritisch für Identitätskontinuität; im bevorzugten Profil offline/SignerProvider-verwaltet und NICHT Teil eines normalen Node-/Storage-Backups.
B. Node Identity Private Key — wichtig für Node-Kontinuität, aber getrennt.
C. StorageBackend — Discovery-Evidenz/State; konsistenter adapter-spezifischer Backup oder backend-neutraler Export.
D. produktive Konfiguration.
E. lokale Seed-/Peer-Konfiguration.
F. Logs nur soweit Betriebsanforderung, nicht als kritisches Restore-Gut.


9. Backup Policy
Dokumentiere:
- Häufigkeit;
- Aufbewahrung;
- Verschlüsselung;
- Speicherorttrennung;
- Zugriff;
- Integritätsprüfung;
- Restore-Testintervall.


Ein Backup ohne getesteten Restore zählt nicht als Backup-Gate PASS.


10. Restore Runbook
- betroffene Instanz stoppen oder Read-Only;
- Backup-Integrität prüfen;
- Schlüssel und Datenbank getrennt korrekt zurückspielen;
- Dateirechte setzen;
- ausgewählten StorageBackend-Integritätscheck;
- config validate;
- manifest/key consistency check;
- health;
- kontrollierter Sync;
- Monitoring nach Restore.


11. Datenbankwartung
- regelmäßiger integrity check;
- Größe beobachten;
- Retention;
- bereinigbare Peer-Transients;
- Quarantäne nach definierter Policy;
- Vacuum nur bewusst;
- keine automatische “Reparatur”, die Identitätshistorie verwirft.


12. Scheduler Operations
Jobs:
sync peers
verify candidates
recheck stale services
build snapshot/chunks
maintenance


Jeder Job:
- lock;
- Laufzeitbudget;
- Work Budget;
- strukturierter Exit Code;
- Retry nicht intern endlos;
- Scheduler entscheidet nächsten Lauf.


13. Health und Readiness
Health soll mindestens unterscheiden:
OK
DEGRADED
READ_ONLY
MAINTENANCE


Readiness:
Kann öffentliche read-only API konsistent antworten?


Health darf keine privaten Pfade, Schlüssel oder sensitive Konfiguration offenlegen.


14. Logging
Standard:
- strukturierte technische Events;
- Rotation;
- begrenzte Retention;
- keine Suchbegriffe;
- keine privaten Schlüssel;
- keine Credentials;
- keine vollständigen sensitiven Request Dumps.


15. Monitoring
Empfohlene lokale Metriken:
- StorageBackend size/disk/journal state;
- candidate queue;
- verification result counters;
- sync age;
- peer health categories;
- scheduler failures;
- rate-limit drops;
- quarantine count;
- software version.


Keine Nutzerverhaltensanalyse.


16. Upgrade Runbook
Vor Upgrade:
- Release Notes lesen;
- Version Compatibility;
- Backup;
- aktuelle health erfassen;
- StorageBackend migration/import plan.


Upgrade:
- Maintenance/controlled state;
- deploy;
- dependency install aus Lockfile;
- StorageBackend migrate/import;
- config validate;
- self tests;
- health;
- controlled sync.


Nach Upgrade:
- API smoke;
- manifest generation/verification;
- scheduler;
- logs prüfen.


17. Rollback
Für jeden Release muss klar sein:
- Code rollback möglich?
- StorageBackend migration/format backward/rollback behavior understood?
- Datenformat downgrade möglich?
- wenn nicht: restore path.


Nie einen “Rollback” dokumentieren, der real nicht getestet wurde.


18. Release Versioning
Semantic Versioning als Ausgangspunkt, sofern ADR bestätigt.
Protokollversion und Softwareversion getrennt.
Software 2.x kann Protocol 1 unterstützen.
Breaking Protocol Change erhöht protocol_version nach Change Process.


19. Release Branch/Tag Policy
- geschützte Hauptlinie soweit Hosting unterstützt;
- Review für sicherheitskritische Änderungen;
- immutable source tag/revision plus the Production release trust-root/update-metadata signatures required by the Secure Release standard; Git tag signing alone is supplementary, not the sole trust anchor;
- Changelog;
- Release Notes;
- Checksums;
- Artefaktliste.


20. Release Build
Aus sauberem Checkout.
Nur gelockte Dependencies.
Toolchain-Versionen dokumentieren.
Tests vollständig.
SBOM erzeugen.
Checksums erzeugen.
Keine lokalen uncommitted Dateien.


21. Release Artefakte
Server:
- Source/Package;
- composer.lock;
- schemas;
- migrations;
- example config;
- docs;
- checksums;
- SBOM.


Client:
- source;
- built static Web Local Search bundle;
- lockfile;
- manifest;
- checksums;
- SBOM;
- Web Client origin/CSP/network-destination/browser-capability diff.


22. Release Notes
MUSS nennen:
- Version;
- Datum;
- unterstützte Protokollversion;
- Security-relevante Änderungen;
- Datenbankmigrationen;
- Web Client Origin-/CSP-/Netzwerkziel-/Browser-Capability-Änderungen;
- Configänderungen;
- Breaking Changes;
- Upgrade Steps;
- Rollback Hinweise;
- Known Issues.


23. Supply Chain
- Dependencys minimieren;
- Lockfiles;
- SCA;
- SAST;
- Secret Scanning;
- SBOM;
- Lizenzprüfung;
- Third-Party CI Actions gepinnt;
- keine beliebigen Download-and-execute Installer;
- CI-Tokens minimal berechtigt.


24. Vulnerability Disclosure
SECURITY-Dokument:
- wohin Sicherheitsmeldungen;
- welche Versionen unterstützt;
- erwarteter Umgang;
- sichere Kontaktoptionen falls eingerichtet;
- keine Garantie unrealistischer Reaktionszeiten.


25. Incident Severity
SEV-1:
aktive oder wahrscheinliche Kompromittierung von Release, Service-Identity-Mechanismus oder großflächiger Privacy-Bruch.


SEV-2:
wichtige Integritäts-/Security-Schwäche mit begrenzterem Scope.


SEV-3:
mittleres Problem ohne unmittelbaren kritischen Impact.


SEV-4:
geringes Problem/Hardening.


26. Incident Lifecycle
DETECT
TRIAGE
CONTAIN
PRESERVE EVIDENCE
FIX
VERIFY
RELEASE
COMMUNICATE
POSTMORTEM
PREVENT REGRESSION


27. Service Identity Key Compromise
Runbook muss bewerten:
- welcher Key;
- Zeitraum;
- welche Revisionen;
- existiert noch ein vertrauenswürdiger Kontinuitätsanker;
- Rotation/Migration nach Protokoll möglich;
- was müssen Clients anzeigen;
- welche Records werden quarantänisiert.


Keine improvisierte Änderung von Identitätsregeln während Incident.


28. Node Identity Key Compromise
- betroffenen Node Key ersetzen;
- alten Node Descriptor markieren;
- Service Identity bleibt getrennt;
- Observations des kompromittierten Zeitraums als Quelle neu bewerten;
- keine automatische Änderung von Service Identity.


29. Protocol Vulnerability
- betroffene Version;
- fail-safe mitigation;
- Patch;
- neue Testvektoren;
- falls Wire Change notwendig: Protocol Change Proposal;
- Clients/Nodes kompatibel koordinieren.


30. Client Privacy Incident
- sofort Datenfluss ermitteln;
- Telemetrie/Netzwerkpfad deaktivieren, falls nötig;
- Regression Tests;
- transparent dokumentieren;
- prüfen, ob sensible lokale Daten übertragen wurden.


31. Supply-Chain Incident
- Releases stoppen;
- Signing/CI credentials rotieren soweit betroffen;
- vertrauenswürdigen Buildpunkt bestimmen;
- Artefakte/Checksums neu verifizieren;
- kompromittierte Versionen identifizieren;
- sauberen Rebuild durchführen.


32. Mass Spam Event
- keine spontanen globalen Sperrlisten als einzige Lösung;
- Rate Limits/Budgets verschärfen innerhalb spezifizierter Grenzen;
- Queue/StorageBackend health beobachten;
- neue Regeln nur mit Tests/ADR, wenn semantisch relevant;
- legitime kleine Services nicht pauschal entfernen.


33. Seed Outage
- vorhandene learned peers;
- alternative Seeds;
- lokaler Index bleibt;
- keine Notfall-Zentralisierung ohne ausdrückliche Änderung der Architektur.


34. Disaster Recovery
Szenario:
Server vollständig verloren.
Recovery:
- neues System;
- Tor Onion Service entsprechend Betreiberstrategie;
- Anwendung;
- separater, getesteter Offline/SignerProvider Service-Identity-Recoverypfad;
- Node Identity Backup optional;
- konsistenter StorageBackend Backup/Export oder verifizierter Neu-Sync soweit Kontinuitätsevidenz nicht ausschließlich lokal ist;
- Config;
- Manifest;
- health;
- kontrollierter Wiedereintritt.


35. Clean-Room Release Test
Vor Production RC:
Eine Person/Agent mit leerer Umgebung und ausschließlich veröffentlichter Dokumentation installiert Node und Client.
Alle fehlenden Annahmen werden als Bugs der Dokumentation behandelt.


36. Reproducibility
Mindestens dokumentieren:
- OS/toolchain;
- PHP;
- Composer;
- Node/npm/pnpm falls Client;
- Build Commands;
- erwartete Output-Dateien;
- Checksums.


37. Support Matrix
Pflege:
- unterstützte PHP-Versionen;
- getestete Tor-Versionen/Interfaces soweit relevant;
- unterstützte Browser-/Tor-Browser-Basis;
- OS-Testmatrix;
- Protokollversionen;
- StorageBackend schema/format version(s);


38. End-of-Life
Wenn Version nicht mehr unterstützt:
- Datum;
- Upgradeziel;
- Security implications;
- keine stillen EOLs.


39. Postmortem
Nach SEV-1/SEV-2:
- Timeline;
- Impact;
- Detection;
- Root Cause;
- Contributing Factors;
- What worked;
- What failed;
- Actions mit Owner;
- Tests/Docs/Architecture changes.
Keine Schuldzuweisung, aber konkrete technische Verantwortung.


40. Production Acceptance
Operations gelten als fertig, wenn ein unabhängiger Clean-Room-Test Installation, Upgrade, Backup, Restore, Scheduler, Health und Rollback praktisch nachweisen kann und alle Runbooks als ausführbar bewertet wurden.
