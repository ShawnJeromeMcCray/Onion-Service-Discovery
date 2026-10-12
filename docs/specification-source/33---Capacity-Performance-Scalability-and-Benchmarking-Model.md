# 33 - Capacity Performance Scalability and Benchmarking Model

Source: https://docs.google.com/document/d/1uOqAkyTQTtpcw_UFK2tgD9uwAnsx7U-ANiYoYLHEgFw/edit?usp=drivesdk

Source modified: 2026-10-11T07:14:30.281Z

---

ONION SERVICE DISCOVERY
CAPACITY, PERFORMANCE, SCALABILITY AND BENCHMARKING MODEL


1. Purpose
Performance limits must be derived from measurements, not guessed. Onion Service Discovery therefore defines repeatable scale tiers, workload models, resource budgets and degradation expectations.


2. Performance Principles
- correctness before speed;
- no security checks skipped for throughput;
- bounded concurrency;
- local search fast even when network unavailable;
- incremental sync preferred over full refresh;
- graceful degradation under overload;
- benchmark data published with environment.


3. Scale Tiers
TIER-S:
1,000 Services
5 Nodes
small operator/demo.


TIER-M:
10,000 Services
20 Nodes.


TIER-L:
100,000 Services
50 Nodes.


TIER-XL:
1,000,000 Services
100+ synthetic Nodes.
Used for capacity modeling; not a promise that reference SQLite deployment is optimal at this tier.


4. Service Data Profiles
MINIMAL:
short name, no description, one language/category.


TYPICAL:
name
200–500 character description
2 languages
3 categories
continuity history.


MAX-VALID:
fields near protocol bounds.


HISTORICAL:
multiple revisions, key rotation, migration, observations.


5. Node Workloads
Idle mirror
Incremental sync
Cold full snapshot import
Candidate verification batch
Stale recheck
Snapshot generation
API serving
DB maintenance.


6. Client Workloads
Cold startup/index open
Snapshot import
Delta apply
Search
filtering
detail render
pin comparison
storage migration
offline startup.


7. Search Benchmark
Queries:
exact name
prefix/partial
multi-term description
category
language filter
common term many hits
Unicode
long bounded query.


Measure:
P50/P95/P99 latency
CPU
memory
index size.


8. Search Target Philosophy
Interactive local search should feel immediate at target supported index size.
Exact thresholds set after baseline on representative low/mid-range devices, including Android-class hardware running the supported Tor Browser Web Local Search flow.


9. Server Benchmark Environment
Record:
CPU
RAM
storage type
OS
PHP
SQLite
Tor/mock transport profile
Node software version
dataset hash.


10. Client Benchmark Environment
Record:
device class
OS
Tor Browser/Firefox base
extension version
dataset hash
cold/warm state.


11. Sync Metrics
State request bytes/time
delta bytes
records/sec validate
transaction duration
full snapshot bytes/time
chunk count
peak memory
DB growth.


12. Verification Metrics
Tor/mock connect duration separated from application processing.
Manifest bytes.
Parser time.
signature verify time.
DB commit time.
requests/hour under configured safe concurrency.


13. Tor-Aware Performance
Do not tune reference Node like a high-concurrency clearnet crawler.
Tor circuit/Service behavior favors conservative concurrency and distributed scheduling.


14. Storage Metrics
Current-view DB size
continuity evidence size
observations
peer assertions
candidate/quarantine
snapshot cache
WAL peak
backup size.


15. Retention Modeling
For each retention policy compute estimated monthly growth under:
low
expected
high
attack-limited
workload.


16. Bandwidth Model
Per Node:
sync ingress/egress
snapshot distribution
verification requests
client index serving.
Avoid assumptions of free unlimited bandwidth.


17. Client Bandwidth
Fresh install full index.
Daily/periodic deltas.
Moderation list if enabled.
No search-query traffic.


18. Cold Bootstrap
Measure:
time to usable local index
bytes downloaded
number of Nodes contacted
verification time.
UI may become partially usable after verified core index before optional evidence completes, if semantics clearly defined.


19. Incremental Update
Primary steady-state target:
small bounded deltas.
Benchmark churn:
0.1%
1%
5%
10% records changed.


20. Snapshot Frequency
Choose based on:
delta retention
generation cost
client recovery
cache.
Do not regenerate full snapshot on each tiny change.


21. Chunk Size Benchmark
Protocol max provides safety bound.
Operational target chunk size optimized for:
Tor latency
memory
retry cost
hash verification.
Benchmark several candidate targets before freeze.


22. SQLite Limits
Test:
100k/1m synthetic records;
indices;
WAL;
concurrent PHP-FPM reads + one writer;
backup;
migration.


If reference SQLite becomes impractical beyond defined supported tier, document support ceiling rather than hiding it.


23. Alternative Backend Boundary
Protocol must not depend on SQLite.
A future PostgreSQL/other adapter can be added after storage conformance tests.
No need to implement prematurely unless evidence requires it.


24. CPU DoS Benchmark
Invalid input should be rejected as cheaply as possible:
byte limit
syntax
schema
key encoding
then signature.
Measure cost of 10k invalid records vs valid.


25. Memory DoS
Large valid-boundary responses processed streaming/batched when possible.
Benchmark peak memory with max changes/chunks.


26. Disk Full Threshold
Soft threshold:
warn/cleanup rebuildable cache.


Hard threshold:
pause discovery/new admissions before continuity evidence risk.
Benchmark cleanup effectiveness.


27. Queue Capacity
Stress:
normal backlog
burst
attack cap.
Measure time to drain and fairness across sources.


28. Scheduler Fairness
No peer/source monopolizes verification.
Benchmark distribution across source buckets.


29. Backoff Impact
Simulation measures requests avoided under repeated outage.


30. Cache Performance
HTTP/content chunk cache optional.
Never cache invalid/unverified data as current.
Measure hit ratio only as operational information, not trust.


31. Compression
If enabled after review:
measure bandwidth savings vs CPU/memory/bomb-defense complexity.
Disable if benefit not worth risk.


32. Client Index Strategy
Benchmark candidate local-search implementations against same fixtures.
Select simplest method meeting latency/memory goals.
Avoid huge dependency solely for small dataset.


33. Search Ranking Stability
Performance optimization must not change ranking nondeterministically.
Golden ranking fixtures.


34. Low-End Device Profile
At least one low-resource device/emulation.
Measure:
install/start
index size
search
delta apply.
Set realistic maximum supported default index profile if necessary.


35. Battery
Client background update frequency should avoid excessive wakeups/network.
Measure rough update cost on mobile where possible.
No per-query network helps.


36. Failure Performance
Timeout storm
slow peer
bad chunks
DB locked.
System should not hang UI indefinitely.


37. Benchmark Dataset
Use versioned synthetic reference dataset documented separately.
Every published benchmark links dataset hash/version.


38. Benchmark Runner
One command:
benchmark --profile TIER-M
or equivalent.
Outputs machine-readable JSON + human summary.


39. Regression Thresholds
CI can detect severe regressions:
search latency
memory
snapshot size
DB size
sync processing.
Do not make noisy microbenchmarks hard blockers without statistical discipline.


40. Release Performance Report
Include:
environment
dataset
results vs previous stable
known bottlenecks
supported scale recommendation
limit defaults derived.


41. Capacity Planning Guide
Operators can estimate:
services
peers
sync frequency
storage retention
disk
RAM
bandwidth.
Provide calculator/table generated from measured coefficients if reliable.


42. Overload Modes
NORMAL
BUSY
DEGRADED
DISCOVERY_PAUSED
READ_ONLY


Transitions based on bounded resources, not ad-hoc crashes.


43. User Experience Under Load
Client:
use old valid index;
show update delayed;
search remains local.


Node:
serve verified read data when safe;
pause discovery/snapshot generation as necessary.


44. No False Scalability Claim
Do not say “internet scale” or “unlimited” without evidence.
Publish tested tiers.


45. Acceptance
Performance readiness PASS requires:
versioned benchmark suite;
S/M/L datasets;
server + client reports;
safe default limits derived from evidence;
low-end client test;
SQLite supported-scale statement;
overload behavior tested.
