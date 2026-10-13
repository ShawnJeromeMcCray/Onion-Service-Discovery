# 14 - Node API Sync Contract and Interoperability Specification

Source: https://docs.google.com/document/d/1vcK0HW3newL3tgIDHdrI8oieSANKXRvB42tvosn3HoY/edit?usp=drivesdk

Source modified: 2026-10-12T19:26:50.382Z

---

ONION SERVICE DISCOVERY
NODE API, SYNC CONTRACT AND INTEROPERABILITY SPECIFICATION


Status
Normative companion for Protocol v1 node-to-node and client-to-node read interactions.


1. Design Goals
- read-oriented;
- pull synchronization;
- bounded responses;
- idempotent retrieval;
- no remote administration;
- no arbitrary URL fetching;
- explicit versioning;
- transport untrusted;
- content independently verifiable.


2. Base Path
/onion-service-discovery/v1/


3. Media Type
Initial baseline:
application/json; charset=utf-8
A project-specific media type may be registered later, but Protocol v1 must not require an unavailable registration.


4. Common Response Envelope
For endpoints returning protocol objects:
{
  "protocol": "Onion Service Discovery",
  "protocol_version": 1,
  "response_type": "...",
  "generated_at": "RFC3339Z",
  "data": ...,
  "pagination": ... optional
}


For signed records, the signed record remains self-contained. The HTTP envelope is not a replacement for record signatures.


5. Error Envelope
{
  "protocol": "Onion Service Discovery",
  "protocol_version": 1,
  "error": {
    "code": "STABLE_SYMBOLIC_CODE",
    "message": "bounded human-safe text",
    "retry_after_seconds": optional bounded integer
  }
}


No stack traces.
No filesystem paths.
No SQL.
No private peer-selection secrets.
No raw exception dumps.


6. GET /node
Returns Node Descriptor or wrapper containing it.


Use:
bootstrap identity/capabilities.


Requirements:
- bounded;
- cacheable with ETag if implemented;
- Node Descriptor signature valid independently;
- does not expose administrative configuration.


7. GET /state
Purpose:
cheap sync comparison.


Data SHOULD include:
node identity key/fingerprint
protocol version
current snapshot/root id
generation/revision identifier
record count bounded integer
latest change cursor token if used
capabilities


State must be small and deterministic enough for frequent pull.


8. State Identifier
A state identifier MUST be content-derived or otherwise unambiguous.
Preferred:
root hash of deterministic index export plus generation metadata.


A peer-provided state ID is only a synchronization hint until associated content hashes validate.


9. GET /peers
Returns bounded Peer Descriptors.


Query parameters:
limit optional, server-capped.
cursor optional opaque value if pagination.


Rules:
- no more than protocol response cap;
- sorted deterministically or cursor-stable;
- peer source information does not imply trust;
- client/node must validate every descriptor.


10. SERVICE LOOKUP ENDPOINTS


Canonical stable identity lookup:
GET /services/by-id/{logical_service_id}


The path value MUST parse as the frozen Protocol v1 Logical Service Identifier representation. URL encoding is applied only as required by HTTP; decode-once semantics apply. The server performs no fuzzy/prefix lookup and no live remote fetch.


Address convenience lookup:
GET /services/{onion}


The path value MUST parse as a strict canonical OnionAddress before StorageBackend lookup. This route is address-oriented convenience and MUST return the resolved logical_service_id/current key epoch with the record. An Onion address alone is not the persistent logical identity across a valid migration.


Response MAY include:
current Service Manifest
continuity metadata
selected directly observed evidence
migration/rotation chain references
stale status


It MUST distinguish signed source data from local observation metadata.


11. GET /changes
Purpose:
bounded incremental synchronization.


Recommended query:
since=<opaque cursor>
limit=<bounded integer>


Cursor:
- opaque to client;
- must not contain secrets;
- stable enough for continuation;
- invalid/expired cursor returns defined error requiring snapshot recovery.


Changes response:
ordered list of immutable change events or record references.


12. Change Event Semantics
Each change event has:
sequence/cursor identity
record type
record identity
content hash
operation semantics


Avoid destructive DELETE events for cryptographic history.
A record may become inactive/stale, but evidence history is not silently erased.


13. Snapshot Fallback
If cursor is too old:
server returns CURSOR_EXPIRED/SNAPSHOT_REQUIRED with current snapshot descriptor reference.
Client fetches snapshot and resumes from associated cursor.


14. GET /snapshots/{snapshot-id}
Returns signed Snapshot Descriptor, not necessarily entire index.


Descriptor:
snapshot id
state/root hash
created_at
protocol version
record count
chunk descriptors
node identity
signature


15. GET /chunks/{hash}
Returns deterministic chunk content matching requested content hash.


Rules:
- hash strict parse;
- response max fixed;
- no arbitrary file-path mapping;
- lookup by DB/content-addressed store only;
- missing => 404;
- mismatch between requested hash and bytes => server bug; client rejects.


16. Chunk Format
Protocol v1 MUST freeze one:
A. canonical JSON object/array with deterministic ordering; or
B. newline-delimited canonical JSON records.


Decision must optimize:
- cross-language simplicity;
- streaming;
- bounded parsing;
- deterministic hashing.


Before freeze create vectors for exact bytes.


17. Recommended Chunk Partitioning
Partition deterministic by sorted primary key and size target.
Chunk boundary must not depend on locale, DB insertion order or runtime hash iteration.


18. Compression
HTTP compression optional.
Hash validates canonical uncompressed content semantics according to frozen chunk design.
Client enforces expanded size cap.


19. Pagination
All list endpoints:
server maximum;
client cannot request unlimited.
Cursor preferred over page numbers for changing sets.
Cursor format opaque.
No DB offset assumptions in protocol.


20. Caching
ETag/If-None-Match may reduce traffic.
ETag is transport optimization, not cryptographic trust.
Cache-Control must avoid unsafe sensitive state; public node data is intended public but state freshness matters.


21. Rate Limiting
Public API MAY enforce rate limits.
429 with bounded Retry-After.
A client respects reasonable backoff.
Rate-limiting headers do not become protocol identity.


22. Client Authentication
Protocol v1 public read API does not require user accounts.
No cookies/session required for standard sync.
This minimizes tracking and operational complexity.


23. Administration
No public endpoint:
POST /admin
PUT configuration
execute job
add arbitrary peer with server write side effect
fetch arbitrary URL
rotate key
delete evidence


Administration uses local CLI/filesystem mechanisms.


24. CORS and Web Profiles
Machine Node API is public read-oriented data.


WEB-COMBINED-V1:
the Web Client uses same-origin endpoints; broad CORS is unnecessary.


WEB-SEPARATED-V1:
the selected reference architecture is frozen by D-004/D-044 after Tor Browser tests:
A. credentialless browser reads from explicitly allowed/configured data-node origins; or
B. the Client Host performs a query-independent generic proxy/cache fetch.


Any direct cross-origin mode MUST use public read-only data, no cookies/Authorization/credentials, fixed methods/content types and a reviewed CSP/network-destination policy.
Credentialed wildcard CORS is forbidden.


25. HTTP Methods
Public Protocol read endpoints use GET/HEAD as specified.
The Candidate Announcement endpoint is the explicit write-like exception: it accepts only an untrusted bounded Candidate hint and grants no remote administrative/storage authority.
Unsupported methods return 405 + Allow.


26. Candidate Announcement
Protocol v1 reference/operator profile includes bounded self-announcement for explicitly opted-in publishing.


Proposed endpoint:
POST /onion-service-discovery/v1/candidates/announce
Exact route/media type is frozen under D-018 before Protocol Freeze.


Accepted input is minimal and bounded:
- Protocol version;
- canonical OnionAddress;
- optional current Manifest revision/hash and public identity hint for correlation only.


The receiver MUST:
- treat the request only as an untrusted Candidate;
- apply global/per-source/target-centric work limits;
- avoid synchronous expensive direct verification;
- independently fetch the fixed Well-Known Manifest over Tor before creating a Direct Observation or active index record.


The endpoint MUST NOT accept:
- arbitrary URLs;
- arbitrary ports/paths/proxies;
- trusted metadata writes;
- Service/Node trust assertions.


Announcement is not required for node-to-node synchronization and is never proof of discoverability.






27. Sync Algorithm Reference
For each selected peer:
Step 1 — GET /node if descriptor stale/unknown.
Step 2 — verify Node Descriptor.
Step 3 — GET /state.
Step 4 — compare local known peer state.
Step 5 — if current => stop.
Step 6 — if valid incremental cursor => GET /changes bounded batches.
Step 7 — validate each received record/envelope.
Step 8 — stage records.
Step 9 — resolve protocol conflicts to quarantine/state, never silent overwrite.
Step 10 — commit the StorageBackend atomic batch/transaction boundary.
Step 11 — update peer cursor only after durable commit.
Step 12 — schedule direct service verification separately under work budget.


28. Never Verify Inline Recursively
While applying peer changes:
do not synchronously chase every related Onion address.
Candidate hints go to bounded queue.
This prevents attacker-controlled recursive fanout.


29. Change Application Idempotency
Applying same valid change twice:
must produce same database state.
Duplicate immutable hash/revision:
no duplicate semantic event.


30. Transaction Boundaries
Recommended:
one bounded change batch per transaction.
If any structural/storage invariant fails:
rollback batch or isolate invalid record according to documented strategy.
Do not advance cursor beyond uncommitted data.


31. Mixed Validity Batch
Protocol policy must freeze whether:
A. one invalid record rejects entire batch; or
B. individually signed records can be quarantined while remaining valid records commit.


Recommended:
transport/envelope/hash failure rejects whole batch.
Record-specific semantic conflict can quarantine that record if batch framing remains trustworthy.
Document exact rule and tests.


32. Peer Assertions vs Direct Observations
Peer sync may bring:
service manifest history
Node DirectObservation
Peer Assertion
migration/rotation evidence


Receiving another node’s DirectObservation does not convert it to local DirectObservation.
Local direct observation only from local transport verifier.


33. Index Export Semantics
Client-facing snapshot represents discoverable current view plus necessary continuity evidence.
Do not require client to ingest every transient peer assertion ever seen.


Define export profiles:
CORE_SEARCH_INDEX
IDENTITY_EVIDENCE
OPTIONAL_OBSERVATIONS
MODERATION


Initial client can request a fixed profile if capability negotiation is not yet used.


34. Privacy
No endpoint accepts search query.
The machine Node API offers no free-text search endpoint. The optional human No-JavaScript Server Search route is a separate Web route with explicit privacy disclosure and is not part of the Node sync API.
This is intentional.


35. Request Logging
Operators may log minimal sync API events, but reference config:
- no client fingerprint token;
- no cookies;
- no query search terms because none exist;
- truncate/normalize invalid paths;
- log rate-limit/security event classes with privacy review.


36. Node Enumeration
/peers can reveal public infrastructure nodes by design.
Node operators opt into node role.
Service-only operators with mirror_directory=false/node disabled are not automatically advertised as peers.


37. Capability Registry
Initial capability tokens could include:
sync.changes.v1
sync.snapshots.v1
index.core.v1
observations.v1


Tokens are informational/negotiation and cannot weaken mandatory verification.


38. Protocol Negotiation
/node lists supported protocol versions.
A v1 client selects v1 if present.
No downgrade from known stronger required version without explicit compatibility policy.
Unknown version => unsupported, not best-effort reinterpretation.


39. Health Endpoint
Operational health MAY exist outside protocol base path.
It is not part of identity/sync semantics.
Public health response minimal.


40. Timeout Budgets
Client/node sets:
connect timeout
total request timeout
maximum body bytes
maximum batch records
maximum cumulative bytes per peer sync run


Server response generation also uses work limits.


41. Error Retry Classes
Retryable examples:
TRANSPORT_TIMEOUT
TEMPORARY_UNAVAILABLE
RATE_LIMITED
CURSOR_EXPIRED via snapshot recovery


Non-retry-immediate examples:
SCHEMA_INVALID
SIGNATURE_INVALID
NODE_DESCRIPTOR_INVALID
PROTOCOL_UNSUPPORTED


42. Cache Poisoning Defense
Client never trusts HTTP cache metadata over signed/hash content.
A stale valid snapshot can be marked stale based on local peer state policy, but cannot silently rewrite newer local identity history.


43. Equivocation
A malicious node may serve different snapshots to different clients.
Mitigations:
- Node-signed snapshot descriptors;
- content hashes;
- clients can compare multiple sources;
- optional future transparency gossip.
Protocol v1 must retain evidence sufficient to diagnose observed conflicting Node-signed states.


44. Snapshot Monotonicity
Node’s own snapshot generation identifier should be monotonic and signed.
If same Node Identity presents same generation with different root:
NODE_EQUIVOCATION/FORK evidence.
Do not hide.


45. Learned-Peer Loop Resistance
Peer A lists B, B lists A:
dedup by Node Identity and Onion.
No recursive immediate crawl.
Peer candidates scheduled separately.
Maximum learned peer DB.


46. API Schema Files
Coding agent MUST produce JSON Schemas/OpenAPI for:
node response
state
peers
service
changes
snapshot descriptor
chunk format
error envelope


OpenAPI describes transport; signed JSON schemas remain protocol truth.


47. Interoperability Fixtures
Create static mock server corpus:
healthy node
stale node
unsupported version
bad node signature
state changes
expired cursor
invalid chunk hash
oversized response
mixed-conflict batch
equivocating snapshot


PHP sync logic and Web Local Search index-update logic reuse the same protocol fixtures where applicable.


48. API Fuzzing
Fuzz:
query parser
cursor handling
path Onion parsing
JSON envelopes
chunk parser
pagination boundaries


49. Backward Compatibility
Within Protocol v1:
server MAY add HTTP headers.
Server MUST NOT add unknown signed record fields outside defined extension policy.
Transport envelope optional additions only if schema explicitly permits and clients safely ignore them.


50. Availability Philosophy
A peer’s API is opportunistic.
Local cached search should remain usable if no peer responds.
Sync errors degrade freshness, not erase index.


51. Reference Implementation Acceptance
Two independently configured PHP nodes plus client must demonstrate:
- bootstrap;
- node descriptor validation;
- snapshot acquisition;
- changes sync;
- cursor expiration recovery;
- conflict quarantine;
- peer outage tolerance;
- active-session local search continues after the Node becomes unreachable once the index is loaded; persistent cross-restart offline behavior is best-effort only.


52. Final Contract Freeze
Before Protocol v1 freeze, exact:
paths
query parameters
schemas
cursor error semantics
chunk bytes
snapshot descriptor
capability tokens
must be locked and test-vectored


53. HUMAN WEB SEARCH ROUTES
The human-facing Web Search routes are intentionally outside the machine Node API semantics, though they use the same local verified index.


GET /onion-service-discovery/
Purpose:
serve the static Web Local Search client.


GET /onion-service-discovery/search/
Optional alias to the same client.


POST /onion-service-discovery/search/server
Optional No-JavaScript server-rendered search fallback.


54. WEB LOCAL SEARCH INDEX ACCESS
The Web Local Search page uses same-origin generic index/snapshot/chunk endpoints.


Requirements:
- no query parameter carries the user’s search term;
- responses are generic with respect to the current query;
- same-origin baseline means no broad CORS requirement;
- client can progressively load bounded chunks;
- snapshot/index freshness is visible.


55. SERVER SEARCH CONTRACT
POST request body contains bounded search parameters.


Reference fields:
query
language optional
category optional
status filters optional.


Rules:
- no cookies/accounts;
- no query in URL;
- no query logging;
- no query-triggered outbound network request;
- search only local verified/derived index;
- finite work/result limits;
- HTML output safely escaped;
- Cache-Control: no-store;
- clear privacy notice that the Node receives the query.


56. SEARCH SEMANTICS VERSION
Local and server search share a versioned search-semantics identifier so equivalent index/query inputs can be regression-tested for comparable ranking/filter behavior.


Search-semantics version is software/client behavior, not Service identity truth.




57. EXACT IDENTITY LOOKUP
The Node API MUST provide a deterministic read-only method to retrieve the active index state for an exact Service Identity or canonical record identifier.


It is not a free-text search endpoint.


Response includes:
Service Identity
canonical Onion
Manifest revision
Manifest hash
active/searchable flag
checkpoint identifier
Node Identity.


58. SIGNED INDEX CHECKPOINT
Production Nodes MUST expose a signed checkpoint for the active searchable index.


Required:
Node Identity
Protocol version
checkpoint sequence
generated timestamp
record count
index root/hash
previous checkpoint hash (genesis representation frozen by D-052)
signature.


59. INDEX ROOT
The active record set MUST have a deterministic root.


Protocol v1 minimum:
deterministic canonical complete-index hash or a frozen Merkle construction.


Preferred scalable capability:
Merkle root with compact inclusion proofs.


60. INCLUSION PROOF ENDPOINT/CAPABILITY
If MERKLE_INCLUSION_PROOF capability is advertised, the Node provides a compact proof binding the exact active Service record to the signed checkpoint root.








The proof MUST be locally verifiable without trusting a Node boolean.


61. SEARCH-EXPORT EQUIVALENCE
The record set committed by the signed active-index checkpoint MUST correspond to the record set eligible for normal Discovery index export/search.


A conformant Node cannot advertise one hidden “proof index” while serving a materially different active search index under the same checkpoint.


62. CHECKPOINT ROLLBACK
Clients/operators track the newest known checkpoint sequence/root per Node where state persists.


Unexpected rollback is flagged and not silently accepted as current.


63. EQUIVOCATION
A Node checkpoint history is valid only when each successor uses exact sequence +1 and previous_checkpoint_hash equals the accepted predecessor hash. Two valid signed checkpoints at the same sequence with different hashes, or two signed histories from the same Node that are not prefix-compatible, constitute equivocation evidence once both histories/evidence are available.


The API/data model preserves both.


64. CHECKPOINT GOSSIP
Optional capability:
CHECKPOINT_GOSSIP.


Nodes can share public signed checkpoint observations for split-view detection.
No user queries are included.


65. API ERROR/ABUSE
Exact identity lookup and proof retrieval:
- validated identifier only;
- finite response;
- no arbitrary query;
- cacheable;
- no live Service fetch triggered;
- rate-limited.




66. LOGICAL SERVICE LOOKUP KEY
Exact identity lookup MUST use logical_service_id or a deterministic active-record identifier, not the current rotatable Service Identity key fingerprint alone.


The response includes:
- logical_service_id;
- current Service Identity key/key epoch;
- canonical Onion;
- current revision/hash;
- active/searchable state;
- signed checkpoint reference.


67. CHECKPOINT HISTORY VERIFICATION
A production index-exporting Node MUST make enough signed checkpoint metadata available for a verifier/auditor to validate the observed predecessor chain or obtain an equivalent frozen consistency proof.
A latest-checkpoint signature alone is insufficient to claim that two views are prefix-consistent.
