# 29 - Reproducible Test Network Chaos Lab and Failure Injection Specification

Source: https://docs.google.com/document/d/19eXxSqAUNN3zU523KW8deC8O1pJfyTCYy-chRgZ_8FQ/edit?usp=drivesdk

Source modified: 2026-10-11T07:14:32.328Z

---

ONION SERVICE DISCOVERY
REPRODUCIBLE TEST NETWORK, CHAOS LAB AND FAILURE INJECTION SPECIFICATION


1. Purpose
The project must be testable without depending on the public Tor network, real user data, or manually operated infrastructure. A reproducible local lab simulates realistic multi-node discovery behavior and adversarial failures.


2. Goals
- one-command local environment;
- deterministic topology;
- multiple Onion-like service identities;
- real protocol records;
- controlled network failures;
- storage crashes;
- node equivocation;
- large-scale synthetic load;
- CI-compatible reduced profile;
- full audit/stress profile.


3. Architecture
Test Controller
-> simulated/real local Tor layer depending profile
-> multiple Service fixtures
-> multiple Discovery Nodes
-> Web Local Search/test client
-> failure injector
-> metrics collector
-> assertions/report generator.


4. Profiles
LAB-FAST
Mock transport, 3 Nodes, 20 Services. Used in regular CI.


LAB-INTEGRATION
Real PHP Nodes, mock/simulated Tor transport boundary, 100 Services.


LAB-TOR
Local Tor processes/Onion Services where feasible, modest traffic, no public crawling.


LAB-STRESS
Mocked transport, 10–50 Nodes, 100k+ synthetic records.


LAB-CHAOS
Fault injection over integration topology.


5. Isolation
No tests may accidentally query arbitrary public Onion Services.
Fixtures use:
- local controlled Onion endpoints;
- synthetic addresses where transport is mocked;
- explicit allowlist.
A CI safeguard fails if outbound destination is outside test allowlist.


6. Deterministic Seeds
All randomized topology/data generation accepts fixed seed.
Failure output records seed for reproduction.


7. Service Personas
Healthy stable Service
Frequently offline Service
Rotating Service key
Migrating Onion address
Withdrawn Service
Forking malicious Service
Oversized/invalid manifest Service
Unicode/confusable Service
High-revision Service
Slow Service
Redirecting Service.


8. Node Personas
Healthy mirror
Discovery-enabled verifier
Read-only mirror
Stale Node
Malicious equivocation Node
Sybil cluster
Slow Node
Invalid-signature Node
Oversized-delta Node
Cursor-reset Node
Node with corrupted DB
Node with old protocol version.


9. Client Personas
Fresh client
Client with stale cache
Client with pins
Client offline
Client with only one malicious source
Client with mixed honest/malicious sources
Client upgrading IndexedDB schema.


10. Failure Injection
Network:
drop
timeout
latency
truncation
connection reset
proxy unavailable
wrong content type
redirect
bandwidth throttle.


Storage:
disk full
SQLite locked
transaction abort
corrupted page/file fixture
read-only filesystem
missing key file.


Process:
kill during sync
kill during key rotation commit
kill during DB migration
restart loop
scheduler overlap.


Clock:
clock skew
clock jumps
stale observations.


11. Identity Failures
Replay older revision
same revision different hash
invalid previous hash
invalid key rotation
conflicting rotation
invalid migration
competing migration
peer lies about observations.


12. Chaos Assertions
After every chaos run:
- no invalid state becomes verified;
- no accepted revision rolls back;
- no local pin silently changes;
- no cursor advances past committed data;
- no private key appears in logs;
- client search still works from last valid cache when network unavailable;
- overload remains bounded;
- recovery follows documented state.


13. Topology Files
lab/topologies/*.yaml or JSON:
nodes
services
peers
seeds
client sources
fault schedule
expected invariants.


Topology format versioned and schema-validated.


14. One-Command Developer UX
Target commands:
make lab-up
make lab-test
make lab-chaos
make lab-down
or equivalent project runner.


Command naming can differ, but setup must be reproducible and documented.


15. No Docker Requirement
Container orchestration can be offered, but a container-specific architecture is not normative.
If Docker Compose used for developer convenience, core protocol tests still run without needing privileged Docker behavior.


16. Real Tor Profile
Where feasible:
run local Tor instances with dedicated data directories and SOCKS ports.
Generate test Onion Services.
Keep service keys ephemeral test-only.
Traffic remains local/Test-owned.


17. Tor Version Matrix
LAB-TOR can run supported Tor versions selected by test matrix.
Do not hammer public Tor network for routine CI.


18. Resource Telemetry
Collect:
CPU
RSS
disk
DB growth
network bytes
request counts
queue size
latencies
search P50/P95
sync duration
verification throughput.


19. Failure Budget
Chaos run defines maximum tolerated:
data loss of rebuildable cache
time to recover
queued work
memory
disk growth.
Continuity evidence must not be lost.


20. Security Logging Assertions
Search generated logs for:
private key patterns
full secrets
queries
unexpected URLs
stack traces in public responses.
Fail on matches according to allowlist.


21. Packet/Network Observation
Test proxy/network adapters instrument destinations.
For client query-privacy tests:
query content must not appear.
For Node:
all Onion verification destinations traverse configured Tor adapter.


22. Equivocation Scenario
Malicious Node presents snapshot A to client 1, B to client 2.
Expected:
signatures attribute both states;
comparison tool can detect same Node generation/revision conflict when evidence brought together.


23. Eclipse Scenario
Fresh client only knows malicious/stale sources.
Expected:
client may have incomplete/stale discovery view, but malicious source cannot forge Service signature/continuity.
UI freshness/source limitations visible.
This tests documented limitation rather than impossible guarantee.


24. Seed Failure Scenario
All built-in seeds down after clients previously learned peers:
existing clients continue via learned peers.
Fresh client fails gracefully with bootstrap diagnostics/manual source option.


25. Withdrawal Scenario
Service valid revision changes publish_service false.
Nodes eventually exclude from normal active search export while preserving continuity evidence.


26. Massive Candidate Flood
Inject:
duplicates
distinct valid-format addresses
invalid addresses
related-service graph.
Assert quotas and bounded verification.


27. Upgrade Lab
Start previous release artifacts with populated state.
Upgrade server/client.
Run migrations.
Sync.
Verify hashes/pins.
Simulate rollback according to supported path.


28. Backup/Restore Lab
Generate known state.
Backup.
Destroy node data.
Restore.
Compare:
identity chain
hashes
current view
peer state as expected
key continuity.


29. Release Candidate Lab
RC package only, not source-dev run:
fresh installation
3+ nodes
10+ services
client
sync
migration
rotation
fork
offline
backup/restore
upgrade from previous stable.


30. Evidence
Every run outputs:
lab profile
source revision
artifact versions
random seed
topology hash
fault schedule
test result
resource stats
logs
failure traces.


31. CI Tiers
Per commit:
LAB-FAST.


Nightly:
LAB-INTEGRATION + selected chaos.


Before RC:
LAB-TOR + LAB-STRESS + LAB-CHAOS + upgrade/restore.


32. Public Demo Mode
A deterministic non-sensitive demo topology may be provided for contributors to understand project behavior visually.
No real malicious content required.


33. Test Key Safety
All test private keys clearly marked TEST ONLY.
Never reuse production keys.
Secret scanner can allow specific fixture path by documented exception.


34. Cleanup
lab-down verifies:
processes stopped
temporary Tor data removed
temporary keys removed
ports released.


35. Acceptance
Chaos Lab PASS requires:
- reproducible one-command environment;
- deterministic failure scenarios;
- CI profile;
- RC profile;
- no public network dependency;
- measurable invariants after faults;
- resource evidence archived.
