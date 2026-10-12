# 12 - Protocol Limits Encoding Registry and State Machines

Source: https://docs.google.com/document/d/1-IqYDKd5RLl3bVUA5BjKoe94bUohVXpGBKtFQIn_Rec/edit?usp=drivesdk

Source modified: 2026-10-11T04:38:51.083Z

---

ONION SERVICE DISCOVERY
PROTOCOL LIMITS, ENCODING REGISTRY AND STATE MACHINES


Status
Normative Protocol v1 companion. These values are deliberately finite. Implementations MAY impose stricter local work budgets, but MUST NOT silently accept values outside the Protocol v1 wire limits and then reinterpret them differently.


1. General Encoding
All Protocol v1 JSON documents:
- UTF-8 only;
- no byte-order mark;
- top-level JSON object;
- no duplicate object member names;
- finite nesting;
- Content-Type application/json or a future registered Onion Service Discovery media type accepted according to version policy;
- signed payloads canonicalized exactly according to the protocol canonicalization ADR.


A parser MUST reject duplicate keys before semantic processing.


2. Numeric Interoperability
JSON integer values used in Protocol v1 MUST remain exactly representable by JavaScript Number:
minimum where applicable: 0
maximum: 9,007,199,254,740,991 (2^53 - 1)


revision:
integer from 1 through 2^53 - 1.


Implementations MUST reject fractional, negative, NaN-like, stringified-number or out-of-range values where an integer is specified.


3. Onion Address
Canonical hostname:
56 lowercase Base32 characters plus ".onion".


Strict validation MUST decode the Tor v3 address payload and verify:
- decoded 32-byte Ed25519 identity public key;
- 2-byte checksum;
- version byte equals 0x03;
- checksum recomputation per Tor specification.


Stored canonical form is lowercase ASCII hostname including ".onion".
Protocol fields MUST NOT contain scheme, path, port, credentials or whitespace when the schema says OnionAddress.


4. Hash Encoding
Protocol v1 content hash:
sha256:<64 lowercase hexadecimal characters>


Example pattern:
^sha256:[0-9a-f]{64}$


Hash is computed over the exact protocol-defined canonical byte sequence.
No uppercase hex.
No implicit algorithm.


5. Ed25519 Public-Key Encoding
Protocol v1 application public key:
ed25519:<base64url-no-padding>


Decoded length MUST equal 32 bytes.
Only URL-safe Base64 alphabet.
No padding.
Canonical encoder required.


6. Ed25519 Signature Encoding
ed25519sig:<base64url-no-padding>


Decoded signature length MUST equal 64 bytes.
Non-canonical encodings rejected.


7. Timestamp Encoding
When timestamps are part of records:
RFC 3339 UTC, second precision, canonical trailing Z.
Example:
2026-10-11T14:32:09Z


Protocol signatures MUST use the canonical text exactly.
Sub-second precision is not used in Protocol v1.
Timestamps are evidence/context, not identity ordering.


8. Document Byte Limits
Limits apply to received body bytes before decompression or, if compression is later specified, both compressed and decompressed limits MUST exist.


Service Manifest: 16 KiB
Key Rotation Record: 8 KiB
Onion Migration Record: 8 KiB
Node Descriptor: 16 KiB
Direct Observation: 8 KiB
Peer Assertion envelope: 16 KiB
Moderation Signal: 16 KiB
State response: 16 KiB
Peers response: 256 KiB
Single service response: 32 KiB
Changes response: 1 MiB
Snapshot Descriptor: 64 KiB
Snapshot/Index Chunk: 512 KiB


Any expansion of these protocol limits requires Protocol Change Proposal and resource-impact tests.


9. JSON Structural Limits
Maximum nesting depth: 16.
Maximum object members at any one object: 128.
Maximum array elements unless a field has a stricter limit: 256.
Maximum JSON string length unless stricter: 4096 Unicode scalar values.
Parser MUST enforce a total byte cap in addition to structural caps.


10. Service Name
Required if publish_service=true.
Length: 1..128 Unicode scalar values.
Leading/trailing whitespace normalization policy must be deterministic.
Control characters prohibited except no embedded line control is necessary; Protocol v1 SHOULD reject C0/C1 control characters.
Bidirectional formatting/control characters are not used to establish identity; client SHOULD warn or display safely.
HTML is not interpreted.


11. Description
0..2048 Unicode scalar values.
Plain text.
No embedded markup semantics.
Control characters prohibited except ordinary line feed may be allowed only if the JSON/schema ADR explicitly permits it.
Client escapes display.


12. Languages
Array maximum: 16.
Each value maximum: 35 ASCII characters.
BCP 47-style language tag validation SHOULD be used through a tested library/parser or conservative grammar.
Case canonicalization policy documented.
Duplicate tags after canonical comparison rejected or deterministically de-duplicated before signing; Protocol v1 should prefer rejection for signed manifests.


13. Categories
Maximum: 32.
Each category ID:
1..64 ASCII characters.
Recommended restricted grammar:
lowercase letters, digits and hyphen, starting/ending alphanumeric.
Free-form human labels belong in future localized metadata, not category identifiers.


14. Related Onion Services
Maximum: 32 per manifest.
Each entry is only canonical OnionAddress.
They are Candidate hints, not inherited trust.


15. Clearnet Links
Maximum: 8.
HTTPS only in Protocol v1 if enabled.
No credentials.
Maximum URL length: 2048 bytes.
No automatic fetch unless separate proof policy enables it.
Clearnet proof processing MUST have a separate outbound-policy and threat-model path.


16. Peer Lists
Maximum Peer Descriptors in one public response: 256.
Node local database MAY know more, but selection/export is bounded.
Each learned peer is separately validated.


17. Capabilities
Maximum capabilities per Node Descriptor: 32.
Capability token max: 64 ASCII characters.
Unknown capability does not imply support.


18. Software Metadata
Implementation name: max 64 ASCII/UTF-8 safe display chars.
Implementation version: max 64.
These fields are informational and MUST NOT participate in service trust.


19. Error-Code Registry
Stable Protocol v1 symbolic codes:
PROTOCOL_UNSUPPORTED
DOCUMENT_TOO_LARGE
JSON_INVALID
JSON_DUPLICATE_KEY
JSON_DEPTH_EXCEEDED
SCHEMA_INVALID
ENCODING_INVALID
ADDRESS_INVALID
ONION_BINDING_MISMATCH
PUBLIC_KEY_INVALID
SIGNATURE_INVALID
HASH_INVALID
HASH_MISMATCH
REVISION_INVALID
REVISION_STALE
REVISION_FORK
REVISION_CHAIN_BROKEN
KEY_ROTATION_INVALID
MIGRATION_INVALID
NODE_DESCRIPTOR_INVALID
OBSERVATION_INVALID
STATE_CONFLICT
RATE_LIMITED
WORK_BUDGET_EXHAUSTED
TRANSPORT_UNAVAILABLE
TRANSPORT_TIMEOUT
TRANSPORT_FAILURE
REDIRECT_FORBIDDEN
CONTENT_TYPE_INVALID
RESPONSE_TOO_LARGE
STORAGE_ERROR
QUARANTINED
INTERNAL_ERROR


Public APIs SHOULD expose stable safe codes without internal exception details.


20. HTTP Status Mapping
Suggested baseline:
200 success
304 cache-valid where formally supported
400 malformed protocol request/path/query
404 unknown record
405 wrong method
409 protocol state conflict where relevant
413 body/request too large
415 unsupported content type
429 rate limited
500 internal failure
503 temporarily unavailable/degraded


A status code never substitutes for signed record semantics.


21. Candidate States
RECEIVED
ADDRESS_VALIDATED
QUEUED
DIRECT_CHECK_PENDING
VERIFIED
BACKOFF
QUARANTINED
REJECTED


Allowed transitions:
RECEIVED -> ADDRESS_VALIDATED | REJECTED
ADDRESS_VALIDATED -> QUEUED | REJECTED
QUEUED -> DIRECT_CHECK_PENDING | QUARANTINED
DIRECT_CHECK_PENDING -> VERIFIED | BACKOFF | QUARANTINED | REJECTED
BACKOFF -> QUEUED | REJECTED
VERIFIED -> QUEUED only for scheduled re-verification as a new attempt while verified historical state remains
QUARANTINED -> QUEUED only by explicit policy/manual or resolved evidence
REJECTED terminal for that candidate record unless a new independently sourced candidate is created according to retention policy.


22. Service States
UNKNOWN
CANDIDATE
VERIFIED_CURRENT
VERIFIED_STALE
TEMPORARILY_UNREACHABLE
LONG_UNSEEN
MIGRATED
IDENTITY_CHANGED
FORKED
QUARANTINED
REJECTED


Important:
Liveness and identity are orthogonal internally even if a convenience aggregate status exists.
A Service can have valid identity continuity while temporarily unreachable.
A Fork is an identity/history conflict and MUST NOT be cleared merely because one branch responds faster.


23. Peer States
UNKNOWN
CANDIDATE
ACTIVE
BACKOFF
STALE
QUARANTINED
DISABLED


Peer state influences synchronization scheduling, not Service identity.


24. Sync States
NEVER_SYNCED
SYNCING
CURRENT
STALE
BACKOFF
ERROR
DISABLED


Only one active sync job per peer/source logical lock unless implementation proves safe concurrency.


25. Verification Result Classes
SUCCESS_CURRENT
SUCCESS_UNCHANGED
SUCCESS_NEW_REVISION
SUCCESS_VALID_ROTATION
SUCCESS_VALID_MIGRATION
FAIL_ADDRESS
FAIL_FETCH
FAIL_TIMEOUT
FAIL_TOO_LARGE
FAIL_PARSE
FAIL_SCHEMA
FAIL_BINDING
FAIL_SIGNATURE
FAIL_STALE
FAIL_FORK
FAIL_CHAIN
FAIL_ROTATION
FAIL_MIGRATION
FAIL_RESOURCE_LIMIT
FAIL_INTERNAL


Result classes are auditable events, not all necessarily public API values.


26. Retry Baseline
Network/transient failures use exponential backoff with jitter.
Default reference schedule MAY begin around:
1 hour
4 hours
12 hours
24 hours
72 hours
then capped periodic retry.


Exact scheduler policy is implementation/local policy, but:
- no retry loop faster than explicit minimum;
- max attempts/work per maintenance run;
- success resets transient backoff appropriately;
- cryptographic invalidity is not treated as transient network failure.


27. Direct Verification Redirect Policy
For /.well-known/onion-service-discovery.json:
- maximum redirects: 0 for identity-binding verification.
If future protocol explicitly supports same-host redirects, it requires Protocol Change Proposal; current v1 does not.


28. Compression
Protocol v1 baseline SHOULD serve uncompressed or ordinary HTTP content coding only if both implementations safely enforce decompressed limits.
A malicious compressed response MUST NOT bypass maximum expanded size.
If robust expanded-limit enforcement is not implemented, disable compressed responses for verification paths.


29. Ordering
Arrays whose ordering has no semantic meaning MUST have a defined signing/canonicalization rule.
Preferred Protocol v1 approach:
- semantic arrays that are sets are required to be lexicographically sorted in their canonical manifest form and contain no duplicates;
- sequence/history arrays where order matters preserve order.


This rule must be reflected in schemas/semantic validation and test vectors.


30. Unknown Fields
Signed Protocol v1 record types:
Unknown top-level or nested fields in a record declared protocol_version=1 are rejected unless the specific schema defines an extension container.


Rationale:
prevents different implementations from signing/verifying different semantic subsets.


Future extensibility:
Use explicit extensions object with namespaced keys only after extension policy is specified, or increment protocol version for new normative semantics.


31. Null vs Absent
Unless a schema explicitly states otherwise:
Optional field absent is canonical.
Explicit null is rejected.
Genesis previous_manifest_hash is the one intentional nullable field if Protocol v1 schema retains that design; alternatively a genesis-specific absent rule may be chosen before freeze. The agent MUST freeze exactly one representation and vector-test it.


32. Booleans
Only JSON true/false accepted.
No numeric/string equivalents.


33. Duplicate Semantic Values
languages, categories, peers, related_onion_services and capabilities MUST NOT contain duplicates after canonical comparison.
Reject signed input with duplicates rather than silently rewriting before verification.


34. Path Construction
The verifier constructs:
/.well-known/onion-service-discovery.json
internally.
The candidate does not provide the path.
No query string.
No fragment.


35. Ports
Protocol v1 direct Service Manifest verification uses the service’s configured standard HTTP endpoint as defined by implementation/operator mapping; network-sourced candidates MUST NOT choose arbitrary ports.
If alternate public ports are to become protocol metadata, a later explicit specification is required.


36. Work Budgets
Protocol limits are not sufficient against DoS.
Reference node MUST also define local:
max new candidates accepted per peer per interval
max verification attempts per maintenance run
max concurrent Tor requests
max bytes per peer sync run
max records applied per transaction
max learned peers retained
max database growth warning/hard thresholds


These local values belong in the operator/security configuration and test matrix.


37. Quarantine Retention
Security-conflict evidence such as forks SHOULD remain long enough for diagnosis and MUST NOT be discarded merely to make current state look clean.
Retention schedule is operator policy but must have a disk budget.


38. Deterministic Sorting for Exports
Snapshot/chunk exports MUST sort records using an explicit binary/text key, such as canonical Onion hostname followed by Service Identity key and revision.
Locale-aware sorting is forbidden.


39. State-Machine Testing
For every state machine:
- exhaustive allowed-transition table;
- forbidden-transition tests;
- property-based random event sequences;
- persistence/restart tests;
- idempotency tests;
- conflict-event tests.


40. Protocol Limit Registry Artifact
The coding agent MUST create:
protocol/limits.json
and generate or verify JSON Schemas/tests against the same constants where practical.
Do not maintain multiple manually divergent copies.


41. Limit Change Process
Any change that increases network/resource exposure:
- Threat Model delta;
- benchmark;
- abuse analysis;
- Protocol Change Proposal if wire acceptance changes;
- test-vector update;
- human Security Review.


42. Final Acceptance
Protocol v1 limit/encoding layer is frozen only when PHP and TypeScript implementations pass the same:
- address vectors;
- key/signature encoding vectors;
- hash vectors;
- JSON boundary vectors;
- integer vectors;
- Unicode vectors;
- state-machine properties;
- exact error classification fixtures.
