# 28 - Independent Conformance Suite and Certification Harness

Source: https://docs.google.com/document/d/1fasTLWHCM9T2D2EiwUBf8iyzYUxGoT84pjI0Ed6lnHA/edit?usp=drivesdk

Source modified: 2026-10-12T19:21:10.482Z

---

ONION SERVICE DISCOVERY
INDEPENDENT CONFORMANCE SUITE AND CERTIFICATION HARNESS


Status
Mandatory interoperability artifact. The conformance suite is language-independent and MUST NOT rely on internals of the PHP reference node or the Web Local Search reference client.


1. Purpose
A protocol is only credible when a third party can implement it without copying the reference code and can objectively determine whether the result is compatible.


The suite defines:
- normative protocol fixtures;
- exact expected outputs;
- positive and negative cases;
- executable black-box tests;
- versioned conformance profiles;
- machine-readable reports.


2. Conformance Does Not Mean Security Certification
A conformant implementation:
- parses/validates records according to Protocol;
- computes exact hashes/signatures;
- applies state semantics correctly;
- exchanges API/sync data compatibly.


Conformance DOES NOT mean:
- free of implementation vulnerabilities;
- safe content;
- Tor Project approval;
- independent audit completed.


3. Independent Repository Layout
protocol-conformance/
  README.md
  LICENSE
  versions/
    v1/
      schemas/
      vectors/
      api/
      state-machines/
      limits/
      fixtures/
      reports-schema/
  runner/
  adapters/
  mock-node/
  mock-service/
  docs/


The suite MAY live in same monorepo initially but must not import application internals.


4. Test Categories
CONF-SCHEMA
CONF-CANON
CONF-CRYPTO
CONF-ADDRESS
CONF-REVISION
CONF-ROTATION
CONF-MIGRATION
CONF-NODE
CONF-OBSERVATION
CONF-SYNC
CONF-API
CONF-LIMITS
CONF-ERRORS
CONF-STATE
CONF-PRIVACY-CLAIMS where black-box observable
CONF-INTEROP


5. Vector Format
Each vector has:
id
protocol_version
category
description
input files
preconditions
expected result
expected error code/state
normative spec references
security invariant references


Machine-readable manifest:
conformance-manifest.json


6. Exact-Byte Tests
Mandatory exact comparison for:
canonical JSON bytes
domain-separated signed message bytes
SHA-256 output
public-key encoding
signature encoding
snapshot/chunk canonical bytes
state-root bytes if applicable.


7. Address Vectors
Include:
valid v3 addresses
checksum failures
wrong version
wrong length
invalid Base32
uppercase handling
whitespace
scheme/path/port injection
Unicode lookalikes
percent-encoding tricks.


8. Record Corpus
Every record type has:
minimum valid
typical valid
maximum-boundary valid
one-past-boundary invalid
missing-field invalid
unknown-field invalid
duplicate-key invalid
wrong-type invalid
wrong-version invalid
signature-invalid
hash-invalid.


9. State Sequence Fixtures
Represent event sequence plus expected final state:
genesis -> revision2
stale replay
fork
valid rotation
conflicting rotation
valid migration
conflicting migration
withdrawal plus outage
peer stale data
sync retry/commit.


10. Black-Box Server Harness
Given a candidate implementation base endpoint:
runner tests:
GET /node
GET /state
GET /peers
GET /services/by-id/{logical_service_id} stable logical-service identity lookup plus GET /services/{onion} address convenience lookup
changes
snapshot/chunk
signed checkpoint/checkpoint-history retrieval
bounded Candidate Announcement POST
method restrictions
pagination
error format
size limits
content types
cursor expiry/recovery.


11. Mock Malicious Peer
Provide deterministic scenarios:
invalid Node descriptor
stale state
forked Service
bad chunk
oversized chunk
cursor rollback
equivocating snapshot
peer list explosion
timeout
truncated JSON.


12. Mock Service
Serve controlled Manifest scenarios:
valid
invalid binding
bad signature
old revision
fork
rotation
migration
too large
redirect attempt
wrong content type.


13. Adapter Contract
Implementations can be tested through:
A. CLI adapter
B. HTTP black-box API
C. library adapter


Reference conformance CLI contract:
implementation-command validate-manifest <file>
implementation-command canonicalize <file>
implementation-command apply-sequence <fixture>
implementation-command inspect-state


Exact interface frozen before first public suite.


14. Third-Language Oracle
At least one minimal independent implementation in a third language MUST execute the CORE-V1 vectors before Protocol v1 Stable.
Purpose:
reduce risk that PHP and TypeScript share same misunderstanding.


It is not allowed to call PHP/TypeScript implementations underneath.


15. Conformance Profiles
CORE-V1
Manifest/address/canonicalization/crypto/revision.


NODE-V1
CORE + Node descriptor/API/sync.


CLIENT-V1
CORE + merge/pin/state/index-ingestion semantics where applicable.


FULL-V1
NODE + CLIENT interoperability expectations where meaningful.


16. Reporting
Machine-readable report:
implementation
version
protocol version
profile
suite version
date
environment
pass
fail
skip
unsupported
test IDs
evidence hashes.


Human report generated from same results.


17. Skip Policy
Required profile test cannot be silently skipped.
Skip requires:
feature outside chosen profile
documented reason.
For a claimed profile, required skips mean profile FAIL.


18. Suite Versioning
Conformance suite version separate from Protocol.
Bugfix in test harness can increment suite version without changing Protocol.
Expected-result changes require review to ensure they are not hidden Protocol changes.


19. Golden Fixture Integrity
Fixture manifest includes SHA-256 hashes.
CI ensures no fixture changes without review.
Crypto golden vectors require Protocol/Security review.


20. Differential Testing
Feed same generated valid/invalid corpus to:
PHP implementation
TypeScript implementation
third-language validator.
Flag any acceptance/error/state divergence.


21. Metamorphic Testing
Examples:
property reorder must not change canonical result.
whitespace change in wire JSON must not change signable semantic canonical bytes if parsed data identical.
changing any signed semantic field must invalidate old signature.
duplicate insertion must reject.
stale insertion must not downgrade state.


22. Fuzz Corpus Sharing
Crashes/minimized parser inputs become conformance negative fixtures when they expose semantic edge cases.
Keep fuzz and normative vectors distinct.


23. API Problem Details
If RFC 9457 integration is adopted, conformance validates:
application/problem+json
status consistency
stable project extension/error identifier
no secret/debug fields.


24. JSON Schema
Schemas declare Draft 2020-12 explicitly.
Suite validates schemas themselves against meta-schema.
Run at least two independent JSON Schema validators where practical to catch implementation-specific assumptions.


25. CI
Every application change:
CORE suite.


Protocol/API change:
all affected profiles.


Release Candidate:
all profiles against packaged artifacts, not only source tree.


26. Public Badge Policy
Project MAY publish factual badge such as:
“Onion Service Discovery Protocol Version 1 Conformance: CORE-V1 PASS”
only if report is reproducible and links suite/version.


Never write generic “certified secure”.


27. Compatibility Matrix
Maintain:
Implementation
Software Version
Protocol Version
Conformance Profile
Suite Version
Status
Date


28. Interoperability Events
Before production stable, run matrix:
PHP Node A <-> PHP Node B
PHP Node <-> mock independent Node
Web Local Search <-> PHP Node
Web Local Search <-> mock Node
third validator <-> exported records.


29. Error Semantics
Tests compare stable symbolic class, not implementation-specific exception text.
Public HTTP wording can differ within bounded human message rules.


30. Determinism
Running suite twice on same artifact/environment produces same semantic result.
Random/property tests record seeds.


31. Fixture Licensing
Synthetic fixtures only; no real private keys/user information.
License allows third-party implementation/testing.


32. Documentation
A new implementer gets:
“How to become conformant”
“How to run CORE-V1”
“How to debug mismatch”
“Protocol vs suite authority”
“Submitting new fixtures”.


33. Authority Rule
Normative Protocol specification is authority.
If suite contradicts specification:
open blocking issue;
do not silently treat test implementation as truth.
Resolve through governance process.


34. Acceptance
Conformance framework PASS requires:
- suite runnable from clean environment;
- PHP/TypeScript pass intended profiles;
- third-language core validator passes CORE;
- negative vectors reject identically at semantic level;
- reports reproducible;
- no skipped required tests


35. INDEX-INCLUSION CONFORMANCE
Add mandatory categories:


CONF-INDEX-RECORD
canonical active-index record bytes and record ID.


CONF-CHECKPOINT
checkpoint byte construction, signature and root.


CONF-INCLUSION
valid membership verification against signed checkpoint.


CONF-INCLUSION-INVALID
corrupted record/path/root rejected.


CONF-CHECKPOINT-ROLLBACK
older checkpoint not accepted as new current state.


CONF-EQUIVOCATION
conflicting signed checkpoint evidence preserved.


CONF-SEARCH-EXPORT-EQUIVALENCE
the active search export matches the signed checkpoint record set.


36. CROSS-LANGUAGE ROOT VECTORS
PHP, TypeScript and the independent third-language validator MUST compute identical:
- canonical active record;
- record hash/ID;
- complete index root or Merkle root;
- proof verification result.


37. OPERATOR END-TO-END VECTOR
Synthetic Service revision 4:
A/B/C verify it;
A/B current index contains revision 4;
C contains revision 3.


Expected dashboard semantic result:
current discoverability = 2 of 3 checked Nodes;
C = stale revision.


38. NODE OMISSION VECTOR
One malicious Node omits the record while two include it.


Expected:
valid inclusion from the two remains valid;
omission is source-specific;
no global non-existence conclusion.


39. NODE EQUIVOCATION VECTOR
One Node signs two roots for the same checkpoint sequence. A second fixture provides two non-prefix-compatible signed checkpoint histories from a common predecessor.
Expected:
equivocation detected and reported;
both signed artifacts retained.


40. SYBIL CONFORMANCE PROFILE
Required black-box tests:
CONF-SYBIL-IDENTITY-COUNT-NEUTRAL
CONF-SYBIL-OBSERVER-COUNT-NEUTRAL
CONF-SYBIL-WITNESS-COUNT-NEUTRAL
CONF-SYBIL-CANDIDATE-DEDUP
CONF-SYBIL-GLOBAL-WORK-BUDGET
CONF-SYBIL-PEER-POOL-BOUNDED
CONF-SYBIL-REFERRAL-ECLIPSE
CONF-SYBIL-RESULT-FLOOD
CONF-SYBIL-MISBEHAVIOR-PROOF


41. ADVERSARIAL IDENTITY MULTIPLICATION
Harness creates:
- 100,000 Service identities;
- 10,000 Node identities;
- 10,000 witness identities;
without treating them as independent operators.


Expected results are invariant under identity multiplication except for bounded operational load.


42. COUNT-NEUTRAL DIFFERENTIAL TEST
Run the same cryptographic evidence set with:
A. one malicious asserting Node;
B. 10,000 malicious asserting Node identities.


Service identity/fork/migration result MUST be identical.


43. RESOURCE BOUND TEST
Multiply candidate/referral identity count while holding configured budgets fixed.


CPU, network jobs, active peer set and persisted attribution MUST remain within documented bounded envelopes rather than scaling linearly without limit.




44. LOGICAL SERVICE ID CONFORMANCE
Required:
CONF-LOGICAL-ID-GENESIS
CONF-LOGICAL-ID-ROTATION-STABLE
CONF-LOGICAL-ID-MIGRATION-STABLE
CONF-LOGICAL-ID-CLAIM-WITHOUT-CHAIN-REJECT


The same logical_service_id MUST survive valid rotation/migration in PHP, TypeScript and the independent validator.


45. CHECKPOINT CHAIN CONFORMANCE
Required:
CONF-CHECKPOINT-SUCCESSOR
CONF-CHECKPOINT-PREVIOUS-HASH
CONF-CHECKPOINT-NONPREFIX-EQUIVOCATION
CONF-CHECKPOINT-MISSING-ANCESTRY


A larger sequence number without valid ancestry is not sufficient current-state evidence.
