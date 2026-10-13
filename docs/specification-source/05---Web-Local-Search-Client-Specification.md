# 05 - Web Local Search Client Specification

Source: https://docs.google.com/document/d/1UH4HobtYVGVG10nTY8De4C7X-uFIGha1-Cc4XgMigtw/edit?usp=drivesdk

Source modified: 2026-10-12T19:30:22.337Z

---

ONION SERVICE DISCOVERY
WEB LOCAL SEARCH CLIENT SPECIFICATION


Status
Binding reference-client specification.


1. Product Decision
Onion Service Discovery does NOT require or ship a separate browser extension as a core product component.


The primary end-user client is a Web Local Search interface served directly by participating Onion Service Discovery Nodes.


Goals:
- immediate use in Tor Browser on desktop and Android;
- no extension installation;
- no browser permission model;
- no content-script access;
- local search in JavaScript when scripting is available;
- explicit no-JavaScript server-search fallback;
- same Protocol Core and search semantics across Nodes.


2. Core User Flow
User visits:
http://<discovery-node>.onion/onion-service-discovery/


The page:
Step 1 — loads static Web Client assets from the selected Client Host/combined Node according to the active deployment profile;
Step 2 — retrieves generic Onion Service Discovery index/snapshot data from the configured data source(s) according to the active deployment profile;
Step 3 — verifies Protocol records and integrity according to the client capability profile;
Step 4 — builds a search index in browser memory and optionally origin-local browser storage;
Step 5 — executes WEB-LOCAL search queries locally in the browser;
Step 6 — opens a selected Onion Service only after explicit user action.


3. No Extension Dependency
The project MUST NOT require:
- any separate browser-extension installation;
- browser extension permissions;
- Native Messaging;
- Tor Control Port access;
- extra executable;
- page-injection/content scripts;
- browser history access;
- bookmarks access;
- extension update channel.


4. Web Client Route
Recommended route:
/onion-service-discovery/


Optional stable alias:
/onion-service-discovery/search/


The final route is frozen before Production Stable and documented in Node deployment examples.


5. Static Asset Policy
The Web Local Search application is composed of:
- HTML;
- local CSS;
- local JavaScript/TypeScript build output;
- optional local WASM only if separately justified.


No third-party CDN.
No remote fonts.
No analytics scripts.
No tracking pixels.
No remote JavaScript.
No executable code fetched from non-local origins.


6. Web Deployment Profiles
Protocol v1 reference software supports two explicit Web deployment profiles.


WEB-COMBINED-V1:
- one Onion origin serves the static Web Client and generic index/snapshot data;
- no broad CORS is required;
- simplest plug-and-play deployment;
- serving origin is also executable-code trust boundary.


WEB-SEPARATED-V1:
- a Client Host serves the static Web Client;
- one or more selected Discovery Nodes/source groups provide Protocol data only;
- data Nodes cannot provide active client code;
- transport is either reviewed credentialless cross-origin reads to configured data-node origins or a query-independent Client Host proxy/cache, as frozen by D-004/D-044.


Neither profile may make search requests dependent on the user's query in WEB-LOCAL mode.
The project MUST state which profile is active.
7. Local Search Privacy
In honest-reference-client operation:
- query text is processed locally;
- query text is not sent in API URLs;
- query text is not sent in request bodies;
- query text is not sent in headers;
- query text does not select a remote endpoint;
- search-result clicks do not generate Onion Service Discovery telemetry.


8. Web-Origin Trust Boundary
Critical limitation:


A remote Web Client is executable code supplied by the visited Node. A malicious Node can alter that code and could intentionally transmit queries or misrepresent verification results.


Therefore the project MUST distinguish:
A. “Reference Web Client design sends search queries nowhere.”
B. “A malicious serving Node cannot be cryptographically prevented from serving modified browser code.”


No documentation may claim unconditional local-query privacy against the Node that supplies the executable Web Client.


9. Mitigations for Web-Origin Trust
Reference Node release SHOULD:
- serve an immutable versioned client bundle;
- expose build version and bundle hashes;
- include bundle artifacts in release checksums/provenance;
- use strict CSP;
- use no dynamic remote dependencies;
- make source/build reproducible;
- allow operators/auditors to compare served bundle to official release;
- include client version in diagnostics.


These controls improve auditability but do not create a browser-enforced trust root.


10. Optional Signed Client Bundle Metadata
A Node MAY expose a signed/static client-build manifest containing:
- application version;
- source revision;
- asset filenames;
- SHA-256 hashes;
- build provenance reference.


This is primarily for operators/auditors and automated deployment verification.
It is not presented as proof to an untrusted browser that the currently executing HTML itself is honest.


11. JavaScript-Available Mode
When JavaScript works:
- render local search UI;
- fetch generic index/snapshot;
- verify/process records;
- perform local search;
- maintain current page/session state;
- optionally cache public index data locally if browser semantics permit.


12. Browser Storage
IndexedDB/Cache Storage MAY be used opportunistically.


Important:
Tor Browser privacy/private-browsing behavior may make persistence limited, isolated, or ephemeral.
Therefore persistent browser storage is NOT a correctness requirement.


The client MUST remain functional if:
- storage is unavailable;
- storage is cleared on browser exit;
- IndexedDB fails;
- user clears site data.


13. Stateless Session Capability
A first visit can:
- load a bounded current search snapshot;
- search locally in memory;
- open results.


This is the baseline Android-friendly mode.


14. Large Index Strategy
The client MUST NOT require loading an unbounded monolithic index.


Use:
- deterministic chunks;
- bounded snapshot descriptors;
- compressed transport only after safe expanded-size handling;
- incremental decoding/indexing;
- progressive UI readiness;
- optional category/language partitions only if Protocol semantics remain deterministic.


15. Search-Ready Threshold
The UI MAY become usable after a verified minimal/core index is loaded, while optional historical evidence continues in background.


It MUST label:
- loading state;
- index timestamp/state;
- incomplete/partial status if applicable.


16. No Server Query in Local Mode
Local mode MUST NOT call a server-side endpoint such as:
/search?q=...
for ordinary search.


Automated instrumentation tests prove this.


17. No-JavaScript Server Search
A Node MAY offer an optional server-rendered search fallback for:
- JavaScript-disabled configurations;
- browser security settings where scripting is unavailable;
- accessibility or compatibility cases where client-side execution fails.


18. Server Search Privacy Disclosure
Before or immediately above the fallback search form, display a plain-language notice:


“Server-side search: your search terms are sent to this Onion Service Discovery Node. The local-search privacy property does not apply in this mode.”


Wording can be localized but meaning cannot be weakened.


19. Server Search Transport
Use HTTP POST rather than putting the query in the URL.


Recommended endpoint:
POST /onion-service-discovery/search/server


Reason:
avoid query in URL/history/referrer/access-log request target.


20. Server Search Request
Form fields bounded:
query
language optional
category optional
technical filters optional.


No account.
No cookie required.
No JavaScript required.


21. Server Search Logging
Reference configuration MUST NOT log query bodies.


Webserver/application logging examples:
- do not record POST body;
- no application search analytics;
- no query persistence;
- normalized event “server_search_request” MAY be counted without query content.


22. Server Search CSRF
The search operation is read-only and unauthenticated.
No state change occurs.
Do not introduce sessions solely for CSRF.


23. Server Search Rate Limits
Finite:
query length;
requests/work per period;
maximum result count;
maximum search CPU time.


No expensive regex supplied by user.
No arbitrary sort expression.


24. Search Index for Server Fallback
The PHP Node searches its local verified/derived index.
It MUST NOT:
- perform live remote Onion fetches in response to a user query;
- reveal which user searched for which service in long-lived telemetry;
- contact candidate Services based on the query.


25. Query Isolation
Server fallback query processing is isolated from:
candidate discovery;
direct verification;
peer sync.


A rare query must not trigger network discovery.


26. Search Results
Both modes use equivalent semantic result fields:
name
description excerpt
canonical Onion
categories
languages
identity state
liveness state
migration state
moderation signals separately
source/index freshness.


27. Ranking Consistency
Local Web Search and Server Search SHOULD share the same versioned ranking specification.


Given the same index snapshot and filters, results SHOULD be semantically consistent.


Implementation language differences require golden ranking fixtures.


28. Search Score Separation
Text relevance is not trust.
Do not rank a result higher simply because:
- more Nodes reported it;
- it has a “valid signature”;
- it is older;
unless a separate explicit user-selected sort/filter says so.


29. Technical Status Labels
Use precise states:
Manifest signature valid
Onion binding observed/verified as available in index evidence
Known Service Identity
Identity continuity confirmed
Migration confirmed
Identity conflict detected
Stale / long unseen.


No generic SAFE/TRUSTED/LEGIT badges.


30. Pinning in a Web Client
Because browser persistence is origin-local and best-effort, durable cross-origin/cross-Client-Host pin portability is NOT guaranteed. A pin anchors logical_service_id, not the current rotatable Service Identity key.


Possible modes:
A. origin-local browser storage pin, best-effort only;
B. explicit export/import of local pin file;
C. future trusted standalone/native client.


The reference Web Client MUST NOT imply that a pin saved on Node A automatically protects the user on Node B.


31. Origin-Local Pinning
If implemented:
- saved only on the current Web origin;
- no server upload;
- no cookie;
- no remote sync;
- clearly labeled “saved in this browser for this search site”;
- resilient to cache rebuild where browser storage persists.


32. Pin Privacy
Pin operation creates no network request in local mode.
Automated network test verifies.


33. Cross-Origin Pin Export
Optional explicit user export MAY create a local versioned file.
No automatic cloud upload.
Import validates format and size.


34. Client-Side Verification
The reference Web Client SHOULD verify:
- schemas;
- signatures;
- record hashes;
- revision/fork state;
- snapshot/chunk integrity.


However the same-origin malicious-code limitation remains: an altered Web Client can bypass its own checks.


35. Node-Side Prevalidation
The Node serves only locally validated index records in its reference implementation.
This provides defense in depth for honest Nodes.


36. CSP
Reference Web Client uses restrictive CSP:
- script-src self with hashes/nonces as appropriate;
- no unsafe-eval;
- object-src none;
- base-uri restrictions;
- frame restrictions;
- no remote connect destinations except explicitly needed same-origin API;
- no remote image/font sources by default.


37. No Service-Provided Assets
Result cards do not load:
- service favicons;
- logos;
- images;
- scripts;
- CSS
from discovered Onion Services.


Prevents tracking and malicious UI injection.


38. XSS
All untrusted metadata rendered as text.
Never innerHTML from Manifest.
URLs constructed from validated structured fields only.


39. Navigation
Opening a Service requires user action.
Reparse canonical Onion.
Construct destination internally.
No javascript:, data:, file:, custom scheme from metadata.


40. Result Link Privacy
Do not add tracking query parameters.
Use safe referrer policy.
Avoid leaking search query to destination through URL/referrer.


41. Search Query UI
Search term exists only in:
input field;
local memory;
server POST body only in explicitly selected No-JavaScript Server Search.


No auto-save.
No local search history in v1.


42. Browser Back/Reload
If local query is not encoded into URL, browser Back/Reload may lose it.
This is acceptable for privacy.
If client uses URL fragment for purely local state, it must ensure it is never transmitted as HTTP fragment and document behavior.
Default can keep query only in memory.


43. Accessibility
Web Local Search targets WCAG 2.2 AA applicable criteria.
No-JavaScript fallback must also be keyboard/screen-reader usable.


44. Mobile
Responsive UI designed for Android-size screens.
Long Onion addresses and fingerprints wrap/copy correctly.
Touch targets sufficient.
No hover-only controls.


45. Security-Level Compatibility
The product supports:
JavaScript mode where scripting is available;
Server Search fallback when scripting is unavailable.


Do not instruct users to lower Tor Browser security settings merely to use Onion Service Discovery.


46. First-Run Privacy Choice
Page explains:
“Local search: your search terms stay in this browser as long as this page serves the reference client as designed.”
“Server search fallback: your search terms are sent to this Node.”


No misleading absolutes.


47. Node Branding
A deployment MAY wrap the reference client with clearly separated site chrome, but the versioned reference client bundle, security labels, verification logic, CSP assumptions and privacy behavior MUST remain byte-identical to the released reference assets when claiming unmodified Reference Web Client conformance. A customized client is a separate client implementation and must not inherit unmodified-reference privacy/security claims automatically.


48. Client Conformance Profiles
WEB-LOCAL-V1 (query behavior common to both deployment profiles):
- local query instrumentation;
- snapshot processing;
- rendering/security semantics;
- CSP/no remote asset;
- ranking;
- accessibility;
- server-fallback disclosure if provided.


49. Release Artifacts
Web Client release includes:
source;
built static assets;
asset manifest/hashes;
SBOM;
checksums;
provenance;
CSP policy;
browser compatibility test report.


No extension package.


50. Deployment
PHP reference node can serve the built static Web Client under its public directory.
nginx/Apache examples include:
route;
cache headers for versioned assets;
CSP/security headers;
No-JavaScript fallback POST route.


51. Asset Caching
Versioned static assets can be long-cache immutable.
HTML bootstrap should use appropriate freshness policy so updates/security fixes propagate.
No service worker required.


52. Service Worker
Not required in v1.
If introduced:
separate threat/privacy/storage review;
version/update correctness;
cache invalidation;
offline semantics.
Do not add only to call the app a PWA.


53. Installable PWA
Not a requirement.
The key user benefit is direct Web access, especially mobile.
Do not depend on platform “install app” support.


54. Offline Behavior
During an active session with index loaded:
search continues if Node later becomes unreachable.


Across browser restarts:
offline persistence is best-effort and browser-dependent.
Do not promise extension-like persistent offline cache.


55. Multi-Node Choice
Users can manually visit another Discovery Node.
A future static bootstrap page may list configured sources, but no central mandatory search origin.


56. Malicious Node UI Risk
Known limitation:
A Node can:
serve manipulated UI;
omit services;
track server-fallback queries;
serve modified JavaScript.


Mitigations:
distinct selected Nodes/source groups;
reproducible reference client;
published hashes;
source transparency;
user choice;
future trusted native client possibility.


This limitation MUST be in Known Limitations and threat model.


57. Node Search Honesty
No cryptographic mechanism in v1 proves that a server-side search result set is complete.
Server Search is a convenience fallback.
Different Nodes can return different results.


58. Server Search Result Marking
Page should indicate:
“Results generated by this Node from its local index”
and show index freshness.


59. JavaScript Failure Recovery
If JS fails:
render a plain HTML explanation and link/form for server fallback.
No blank application shell.


60. Browser Compatibility Matrix
Test:
Tor Browser Android current target;
Tor Browser desktop current target;
JavaScript enabled;
JavaScript unavailable;
storage unavailable;
small viewport;
slow Node;
large index;
RTL/accessibility.


61. Privacy Tests
Automated:
local query not present in network;
pin/favorite not networked;
no remote assets;
no click telemetry;
server fallback sends query only after explicit form submit;
query absent from URL;
query not written to app logs.


62. Security Tests
XSS corpus
CSP
URL scheme
index poisoning
oversized chunks
storage failure
malicious metadata
same-name identities
fork/migration warnings.


63. Performance
Measure:
first usable index time;
bytes to first searchable state;
memory;
search P50/P95 on Android-class hardware;
chunk processing.


64. Acceptance
Web Local Search is Production Stable when:
- no extension is required;
- Android and desktop Tor Browser flows tested;
- local mode has zero query network side effects in reference build;
- malicious-serving-origin limitation is documented;
- No-JavaScript fallback has explicit disclosure;
- query uses POST and is not logged by reference config;
- CSP/XSS/accessibility/performance gates pass;
- static bundle release/provenance is reproducible.




65. SEPARATED CLIENT HOST PROFILE
The stronger Web profile separates executable code from discovery data.


Client Host:
serves the reference static client bundle.


Discovery Nodes:
serve data only.


The user can choose a Client Host independently from the Nodes used as data sources.


66. SELF-HOSTED CLIENT HOST
An operator/user MAY host the official static client bundle on an Onion Service they control.


This removes dependence on a third-party Node for executable code while retaining ordinary browser use and requiring no extension.


67. MULTI-SOURCE FETCH
WEB-SEPARATED-V1 MUST support a bounded set of configured/selected Discovery Node identities or source groups. The UI MUST NOT infer real-world operator independence from key count.


The client:
- retrieves generic query-independent snapshots/chunks;
- validates all records;
- stores source attribution;
- unions valid discoverable records;
- preserves conflicts;
- tracks signed checkpoint freshness;
- does not use majority vote as Service identity.


68. DATA NODE ACTIVE-CONTENT PROHIBITION
A separated Discovery Node cannot supply:
JavaScript
HTML fragments
CSS
WASM
templates
or other executable/presentational code to the Web Client.


Service metadata is data only and rendered by the Client Host.


69. CENSORSHIP/OMISSION MITIGATION
A Node can omit data, and cryptography cannot prove universal completeness.


Mitigations:
multiple distinct selected Nodes/source groups;
union of valid records;
manual source choice;
checkpoint comparison;
future public checkpoint gossip/witnessing.


70. EQUIVOCATION
Node checkpoints are signed.


If the same Node signs conflicting checkpoints for an equivalent sequence/state position, the client/auditor records a Node equivocation event.


This damages that Node’s source reliability but does not change Service identity truth.


71. CLIENT HOST LIMITATION
A malicious Client Host can still alter the application and leak queries.


No self-verifying JavaScript mechanism from that same malicious origin can solve this completely.


The strongest v1 mitigation is:
self-hosted or consciously trusted Client Host + reproducible bundle + multiple independent data Nodes.




72. CATEGORY REGISTRY UI
Web Local Search MUST render category filters from the generated Protocol Category Registry v1 artifact.


Do not maintain an independent handwritten category list.


73. ADULT CATEGORY
adult is a normal category token.


Client SHOULD provide a local preference:
SHOW
DE-EMPHASIZE
HIDE


This preference is local and MUST NOT be sent to a Discovery Node in local-search mode.


74. ADULT SECURITY EQUALITY
adult-category results receive the same strict rendering/security isolation as every other Service.


No:
remote thumbnails;
automatic media loading;
relaxed CSP;
special HTML parser;
different navigation safety.


75. CATEGORY COUNT NEUTRALITY
Selecting more categories cannot improve trust or ranking solely by count.


76. TAG DISPLAY
If tags are supported, render as plain text with the project's Unicode/control/confusable rules.
