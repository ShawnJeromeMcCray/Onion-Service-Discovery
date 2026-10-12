# 10 - Required Engineering Review and Release Templates

Source: https://docs.google.com/document/d/1gQrpvdulg1TMiiAHo6TPGQvhNdH5Qw99ByS-SA-3sLg/edit?usp=drivesdk

Source modified: 2026-10-11T07:17:08.415Z

---

ONION SERVICE DISCOVERY
REQUIRED ENGINEERING, REVIEW UND RELEASE TEMPLATES


Dieses Dokument enthält verbindliche Vorlagen. Der Coding-Agent soll sie als Dateien im späteren Code-Repository übernehmen und projektspezifisch ausfüllen.


TEMPLATE A — ARCHITECTURE DECISION RECORD


# ADR-XXX: <Titel>


Status:
Proposed | Accepted | Superseded | Rejected | Needs Human Review


Date:
Owner:


## Context
Welche technische oder produktspezifische Entscheidung ist notwendig?
Welche bestehende Spezifikation betrifft sie?
Welche Randbedingungen gelten?


## Decision
Präzise Entscheidung. Keine Marketingformulierung.


## Alternatives Considered
1.
2.
3.


Für jede Alternative:
- Vorteile
- Nachteile
- Security
- Privacy
- Compatibility
- Operational complexity


## Security Impact
Welche Trust Boundary, Inputs, Keys, Berechtigungen oder Limits ändern sich?


## Privacy Impact
Welche Daten werden neu gespeichert/übertragen? Ändert sich Query/Browsing Privacy?


## Protocol/Wire Impact
Keine | kompatibel | breaking
Betroffene Schemas/Testvektoren:


## Storage/Migration Impact
DB/IndexedDB Änderungen:
Migrationsstrategie:
Rollback:


## Testing
Welche neuen Tests beweisen die Entscheidung?


## Documentation
Welche Dokumente ändern sich?


## Human Review
Required:
Reviewer:
Status:


TEMPLATE B — PROTOCOL CHANGE PROPOSAL


# Protocol Change Proposal PCP-XXX


Problem:
Current behavior:
Proposed behavior:
Affected protocol version:
Breaking:
Compatibility strategy:
Migration strategy:
Security analysis:
Privacy analysis:
Resource limits:
New/changed schemas:
New/changed error codes:
Test vectors:
PHP implementation impact:
Client implementation impact:
Operational impact:
Rollout:
Rollback:
Open questions:
Human approvals:


Regel:
Kein breaking Wire Change unter derselben protocol_version.


TEMPLATE C — SECURITY EXCEPTION


# Security Exception SEC-EX-XXX


Requirement/Invariant:
Reason exception is requested:
Affected versions/components:
Threat:
Likelihood:
Impact:
Compensating controls:
User/operator exposure:
Expiration date:
Owner:
Remediation plan:
Tests still required:
Human security approver:
Approval status:


Regel:
Security Exceptions sind zeitlich begrenzt. Keine unbegrenzte “temporary” Ausnahme.


TEMPLATE D — CHANGE SELF REVIEW


Change:
Author/Agent:
Date:


External inputs changed? yes/no
Trust boundary changed? yes/no
New network destination? yes/no
New Web Client network destination, remote asset, CSP weakening, service worker or server-query data flow? yes/no
New dependency? yes/no
New persistent data? yes/no
New secret? yes/no
New log/telemetry field? yes/no
Protocol/Wire change? yes/no
DB schema change? yes/no
IndexedDB change? yes/no
Performance/resource limit changed? yes/no


Security invariants affected:
Tests added/changed:
Negative tests:
Docs changed:
Migration:
Rollback:
Known risks:
Human review required:


Reviewer checklist:
[ ] Fail closed
[ ] bounded input
[ ] bounded work
[ ] no secret logging
[ ] no private Tor key access
[ ] peer remains untrusted
[ ] identity semantics preserved
[ ] query privacy preserved
[ ] errors typed
[ ] tests prove behavior


TEMPLATE E — MILESTONE COMPLETION REPORT


Milestone:
Date:
Status: PASS | BLOCKED | PARTIAL


Deliverables:
Evidence files/commits:
Tests run:
Results:
Static analysis:
Security scan:
Conformance:
Performance:
Documentation:
Security impact:
Privacy impact:
Known issues:
Security exceptions:
Human review required:
Unresolved blockers:
Next milestone:


Rule:
PARTIAL ist kein PASS.


TEMPLATE F — SECURITY BUG REPORT INTERNAL


ID:
Title:
Discovery date:
Reporter:
Affected versions:
Severity:
Component:
Security invariant:
Reproduction summary:
Expected:
Actual:
Impact:
Exploit preconditions:
Root cause:
Fix:
Regression test:
Related variants searched:
Threat model update:
Protocol impact:
Release/advisory impact:
Human reviewer:
Status:


TEMPLATE G — INCIDENT TIMELINE


Incident:
Severity:
Start:
Detected:
Contained:
Resolved:


Timeline:
UTC timestamp | Event | Evidence | Decision | Owner


Impact:
Affected components:
Affected releases:
Affected data/keys:
User/operator implications:
Containment:
Recovery:
Communication:
Root cause:
Contributing factors:
What worked:
What failed:
Action items:
Regression tests:
Docs updated:


TEMPLATE H — DEPENDENCY ACCEPTANCE


Dependency:
Version:
Component:
Purpose:
Runtime or development only:
License:
Maintainer/project health:
Transitive dependency count:
Security/advisory status:
Why standard library/internal small implementation is insufficient:
Browser bundle impact if applicable:
Update strategy:
Removal strategy:
Approved by:
ADR reference:


TEMPLATE I — WEB CLIENT ORIGIN, CSP AND QUERY-PRIVACY REVIEW


Feature/change:
Serving route/origin:
Purpose:
Static assets changed:
New remote dependency:
New network destination:
CSP change:
Service worker introduced:
Browser storage change:
Local-search query network impact:
Server-search query handling impact:
Does query enter URL:
Does query enter logs:
Does query trigger outbound remote work:
Serving-origin trust impact:
Android/Desktop Tor Browser impact:
Accessibility impact:
Privacy disclosure changed:
Tests:
Alternatives:
Human review:
Decision:
TEMPLATE J — NETWORK DESTINATION INVENTORY


Destination type:
.onion node | .onion service | optional clearnet proof


Who initiates:
Why:
Trigger:
Is trigger independent of search query:
Transport adapter:
Proxy path:
Hostname resolution path:
Timeout:
Max bytes:
Redirect policy:
Concurrency:
Rate limit:
Data sent:
Data received:
Stored:
Logged:
Tests:


Rule:
Jede neue Destinationklasse benötigt Threat-Model-Prüfung.


TEMPLATE K — DATA INVENTORY / PRIVACY RECORD


Data field:
Source:
Purpose:
Stored where:
Retention:
Transmitted:
Recipients:
Is user-sensitive:
Is it required:
Deletion/reset behavior:
Export behavior:
Logging:
Security controls:
Tests:


Pflichtfelder unter anderem:
search query
favorites
identity pins
node list
service index
observations
settings
diagnostics


TEMPLATE L — API ENDPOINT REVIEW


Endpoint:
Method:
Authentication:
Public:
Purpose:
Input schema:
Output schema:
Max request:
Max response:
Pagination:
Rate limit:
Cache:
Errors:
Sensitive fields:
Network side effects:
Storage side effects:
Security invariants:
Contract tests:
Abuse cases:


TEMPLATE M — DATABASE MIGRATION REVIEW


Migration:
From schema:
To schema:
Forward steps:
Backward/rollback support:
Data rewrite:
Expected duration:
Disk usage:
Lock behavior:
Crash point analysis:
Backup requirement:
Integrity checks:
Tests:
Production rollout:
Recovery if failure:


TEMPLATE N — RELEASE GATE MATRIX


Version:
Commit:
Protocol versions:
Release candidate:


Columns:
Gate ID
Requirement
Status PASS/FAIL/BLOCKED
Evidence
Test/Report
Reviewer
Exception ID
Notes


Gate families:
SPEC
PROTOCOL
CRYPTO
SECURITY
PRIVACY
PHP
CLIENT
STORAGE
NETWORK
CONFORMANCE
FUZZ
PERFORMANCE
OPERATIONS
BACKUP
RESTORE
UPGRADE
ROLLBACK
SUPPLY_CHAIN
DOCUMENTATION
HUMAN_REVIEW


Rule:
UNKNOWN = FAIL.
SKIPPED = FAIL außer explizit nicht anwendbar + human approved.


TEMPLATE O — RELEASE TEST REPORT


Version:
Build environment:
Toolchain:
Protocol:
DB schema:
Client storage schema:


Unit:
Integration:
Conformance:
E2E:
Security regression:
Fuzz:
Static analysis:
Dependency scan:
Secret scan:
License:
SBOM:
Performance:
Clean install:
Upgrade:
Backup/restore:
Rollback:
Known issues:
Security exceptions:
Human review:
Final recommendation:


TEMPLATE P — CLEAN-ROOM INSTALLATION REPORT


Tester/Agent:
Starting environment:
Documentation version:
Installation steps followed:
Undocumented assumption found:
Failure:
Resolution:
Node health:
Manifest generation:
Peer sync:
Client install:
Client update:
Local search:
Backup:
Restore:
Result PASS/FAIL:


Any undocumented required step is a documentation bug.


TEMPLATE Q — KEY ROTATION RUN REPORT


Key type:
Reason:
Old fingerprint:
New fingerprint:
Backup confirmed:
Rotation record:
Revision:
Tests:
Manifest updated:
Observed by test node:
Client continuity result:
Rollback/recovery option:
Operator confirmation:
Security review:


Never paste private key material into this report.


TEMPLATE R — ONION MIGRATION RUN REPORT


Service Identity fingerprint:
Old Onion:
New Onion:
Reason:
Migration record:
Identity signature valid:
Old endpoint confirmation status:
New endpoint confirmation status:
Client expected UX:
Pins tested:
History preserved:
Rollback/communication:
Human review:


TEMPLATE S — THREAT MODEL FEATURE DELTA


Feature:
Assets:
Actors:
New inputs:
New outputs:
New trust boundaries:
New persistence:
New permissions:
Spoofing risks:
Tampering risks:
Repudiation/audit:
Information disclosure:
Denial of service/resource abuse:
Privilege boundary:
Sybil/abuse considerations:
Privacy:
Mitigations:
Residual risk:
Tests:
Docs:
Approval:


TEMPLATE T — FINAL PRODUCTION HANDOFF


Product version:
Protocol:
Server package:
Client package:
Checksums:
SBOM:
Documentation index:
Install guide:
Upgrade guide:
Backup/restore:
Incident response:
Security policy:
Privacy:
Known limitations:
Release Gate Matrix:
Test Report:
Audit findings:
Exceptions:
Human approvals:
Support/EOL policy:
Next planned work:


FINAL TEMPLATE RULE
Der Coding-Agent darf Vorlagen erweitern, aber keine Pflichtsektion still entfernen. Wenn eine Sektion nicht anwendbar ist, “N/A” plus Begründung eintragen statt sie zu löschen.
