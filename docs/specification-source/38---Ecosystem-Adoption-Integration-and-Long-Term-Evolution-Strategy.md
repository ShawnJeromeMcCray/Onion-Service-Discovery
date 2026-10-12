# 38 - Ecosystem Adoption Integration and Long-Term Evolution Strategy

Source: https://docs.google.com/document/d/1NbPjqAkr5s0MiM5lCU98zgFDwaa30PoUlpiM5OodmiE/edit?usp=drivesdk

Source modified: 2026-10-11T07:13:14.985Z

---

ONION SERVICE DISCOVERY
ECOSYSTEM ADOPTION, INTEGRATION AND LONG-TERM EVOLUTION STRATEGY


1. Purpose
A technically excellent protocol can still fail if deployment is too difficult, integration is unclear, or long-term evolution depends on one maintainer. This strategy defines a path from reference project to durable ecosystem.


2. Adoption Principles
- publish-only participation must be simple;
- full Node operation remains optional;
- Protocol independence from one client/server;
- no mandatory commercial service;
- transparent governance;
- clear compatibility;
- no false “official Tor integration” claim.


3. Adoption Layers
Layer A — Service Publisher
Only publishes signed Manifest.


Layer B — Mirror Node
Serves verified public index/sync data.


Layer C — Discovery Node
Verifies Candidates and contributes observations.


Layer D — Client
Consumes index and searches locally.


Layer E — Tooling/SDK
Validators, integrations, research tools.


4. Publish-Only Integration
Goal:
existing Onion Service operator can participate without running a full Discovery Node.


Provide:
CLI manifest generator;
PHP library;
static JSON generation;
webserver deployment examples;
rotation/migration commands;
self-check.


5. Existing Stack Guides
Document:
nginx + PHP-FPM
Apache + PHP
static site generation
containerized deployment optional.
No requirement to reorganize the operator’s whole service.


6. Integration Contract
For publish-only:
operator needs:
canonical Onion address;
Service Identity key;
metadata;
Well-Known route.


No recurring registration or central API token.


7. Full Node Guide
Community operators get:
capacity planning;
role configuration;
Tor SOCKS;
scheduler;
backup;
monitoring;
abuse limits;
seed/peer management.


8. Seed Ecosystem
Initial seed set is bootstrap convenience.
Long-term healthy network should have:
multiple operators;
different jurisdictions/providers where practical;
transparent criteria;
easy manual source addition.


9. Community Node Directory
If project publishes a list of known Nodes, treat it as informational bootstrap material, not trust authority.
Format signed/reproducible if distributed with software releases.


10. Reference Client Distribution
Distribution channel chosen through Human Gate.
Documentation distinguishes:
Web-serving-origin trust and JavaScript/browser-storage limitations;
protocol compatibility;
stock Tor Browser considerations.


11. Future Native Integration
Long-term desirable possibility:
Tor Browser/native upstream integration.
Not assumed.
If pursued:
prepare protocol docs, privacy evidence, permission reduction rationale and test vectors for upstream discussion.


12. Alternative Clients
Encourage:
CLI
desktop
research
accessibility-focused
future native integrations.
Must pass conformance.


13. Operator Feedback Loop
Collect:
deployment pain;
performance;
false warnings;
sync behavior;
without requiring telemetry.
Use issue tracker/community reports and synthetic reproducers.


14. Adoption Telemetry
No mandatory product telemetry.
Project adoption estimates can use:
voluntary operator reports;
public Node lists;
release download statistics from hosting if available;
never user search/click collection.


15. Documentation Entry Points
Different personas:
“I run an Onion Service”
“I want to run a Node”
“I want to use/search”
“I want to implement the Protocol”
“I want to audit/security review”
“I want to contribute”.


16. Five-Minute Operator Contract
One short document answers:
what becomes public;
what key must be backed up;
what files are installed;
what network traffic occurs;
how to opt out;
what withdrawal cannot erase globally;
how to migrate address.


17. First-Run Success
Publish-only operator can verify:
Manifest locally;
Manifest via own Onion;
Node sees it;
without external maintainer action where connected discovery path exists.


18. Discovery Bootstrapping for New Service
Path options:
link/reference from known participant;
operator manually submits Candidate to a Node if supported;
operator asks independent Node operator;
future standardized announcement.
No recurring registration.


19. Community Testing Events
Periodic interoperability/test events:
different implementations;
shared synthetic fixtures;
public reports.
Avoid testing abusive load on public Tor.


20. Protocol Maturity Labels
Experimental
Draft
Candidate
Stable
Deprecated.


Software maturity separate:
Alpha
Beta
RC
Stable.


21. Backward Compatibility Commitment
For stable Protocol:
document support horizon;
avoid gratuitous breaking changes;
provide transition period for new version.


22. Protocol v2 Rule
Only when concrete limitations require breaking change.
Do not create v2 for implementation refactor.


23. Web Client Optional Features
Prefer optional capability tokens for genuinely optional behavior that can be safely ignored.
Never use capability as escape hatch for ambiguous identity semantics.


24. Ecosystem Registry
Maintain:
known conformant implementations;
Nodes optionally self-listed;
SDK packages;
Protocol versions;
conformance reports.
Informational, not authority.


25. Security Advisory Distribution
Provide standard channels:
repository security advisory/SECURITY file;
release notes;
signed release metadata where feasible.
Node/client should expose version so operator can check support.


26. EOL Communication
Version EOL:
date
last security update
upgrade path
Protocol support.
No silent abandonment.


27. Maintainer Succession
Document:
release process;
signing/key custody;
domain/registry control;
IANA change-controller succession;
seed list governance.
Avoid founder-only undocumented credentials.


28. Bus-Factor Drill
A different maintainer should be able to:
build release
run conformance
publish docs draft
operate test lab
without original author’s local machine.


29. Standards Engagement
If public adoption grows:
consider public Internet-Draft/IETF community discussion where appropriate;
IANA well-known registration;
Tor community feedback.
No standards-body claim before actual process.


30. Tor Community Respect
Project documentation should proactively acknowledge:
Web-origin integrity/query-privacy limitation;
no Tor fork;
no replacement for Tor;
no private Tor key access.
Seek technical feedback rather than implying endorsement.


31. Research Collaboration
Formal model/test corpus can support:
universities;
security researchers;
privacy researchers.
Use synthetic datasets.


32. Vulnerability Research
Provide clear safe disclosure.
Test lab reduces need to probe real nodes/services.


33. Translation Community
Protocol normative English.
User docs can be translated.
Security-sensitive translation review process.
Translation does not alter Protocol terms/identifiers.


34. Accessibility Adoption
Ensure operator/client docs usable with assistive technology.
Accessibility feedback channel.


35. Packaging Ecosystem
Future packages:
Composer
Web Local Search client
possibly OS/container/community packages.
Third-party package status clearly labeled; official reference release hashes/provenance remain source of truth.


36. API Stability for SDK
Document deprecation window.
Avoid churn that punishes adopters.


37. Example Integrations
Synthetic examples:
blog
forum
documentation service
multi-language service
migration.
No promotion of real risky content.


38. Adoption Barriers Review
Before stable release, have independent operator try:
publish only
full node
client.
Every undocumented workaround becomes issue.


39. Sustainability
Core network requires no paid backend.
Project maintenance can continue as ordinary open-source source/spec releases.
Donations/sponsorship if ever used must not buy trust ranking/seed authority.


40. Commercial Neutrality
No pay-to-verify.
No paid cryptographic trust.
If sponsorship exists, clearly separate from search/identity.


41. Metrics of Success
Technical:
multiple independent Nodes
multiple implementations
conformance pass
no central runtime dependency
stable local search.


Not:
number of tracked users
search query volume
click conversion.


42. Long-Term Research Backlog
transparency/gossip
DHT
threshold keys
recovery mechanisms
native integration
advanced moderation
reputation
formal proofs.
Each only after measured need and separate threat model.


43. Sunsetting Project
If maintainers stop:
published spec/test vectors remain;
Nodes can continue;
third parties can fork;
no central runtime switch kills network.
Document archival/signing/domain succession where possible.


44. Acceptance
Ecosystem readiness PASS requires:
persona-based docs;
five-minute operator contract;
integration examples;
maintainer succession plan;
Protocol maturity policy;
implementation registry design;
no hidden central service dependency.
