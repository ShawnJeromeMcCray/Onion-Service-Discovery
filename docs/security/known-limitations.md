# 26 - Known Limitations Non-Claims and Future Research Boundary

Source: https://docs.google.com/document/d/1CJo71y488nBv81WM-6GVfQ5rf_K9F7hn4llcDy9VBfY/edit?usp=drivesdk

Source modified: 2026-10-11T07:39:02.176Z

---

ONION SERVICE DISCOVERY
KNOWN LIMITATIONS, NON-CLAIMS AND FUTURE RESEARCH BOUNDARY


Purpose
A high-quality security project must state what it does NOT solve. This document consolidates limitations so reviewers and users do not infer stronger guarantees than the protocol provides.


1. No Guarantee of Honest Content
A valid Service Identity signature proves control of the Service Identity key, not honesty, legality, malware-freedom, accuracy or business legitimacy.


2. No Human/Legal Identity by Default
Self-published “Example Bank” does not prove the operator is a bank or owns that name.
Optional external cross-links may provide additional evidence but remain specifically scoped.


3. No Global Name Ownership
Names are non-unique metadata.
Protocol v1 does not create a decentralized trademark/DNS naming system.


4. No Perfect Phishing Prevention
The client can:
show full Onion/fingerprint;
pin identities;
warn on changed identity;
warn on Unicode confusables.
A user can still ignore warnings or be socially engineered.


5. No Global Sybil Solution
An actor can operate many Nodes.
Protocol therefore deliberately does not treat Node majority/count as cryptographic truth.
Future reputation/Sybil work is separate research.


6. No Guaranteed Global Completeness
Discovery only finds Services that opt in and become connected to discovery pathways.
An isolated Service unknown to any participant cannot be discovered magically.


7. Bootstrap Is Necessary
Fresh clients need initial Seed information or another out-of-band peer.
Seed Nodes are not authorities, but bootstrap cannot emerge from zero knowledge.


8. Seed Governance Is a Soft Dependency
Shipped seed choices influence initial connectivity.
Governance, diversity and user-added peers reduce but do not eliminate this soft influence.


9. No Guaranteed Global Deletion
Once public metadata has been mirrored by independent Nodes, one operator cannot force all copies to be deleted.
Withdrawal stops/changes normal discoverability policy but is not global erasure.


10. No Guarantee of Immediate Freshness
Eventual consistency means different clients/nodes can temporarily have different index freshness.
Identity conflict rules are deterministic, freshness is not instantaneous.


11. Offline Does Not Mean Malicious
Service outage is liveness evidence only.
The system intentionally avoids deleting or accusing based solely on unreachability.


12. Old Valid Data Can Exist
Peers may hold stale signed revisions.
Clients/nodes prevent known downgrade but a fresh client with limited history may initially learn older state until synchronization/direct evidence improves.


13. Eclipse/Source-Bias Risk
A client connected only to malicious/stale peers may receive a biased view of what exists.
Cryptographic Service records limit identity forgery, but availability/completeness can still be censored by sources.
Multiple sources and source controls mitigate, not eliminate.


14. Node Equivocation Possible
A malicious Node can serve different signed Node snapshots to different clients.
Signatures make evidence attributable; Protocol v1 may not provide a complete global transparency gossip system.


15. No Global Transparency Log in Core v1
Append-only global log/Merkle consistency gossip is future research unless explicitly promoted before freeze.
The core remains useful without it.


16. Timing Analysis Not Solved
Local search avoids query-content disclosure to Discovery Nodes.
It does not guarantee resistance to all traffic timing/global adversaries.
Tor’s own threat model remains relevant.


17. Tor Network Guarantees Are External
Onion Service Discovery relies on Tor for Onion transport properties.
It does not improve or replace Tor’s anonymity/security protocol and cannot guarantee against a compromised OS/browser/Tor network adversary outside its scope.


18. Web-Origin Trust Limitation
The separate browser extension has been removed.


The reference client is now a Web Local Search application served by a Discovery Node.


Benefit:
no extra Tor Browser extension, permission set or extension-specific fingerprinting delta.


Limitation:
the serving Node controls the HTML/JavaScript and can maliciously or accidentally alter client behavior.


Therefore:
the reference build can prove by testing that it does not transmit local-mode queries, but a malicious serving origin cannot be prevented by that same remotely supplied code from violating the behavior.


19. Reference Client Is Not Tor Project Endorsement
Unless factual future collaboration/approval exists, the project must not imply official Tor Project support.


20. JavaScript and Browser Platform Changes
Tor Browser Web behavior can evolve.


The project must refresh:
- scripting/security-level behavior;
- browser storage behavior;
- CSP;
- WebCrypto;
- mobile compatibility.


A future browser change can require Web Client architecture modification without changing Protocol v1.


21. No Browser-to-Browser Direct Peer in Core
Without Tor integration/native listener, the reference browser client is primarily a consumer of Node data, not directly reachable peer server.
This is intentional.


22. PHP Is Reference Implementation Only
Protocol is language-independent.
PHP-specific behavior is not normative unless explicitly reflected in Protocol specs/vectors.


23. Storage Backend Scale Limits
No storage backend is claimed to scale without bound. FilesystemStore, SQLiteStore and server-database adapters each publish tested scale profiles. Operators choose based on workload. Every backend must preserve the same evidence, atomicity and continuity semantics.


24. Search Is Metadata Search
Core index searches operator-published name/description/categories/languages and technical fields.
It is not full-text indexing of every page/document behind a Service.


25. Search Ranking Is Gameable
Metadata can be keyword-stuffed within limits.
Local ranking/spam damping can reduce manipulation but cannot objectively rank “best” or “most trustworthy”.


26. No Popularity Truth
The project intentionally avoids central click/popularity tracking.
Therefore it may not offer popularity ranking comparable to commercial search engines.


27. Moderation Cannot Be Perfectly Neutral
Any selected moderation source embodies policy and can make errors or be abused.
Protocol keeps it separate and locally selectable.


28. No Mandatory Global Moderation
This preserves decentralization but means two clients can show different moderation warnings/filtering.


29. Illegal/Harmful Content Exists Independently
A public discovery protocol can surface content operators/users may find illegal or harmful.
Technical identity verification is not endorsement.
Operator legal obligations vary by jurisdiction.


30. No Universal Legal Compliance Claim
The project cannot claim compliance with every country’s privacy/content/security law.
Technical privacy design and documentation support informed operator review; legal advice is outside scope.


31. Service Key Loss Is Serious
Without old Service Identity private key, Protocol v1 cannot manufacture cryptographic continuity.
Backups are essential.
A new key may be a new identity.


32. Key Compromise Can Cause Forks
If attacker and legitimate operator both control/use a compromised Service key, conflicting valid signatures may exist.
Protocol can expose conflict; it cannot retroactively know human intent without additional trusted recovery mechanism.


33. No Central Recovery Authority
Core v1 intentionally avoids a project-maintainer recovery key.
This prevents central takeover authority but makes lost-key recovery harder.


34. Onion Key and Service Key Are Different
Compromise/loss scenarios can differ.
Protocol does not claim Application Service key proves possession of Tor private key beyond direct Onion-binding observations.


35. Clearnet Cross-Link Limits
HTTPS/Onion-Location-style links can show technical cross-reference.
They do not inherently prove legal corporate ownership or absence of domain compromise.


36. Observer Independence Unknown
Ten Node signatures do not guarantee ten independent humans/organizations.
UI must not imply independence without provenance.


37. Clock Trust Is Limited
Signed observation timestamps reflect Node’s claimed clock.
Protocol avoids using time as sole identity conflict resolver.


38. Availability Is Best Effort
Tor/network/Node outages can delay updates.
Cached local search remains a design goal but fresh data cannot be guaranteed offline.


39. DoS Cannot Be Eliminated
Finite resources can be exhausted by sufficiently capable attackers.
The design aims for bounded work/graceful degradation, not infinite capacity.


40. Rate Limits Can Cause False Negatives/Delay
Defensive quotas may delay legitimate new Services under heavy load.
Manual/operator pathways and retries mitigate.


41. Proof of Work Is Not Magic
If added later, PoW can disadvantage low-power operators and be bypassed by resource-rich attackers.
Not part of core guarantee.


42. No Blockchain Requirement
Protocol does not use blockchain. Adding one is not presumed to improve identity/decentralization and would require separate justification.


43. No DHT Requirement in Core v1
Node pull sync and snapshots can achieve decentralized mirroring without an initial DHT.
A DHT may be future research but introduces new routing/Sybil/privacy complexity.


44. Merkle Optimization May Evolve
Core exact snapshot/delta format is frozen for v1 when decided.
Advanced Merkle DAG/transparency mechanisms can arrive in later protocol versions/extensions.


45. Privacy Depends on Implementation Correctness
“Queries stay local” is only true if extension implementation, dependencies and update behavior pass tests/audit.
A compromised extension can violate it.


46. Supply Chain Remains a Threat
SBOM/provenance/reproducibility reduce risk but do not guarantee no dependency/CI compromise.


47. Audit Is Point-in-Time
Even a real independent audit covers specific version/scope/date.
Later changes can introduce bugs.
Do not market “audited” as permanent blanket security.


48. Formal Verification Not Core Requirement
Property tests and rigorous specs are required.
Full formal verification of protocol/implementation is not guaranteed.
Future formal modeling could improve assurance.


49. Memory Safety
PHP/TypeScript runtimes reduce some classes of memory errors but dependencies/runtime/browser can contain vulnerabilities.
No blanket memory-safety claim.


50. Secret Erasure
High-level runtimes cannot guarantee perfect private-key zeroization from memory.
Design minimizes exposure/copies, but docs must not promise guaranteed RAM erasure.


51. Device Compromise
If user OS/browser profile is compromised, local pins/search/index can be read/manipulated.
Protocol cannot secure a hostile endpoint.


52. Server Compromise
If PHP app account/service key is compromised, attacker may publish signed application updates using stolen key.
Tor key separation can limit compromise scope, but incident response is required.


53. Tor Daemon Compromise
Outside application boundary; can undermine transport/Onion service operation.
Project does not defend against fully compromised Tor runtime/host.


54. Human Error
Operators may misconfigure permissions, backups, seeds or metadata.
Secure defaults/self-checks reduce, not eliminate.


55. UX Warning Fatigue
Too many warnings can reduce effectiveness.
Security UX must be tested and prioritized; not all uncertainty should be alarming red state.


56. Accessibility vs Dense Security Detail
Default UI must balance understandable search with access to detailed evidence.
Experts can inspect details; normal users should not need to parse raw crypto.


57. Internationalization Risks
Translated security terms can shift meaning.
Reference English semantics/glossary remains normative for implementation.
Translations require semantic review.


58. Index Size Unknown in Real Deployment
Real number of opt-in public services and metadata growth are uncertain.
Performance budgets must be measured and architecture retains chunk/delta evolution path.


59. Independent Node Adoption Is Social
Protocol can permit anyone to run a Node, but cannot force independent organizations to do so.
A small early network may be practically concentrated even if architecture is decentralized.


60. Project Distribution Can Be Centralized
Source/releases may initially be commonly downloaded from one hosting platform.
Runtime protocol does not depend on that host; mirrors/signatures/provenance can reduce distribution concentration.


61. Search Result Liability/Policy
Reference project provides technical discovery; operators of public Nodes/clients may face different policy/legal choices.
Technical design does not decide all governance questions.


62. Future Research Boundaries
Do not place in v1 core without separate proposal:
global reputation
social recovery
threshold/multisig service identity
transparency gossip
DHT routing
browser peer hosting
proof-of-work
personalized ranking
recommendation engines
remote search
accounts/cloud sync.


63. Criteria for Promoting Future Feature
Need:
measured problem
threat model
privacy analysis
protocol proposal
migration
conformance vectors
resource analysis
human security review.


64. Public README Non-Claims
README should explicitly state:
- technical discovery/identity, not safety endorsement;
- no guarantee of complete index;
- no global deletion guarantee;
- Web-serving-origin trust and local-vs-server-search privacy caveat;
- no central authority, but bootstrap/maintainer distribution soft dependencies exist;
- local query privacy scope;
- no perfect Sybil solution.


65. Reviewer Honesty Gate
If a reviewer identifies a real limitation, add it here rather than trying to market around it.
A transparent limitation is preferable to an untrue security claim.


66. Release Requirement
Known limitations must be reviewed at RC and included/linked in user/operator documentation.
New unresolved high-impact limitation can block release depending severity.


Final Statement
Onion Service Discovery aims to make public opt-in Onion Services easier to discover while improving technical identity continuity and query privacy. It intentionally does not claim to solve trust, human identity, content safety, global Sybil resistance, endpoint compromise or all anonymity problems.
