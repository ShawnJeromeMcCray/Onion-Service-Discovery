# 48 - Verifiable Discoverability Index Inclusion and Checkpoint Proof Standard

Source: https://docs.google.com/document/d/16JxH-eAjGOsSVAl79okb2YgW_7qBjIziXJq7PIge-eI/edit?usp=drivesdk

Source modified: 2026-10-12T19:26:53.173Z

---

ONION SERVICE DISCOVERY
VERIFIABLE DISCOVERABILITY, INDEX INCLUSION AND CHECKPOINT PROOF STANDARD


Status
Binding protocol/operational standard for proving sampled discoverability.


1. Purpose
An operator must be able to verify that their exact current Onion Service Discovery state is actually present in the index data a Discovery Node distributes.


A Node saying “you are listed” is not sufficient evidence by itself.


2. Discoverability Evidence Levels
LEVEL 0 — LOCAL_READY
Local configuration and signed Manifest valid.


LEVEL 1 — SELF_REACHABLE
The Manifest is directly reachable through the operator's own Onion Service and passes the normal verification pipeline.


LEVEL 2 — EXTERNALLY_OBSERVED
At least one selected external Discovery Node has directly observed the exact current Service state.


LEVEL 3 — INDEX_INCLUDED
The exact current Service state is demonstrably included in a Discovery Node's published active index/snapshot.


LEVEL 4 — MULTI_SOURCE_INCLUDED
The exact current Service state is included in the independently obtained active index/snapshot of multiple selected Discovery Nodes.


LEVEL 5 — STRONG_MULTI_SOURCE
LEVEL 4 plus valid signed checkpoints and no detected rollback/equivocation among checked sources.


3. Exact Identity Tuple
Inclusion is checked against the exact tuple:


logical_service_id
current Service Identity key/key epoch
canonical Onion address
Manifest revision
Manifest hash
Protocol version


The implementation MUST NOT infer inclusion from display name alone.


4. Search Ranking Is Not Evidence
The operator dashboard MUST NOT test discoverability by entering the Service name into the ordinary text-search UI and looking for a visible result.


Reasons:
ranking;
pagination;
filters;
language settings;
same-name Services;
search implementation differences.


Discoverability verification uses a deterministic identity lookup or index membership proof.


5. Canonical Index Record
Every actively discoverable Service record in an exported index has a canonical, deterministic representation containing enough information to bind:
- logical Service Identity;
- current canonical Onion;
- current Manifest revision;
- current Manifest hash;
- active publication state.


6. Deterministic Service Record Identifier
Define a deterministic record identifier derived from the canonical active record.


Recommended:
SHA-256(domain-separated canonical active index record).


Exact byte construction is frozen before Protocol v1 Stable.


7. Direct Lookup Endpoint
A Discovery Node MUST expose the canonical read-only deterministic stable-identity lookup.


Protocol v1 route:
GET /onion-service-discovery/v1/services/by-id/{logical_service_id}


This route is fixed by the Node API specification; alternate route spellings are not Protocol v1 conformance.


The response MUST identify:
- current indexed record;
- Manifest revision;
- Manifest hash;
- active/searchable state;
- index/checkpoint identifier;
- source Node Identity.


8. No Search Query Leakage
The identity lookup endpoint is an operator/protocol check, not a free-text search.


It accepts only a validated logical_service_id (or a separately specified exact canonical record identifier for proof retrieval). It never accepts the current rotatable Service Identity key fingerprint as the persistent identity selector.


No arbitrary search expression.


9. Snapshot Inclusion
When a client/operator has downloaded the full signed snapshot, it can independently search the snapshot locally for the exact identity tuple.


If present:
INDEX_INCLUDED can be established for that snapshot.


10. Signed Checkpoint
Every production Discovery Node that exports an active searchable index MUST publish a signed checkpoint describing that active index.


Required fields:
Protocol version
Node Identity
checkpoint sequence/revision
generated-at timestamp
record count
index root/hash
previous_checkpoint_hash according to the frozen checkpoint-chain rule
signature.


11. Checkpoint Meaning
The checkpoint signature means:
“This Node signed this exact index state.”


It does NOT mean:
all records are trustworthy;
the index is globally complete;
the Node is honest;
the Service is safe.


12. Index Root
For small/simple indexes, the root MAY initially be a deterministic hash of the canonical complete index representation.


For scalable operation, a Merkle-tree-based root SHOULD be used or introduced through a frozen compatible protocol mechanism.


13. Merkle Inclusion Proof
Where the index root uses a Merkle structure, the Node MAY return a compact inclusion proof for one exact Service record.


Proof inputs:
record identifier/hash
leaf position/key if applicable
sibling path
tree algorithm/version
signed checkpoint root.


The verifier locally recomputes the root.


14. Inclusion Proof Result
A valid proof establishes:
“This exact record is a member of this exact Node-signed index checkpoint.”


It does not establish:
global network inclusion;
honesty of the Service;
independence of Nodes;
absence of other omitted records.


15. Non-Inclusion
A simple Node statement “not found” is not a cryptographic proof of global non-existence.


If the chosen Merkle/data structure supports verifiable non-inclusion, a future Protocol version/capability MAY define it.


Protocol v1 does not need global non-inclusion proofs.


16. Operator Dashboard Evidence
For each checked Node, display one of:


CURRENT_INDEX_INCLUDED
CURRENT_INDEX_INCLUDED_PROOF_VERIFIED
STALE_REVISION_INCLUDED
IDENTITY_NOT_PRESENT
NODE_UNREACHABLE
CHECKPOINT_INVALID
PROOF_INVALID
NODE_EQUIVOCATION_DETECTED
CONFLICTING_SERVICE_STATE


17. Human-Facing Wording
Example:


“Current revision 17 is included in the signed active index of 3 of 4 checked Discovery Nodes.”


If Merkle proof verified:


“Index inclusion proof verified.”


Do not say:
“Listed everywhere.”
“Globally registered.”
“Network approved.”


18. Multi-Source Verification
The operator dashboard SHOULD check multiple configured/learned Nodes.


For each Node:
1. obtain signed checkpoint;
2. validate Node signature;
3. obtain current record or inclusion proof;
4. verify exact Service tuple;
5. compare checkpoint sequence/root against previously known state where available;
6. preserve source attribution.


19. No Majority Identity Rule
Three Nodes agreeing does not override invalid Service cryptography.


Node count improves evidence of propagation/availability only.


20. Stale Revision Detection
If Node contains:
same Service Identity
but lower Manifest revision/hash than operator's current state,
show:
STALE_REVISION_INCLUDED.


Do not count it as current discoverability.


21. Wrong Onion / Identity
If the same display name is present with another Service Identity:
it is unrelated to the operator's discoverability check.


22. Withdrawal
If current valid Manifest state is withdrawn:
the dashboard should expect active index removal according to Node policy.


Historical inclusion may remain in retained evidence and old signed checkpoints.


23. Checkpoint Rollback
If a Node previously supplied checkpoint sequence N/root R and later supplies an older checkpoint without a valid reset/recovery rule:
flag rollback/stale source.


24. Node Equivocation
If the same Node signs two incompatible checkpoints at one sequence, or produces two signed checkpoint histories that are not prefix-compatible:
record equivocation evidence.


Do not silently select one.


25. Checkpoint Gossip
Nodes MAY exchange or publish signed checkpoint observations.


Purpose:
make targeted split views harder to sustain without detection.


Gossip contains public Node/index metadata only, never search queries.


26. Witnesses
Optional independent witnesses MAY sign that they observed a particular Node checkpoint.


Witness counts are evidence of publication, not proof of independent organizations or Service trust.


27. Client-Host Separation
In separated mode:
the trusted/self-hosted Client Host verifies Node checkpoint and inclusion proofs locally.


A malicious data Node can serve invalid/stale/incomplete data but cannot make an invalid proof verify.


28. Combined Host Caveat
If the same malicious origin supplies both JavaScript and the data, it can falsify what the UI displays.


Therefore cryptographic inclusion verification is strongest when:
- client code is independently trusted/self-hosted; or
- results are checked by independent CLI/auditor tooling.


29. CLI Verification
Provide:
onion-service-discovery network:discoverability-check


Machine-readable output includes:
local exact tuple
Nodes checked
checkpoint identity/sequence/root
record state
proof state
external observation state
freshness
equivocation status
overall sampled discoverability.


30. CLI Proof Verification
Provide a command equivalent to:
onion-service-discovery index:verify-inclusion <checkpoint> <record> <proof>


It MUST work offline from supplied artifacts.


31. Exportable Evidence
Operator MAY export a small discoverability evidence bundle containing:
- own public Service tuple;
- Node public identity;
- signed checkpoint;
- inclusion proof or relevant snapshot record;
- verification result;
- timestamps.


No private key or user-query data.


32. Privacy
Discoverability checks concern the operator's own intentionally public Service.


Reference implementation does not use this mechanism for user search terms.


33. Abuse Resistance
Exact identity lookups/inclusion proof requests are bounded and cacheable.


Apply:
identifier validation
response-size limit
rate limit
no live Service fetch triggered by lookup
no dynamic expensive free-form query.


34. Snapshot Generation
Checkpoint/root generation MUST be deterministic for a given logical active index state and format version.


DB/storage iteration order must not alter the root.


35. Sorting
Define a deterministic sort/key order for full-index hashing or Merkle leaf placement.


Recommended key:
canonical logical_service_id followed by canonical record identifier where required.


Exact scheme frozen in Protocol spec.


36. Merkle Algorithm
If Merkle is used:
hash algorithm
leaf domain prefix
branch domain prefix
tree shape
odd-leaf handling
key ordering
empty-tree root
proof encoding
MUST be normative and backed by cross-language vectors.


37. Upgrade
Changing Merkle/root semantics requires a new index-root algorithm/version and cannot silently reinterpret old checkpoints.


38. Current Index vs Historical Evidence
The discoverability proof is about the Node's active/current searchable index.


Historical archives are not counted as current discoverability.


39. Search Export Equivalence
The active record set used for discoverability proof MUST correspond to the record set eligible for normal local search export under the same index generation.


A Node must not maintain one “proof index” and a different unaccounted searchable index while claiming conformance.


40. End-to-End Test
Required scenario:


Step 1 — synthetic Service publishes revision 4;
Step 2 — self-verification passes;
Step 3 — Service announces to Node A/B/C;
Step 4 — each Node independently performs direct verification;
Step 5 — each Node updates active index;
Step 6 — signed checkpoints produced;
Step 7 — operator retrieves checkpoint + record/proof;
Step 8 — local verifier confirms revision 4/hash;
Step 9 — Web search snapshot contains same record;
Step 10 — dashboard reports current sampled discoverability.


41. Stale Propagation Test
Node C intentionally remains at revision 3.


Expected:
A/B count as current;
C displayed stale;
dashboard says current revision included on 2 of 3 checked Nodes.


42. Omission Test
Node B maliciously omits the Service.


Expected:
A/C evidence remains valid;
B shows not present;
operator sees source disagreement;
Service is not globally declared absent.


43. Equivocation Test
Node A signs two roots for the same checkpoint sequence, and a second fixture creates two divergent signed checkpoint histories from a common predecessor.


Expected:
equivocation evidence retained;
Node A flagged;
its presence result does not erase valid Service evidence from B/C.


44. Invalid Proof Test
Node supplies record with corrupted Merkle path.


Expected:
PROOF_INVALID;
not counted.


45. Reproducibility
PHP, TypeScript and independent validator MUST compute identical:
active record canonical bytes;
record identifier;
checkpoint root;
inclusion proof verification.


46. Conformance
Add tests:
CONF-INDEX-RECORD
CONF-CHECKPOINT
CONF-INCLUSION
CONF-ROLLBACK
CONF-EQUIVOCATION
CONF-SEARCH-EXPORT-EQUIVALENCE


47. Formal Model
Model safety property:
INDEX_INCLUDED for Node N can only become true after validating a record/proof against a valid checkpoint or independently verifying membership in the exact signed snapshot.


48. Dashboard Overall State
Recommended derivation:


DISCOVERABLE:
at least one checked Node has CURRENT_INDEX_INCLUDED or CURRENT_INDEX_INCLUDED_PROOF_VERIFIED for exact current tuple.


DISCOVERABLE_REDUNDANT:
at least configured threshold of distinct selected Nodes have current inclusion; this is redundancy, not trust consensus.


DISCOVERABLE_DEGRADED:
prior/current evidence exists but checked Nodes are stale/unreachable below configured operational redundancy.


49. Threshold Configuration
The redundancy threshold is local UX/operations policy.


It must not change Protocol identity truth.


50. Evidence Freshness
Dashboard reports age of each checkpoint/observation.


Old valid inclusion is not presented as fresh current confirmation beyond configured freshness policy.


51. Node Capability
Nodes advertise whether they support:
EXACT_IDENTITY_LOOKUP
SIGNED_CHECKPOINT
MERKLE_INCLUSION_PROOF
CHECKPOINT_GOSSIP
through the Protocol capability registry.


52. Backward Compatibility
A Node without Merkle proof support can still establish sampled inclusion by:
- signed deterministic complete snapshot; and
- local exact membership verification.


The UI shows weaker evidence type precisely.


53. Minimum v1 Requirement
For Protocol v1 production:
- exact identity lookup;
- deterministic active index;
- signed checkpoint;
- local membership verification.


Merkle inclusion proofs MAY be optional capability initially if implementing them safely would delay the core unnecessarily.


54. Preferred Scalable Profile
For large indexes:
signed Merkle root + compact inclusion proof.


55. Final Security Semantics
There are three separate claims:


A. Service Identity valid.
B. Node directly observed current state.
C. Current state is included in that Node's active signed index.


The UI and API MUST keep them separate.


56. Acceptance
PASS requires:
- exact current Service tuple;
- deterministic active index membership;
- Node-signed checkpoint;
- operator-local inclusion verification;
- multi-Node comparison;
- stale/omission/equivocation handling;
- offline proof verifier;
- search-export equivalence test;
- no global-completeness claim


57. SYBIL INTERPRETATION OF MULTI-SOURCE INCLUSION
Inclusion on N Node identities demonstrates distribution across N distinct cryptographic Node identities.


It does NOT cryptographically prove N independent humans/operators.


58. SOURCE-GROUP REDUNDANCY
Operators MAY define local source groups to avoid counting known/declared related infrastructure multiple times for redundancy goals.


Group membership can reduce redundancy credit; it cannot increase cryptographic trust.


59. CHECKPOINT WITNESS COUNTS
Many witness identities do not increase the truth value of a checkpoint.


Their useful function is dissemination/detection:
if contradictory signed checkpoints exist, a single independently verified contradictory pair is sufficient evidence of Node equivocation.








60. SYBIL-PROOF MISBEHAVIOR LOGIC
Objective evidence is binary/verifiable, not a vote.


A Node cannot erase its own valid contradictory signatures by creating many Sybil identities that attest otherwise.




61. CHECKPOINT CHAIN CONSISTENCY
A checkpoint is not evaluated in isolation when continuity evidence is available.


Verifier rules:
- sequence successor is exact +1;
- previous_checkpoint_hash binds to the exact predecessor;
- a later checkpoint is current only if its observed chain is a valid extension of the previously accepted chain;
- a non-prefix-compatible signed history from the same Node is retained as equivocation evidence;
- missing history is reported as insufficient consistency evidence, not silently treated as proof of consistency.


62. STABLE IDENTITY LOOKUP
Index membership keys on logical_service_id.
The current signing key/fingerprint is a mutable key-epoch attribute and MUST NOT be used alone as the persistent identity lookup key.
