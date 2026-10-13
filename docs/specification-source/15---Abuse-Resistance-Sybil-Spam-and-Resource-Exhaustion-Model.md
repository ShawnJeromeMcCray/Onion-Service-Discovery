# 15 - Abuse Resistance Sybil Spam and Resource Exhaustion Model

Source: https://docs.google.com/document/d/1Kg3MOLH7urZ3i6UHTa0Hte3AnZOC8BwgvFBOJHhj7P8/edit?usp=drivesdk

Source modified: 2026-10-12T19:29:11.452Z

---

ONION SERVICE DISCOVERY
ABUSE RESISTANCE, SYBIL, SPAM AND RESOURCE EXHAUSTION MODEL


Status
Mandatory security design for any implementation that discovers, verifies, mirrors, synchronizes or displays third-party services.


1. Principle
Discovery is an adversarial workload. Any field or peer behavior that can cause:
- network requests;
- CPU work;
- signature verification;
- database writes;
- disk growth;
- memory growth;
- UI clutter;
must be bounded.


2. Threat Classes
A. Candidate Flood
Attacker injects huge numbers of syntactically valid Onion addresses.


B. Peer Flood
Attacker advertises many fake Node descriptors.


C. Service Flood
Attacker runs or generates many Onion Services to dominate result space.


D. Metadata Spam
Large/repetitive names, descriptions, categories or link graphs.


E. Retry Amplification
Attacker causes repeated transient-looking failures to consume work.


F. Sync Amplification
Peer repeatedly changes state or advertises large deltas.


G. Sybil Trust Manipulation
One actor runs many nodes/observers to manufacture apparent consensus.


H. Storage Exhaustion
History, quarantine, observations or chunks grow without bound.


I. CPU Exhaustion
Many signature/schema/canonicalization operations.


J. UI/Ranking Abuse
Keyword stuffing, duplicate identities, confusable names.


3. Core Rule
No untrusted event triggers unbounded immediate follow-up work.
All work enters:
- validation;
- quota;
- queue;
- scheduler;
- bounded execution.


4. Admission Pipeline
Incoming Candidate:
Step 1 — source allowed?
Step 2 — source quota?
Step 3 — total admission quota?
Step 4 — exact Onion parse/checksum?
Step 5 — dedup?
Step 6 — already rejected/backoff?
Step 7 — store minimal Candidate only.
Step 8 — schedule later according to budget.


No direct network verification in the same untrusted ingestion call unless a tightly bounded manual operator command explicitly requests it.


5. Source Provenance
Every candidate records source type:
configured seed
learned peer
service related link
manual operator
bounded Protocol v1 public Candidate Announcement
crawler if implemented


Also source identifier/hash where privacy-safe.
This enables per-source budgets and abuse triage.


6. Candidate Quotas
Reference configuration must expose:
max pending candidates globally
max candidates accepted from one peer per sync batch
max candidates accepted from one peer per hour/day
max candidates from one service manifest
max new candidates scheduled per maintenance run


Defaults conservative.
Hard caps cannot be set to “unlimited” without explicit unsafe override warning.


7. Candidate Deduplication
Primary candidate identity:
canonical Onion hostname.
Repeated advertisement:
- may update source evidence counters/timestamps within bounds;
- does not create another expensive verification job;
- does not increase trust linearly.


8. Verification Budget
Config:
max_verifications_per_run
max_concurrent_verifications
max_total_response_bytes_per_run
max_total_wall_time_per_run
max_cpu/signature workload indirectly bounded by record count


Scheduler stops cleanly when exhausted.


9. Backoff Classes
Transient transport errors:
exponential backoff + jitter.


Cryptographic invalidity:
long quarantine/rejection; not frequent retry.


Protocol unsupported:
long backoff until software/protocol state changes.


Rate limited:
respect bounded Retry-After subject to local maximum.


10. Jitter
Use CSPRNG or adequate random source for scheduling jitter.
Purpose:
avoid synchronized node retry storms.
Jitter is not a security secret.


11. Peer Admission
Learned peer:
- strict Node Descriptor parse;
- signature;
- canonical Onion;
- supported protocol;
- dedup by Node Identity and Onion;
- candidate peer quota;
- not immediately fully trusted/selected.


12. Maximum Peer Set
Maintain:
configured/pinned peers separately;
bounded learned peer active set;
bounded candidate peer pool;
stale eviction policy;
quarantine separate.


A malicious peer list cannot cause millions of persistent entries.


13. Peer Selection Diversity
Do not select solely:
- highest advertised popularity;
- peer count votes;
- lowest latency;
because attackers can game them.


Selection can mix:
- pinned/known sources;
- random learned peers;
- diversity across independently configured trust sources where known.


Do not infer real-world operator independence without evidence.


14. Sybil Resistance Philosophy
Protocol v1 does not attempt to solve global Sybil resistance perfectly.
Therefore:
- arbitrary node count has low/no direct trust weight;
- direct Service signature/Onion binding is primary;
- observer count shown carefully;
- selected observer sets are local policy;
- no “51% of nodes says so” identity rule.


15. Search Spam
A valid self-signed service can keyword-stuff metadata.
Mitigations:
- field length limits;
- category count limits;
- tokenizer repetition damping;
- name exactness weighted but not exclusive;
- description term-frequency saturation;
- duplicate near-identical entries can be grouped locally by technical identity/history, not deleted as fraud automatically.


Ranking algorithm documented and tested against spam fixtures.


16. Result Flood
Attacker creates thousands of distinct identities matching same keyword.
Client:
- pagination/virtualization;
- deterministic ranking;
- optional filters;
- local moderation/spam sources;
- no server-side query leaking for anti-spam.


17. Name Impersonation
Handled by anti-fraud spec:
same name is allowed;
technical identity visible;
confusable warning;
pin mismatch warning.


18. Related-Link Graph Abuse
related_onion_services max fixed.
Nodes do not recursively crawl immediately.
Candidates from links receive lower/default priority unless operator policy differs.
Depth is not propagated as recursive call stack.


19. Crawler Policy
Crawler is optional, separate capability.
If implemented:
- disabled by default for reference node unless explicitly enabled;
- only public pages within already opted-in Service scope according to separate spec;
- strict fetch pages/bytes/depth/time;
- robots-like policy can be considered but Discovery manifest opt-in remains authoritative;
- found Onion addresses remain Candidates only.
Crawler cannot be prerequisite for core network.


20. Public Candidate Announcement Endpoint
Protocol v1 reference/operator profile includes bounded self-announcement for explicitly opted-in publishing.
Required controls:
- explicit operator/publish enablement;
- canonical Onion only; no arbitrary URL/port/path;
- request body tiny and schema-bounded;
- IP-level limits are complicated behind Tor and must not be sole control;
- global token bucket/work queue limits;
- duplicate suppression;
- optional proof-of-work only after separate review;
- no synchronous expensive fetch;
- accepted announcement creates only an untrusted Candidate hint;
- independent direct Manifest verification over Tor is required before Direct Observation or active index inclusion.


21. Proof of Work
Not mandatory Protocol v1.
Before adding:
- accessibility/device cost;
- botnet advantage;
- operator burden;
- parameter evolution;
- denial-of-service through verifier work;
must be reviewed.


PoW may gate announcement admission, never user search or normal service availability.


22. Signature Verification DoS
Ed25519 verification is finite but attackers can send many invalid records.
Mitigations:
- byte/schema/key encoding checks first;
- batch count limits;
- peer byte/work budgets;
- only crypto-verify after cheap structural checks;
- backoff/quarantine malicious peers.


Never skip signature validation for performance.


23. JSON Parser DoS
- body byte cap before parse;
- depth cap;
- member/array caps;
- duplicate key detection;
- streaming/defensive parser if library behavior requires;
- no arbitrary recursive structures.


24. Database DoS
Limits:
max candidate rows
max learned peers
max transient assertions per service
max retained snapshots
max chunks/cache
max moderation entries per source
max quarantine disk budget


When limits reached:
- preserve cryptographic identity/history first;
- evict reproducible/transient cache according to documented priority;
- never delete keys/history silently to accept spam.


25. Retention Priority
Highest retention:
accepted Service identity/revision chain
rotation/migration evidence
user pins (client)
security conflict/fork evidence within incident retention


Lower/rebuildable:
peer sync caches
snapshot chunks
transient failed candidate attempts
learned stale peers


26. Quarantine Budget
Quarantine can itself be attacked.
Store compact normalized evidence:
record hash
source
failure reason
minimal bounded payload or external debug sample according to privacy/security policy.
Do not retain unlimited raw attacker blobs.


27. Snapshot Abuse
Node snapshot generation:
- bounded frequency;
- reuse immutable chunks;
- no new full snapshot on every trivial peer request;
- scheduler controlled.


Client:
- rejects excessive chunk count/total bytes;
- enforces advertised and local maximum index size.


28. Equivocation Abuse
Malicious peer changes signed state constantly.
Local:
- peer churn counter;
- bounded history;
- backoff on excessive invalid/conflicting states;
- retain evidence hashes;
- peer may be quarantined.


29. Rate-Limit Architecture
Use layered buckets:
endpoint request limits
peer-sync bytes
candidate admissions
verification work
snapshot generation
maintenance work


Rate limits are local operational controls, not wire identity semantics.


30. Fairness
Scheduler SHOULD prevent one noisy peer/source from consuming all verification slots.
Possible weighted round-robin across source buckets.
Manual/pinned operator requests can have controlled priority.


31. Adaptive Defense
Automatic defense can tighten local work budgets under load.
It MUST NOT:
- change cryptographic validity rules;
- mark unverified data valid;
- change identity winner;
- globally blacklist based on unverifiable popularity.


32. Circuit/Connection Pressure
Tor requests are expensive.
Reference defaults should keep concurrency low and configurable.
Benchmark against Tor behavior.
Do not optimize for raw HTTP crawler throughput.


33. Liveness Rechecks
Verified services need not be pinged excessively.
Use:
last seen
stale threshold
backoff after failure
randomized scheduling
to distribute load.


Avoid causing synchronized periodic traffic to every Service.


34. Privacy vs Abuse
Do not introduce persistent user identifiers merely for rate limiting Web Local Search or server-search fallback.
Nodes can protect public read APIs with global/bounded mechanisms that do not require user accounts.


35. Reputation
Any future reputation score requires:
- separate spec;
- Sybil analysis;
- explainability;
- gaming analysis;
- privacy impact;
- no replacement of cryptographic identity.


Protocol v1 SHOULD avoid global reputation.


36. Moderation Spam
Moderation source is selected/trusted separately.
Limit records per source.
Signed provenance.
A malicious moderation source can be disabled without changing core index.


37. Operator Controls
Expose:
queue counts
top error classes
work budget exhaustion
peer churn
selected StorageBackend size
cache size
quarantine counts
without exposing user search activity.


38. Safe Overload Behavior
Under overload:
- stop accepting low-priority candidates;
- pause discovery;
- continue serving cached read index if safe;
- preserve existing verified data;
- health = DEGRADED;
- do not crash loop;
- do not wipe index.


39. Disk Full
Test disk-full behavior.
Writes fail safely.
Existing readable DB remains available if SQLite permits.
No partial state considered committed.
Operator receives explicit health/log event.


40. Memory Pressure
Avoid loading entire peer/snapshot corpus into memory if streaming/batched processing practical.
Max record count before allocation.


41. Malicious Compression
Expanded byte limit.
Compression bomb test if compressed responses accepted.
Otherwise disable content encodings in security-critical fetch path where practical.


42. Queue Poisoning
Invalid candidates should not repeatedly cycle because another peer keeps advertising them.
Global per-Onion rejection/backoff state takes precedence over new duplicate source until retry eligibility.


43. False Suppression Risk
Defenses can harm new/small legitimate services.
Therefore:
- no permanent rejection solely due to popularity;
- manual operator verification path;
- explicit retry after transient failure;
- reasons visible in diagnostics;
- local policies not global censorship.


44. Abuse Test Fixtures
Generate:
100k duplicate candidates
100k distinct valid-format Onion candidates
peer advertises max+1 peers
manifest max related links
rapid peer state churn
invalid signatures batch
mass stale revisions
fork flood
large quarantine payload
disk limit condition
snapshot chunk explosion
query keyword spam corpus


Use simulation/mocks; tests need not burden the public Tor network.


45. Benchmark Evidence
For each stress fixture record:
wall time
CPU
peak memory
DB growth
network requests attempted
queue resulting size
rate-limit behavior
recovery time


Define production budgets after measured baseline.


46. Abuse Security Invariants
ABUSE-001 Untrusted input cannot trigger unbounded immediate network recursion.
ABUSE-002 Queue sizes are finite.
ABUSE-003 Network concurrency is finite.
ABUSE-004 Per-run work is finite.
ABUSE-005 Duplicate advertisements do not linearly multiply expensive work.
ABUSE-006 Node count is not identity truth.
ABUSE-007 Overload preserves existing verified state where possible.
ABUSE-008 Search remains local even during anti-spam filtering.


47. Human Review Gate
Before Production:
security reviewer reviews default limits using performance evidence.
Values too high/low are adjusted by ADR.
Every “0 means unlimited” configuration option is prohibited for externally triggered workload unless explicitly marked unsafe and unavailable in standard config.


48. Incident Mode
Mass spam incident may use temporary local emergency policy:
tighter admission
longer backoff
discovery pause


Emergency policy MUST be documented and must not alter signature/identity validation.


49. Future Research
Potential later mechanisms:
transparency logs
operator attestations
proof-of-work announcements
trust-selected observer sets
content-addressed DHT-like distribution
must remain optional research until separate specs are reviewed.


50. Acceptance
Abuse resistance passes only when automated stress tests demonstrate bounded:
memory
disk
CPU/work
network attempts
queue sizes
and valid existing search/index operation degrades gracefully under attack


51. IDENTITY QUANTITY NEUTRALITY — HARD RULE
One attacker may create arbitrarily many Onion addresses, Service keys and Node keys.


Therefore:
- one key is NOT one person;
- one Onion is NOT one organization;
- one Node identity is NOT one independent operator;
- raw counts never become trust authority.


52. SYBIL FARM WORK CONTAINMENT
A farm of new identities cannot linearly multiply expensive verification work.


Required:
- bounded active peer set;
- bounded probation pool;
- per-source contribution caps;
- global verification budget;
- target-centric duplicate collapse/backoff;
- no synchronous verification on announcement.


53. SEARCH FARM CONTAINMENT
Thousands of cryptographically valid near-identical Services can still be spam.


Client MAY locally cluster/collapse similar result entries for presentation while preserving access to each distinct identity.


Creating more Services MUST NOT boost another Service's relevance/trust through backlinks, observation count or witness count.


54. WITNESS INFLATION
Witness/observer counts are propagation evidence only.


A valid objective misbehavior proof remains valid regardless of how many Sybil identities deny it.


55. SOURCE DIVERSITY
Different Node keys are called “distinct Node identities,” not “independent operators,” unless a local/human-reviewed source grouping provides that operational assumption.


Known/declared related Nodes count as one source group for redundancy displays.


56. OPTIONAL ADMISSION EFFORT
Adaptive proof-of-work MAY be evaluated for candidate-announcement overload.


It is an admission/work-scheduling mechanism only.
It MUST NOT:
- increase Service trust;
- increase search ranking;
- prove unique identity;
- resolve forks.


57. SYBIL STRESS
Required stress profiles include:
100,000 Service identities;
10,000 Node identities;
rotating announcement identities;
same-target repeated advertisements;
near-duplicate search flooding.


Existing verified state and search usability must survive within documented resource bounds.
