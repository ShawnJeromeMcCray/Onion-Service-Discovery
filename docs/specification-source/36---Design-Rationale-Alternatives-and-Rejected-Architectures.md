# 36 - Design Rationale Alternatives and Rejected Architectures

Source: https://docs.google.com/document/d/11YY3OH0OnJtRHf3JkTgidTDigN2GgdSybtmBFjTx7Gs/edit?usp=drivesdk

Source modified: 2026-10-11T07:38:59.963Z

---

ONION SERVICE DISCOVERY
DESIGN RATIONALE, ALTERNATIVES AND REJECTED ARCHITECTURES


1. Purpose
A mature architecture explains not only what it does, but why obvious alternatives were rejected. This document prevents future maintainers from accidentally reintroducing previously rejected centralization, privacy or complexity tradeoffs without understanding the consequences.


2. Why No Central Search Server
Rejected:
single hosted search backend receiving queries.


Reasons:
- query privacy loss;
- single point of failure/control;
- censorship pressure;
- maintainer dependency;
- scaling/abuse concentration.


Chosen:
local search index in client; Nodes distribute public discovery data generically.


3. Why No Central Registry
Rejected:
one canonical registration database.


Reasons:
- operational centralization;
- ownership disputes;
- compromise blast radius;
- availability dependency.


Chosen:
self-published signed Service Manifest + independently mirrored/observed evidence.


4. Why Service Operators Self-Publish
Benefits:
- metadata source is explicit;
- opt-in clear;
- no recurring manual renewal;
- operator controls descriptions/categories/languages;
- cryptographic continuity possible.


Tradeoff:
malicious operators can publish misleading self-description.
Mitigation:
identity semantics separate from trust/moderation.


5. Why a Separate Service Identity Key
Rejected:
use Tor Hidden-Service private key directly in PHP/application.


Reasons:
- expands exposure of Tor’s most sensitive key;
- violates separation of concerns;
- difficult operational permissions;
- application compromise could expose Tor identity.


Chosen:
separate Ed25519 application-level Service Identity key, bound through direct retrieval from Onion address.


6. Why Separate Node Identity
Rejected:
reuse Service Identity key for node observations.


Reasons:
- Node compromise would affect Service continuity;
- distinct roles/trust semantics;
- a node can mirror many Services unrelated to operator identity.


Chosen:
separate Node Identity Ed25519 key.


7. Why Direct Verification
Rejected:
trust peer announcement as authoritative.


Reasons:
- malicious peer can invent metadata;
- stale propagation;
- Sybil amplification.


Chosen:
peer data produces candidates/assertions; local Node directly fetches target Manifest under controlled budget.


8. Why Pull Sync
Rejected:
arbitrary peer push/writes into remote Node DB.


Reasons:
- attack surface;
- authentication complexity;
- unsolicited work;
- harder resource control.


Chosen:
read-oriented public API; Nodes pull bounded state/deltas/snapshots.


9. Why No Majority Vote for Identity
Rejected:
“most Nodes agree” = truth.


Reasons:
- Sybil attacks;
- unknown operator independence;
- easy low-cost identities.


Chosen:
cryptographic Service evidence + local direct observations; Node counts informational only.


10. Why No Blockchain
Rejected as core:
global blockchain/log for Service registration.


Reasons:
- unnecessary consensus cost;
- public permanence/privacy complications;
- new token/economic/governance surface;
- does not inherently solve naming/phishing/Sybil;
- harder deployment.


Chosen:
signed self-publishing + decentralized mirrors + possible later transparency mechanisms.


11. Why No DHT in Protocol v1
Rejected as initial core:
full distributed hash table for discovery.


Reasons:
- routing/Sybil/eclipse complexity;
- implementation burden;
- privacy analysis;
- no need to prove core identity/sync concept.


Chosen:
multiple Onion Nodes with peer discovery, snapshots/deltas and local bootstrap.
Future DHT possible only if measured need.


12. Why PHP Reference Node
Reasons:
- widely deployable on ordinary web hosting/server stacks;
- integrates with nginx/Apache/PHP-FPM;
- no custom mandatory binary/daemon;
- operator familiarity;
- ext-sodium/cURL plus a selectable storage backend; no universal SQLite assumption.


Tradeoff:
not necessarily optimal for very high-throughput nodes.
Protocol remains language-independent.


13. Why SQLite
Reasons:
- zero external DB service;
- simple backup;
- excellent small/medium deployment;
- transactional;
- operator-friendly.


Tradeoff:
extreme scale/multi-writer workloads may favor different backend.
Storage abstraction preserves future choice.


14. Why Web Local Search Is the Primary Client
Constraint:
Tor must not be forked, and mobile/desktop users need immediate search without installing an additional browser component.


Benefits:
- opens directly on a participating Onion Service;
- works on Android and desktop Tor Browser;
- local query processing when JavaScript is available;
- no extension permissions;
- no content scripts;
- no extension-specific update/signing path;
- simpler onboarding.


Tradeoff:
the serving Node controls the executable Web code and could violate reference query-privacy behavior.


Chosen mitigation:
reproducible static client bundle, strict CSP, published hashes/provenance, independent Nodes, explicit public limitation, and no false claim that a remote page can prove its own honesty.


15. Why the Web Page Is Now the Primary Client
Previously a pure Web page was considered secondary because of JavaScript/storage/origin constraints.


The architecture now deliberately accepts those limitations because:
- Android usability without an extension is more important;
- persistent storage is optional rather than required;
- same-origin index serving avoids broad CORS;
- no-JavaScript users can use a clearly disclosed server-search fallback;
- the protocol remains client-independent for future native/standalone clients.


The Web Client therefore treats in-memory local search as the baseline and origin-local persistent storage only as an optimization.


16. Why Not WebRTC
Rejected:
browser peer networking.


Reasons:
- unnecessary IP/privacy complexity;
- Tor Browser protections;
- does not provide Onion-service listening capability;
- attack surface.


17. Why Browsers Do Not Directly Become Nodes
Constraint:
ordinary extension cannot expose a stable Onion Service listener without deeper Tor integration/native control.


Chosen:
stable Onion Services act as Nodes; browser is outbound consumer.


18. Why Well-Known Manifest
Benefits:
- deterministic location;
- self-hosted;
- no crawling ambiguity;
- compatible with established Internet well-known concept;
- easy operator deployment.


19. Why Fixed Identity Verification Path
Rejected:
peer supplies arbitrary metadata URL.


Reasons:
SSRF, redirects, ambiguity, tracking.


Chosen:
construct fixed Well-Known path internally on validated canonical Onion address.


20. Why RFC 8785/JCS
Need:
same signed semantic JSON in PHP/TypeScript/other languages.


Rejected:
default serializer output.


Reason:
property order/number/string differences.


Chosen:
standard deterministic canonicalization, pending frozen test vectors.


21. Why SHA-256 for Application Content Hashes
Reasons:
widely implemented
sufficient security margin for content addressing in context
interoperability.
Not confused with Tor Onion checksum, which follows Tor’s SHA3-256 construction.


22. Why Revision Chain
Need:
detect replay/rollback and preserve state history.
Sequence alone without previous hash would not cryptographically link history.


23. Why Forks Are Preserved
Rejected:
last write/time/majority wins.


Reasons:
can hide compromise or malicious equivocation.
Chosen:
store conflict evidence and make it visible.


24. Why No Automatic Key-Loss Recovery Authority
Rejected:
project-maintainer/master recovery key.


Reasons:
central takeover power;
compromise blast radius;
governance complexity.


Chosen:
backup old Service Identity key; loss may mean continuity cannot be proven.


25. Why Moderation Is Separate
Rejected:
moderation flag changes cryptographic verification status.


Reason:
confuses “who published it” with “is it considered harmful”.
Chosen:
separate locally selected moderation/provenance layer.


26. Why Local Pins
Benefits:
strong protection against same-name replacement;
no central trust service;
user-specific identity memory stays local.


27. Why No Global Popularity Ranking
Rejected:
collect clicks/search analytics to rank services.


Reasons:
privacy;
gaming;
central telemetry.
Chosen:
local textual relevance and explicit technical signals.


28. Why No Auto-Delete on Downtime
Tor Services can be intermittent.
Outage is not proof of abandonment/compromise.
Use liveness decay states.


29. Why Multiple Seeds
One seed = operational single point.
Several seeds + learned peers + manual sources improve resilience.
Still acknowledge bootstrap problem.


30. Why No Mandatory Accounts
Search/index sync is public data.
Accounts would add tracking, auth infrastructure and centralization without core need.


31. Why No Remote Admin API
Local CLI is simpler/safer.
Remote admin would add authentication/CSRF/credential attack surface.


32. Why Explicit Resource Limits
Discovery inherently converts untrusted metadata into work.
Unbounded “flexibility” is a DoS vulnerability.


33. Why Formal Model
Critical state transitions have enough complexity that prose/tests alone may miss sequence bugs.
Model checking provides counterexamples for state logic.


34. Why Independent Conformance Suite
Reference implementation cannot be sole definition of protocol.
Third-party interoperability requires objective black-box vectors.


35. Why Synthetic Dataset
Avoid real-user privacy issues and public-network dependency; reproducible performance and UI testing.


36. Why Accessibility Is Security-Relevant
An inaccessible identity warning is effectively absent for some users.
Warning comprehension is part of anti-phishing design.


37. Why Standards/IANA Planning
A stable public protocol should avoid accidental namespace collisions and proprietary error/schema conventions where established standards exist.


38. Why Not Register Too Early
Premature IANA/public standard commitment can freeze poor semantics.
Registration follows Protocol v1 stabilization, not the reverse.


39. Why Protocol and Software Versions Differ
Implementations evolve more frequently than wire semantics.
Separating versions allows bug/security releases without protocol churn.


40. Why Known Limitations Are First-Class
Security credibility improves when claims are bounded honestly.
No “perfect security” marketing.


41. Rejected Architecture Register
Maintain machine-readable/docs table:
proposal
date
reason rejected
conditions for reconsideration
related ADR.


42. Reconsideration Rule
A rejected architecture can be revisited if:
measured problem changes;
new platform capability exists;
new threat evidence;
research proves benefits.
Requires new ADR, not casual reversal.


43. Acceptance
Design rationale PASS means every major architectural constraint has documented:
problem
alternatives
tradeoffs
security/privacy impact
conditions for reconsideration.
