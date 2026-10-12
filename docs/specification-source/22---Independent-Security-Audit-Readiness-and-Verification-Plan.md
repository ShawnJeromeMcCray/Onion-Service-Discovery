# 22 - Independent Security Audit Readiness and Verification Plan

Source: https://docs.google.com/document/d/1_ZF_9UOwtv3FpkVypW5dnXzIYkmJnQz5Kx7Jpn5MKXg/edit?usp=drivesdk

Source modified: 2026-10-11T07:13:11.154Z

---

ONION SERVICE DISCOVERY
INDEPENDENT SECURITY AUDIT READINESS AND VERIFICATION PLAN


1. Purpose
Prepare the project so an external security/protocol reviewer can evaluate it efficiently, reproduce claims, and trace every security requirement to code and tests.


2. Audit Scope
Protocol v1
cryptographic construction
PHP reference node
Tor transport adapter
Web Local Search reference client
storage/migrations
sync/discovery
anti-abuse
privacy/data flows
release/supply chain
operations/incident response


3. Audit Preconditions
Do not request independent audit before:
- Protocol v1 draft frozen enough to review;
- test vectors complete;
- all known Critical/High internal findings fixed or explicitly disclosed;
- architecture/threat model current;
- code builds/tests from clean checkout;
- no large unreviewed feature branch pending.


4. Audit Package
Provide:
architecture diagrams
Trust Boundaries
Protocol specs
schemas
limits registry
crypto spec
test vectors
Threat Model
security invariants
privacy spec
anti-fraud
abuse model
data model
API contract
deployment hardening
Web Client origin-trust/CSP/server-search privacy review
dependency inventory
SBOM
CI/release docs
known issues
security exceptions
prior findings.


5. Code Map
Create AUDIT-MAP.md:
security area
entry points
critical classes/modules
tests
spec references
owner/contact.


6. Critical Code Areas
Flag:
canonicalization
signature input
key rotation
migration
OnionAddress parser
Tor transport
redirect handling
candidate scheduler
sync conflict application
database transactions
browser network instrumentation and Web Client fetch layer
optional Web Client storage/cache behavior and migration/failure handling
pinning
permission manifest
release scripts.


7. Security Invariant Traceability
For every SEC-INV/PRIV/ABUSE invariant:
spec
code
tests
manual review item.
Auditor should be able to search invariant ID in repository.


8. OWASP ASVS Mapping
Use current stable OWASP ASVS as external checklist for applicable server/web controls.
Create matrix:
ASVS requirement ID/version
applicable yes/no
component
implementation
test/evidence
notes.


N/A must be reasoned.
Do not claim ASVS certification.


9. Protocol-Specific Review
External reviewer asked to assess:
canonicalization ambiguity
signature domain separation
hash-chain semantics
fork handling
key compromise/rotation
migration hijack
unknown-field behavior
integer/Unicode interoperability
version downgrade
malicious peer equivocation.


10. Tor-Specific Review
Assess:
local Onion parsing against Tor spec
SOCKS hostname resolution
absence direct fallback
isolation design
timeout/concurrency
proxy configuration trust
no Tor private key access.


11. Web Local Search Review
Assess:
permissions
host permissions
content script absence/scope
remote code
CSP
network destinations
query privacy
pin storage
navigation construction
fingerprinting impact documentation
platform compatibility assumptions.


12. Privacy Review
Data-flow inspection:
search query
result click
favorite
pin
sync
diagnostics
logs
moderation lists
Clearnet proof.
Test claims against packet/network instrumentation.


13. Abuse Review
Try:
candidate flood
peer flood
snapshot flood
invalid signature batches
recursive link graph
retry amplification
disk/quarantine exhaustion
keyword spam
confusable names.
Validate bounded behavior.


14. Storage Review
Check:
constraints
transactions
upsert semantics
fork preservation
rotation atomicity
migration atomicity
sync cursor commit ordering
corruption behavior
backup/restore.


15. API Review
Check:
method restrictions
arbitrary fetch absence
pagination/limits
error leakage
CORS
cache semantics
path validation
chunks by hash
no remote administration.


16. Supply Chain Review
Check:
locks
dependencies
CI permissions
actions pinning
secret handling
SBOM
provenance
release hashes
reproducible build evidence
signing.


17. Operations Review
Clean install
permissions
webroot exposure
runtime support
backup
restore
upgrade
rollback
key incident
DB incident
logging privacy.


18. Static Tools
Agent selects appropriate tools, but audit package should include output/summaries for:
PHP static analysis
TypeScript type/lint
dependency scanning
secret scanning
SAST where useful
Web Client static-bundle/CSP/XSS lint
schema validation
SBOM validation.


19. Dynamic Testing
Local isolated environment.
Use mock Onion/Tor adapters for destructive/adversarial corpus tests.
Use real Tor integration tests only in controlled modest workloads.


20. Fuzzing
Audit receives:
targets
corpus
duration
tool/version
crashes
coverage indicators where available
fixed regressions.


21. Code Coverage
Coverage is supporting evidence, not quality proof.
Highlight security-critical branches lacking tests rather than optimizing a global percentage.


22. Manual Crypto Review
Mandatory.
Automated tools cannot substitute.
Reviewer signs off exact:
canonical bytes
domain prefix
hash definition
rotation
migration
fork semantics.


23. Independent Build
Auditor/reviewer should build from source without developer workstation.
Compare release artifact structure/hashes where reproducible.


24. Test Vector Reproduction
Reviewer runs vectors in PHP and TypeScript.
Expected exact equality.


25. Network Privacy Experiment
Instrument client.
Run searches.
Prove no query leaves client.
Run favorite/pin operations.
Prove no event telemetry.


26. Direct-Fallback Experiment
Configure Tor SOCKS unavailable while direct Internet works.
Onion Service Discovery outbound verifier must fail; it must not attempt direct path.


27. DNS/Resolution Experiment
Use controlled observation to ensure Onion host is handed to Tor proxy, not system DNS.
No public sensitive query needed.


28. Permission Review
Compare source asset manifest/CSP/network inventory to the built and served Web Client bundle.
No build-time permission injection.
Permission inventory matches docs.


29. Artifact Inspection
Unpack static Web Client and server packages.
Search:
secrets
dev endpoints
source paths
remote URLs
unexpected binary blobs
debug config.


30. Threat-Model Challenge
Auditor encouraged to add threats project missed.
Every valid new threat maps to:
issue
severity
mitigation/accepted risk
test/doc update.


31. Findings Format
ID
title
severity
component
invariant
description
impact
preconditions
evidence/reproducer
recommendation
project response
fix reference
regression test
status.


32. Severity
Use a documented severity rubric combining:
impact
exploitability
scope
privacy/integrity/availability
default exposure.


CVSS may be supplementary but project-specific risk context remains.


33. No Finding Suppression
All audit findings retained in internal/public report according to disclosure policy.
Fixed finding does not disappear from history.


34. Audit Remediation
For each finding:
root cause
fix
variant search
regression test
Threat Model update if systemic
docs update
reviewer verification if high/critical.


35. Release Blocking
Open Critical: block.
Open High: block unless human Security Exception with explicit risk and rationale, strongly discouraged for first production.
Medium: reviewed/plan.
Low/info: documented.


36. First Production Bar
For first production release, target zero open Critical/High and no unresolved crypto/protocol ambiguity.


37. Audit Re-Trigger
New independent review considered when:
new protocol version
crypto changes
new recovery mechanism
new browser broad permissions
new remote admin
major sync architecture
new anonymity/privacy data collection
major supply-chain change.


38. Continuous Internal Review
Before every release run lighter internal audit checklist.
External audit is not permanent immunity.


39. Verification Standard Mapping
Maintain:
OWASP ASVS current baseline
project security invariants
privacy invariants
supply-chain gates.
Do not contort protocol to fit irrelevant web checklist items.


40. Audit Environment
Provide reproducible compose/scripts/mocks where appropriate.
No auditor needs production secrets.
Test keys only.


41. Audit Documentation Quality
Specs contain normative language and examples.
Diagrams match code.
Dead/outdated docs treated as bugs.


42. Known Limitations
Audit package explicitly states:
serving-origin trust limitation and browser-storage/JavaScript compatibility concerns
no global Sybil solution
technical identity not operator honesty
decentralized withdrawal cannot guarantee deletion
timing/global adversary not fully solved.


Hiding limitations is itself an audit failure.


43. Independent Reviewer Questions
Can peer rewrite identity?
Can old revision win?
Can service name impersonate pin silently?
Can network input force arbitrary URL fetch?
Can Tor outage cause direct fallback?
Can query leak through updates?
Can one key cross-sign wrong record type?
Can migration be hijacked?
Can DB conflict be overwritten?
Can release artifact differ from reviewed source?


44. Audit Evidence Directory
release-evidence/<version>/audit/
audit-scope.md
audit-map.md
tool-results/
manual-review/
findings/
remediation/
final-status.md


45. Final Audit Status
PASS means:
scope completed;
all Critical/High closed;
protocol/crypto manual review complete;
claims tested;
remaining risks documented.


It does not mean “unhackable”.


46. Public Summary
If an external audit occurs, publish a factual summary/report where agreements allow:
scope
date
version/commit
findings counts
remediation status
limitations.


47. Reviewer Independence
“Independent” should mean reviewer is not the same agent/person who authored the relevant code, where feasible.
AI self-review is useful but not independent audit.


48. Human Security Gate
The coding agent MUST mark:
EXTERNAL_AUDIT_NOT_PERFORMED
until actual audit evidence exists.
It may prepare the package but may not mark this as pass.


49. Audit Acceptance
Project is “audit-ready” when an external reviewer can begin without first reverse-engineering undocumented assumptions, and every major security claim has source, code and test evidence.
