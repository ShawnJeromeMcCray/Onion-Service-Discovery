# 42 - Malicious Node Isolation Client Host and Multi-Source Integrity Architecture

Source: https://docs.google.com/document/d/1FtNkCQMShutKcLzRwVA-LBUS7XuCa_GFCPqgRDty4PY/edit?usp=drivesdk

Source modified: 2026-10-11T07:36:31.223Z

---

ONION SERVICE DISCOVERY
MALICIOUS NODE ISOLATION, CLIENT HOST AND MULTI-SOURCE INTEGRITY ARCHITECTURE


Status
Binding security architecture for browser-based search.


1. Problem
If a Discovery Node serves both:
- executable Web Client code; and
- discovery data,
then that Node can modify the JavaScript that is supposed to keep queries local.


No JavaScript self-check can fully solve this against a malicious origin, because the malicious origin can modify the checking code too.


2. Architectural Response
Separate two roles:


CLIENT HOST
Serves only the audited static Onion Service Discovery Web Local Search application.


DISCOVERY NODE
Serves only Protocol data:
- signed records;
- snapshots;
- chunks;
- Node Descriptors;
- observations;
- changes.


A deployment MAY combine both roles for convenience, but security documentation distinguishes them.


3. Security Benefit
When code comes from a chosen Client Host and data comes from separate Discovery Nodes:
a malicious Discovery Node cannot modify the executable search code.


It can still:
- omit data;
- serve stale data;
- equivocate;
- delay;
- refuse;
- send invalid data.


It cannot forge valid Service signatures or cause query exfiltration through executable code if the Client Host is honest and the browser does not execute Node-provided active content.


4. Client Host Trust
The Client Host remains a trust boundary for:
- local-query privacy;
- UI integrity;
- cryptographic verification code;
- ranking implementation;
- warning display.


A malicious Client Host can break those properties.


5. No False Claim
A browser-only system with remotely supplied executable code cannot cryptographically prove the honesty of the origin that supplied that code without an independent trust anchor.


Therefore Onion Service Discovery does NOT claim to eliminate malicious Client Host risk.


6. Decentralizing Client Hosts
There is no mandatory central Client Host.


Any operator can host the exact reference static client bundle.


Users may choose:
- a Client Host run by a trusted community operator;
- a Client Host run by their own organization;
- their own Onion Service as Client Host;
- convenience mode where a Discovery Node also serves the client.


7. Self-Hosted Client Host
Highest browser-only trust option:
the user/operator serves the audited static client bundle from an Onion Service they control.


This requires no extension and works with ordinary browser navigation.


8. Reference Client Bundle
The reference bundle is:
- static;
- reproducibly built;
- versioned;
- no remote runtime dependencies;
- strict CSP;
- asset hashes;
- SBOM;
- release provenance.


9. Data-Only Discovery Node Interface
Discovery Nodes MUST NOT return executable JavaScript/HTML inside protocol data.


Protocol responses use strict schemas/content types.


Untrusted Service metadata is rendered as text only by the Client Host application.


10. Cross-Origin Data Fetch
If Client Host and Discovery Nodes use different Onion origins, browser access requires an explicit cross-origin design.


Requirements:
- narrowly scoped CORS for public read-only Protocol endpoints;
- no credentialed requests;
- no cookies;
- no Authorization;
- fixed allowed methods;
- fixed content types;
- no user-specific responses;
- no query-bearing requests.


Exact Tor Browser privacy behavior for cross-origin requests MUST be revalidated against current supported versions before freeze.


11. Alternative Proxy Mode
A Client Host MAY proxy generic public snapshots from Discovery Nodes.


Benefit:
simpler browser same-origin model.


Tradeoff:
Client Host sees which upstream Nodes it contacts and can influence source selection.


The proxy MUST remain query-independent.


12. Local Query Rule
Regardless of direct-fetch or proxy mode:
local-mode query MUST NOT influence:
- requested Node;
- URL;
- request body;
- request headers;
- snapshot partition selection;
- timing of on-demand remote Service fetch.


13. Multi-Source Default
The stronger reference profile SHOULD combine generic index data from multiple independent Discovery Nodes.


Example:
3–5 configured sources, operator/user selectable.


Do not use raw majority as identity truth.


14. Union-First Discovery
For discoverability/completeness, client can form a union of valid records from multiple sources.


One Node omitting a Service does not automatically remove it if another source supplies valid evidence.


15. Conflict Preservation
If sources disagree:
- preserve signed evidence;
- validate independently;
- show conflict/freshness;
- do not silently choose the most popular assertion.


16. Source Diversity
Client Host/operator MAY recommend source diversity by:
- distinct operators;
- distinct hosting environments;
- separate Node identities.


This is heuristic, not a cryptographic proof of independence.


17. Service Authenticity
A malicious Node cannot create a valid Manifest for another Service Identity without the relevant Service key.


Client verifies:
- canonical Onion binding evidence;
- Service Identity signatures;
- revision chain;
- rotations;
- migrations;
- fork semantics.


18. Stale Data Defense
Client tracks:
- signed revision;
- previous-hash continuity;
- observation freshness;
- snapshot/checkpoint freshness;
- locally seen newer state where available.


Older state cannot silently overwrite known newer continuity.


19. Node-Signed Snapshots
Each Node SHOULD sign snapshot/checkpoint metadata:
- Node Identity;
- generation time;
- Protocol version;
- record count;
- root/hash;
- previous checkpoint hash where defined.


This provides attribution and tamper evidence.


20. Merkleized Snapshots
For scalable integrity, snapshots MAY use Merkle roots/chunks.


Benefits:
- chunk integrity;
- efficient delta proof;
- compact checkpoint comparison;
- support future transparency/gossip.


21. Equivocation Detection
A malicious Node may serve different validly Node-signed roots to different clients.


Detection improves if:
- clients compare checkpoints from several Nodes;
- Nodes gossip observed checkpoints;
- auditors collect conflicting signed checkpoints.


Two conflicting signed checkpoints for the same Node/sequence become evidence of equivocation.


22. Transparency Witnesses
Future/optional:
independent witness Nodes can sign that they observed a specific Node checkpoint.


Witness signatures are evidence, not global truth.


No majority-of-witnesses identity rule.


23. Gossip
Nodes MAY exchange signed checkpoint observations.


Goal:
make targeted split-view/equivocation harder to sustain undetected.


Privacy:
gossip carries public checkpoint data, not user queries.


24. Pinning Node Checkpoints
A client MAY remember the latest accepted checkpoint for a source.


If later served an older checkpoint:
warn/reject according to rollback policy.


Browser persistence is best-effort; persistent protection improves with self-hosted/trusted local client state.


25. Source Pinning
Users/operators can explicitly pin preferred Discovery Node identities.


This controls source selection only.
It does not make all content from a pinned Node trustworthy.


26. Malicious Node Omissions
Cryptography cannot prove a Node returned every valid Service in existence.


Mitigations:
- multiple Nodes;
- union of valid data;
- checkpoint comparison;
- independent crawling/verification Nodes;
- transparency/gossip;
- user-chosen sources.


27. Malicious Node Fabrication
A Node can fabricate unsigned claims/candidates.


Client MUST distinguish:
- Service-signed records;
- Node-signed observations;
- peer assertions;
- moderation signals.


Node statements never become Service identity truth without Service cryptographic evidence.


28. Malicious Node Ranking
Data Nodes MUST NOT send executable ranking logic.


Reference Client Host computes ranking locally.


If Node supplies suggested score metadata, it is untrusted/non-normative and should be ignored by default.


29. Malicious Node Tracking
Data endpoints:
- no cookies;
- no accounts;
- no per-user tokens;
- no query;
- generic cacheable public data where appropriate.


A Node can still observe connection timing and source IP only through Tor-visible server-side metadata, subject to Tor’s normal anonymity model.


30. Timing Correlation
A client fetching data immediately for each query would leak query behavior indirectly.


Therefore index synchronization is generic and decoupled from search input.


Use scheduled/batched/update-on-open behavior independent of terms.


31. Partition Privacy
If index is partitioned by category/language and client fetches only partitions based on query, that leaks interests.


Reference privacy profile:
fetch partitions independently of individual query, or clearly document weaker behavior.


32. No Result Prefetch
Do not prefetch/open candidate Onion Services based on result visibility or hover.


Only explicit navigation.


33. Static Client Integrity Against Accidental/Tertiary Compromise
CSP, SRI-like asset integrity, release hashes and reproducible builds help detect:
- CDN/deployment corruption;
- supply-chain drift;
- accidental modification.


They do NOT defeat a fully malicious top-level Client Host that can rewrite the HTML and checks.


34. Client Host Comparison
Operators/auditors can compare served assets to published release hashes.


Reference CLI:
web-client:verify <client-host-onion>
MAY fetch static assets and compare against release manifest.


This is external verification evidence.


35. Cross-Host Consensus for Client Build
Optional:
several Client Hosts publish the same release asset manifest/hash.


A user/auditor can compare them out of band.


Again, a malicious page itself cannot be trusted to report its own hash.


36. Trusted Bookmark Concept
A bookmark to a known Client Host Onion improves operator continuity but is not code-integrity pinning.


Do not describe it as cryptographic pinning of the web app.


37. Portable Local Bundle
Future optional advanced mode:
publish a signed static client bundle that advanced users can download and serve locally/self-host.


This provides an independent code trust path without a browser extension.


Platform-specific local-file restrictions and update UX require separate design.


38. Native/Integrated Future Client
A future Tor Browser-integrated or standalone signed client could remove the remote-code-origin trust problem.


Not required for Protocol v1.


39. Convenience Combined Mode
A Discovery Node MAY serve the reference Web Client too.


UI MUST NOT imply stronger isolation than exists.


Documentation can label:
Combined Node + Client Host
versus
Independent Client Host.


40. Recommended Strong Profile
For privacy/security-conscious users:
- trusted/self-hosted Client Host;
- 3+ independent Discovery Nodes;
- generic query-independent synchronization;
- local ranking/search;
- signed records;
- signed Node checkpoints;
- rollback protection;
- conflict preservation.


41. Recommended Simple Profile
For ordinary users:
- visit any reputable participating Onion Service hosting the reference client;
- local search in reference mode;
- optionally compare another Client Host/Node if results look suspicious.


Simple profile is easier but trusts the serving Client Host.


42. No-JavaScript Server Search
Server Search cannot hide the query from the selected server by definition.


It remains a compatibility fallback, not the high-privacy mode.


43. Security UI
Client shows source/index facts without claiming:
“complete”
“globally verified”
“safe”.


Possible status:
- data obtained from N configured sources;
- checkpoint freshness;
- source disagreement detected;
- Service signature valid;
- identity continuity valid.


44. Red-Team Scenarios
- malicious Node omits popular Service;
- malicious Node invents unsigned Service;
- malicious Node replays old snapshot;
- malicious Node equivocates checkpoints;
- malicious Node sends XSS metadata;
- malicious Node sends huge chunk;
- malicious Node changes ranking suggestion;
- compromised combined Node alters JS to leak query;
- honest Client Host + malicious data Node;
- malicious Client Host + honest Nodes.


45. Conformance Profiles
WEB-COMBINED-V1
Client Host and Discovery Node may be same origin.


WEB-SEPARATED-V1
Client Host is distinct from Discovery Nodes and uses reviewed data-fetch/proxy mode.


WEB-SELFHOSTED-V1
Client Host operated by user/operator from verified release bundle.


46. Release Gate
WEB-SEPARATED-V1 cannot be claimed until:
- cross-origin/proxy privacy behavior tested in target Tor Browser;
- no query-bearing network behavior;
- CORS/no-credentials rules verified;
- malicious Node tests pass;
- source union/conflict semantics deterministic.


47. Fundamental Boundary
With no browser extension, native application, locally installed trusted bundle or browser-integrated trust anchor:
the browser must trust the remote origin that provides executable code.


This is not a defect specific to Onion Service Discovery; it is the ordinary Web execution trust model.


The project handles it through role separation, decentralization, reproducibility, independent sources and precise non-claims.


48. Acceptance
PASS requires:
- Client Host role defined separately from Discovery Node;
- data Nodes cannot supply active content to separated client;
- multi-source valid-record union;
- no majority-based Service identity;
- signed Node checkpoints;
- rollback/equivocation handling;
- query-independent synchronization;
- malicious-origin limitation explicit;
- self-hosted Client Host path documented.
