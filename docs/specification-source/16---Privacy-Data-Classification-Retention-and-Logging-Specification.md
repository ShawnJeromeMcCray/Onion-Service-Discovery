# 16 - Privacy Data Classification Retention and Logging Specification

Source: https://docs.google.com/document/d/1afBIjZ14dkuQdu857tUjSvX-aANQmxJa7GjyzSBhFAI/edit?usp=drivesdk

Source modified: 2026-10-12T18:11:27.316Z

---

ONION SERVICE DISCOVERY
PRIVACY, DATA CLASSIFICATION, RETENTION AND LOGGING SPECIFICATION


1. Purpose
Privacy is a protocol/product property, not a privacy-policy afterthought. This document defines what data exists, where it may flow, what must never be collected, how long data is retained, and how diagnostics avoid becoming user surveillance.


2. Privacy Objectives
PRIV-001 In WEB-LOCAL mode, search queries remain local to the browser and cause no query-dependent network request. The optional No-JavaScript Server Search is a separate disclosed privacy profile in which the selected serving server receives the POSTed query.
PRIV-002 Browsing history is not automatically reported to Discovery Nodes.
PRIV-003 No persistent global user identifier is required.
PRIV-004 Standard node sync does not require user accounts, cookies or login.
PRIV-005 Third-party analytics are absent by default.
PRIV-006 Logs minimize network/client-identifying metadata.
PRIV-007 Service operators explicitly opt in to public discovery.
PRIV-008 User-local pins/favorites remain local unless the user explicitly exports them.
PRIV-009 Diagnostics do not expose secrets or query history.
PRIV-010 Anti-abuse controls do not silently create tracking profiles.


3. Data Classes
Class A — Public Protocol Data
Intended to be public:
Service Manifest
Service public key
Onion address
Node Descriptor
Node public key
public DirectObservation records
public snapshot/index records
public rotation/migration records


Class B — Public-but-Operational
Peer health categories
snapshot IDs
sync cursors if non-secret
capability/version metadata
liveness timestamps


Class C — Local User Data
search queries in memory
favorites
identity pins
selected sources/moderation preferences
client settings
local diagnostics/cache state


Class D — Operator-Sensitive
private Service Identity key
private Node Identity key
production configuration secrets
filesystem paths
backup encryption material
CI/release credentials


Class E — Security Diagnostic
quarantine reason
error categories
bounded malicious record samples if deliberately retained
incident evidence


4. Search Query Lifecycle
WEB-LOCAL mode:
- query enters browser UI;
- is normalized/tokenized locally;
- queries the locally loaded index;
- renders results;
- causes no query-dependent request, destination, header or payload;
- is not included in support logs;
- is not persisted by default.


SERVER-FALLBACK mode:
- user is explicitly told before submission that the selected serving server receives the query;
- query is submitted by POST only;
- query is processed only against the server's already-local active search index;
- query is not logged/persisted by the reference implementation;
- query triggers no remote Service/peer/moderation fetch.


If optional local search history is ever introduced:
- off by default;
- local only;
- clear retention/delete controls;
- separate privacy review.
Protocol v1 reference client SHOULD omit search history entirely.


5. Network Update Independence
Index update scheduling MUST NOT be triggered synchronously by query text.
A user searching “X” should not cause a node fetch containing, identifying or predictably encoding “X”.
Updates use startup/periodic/manual generic triggers.


6. Browser Navigation
When user explicitly opens an Onion result, normal Tor Browser navigation occurs outside the Discovery protocol’s search-index request flow.
Reference client MUST NOT send a “clicked result” event to Discovery Nodes.


7. Favorites
Favorite:
local convenience state.
Stored locally.
No automatic node sync.
Export only by explicit user action if feature exists.


8. Identity Pins
More sensitive than favorites because they reveal which identities user deliberately recognizes.
Local only.
Not included in diagnostics by default.
Export encrypted or clearly user-controlled if export exists.
Cache reset SHOULD preserve pins unless user explicitly requests full reset.


9. Node Request Metadata
A node necessarily receives transport-level connection traffic through Tor, but application design SHOULD avoid creating additional identifiers:
no user account
no cookie
no install UUID
no auth token for public reads
no query fingerprint
no persistent client-specific header unless protocol requires.


10. User-Agent Policy
Reference client/node sync User-Agent behavior requires privacy review.
Prefer generic project/version or minimal standard header if required for interoperability.
Do not generate per-installation variations.


11. Headers
Do not send:
local hostname
OS-specific unnecessary data
browser locale unless protocol specifically needs it
unique client ID
favorite/pin counts
search terms


12. Logs — Node
Default production logs MAY contain:
timestamp
event code
severity
endpoint class
response status
bounded peer/service hash or canonical public identifier where necessary
work-budget/rate-limit counters


Default logs SHOULD NOT contain:
WEB-LOCAL queries — none reach the server; SERVER-FALLBACK queries — received only for in-request processing and prohibited from application/webserver body logging in the reference deployment
full client fingerprint
cookies
private keys
authorization secrets
full configuration
arbitrary request body dumps
complete malformed attacker blobs
unnecessary remote address retention.


13. Tor Client Address Logging
Because Onion Service connections arrive through Tor infrastructure and server-side networking semantics can differ, the operator must not assume observed network addresses identify end users.
Reference configuration SHOULD avoid retaining unnecessary access-log identifiers and document webserver log minimization.


14. Webserver Access Logs
Provide hardened nginx/Apache examples with:
- minimal log format;
- optional disabling of public Discovery access logs if operator needs no audit trail;
- no query string for endpoints where unnecessary;
- rotation;
- retention cap.


15. Security Event Logging
Security-relevant events can be logged:
invalid signature
protocol violation
oversized document
rate-limit event
repeated peer failure
fork evidence
storage integrity issue


Logs record normalized error codes and bounded identifiers.
Never log private material.


16. Log Injection
Attacker-controlled strings:
- normalized/escaped;
- structured fields;
- length bounded.
Prefer hashes/error IDs to raw untrusted values.


17. Retention Matrix
Service identity/revision history:
long-lived because continuity depends on history; retention linked to storage budget and archival policy.


Direct observations:
bounded historical window or compressed summaries; enough for diagnostics/continuity evidence.


Peer assertions:
shorter-lived/rebuildable.


Candidates:
expire after rejection/backoff policy unless needed for security evidence.


Peer health:
short-lived operational.


Snapshot/chunk cache:
rebuildable; aggressive retention allowed.


Quarantine:
bounded by security evidence policy and disk budget.


Node logs:
short, documented retention.


Client index:
until user reset/rebuild; public data.


Client pins:
until explicit user deletion/full reset.


18. Data Minimization Rule
If a field is not required for:
protocol correctness
security
operations
explicit user feature
do not collect/store it.


19. Quarantine Privacy
Quarantine can hold attacker-controlled data.
Preferred:
store:
record hash
source class
error reason
first/last seen
small bounded sanitized excerpt only if essential.


Avoid unlimited raw HTTP body retention.


20. Support Bundle
A “Copy diagnostics” feature MUST exclude by design:
search query history
favorites
pins unless user explicitly selects identity diagnostic
private keys
full production config secrets
raw browser history


Include:
app version
protocol version
storage schema/backend format
source health
generic error classes
index size/count
last sync time


21. Crash Reporting
Reference project:
no automatic third-party crash reporting.
If future optional crash reporting:
explicit opt-in
data preview
redaction
separate privacy ADR
no private keys/search history.


22. Telemetry
No remote telemetry by default.
If project later considers metrics:
must be aggregate/minimized
opt-in or separately justified
not needed for core operation
cannot affect access.


23. Analytics
No Google Analytics, tracking pixels, ad network SDKs or remote analytics in Web Local Search or Node UI.


24. Remote Assets
No remote font/image/script required by reference client.
Service-provided metadata does not cause background asset fetches.


25. Clearnet Proof Privacy
Optional Clearnet identity proof verification is performed by dedicated nodes/operator logic, not tied to a user’s search.
Client need not contact Clearnet domains for every result.


26. Moderation Source Privacy
Client downloads selected moderation lists generically.
Do not query a moderation server with “is service X bad?” per user search; that leaks interests.
Prefer whole/delta list synchronization.


27. Source Selection
User-selected custom Node sources are local settings.
Diagnostics may include their public Onion addresses only when user explicitly exports support information or because they are non-sensitive public config; default support bundle can include count and health, not necessarily full custom list.


28. IP/Clearnet Prohibition
Reference client does not use direct clearnet fallback for Discovery when Onion nodes fail.
A fallback would materially alter privacy and requires new architecture/human approval.


29. Backup Privacy
Operator backups contain:
private application keys
storage/public metadata
config
potential logs
Treat as sensitive.
Encrypt according to operator environment.
Test restore without exposing secrets into logs.


30. Client Backup
Browser profile backup may contain pins/settings/index.
Project does not upload it.
User documentation notes local profile sensitivity.


31. Data Export
If user export functionality exists:
clear file contents;
no hidden telemetry IDs;
versioned format;
explicit confirmation;
public index export separated from private local pins/settings.


32. Data Deletion
Client:
clear cache/index
clear selected settings
clear pins/favorites
full reset
must be distinct actions where accidental loss risk exists.


Node:
public protocol identity history may be retained for continuity/security even if a service sets publish=false, but normal search export removes/inactivates it according to protocol policy.
Document historical-retention rationale.


33. Service Withdrawal
publish_service=false or manifest removal:
does not retroactively erase cryptographic observations from all decentralized nodes.
UI/search policy:
stop normal active discovery display after verified withdrawal/staleness policy.
Documentation MUST explain decentralized persistence limits.


34. Right-to-Delete Claims
Project MUST NOT promise global deletion from independent decentralized nodes.
It can provide reference-node retention/withdrawal behavior and local deletion controls.
Legal obligations depend on operator jurisdiction and require legal review outside this technical spec.


35. Jurisdiction
Reference project avoids centralized collection partly to reduce unnecessary controller-like data concentration.
Project documentation must not claim legal compliance in all jurisdictions.
Operators are responsible for local legal obligations.


36. Data Protection Impact Review
Before production, create a structured privacy review:
data inventory
purpose
lawful/operational rationale as appropriate
retention
recipients
user controls
security controls
residual risk


This is a technical privacy assessment, not legal advice.


37. Threats — Query Correlation
Potential leak channels:
network request immediately after query
different source selection by query
remote image fetch from result
moderation lookup per result
telemetry event
error report containing query


Tests MUST cover all.


38. Threats — Pin Exposure
Pin list reveals trusted/interested services.
No remote sync in v1.
No automatic support export.


39. Threats — Timing
Periodic update timing can still create traffic patterns.
Use generic schedule independent of query.
Optional jitter to avoid synchronized client population.
Do not overclaim resistance to global timing analysis.


40. Threats — Node Observations
A Node performing direct verification reveals to Tor that it is connecting to a service, but it is a Discovery Node role voluntarily doing so.
Normal user client SHOULD not directly verify arbitrary service candidates in v1.


41. Privacy Test Matrix
Test:
query causes zero node request
click causes no Discovery telemetry
favorite causes no network
pin causes no network
result rendering loads no remote asset
moderation filtering local after list sync
diagnostics excludes query/pins
cache rebuild preserves pins only when the pin store is distinct and actually persisted; if browser storage was cleared/unavailable, no persistence is falsely claimed
full reset deletes local private preference data.


42. Retention Configuration
Reference node config must define explicit finite values for:
log retention
peer assertion retention
candidate history
quarantine metadata
snapshot cache
stale peer data


Service continuity history may be “retain unless operator archival policy” but DB budget and export behavior remain bounded.


43. Privacy Documentation for Users
Plain-language section:
What leaves your browser?
What stays local?
What nodes can observe?
What is public by design?
What does uninstall/reset delete?
What can decentralized operators retain?


44. Privacy Documentation for Operators
Explain:
public nature of manifest
public Node descriptor
access log minimization
backup sensitivity
withdrawal limitations
optional moderation/Clearnet features.


45. No Dark Patterns
Do not enable telemetry, broad permissions or public publishing through misleading defaults.
publish_service is an operator explicit opt-in.


46. Privacy Review Gate
Any feature that adds:
account
cookie
persistent ID
remote telemetry
cloud sync
query API
click tracking
personalization
recommendations based on behavior
requires Privacy ADR and human review.


47. Data Inventory Artifact
Agent MUST maintain:
docs/privacy/data-inventory.csv or markdown table
with:
field
classification
source
purpose
storage
transmission
retention
deletion
tests.


48. Network Data-Flow Artifact
Maintain diagram:
Client -> Nodes: generic index sync only.
Node -> Services: direct protocol verification.
Node -> Nodes: sync.
Client -> Onion result: user navigation via Tor Browser, not Discovery telemetry.


49. Acceptance
Privacy is PASS only if architecture and automated instrumentation prove:
no query exfiltration
no click telemetry
no mandatory account/cookie
no remote assets
no automatic visited-service reporting
bounded/minimal logs
documented retention
explicit serv


50. ARCHITECTURE REVISION — WEB LOCAL SEARCH
The separate browser extension is removed.


Primary client:
a Web Local Search application served by a selected Discovery Node.


Reference-build privacy:
search queries remain in browser memory and do not cause query-dependent requests.


Critical caveat:
the serving Node supplies the executable Web code. A malicious/compromised Node can serve modified code that violates the reference privacy behavior. Therefore local-query privacy is a property of the reference Web Client build and honest deployment, not an unconditional guarantee against the serving origin.


51. SERVER-SEARCH DATA CLASS
Optional No-JavaScript Server Search introduces a distinct data class:
SERVER_SEARCH_QUERY


Source:
user-submitted POST form.


Purpose:
produce server-rendered results from the Node’s local index when local scripting is unavailable.


Transmission:
to the selected Node only.


Persistence:
none in reference implementation.


Logging:
prohibited in application logs; webserver/reverse-proxy examples MUST NOT log POST bodies.


Retention:
zero beyond in-request processing unless a legally/operationally required deployment explicitly changes this, which would be outside reference privacy behavior and must be disclosed.


52. SERVER-SEARCH DISCLOSURE
The fallback UI MUST state before submission that the query will be sent to the Node.


The project MUST NOT present server-search mode as having the same query-locality guarantee as Web Local Search.


53. NO QUERY IN URL
Server fallback uses POST.
Query MUST NOT be encoded in the request URL by the reference implementation.


54. NO QUERY-DRIVEN REMOTE WORK
Server Search:
- does not contact discovered Services;
- does not trigger Candidate verification;
- does not sync Peers;
- does not call remote moderation endpoints.


This prevents the query from creating observable third-party traffic.


55. WEB CLIENT STORAGE
Origin-local IndexedDB/Cache Storage is optional and best-effort.
No persistent client installation identifier.
No account.
No cloud sync.
No assumption that Tor Browser preserves site data between sessions.


56. ORIGIN-LOCAL PINS
If Web pinning is implemented:
- anchors the stable logical_service_id plus remembered current key epoch/Onion context;
- remains local to the browser origin;
- never sent to Node;
- no automatic cross-Node portability;
- export/import only by explicit user action.


57. WEB CLIENT NETWORK-FLOW DIAGRAM UPDATE
Web Local Search:
WEB-COMBINED-V1: Browser -> selected Node/Client Host: static assets + generic index/snapshot/chunks.
WEB-SEPARATED-V1 direct profile: Browser -> selected Client Host for code; Browser -> configured credentialless data-node origins for generic Protocol data.
WEB-SEPARATED-V1 proxy profile: Browser -> selected Client Host; Client Host obtains/caches generic query-independent Protocol data from configured data Nodes.
WEB-LOCAL search query: browser-local only in the unmodified reference build; no query-dependent network side effect.
Browser -> result Onion: only after explicit user navigation.


Server Search fallback:
Browser -> selected serving Client Host/combined Node: POST containing query after explicit disclosure.
Selected server -> its already-local active search index only.
No query-dependent outbound network call.


58. PRIVACY ACCEPTANCE UPDATE
PASS requires:
- no separate extension;
- local-mode query absent from all network instrumentation;
- server fallback explicitly disclosed;
- server query absent from URL/logs;
- no remote assets/analytics;
- malicious-serving-origin limitation documented.
