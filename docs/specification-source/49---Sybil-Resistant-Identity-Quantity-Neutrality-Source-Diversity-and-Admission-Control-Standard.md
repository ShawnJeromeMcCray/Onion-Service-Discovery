# 49 - Sybil-Resistant Identity Quantity Neutrality Source Diversity and Admission Control Standard

Source: https://docs.google.com/document/d/1S9XHh_lmhPrau9oI2xRFro-JVchVKeXE6XZGqHvMKXA/edit?usp=drivesdk

Source modified: 2026-10-12T19:30:13.509Z

---

ONION SERVICE DISCOVERY
SYBIL-RESISTANT IDENTITY QUANTITY NEUTRALITY, SOURCE DIVERSITY AND ADMISSION CONTROL STANDARD


Status
Binding anti-Sybil and anti-inflation security standard.


1. Problem
Onion addresses, Service Identity keys and Node Identity keys are cheap to create automatically.


Therefore an attacker can create:
- many Onion Services;
- many Service Identity keys;
- many Node identities;
- many witness identities;
- many candidate announcements;
without representing many independent real-world operators.


This is the Sybil problem.


2. Fundamental Security Rule
IDENTITY COUNT IS NOT TRUST.


No Protocol or UI decision may treat the raw number of:
- Onion addresses;
- Service identities;
- Node identities;
- witnesses;
- observations;
- mirrors;
- backlinks;
- candidate announcements
as proof of honesty, legitimacy, independence or identity truth.


3. Design Consequence
One attacker controlling 100,000 identities MUST NOT gain 100,000 times the authority of one honest participant.


The system is designed so additional identities mostly create:
- additional bounded input;
- additional storage/work pressure;
not additional cryptographic authority.


4. No Global Unique-Human Assumption
Onion Service Discovery does not attempt to prove:
one key = one person;
one Onion = one organization;
one Node = one independent operator.


No KYC, government identity, payment identity or central certification is required by the core Protocol.


5. What Cryptography Can Establish
Service signatures can establish:
- possession of the Service Identity private key;
- continuity of the Service Identity chain;
- authorization of revisions/rotations/migrations.


Direct Onion verification can establish:
- that the checked Onion endpoint served the validated Manifest at observation time.


Neither establishes that different keys belong to different humans.


6. Service Quantity Neutrality
Creating many Service identities does NOT:
- increase trust;
- increase legitimacy;
- increase ranking merely due to count;
- make a name more authoritative;
- override another Service Identity;
- create a majority identity decision.


7. Node Quantity Neutrality
Creating many Node identities does NOT:
- make observations more true;
- allow majority-based Service identity decisions;
- grant more global ranking authority;
- increase a Service trust score.


8. Witness Quantity Neutrality
Witness signatures mean:
“This witness reports observing this public checkpoint/evidence.”


They do not prove witness independence.


Raw witness count MUST NOT be a global trust score.


9. Misbehavior Proof Asymmetry
Cryptographically provable misconduct often needs only one valid proof.


Example:
two incompatible checkpoints signed by the same Node at the same unique sequence.


Once the contradictory signatures verify, 10,000 Sybil witnesses cannot make that evidence false.


10. Evidence Beats Votes
When evidence conflicts with vote count:
verifiable evidence wins.


Examples:
- invalid Service signature remains invalid if 1000 Nodes claim valid;
- valid fork evidence remains a fork if 1000 Nodes choose one branch;
- Node equivocation remains proven if 1000 Sybil witnesses deny it.


11. Distinct Sources vs Independent Sources
The system MUST distinguish DISTINCT_NODE_IDENTITIES from KNOWN/SELECTED_OPERATOR_DIVERSITY.


Different Node keys are not automatically described as independent operators.


12. UI Language
Allowed:
“current revision included on 4 distinct checked Node identities.”


Only say:
“4 independent operators”
if independence comes from an explicitly configured/human-reviewed source set with documented basis.


Even then it is an operational assumption, not cryptographic proof.


13. Local Source Sets
Each client/operator may maintain a bounded selected source set.


Source selection is local policy.


The Protocol does not define a global authoritative source list.


14. Source Family Metadata
Operators MAY self-declare a non-secret operator/source-family identifier to help honest deployments avoid accidental concentration.


This metadata:
- is not trusted proof;
- cannot increase authority;
- may only reduce apparent diversity by grouping declared-related Nodes.


An attacker lying about independence is still possible.


15. Conservative Diversity Accounting
When multiple Nodes are known or declared to belong to the same source family:
count them as one diversity group for redundancy UI/policy.


Never count them as multiple independent operators merely because they use different keys/Onion addresses.


16. Seed Diversity
Bundled seeds should come from multiple human-approved operators/infrastructure contexts where possible.


Seeds are bootstrap hints, not truth authorities.


The final actual seed set remains a Human Gate.


17. Bounded Active Peer Set
A Node MUST NOT sync with every advertised peer.


Maintain finite:
- active peer set;
- probation peer pool;
- candidate peer queue.


This prevents an attacker from multiplying work merely by multiplying peer identities.


18. Peer Admission
New peer identities start with limited work budget.


Admission into active sync set depends on local policy and observed protocol behavior, not global identity count.


No rule of the form:
“peer is advertised by N Nodes, therefore admit/trust it.”


19. Source Contribution Caps
For each sync source:
- max new candidate Onions per batch;
- max new candidate Onions per time window;
- max peer referrals;
- max observation records;
- max bytes;
- max validation jobs caused.


A newly created Sybil Node cannot force unbounded work.


20. Global Work Budget
All untrusted discovery input competes for a finite global verification budget.


When overloaded:
- preserve already verified current state;
- stop/slow low-priority discovery;
- retain bounded queues;
- do not sacrifice search availability/current trusted evidence.


21. Candidate Deduplication
Exact duplicate Onion candidates collapse before expensive work.


Repeated advertisements of the same candidate from many Node identities MUST NOT multiply verification effort linearly.


22. Target-Centric Backoff
Verification failure/backoff attaches primarily to the candidate target/Service state, not only to the reporting source.


If 10,000 Sybil Nodes repeatedly advertise the same failing Onion:
the target remains under one bounded retry schedule.


23. Candidate Source Attribution
Keep bounded source attribution for diagnostics/evidence.


Do not retain unbounded lists of every Sybil reporter.


24. Candidate Announcement
Self-announcement is an untrusted hint only.


It can cause at most:
- bounded candidate admission;
- later scheduled direct verification.


It never creates:
- Direct Observation;
- indexed trust;
- search authority
without direct verification.


25. Announcement Identity Rotation
Creating new Service/Node keys to bypass announcement limits MUST NOT reset the global work budget.


Local per-source limits are only one layer; global and target-centric budgets always remain.


26. Announcement Proof-of-Work
Optional adaptive proof-of-work MAY be used to prioritize/gate candidate announcements during measured overload.


PoW means:
“the sender spent computational effort.”


PoW does NOT mean:
“the sender is a unique person.”
“the Service is trustworthy.”
“the Node is independent.”


27. PoW Policy
PoW is NOT a Service trust primitive and MUST NOT influence search ranking or identity state.


If adopted:
- challenge/replay semantics require separate specification;
- verification must be cheap relative to solving;
- effort adapts to load;
- accessibility/resource impact considered;
- no permanent requirement when system is not under abuse unless explicitly justified.


28. No Financial Stake Requirement
Protocol v1 does not require cryptocurrency, payment, token staking or proof-of-stake.


Such mechanisms introduce economic centralization and do not inherently prove unique operators.


29. No CAPTCHA Identity
CAPTCHA may distinguish some automation classes but cannot prove durable unique identity and creates accessibility/privacy issues.


It is not core Sybil defense.


30. No IP Uniqueness Assumption
Do not use one-IP-one-identity logic.


Reasons:
- Tor deliberately hides source identity;
- NAT/shared hosting;
- privacy;
- easy evasion by distributed attackers.


31. No Hosting-ASN Trust Score
Hosting/network location may be an optional operational diversity hint for publicly known infrastructure, but MUST NOT become identity truth or a mandatory privacy-invasive fingerprint.


32. Search Result Sybil Flood
An attacker can create many distinct valid Services matching the same query.


Search must remain usable even when all records are cryptographically valid.


33. Search Ranking Quantity Neutrality
Search ranking MUST NOT boost a Service because:
- many Nodes observed it;
- many Services link to it;
- many witnesses mention it;
- many mirrors contain it;
- many candidate announcements exist.


34. Metadata Spam Resistance
Ranking uses bounded relevance signals such as:
- exact/normalized name match;
- term-frequency saturation;
- field limits;
- query-field relevance;
without count-based trust.


35. Similarity Clustering
The client MAY detect near-duplicate result entries using local metadata similarity.


Purpose:
presentation/spam damping.


It MUST NOT claim:
“same operator”
unless separately proven.


36. Similar-Result Collapsing
When many near-identical Services flood a query, UI MAY show:
“17 similar entries”
with an expand control.


This preserves access without allowing one automated farm to occupy the entire first screen.


37. Diversity in Result Presentation
Local ranking MAY enforce a presentation-diversity rule so that near-identical records do not monopolize top results.


This is a UX/spam mechanism, not trust judgment.


38. Legitimate Mirrors
Similarity clustering must not silently delete legitimate mirrors/clones.


Records remain accessible and technically distinct.


39. Names Are Not Scarce
Registering many Services with the same display name does not confer name ownership.


Identity fingerprint and pinning remain authoritative for continuity.


40. Age Is Not Identity
Long-lived Node/Service age MAY inform operational scheduling/stability diagnostics.


Age MUST NOT become proof of honesty or human uniqueness.


A patient Sybil attacker can age identities.


41. Uptime Is Not Trust
High uptime MAY inform availability selection.


It MUST NOT prove Service legitimacy or Node honesty.


42. Resource Contribution Is Not Truth
Bandwidth/storage donated by a Node can improve capacity.


It MUST NOT give more authority over Service identity or moderation truth.


43. Multi-Source Inclusion Semantics
Index inclusion on multiple distinct Nodes proves broader propagation.


It does not prove:
different owners;
Service legitimacy;
content safety;
truth.


44. Redundancy Threshold
Operators may configure a redundancy goal:
for example, “current revision present on at least 3 selected source groups.”


This is availability/propagation policy, not voting.


45. Selected Source Groups
A source group is a locally configured operational grouping.


Examples:
- operator A infrastructure;
- operator B infrastructure;
- own Node;
- community Node set.


No global source-group registry is required.


46. Default Source-Set Safety
Reference defaults should avoid taking many Nodes automatically from a single advertisement source.


Peer discovery should mix:
- bundled bootstrap;
- already successful sources;
- bounded randomized probation sources;
without allowing a single peer to fill all slots.


47. Eclipse Resistance
The system cannot guarantee global Sybil prevention, but it can reduce eclipse risk through:
- multiple bootstrap origins;
- pinned known sources;
- bounded source replacement;
- retention of historically good distinct source groups;
- no immediate full peer-table replacement;
- operator-visible source concentration.


48. Source Replacement
A newly learned peer does not immediately evict all stable sources.


Use gradual bounded rotation.


49. Source Concentration Warning
Dashboard/doctor SHOULD warn if all active sources are:
- from one configured source family;
- learned exclusively through one peer;
- otherwise locally known to lack diversity.


Do not infer hidden real-world ownership without evidence.


50. Cross-Auditing
Nodes MAY randomly audit public checkpoints/index proofs from other selected Nodes.


Audit jobs have strict budgets.


More honest participants increase the chance of detecting inconsistency, but Sybil identities do not increase formal authority.


51. Gossip
Checkpoint/misbehavior-proof gossip propagates objective evidence.


Gossip acceptance requires local cryptographic verification.


Do not accept “Node X is malicious” as a bare signed opinion.


52. Objective Misbehavior Classes
Examples:
- incompatible signed checkpoints at same sequence;
- invalid claimed inclusion proof;
- impossible signature claim where signed evidence is supplied;
- protocol response contradicting its own signed checkpoint.


53. Subjective/Operational Failures
Examples:
- slow;
- offline;
- omits a Service;
- stale.


These may reduce local source preference but are not automatically “malicious” without stronger evidence.


54. Local Quarantine
A locally verifiable objective misbehavior proof MAY trigger:
- source quarantine;
- sync disable;
- warning;
- retention of proof.


This decision is local and reversible by documented recovery policy.


55. No Global Ban Vote
Protocol does not allow N Nodes to vote another Node globally banned.


A Sybil attacker could otherwise manufacture ban majorities.


56. Misbehavior Proof Portability
Objective proofs can be exported/gossiped so every recipient independently verifies them.


57. Sybil Farm Stress Fixture
Required test:
100,000 Service identities;
10,000 Node identities;
large candidate flood;
near-duplicate search metadata;
repeated same-target advertisements.


Expected:
- bounded CPU/memory/disk/network;
- no trust elevation from counts;
- search remains usable;
- existing verified state retained.


58. Sybil Majority Test
Fixture:
attacker creates 10,000 Nodes all claiming a forged/invalid Service state;
one direct verifier has valid cryptographic Service evidence.


Expected:
invalid attacker state never becomes identity truth.


59. Honest Minority Fork Test
If one Node supplies cryptographically valid fork evidence and 10,000 Sybil Nodes suppress it:
the fork evidence remains preserved once obtained.


60. Witness Inflation Test
10,000 witness identities attest to one checkpoint.


Expected:
UI may report raw distinct witness identities in technical details if useful, but MUST NOT call that 10,000 independent operators or translate count into trust.


61. Result-Flood Test
10,000 distinct valid Service identities share near-identical metadata matching one query.


Expected:
bounded indexing/search time;
near-duplicate presentation controls;
no first-page monopolization solely from farm size;
all records remain inspectable.


62. Announcement Flood Test
Large number of valid-format announcements from rotating identities.


Expected:
global work budget dominates;
new identity creation does not scale verification work linearly;
no synchronous Tor fetch storm.


63. Peer Referral Flood Test
One or many Sybil peers advertise huge peer sets.


Expected:
bounded probation pool;
bounded active source set;
no automatic admission by popularity.


64. Formal Properties
SYBIL-001:
Raw identity count cannot change Service cryptographic validity.


SYBIL-002:
Raw observer/witness count cannot resolve Service fork/rotation/migration truth.


SYBIL-003:
Duplicate advertisements cannot multiply direct verification jobs without bound.


SYBIL-004:
Active peer-set size remains bounded independent of advertised identity count.


SYBIL-005:
One source cannot fill all active-source slots solely through referrals.


SYBIL-006:
Objective Node equivocation remains provable regardless of how many other identities deny it.


SYBIL-007:
Search-result prominence cannot be increased solely by creating more observing/witness/linking identities.


65. Conformance
Add tests:
CONF-SYBIL-IDENTITY-COUNT-NEUTRAL
CONF-SYBIL-OBSERVER-COUNT-NEUTRAL
CONF-SYBIL-WITNESS-COUNT-NEUTRAL
CONF-SYBIL-PEER-POOL-BOUNDED
CONF-SYBIL-CANDIDATE-WORK-BOUNDED
CONF-SYBIL-RESULT-FLOOD
CONF-SYBIL-ECLIPSE-SOURCE-REPLACEMENT


66. Metrics
Local operational metrics MAY include:
candidate admission rate;
duplicate collapse rate;
active/probation peer counts;
source concentration;
verification queue load;
similar-result cluster counts.


No global user tracking.


67. User-Facing Trust Semantics
Never show:
“Trusted because 500 Nodes agree.”
“Verified by majority.”
“99% community trust.”


May show:
“Current state observed by 5 distinct selected Node identities.”
with explanation that count is propagation evidence, not proof of independence or legitimacy.


68. Strongest Security Model
The strongest practical model is:
- Service identity secured by Service signatures;
- current endpoint checked directly over Tor;
- client uses locally selected diversified source groups;
- signed checkpoints/proofs;
- objective misbehavior proofs;
- bounded admission/work;
- no raw-count authority.


69. Fundamental Limitation
Without a trusted unique-identity authority or an enforceable scarce resource, a decentralized system cannot generally prove that many public keys represent many independent real-world actors.


Onion Service Discovery deliberately avoids making security depend on solving that unsolved identity problem.


70. Acceptance
PASS requires:
- raw identity quantity has zero trust authority;
- observer/witness count cannot resolve identity truth;
- finite peer/candidate/source work budgets;
- source concentration visible;
- no popularity-based peer trust;
- search spam resistant to valid Sybil farms;
- objective misbehavior proofs independent of voting;
- Sybil stress fixtures pass;
- announcement flood bounded;
- no false “independent” wording from key count alone.
