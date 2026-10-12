# 19 - Web Search Client Security Privacy and No-JavaScript Fallback Review

Source: https://docs.google.com/document/d/1_1LTqsSS_402Jyb0IUvZo4y8ZG5geePELgeaJdDwx2I/edit?usp=drivesdk

Source modified: 2026-10-11T07:10:12.022Z

---

ONION SERVICE DISCOVERY
WEB SEARCH CLIENT SECURITY, PRIVACY AND NO-JAVASCRIPT FALLBACK REVIEW


1. Purpose
This document replaces all WebExtension-specific security and permission requirements. Onion Service Discovery no longer depends on a browser extension.


The client is now a Web Local Search application served directly by participating Onion Service Discovery Nodes, with an optional explicit No-JavaScript Server Search fallback.


2. Security Improvement from Removing the Extension
Removing the separate extension eliminates:
- browser extension permissions;
- host-permission prompts;
- content-script attack surface;
- Native Messaging;
- extension-specific fingerprinting changes;
- extension package/update channel;
- extension manifest compatibility burden;
- accidental browsing-history/tab access.


3. New Security Tradeoff
A Web Client is served by the Node the user visits.


That Node controls:
- HTML;
- JavaScript;
- CSS;
- API responses.


A malicious Node can therefore serve modified code that:
- transmits queries;
- lies about cryptographic checks;
- hides services;
- changes ranking;
- changes warnings.


This is a fundamental Web-origin trust boundary.


4. Non-Claim
The project MUST NOT claim:
“a malicious Discovery Node can never see your local-mode search query.”


The correct claim is:
“The reference Web Local Search implementation performs search locally and does not transmit the query.”


5. Why This Tradeoff Is Accepted
Benefits:
- immediate desktop/mobile Tor Browser use;
- no additional extension;
- fewer permissions;
- reduced fingerprinting surface from add-ons;
- simpler onboarding;
- works on Android where extension deployment may be inconvenient or unavailable;
- every participating Node can expose its own search UI.


6. Honest-Node Reference Privacy
For the official/reference build:
network instrumentation MUST prove that a local-mode query causes no:
- fetch;
- XHR;
- WebSocket;
- beacon;
- form submit;
- image request;
- remote resource selection;
whose URL/body/header depends on query text.


7. Same-Origin Index
Preferred:
Web Client downloads a generic current index/snapshot from same Onion origin.


The query never changes which records are downloaded.


8. Remote Assets
Prohibited:
external fonts
analytics
CDN JS
remote styles
result favicons
service logos
tracking pixels
remote recommendation APIs.


9. CSP Baseline
Strict CSP:
default-src 'self' or stricter;
script-src restricted to packaged assets/hashes/nonces;
object-src 'none';
base-uri 'none' or 'self' as required;
frame-ancestors restrictive;
connect-src same-origin protocol endpoints only;
img-src self/data only if needed;
font-src self;
form-action self.


Exact header tested.


10. XSS
Service names/descriptions/categories are untrusted plain text.
Render using safe DOM text APIs.
No network-provided HTML.
No Markdown-to-HTML unless a separately reviewed safe renderer exists; reference v1 should avoid it.


11. DOM Injection
No:
innerHTML with untrusted content
document.write
eval
new Function
unsafe template execution.


12. URL Construction
Only canonical parsed Onion addresses can become result destinations.
Metadata cannot supply:
javascript:
data:
file:
custom scheme
credentials
arbitrary host.


13. Referrer
Use strict/no-referrer policy so local search state is not leaked through navigation referrers.
Search query SHOULD NOT be in URL.


14. Local Query State
Default:
query remains in input/memory.


If URL fragment is used for local state:
- document that fragments are not transmitted in HTTP requests;
- do not mirror it into analytics/logs;
- do not put sensitive diagnostics into fragment.


15. Browser Storage
IndexedDB/Cache/localStorage:
best-effort only.
No correctness assumption of persistence.
No unique installation ID.
No user profile.


16. Local Pinning
If offered:
origin-local browser storage only;
no server upload;
no sync;
no cookie;
no query/pin analytics.


17. Cross-Node Pin Limitation
Pin stored while visiting Node A does not automatically exist on Node B.
UI must explain this if pinning is enabled.


18. Client Bundle Integrity
Reference release publishes:
asset manifest
SHA-256 hashes
source revision
SBOM
provenance.


Operator deployment self-check can compare served assets.


19. Browser Enforcement Limitation
Because the integrity-checking code would itself come from the Node, asset hashes do not create an independent trust root for a user visiting a malicious Node.


This limitation is documented rather than obscured.


20. Node Operator Integrity
Reference deployment tool SHOULD refuse/alert if served Web Client assets differ from installed signed/released asset manifest unexpectedly.


21. No Service Worker Baseline
No service worker in Protocol v1 reference client.


Reason:
- cache/update complexity;
- persistence uncertainty;
- harder incident response;
- not needed for local session search.


Future service worker requires separate review.


22. No-JavaScript Fallback
Optional server-side HTML search.


Purpose:
- JavaScript unavailable;
- JavaScript disabled;
- client runtime fails;
- compatibility/accessibility fallback.


23. Privacy Disclosure
The fallback page MUST clearly state before query submission:
the search terms will be sent to this Node.


No implication that local-query privacy applies.


24. POST Only
Reference server fallback uses POST for query.


Do not use:
GET ?q=...
because it commonly puts query into:
address bar
browser history
server access logs
copied URLs
referrer contexts.


25. Server Search Endpoint
Recommended:
POST /onion-service-discovery/search/server


Request:
application/x-www-form-urlencoded for plain HTML form
or equivalent bounded standard form encoding.


No JavaScript required.


26. No Query Logging
Application:
does not log query.


Webserver:
does not log request body in reference configuration.


Reverse proxy:
reference docs warn against body logging.


27. No Search Analytics
Do not count terms.
Do not build “popular searches”.
Do not retain result clicks.
Aggregate request count without content is acceptable if privacy reviewed.


28. Server Search Limits
max query bytes
max token count
max result count
max CPU/work
timeout
rate limit.


29. Server Search Result Completeness
A server can censor or omit results.
No completeness guarantee.
Page displays Node identity and index freshness.


30. Query-Induced Network Isolation
Server Search works only against local Node database/index.
It MUST NOT perform:
live Onion fetches
candidate discovery
peer lookup
remote moderation lookup
based on query.


31. JavaScript Client Failure
HTML bootstrap includes a usable noscript/fallback section.
Do not render a blank page when JS unavailable.


32. Origin Compromise
If Node’s web server/app compromised:
attacker can alter Web Client.
This can break local query privacy and UI integrity.


Incident response must treat compromised Web Client distribution as privacy/security incident.


33. Static Asset Immutability
Built asset filenames SHOULD include content hash/version.
HTML points to known release assets.
Release update intentionally changes bootstrap.


34. Asset Permissions
Files read-only to runtime where possible.
Web process does not rewrite production JS/CSS on request.


35. Supply Chain
Browser bundle dependencies:
locked;
minimal;
audited;
SBOM;
no remote runtime dependencies.


36. Build
TypeScript strict if used.
Static output reproducible.
No environment-specific secret injection.


37. Browser Feature Detection
Avoid collecting feature fingerprints.
Feature detection can be local functional fallback only.
Do not transmit capability fingerprint.


38. Browser Compatibility
The reference client targets current supported Tor Browser desktop and Android versions defined by refreshed test matrix.


No claim of support without test.


39. Tor Browser Security Settings
The UI supports:
client-side local mode when JS executes;
server fallback when it does not.


Project MUST NOT tell users they must lower Tor Browser security settings.


40. Mobile Security
No third-party deep links.
Copy/open Onion actions explicit.
Responsive layout does not hide identity warnings.


41. Click Behavior
Opening result:
explicit user action;
no prefetch;
no prerender;
no background reachability probe.


42. Browser Speculative Loading
Reference HTML should avoid attributes/mechanisms that prefetch result destinations.
Review browser behavior for preconnect/prefetch and disable where applicable.


43. Search Input Autofill
Search field SHOULD avoid unnecessary browser autocomplete persistence if doing so improves privacy and remains usable.
Exact attributes tested; no unsupported guarantee.


44. Cache Headers
Search-result HTML/server POST response:
no-store preferred for query-containing server results.


Static local client assets:
versioned immutable cache allowed.


Index data:
cache policy chosen for freshness/privacy and no user-specific variation.


45. Server Search HTML
Do not include query in:
page title if avoidable;
external link parameters;
analytics;
hidden tracking fields.


Displaying query back in input/result heading is allowed with proper escaping.


46. Cookies
No cookie needed.
Reference search UI MUST NOT create session cookies.


47. CSRF
Server search has no persistent state change.
No session introduced solely for CSRF.
If future stateful web settings appear, separate threat model.


48. CORS
Same-origin design means broad CORS is unnecessary for Web Local Search.
Default deny/no CORS for browser search endpoints unless a separate interoperability need is approved.


49. API vs Web Routes
Machine Node API and human Web Search routes are distinct.
Search route does not expose admin operations.


50. UI Trust Semantics
A malicious Node can lie in UI; reference client cannot prevent hostile origin code.
Cryptographic Protocol evidence remains useful for independent tools and honest clients.


This distinction belongs in public Known Limitations.


51. Comparing Nodes
Users may choose another independent Node if one appears suspicious or stale.
No single Node is mandatory.


52. Web Client Version
Visible diagnostics show:
client version
Node software version
Protocol version
index state timestamp/root
asset build hash optionally.


53. Reproducible Verification for Operators
CLI:
web-client:verify or security:check
compares deployed static assets against release manifest.


54. Query Privacy Automated Test
Instrument browser networking:
type unique token;
perform local searches;
assert token absent from all outgoing request URLs/bodies/headers;
assert no additional query-dependent request.


55. Server Fallback Privacy Test
Submit unique token:
assert one intended POST contains token;
assert URL does not;
assert app logs do not;
assert no secondary network request includes token.


56. XSS Corpus
Names/descriptions include:
HTML tags
script strings
quotes
Unicode
bidi
long text.
Expected:
text only.


57. CSP Test
Browser automation attempts:
inline injection
eval
remote script
remote image.
Expected blocked according to policy.


58. No Extension Artifact
Release pipeline MUST NOT build or publish a browser extension package as an Onion Service Discovery product artifact.


59. Removed Gates
Delete/replace all previous requirements for:
extension manifest version;
host permissions;
optional permissions;
permission-diff gate;
extension signing/distribution;
content scripts;
Native Messaging;
extension fingerprinting review.


60. Replacement Gates
Production Web Client requires:
CSP review;
origin trust limitation documentation;
local-query network instrumentation;
No-JavaScript fallback disclosure/logging review;
mobile Tor Browser test;
asset provenance;
XSS/navigation tests;
accessibility.


61. Acceptance
PASS only when:
- separate extension has been removed from architecture/release artifacts;
- reference local search emits no query network traffic;
- server fallback privacy difference is explicit;
- no query logging;
- same-origin client works without broad CORS;
- static asset supply chain is reproducible;
- malicious-origin limitation appears in threat model and Known Limitations;
- Android/Desktop compatibility is tested.
.
