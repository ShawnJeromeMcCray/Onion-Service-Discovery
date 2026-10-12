# 25 - Normative Glossary Terminology and Security Semantics

Source: https://docs.google.com/document/d/19HdzJKytmIt44-HrWAsnmMLtblsuBJjWpiRk08qxxvU/edit?usp=drivesdk

Source modified: 2026-10-11T04:51:25.705Z

---

ONION SERVICE DISCOVERY
NORMATIVE GLOSSARY, TERMINOLOGY AND SECURITY SEMANTICS


Purpose
Terms in this document have one project-wide meaning. Public UI may use friendlier wording, but implementation/specification semantics must map back to these definitions.


Onion Service Discovery
The full public name of the project/protocol. Do not replace it with an acronym in public or normative documentation.


Onion Service
A Tor v3 Onion Service identified by a canonical v3 .onion hostname. “Service” in this project does not imply trustworthiness, ownership, legality or availability.


Onion Address
Canonical Tor v3 hostname validated according to Tor’s address encoding specification, including checksum and version, not merely a string suffix.


Tor Identity
The cryptographic identity represented by the Onion address and handled by Tor. Onion Service Discovery never requires access to its private key.


Service Identity
The application-level logical identity used by Onion Service Discovery, rooted in an Ed25519 Service Identity key and continuity records.


Service Identity Key
Application-level Ed25519 keypair used to sign Service Manifest revisions, Service-key rotations and Onion migrations. Separate from Tor identity key.


Logical Service Identity
The continuity object that can span multiple Service Identity key epochs when each transition is validly authorized, and can span Onion-address migrations.


Key Epoch
A period in one logical Service identity during which one Service Identity public key is valid.


Node
An Onion Service Discovery participant that exposes the Node read API and may mirror/synchronize/verify public discovery data.


Node Identity
Application-level Ed25519 identity of a Discovery Node.


Node Descriptor
Signed public record describing Node Onion address, protocol versions, capabilities and bounded software metadata.


Seed Node
A Node included/configured only to help bootstrap peer discovery. It is not a root authority or automatic trust authority.


Pinned Peer
A Node source explicitly selected/configured by the operator/user. Pinning affects source selection, not Service cryptographic truth.


Peer
Another Discovery Node known to a Node/client.


Candidate
A syntactically valid Onion address that may be worth verifying but is not yet directly verified.


Candidate Source
The bounded provenance explaining how a Candidate was learned.


Direct Verification
A local Node’s controlled fetch of the standardized Service Manifest path on exactly the target Onion Service through the approved Tor transport, followed by all schema, binding, signature and continuity checks.


Direct Observation
A signed statement by a Node describing the result/state it directly observed after its own verification process.


Imported Observation
A Direct Observation made by another Node and received through sync. It remains that Node’s observation and is never converted into the local Node’s Direct Observation.


Peer Assertion
A claim received from a Peer that has not been established as local Direct Observation. Its precise record type can include references or claims about services.


Service Manifest
The self-published signed Protocol v1 record under the standardized Well-Known path containing Service Identity, revision, discoverability and bounded metadata.


Manifest Revision
One signed state in the Service Manifest continuity chain.


Genesis Revision
Revision 1, first protocol state for a Service Identity continuity chain under the chosen genesis rules.


Manifest Hash
Protocol-defined SHA-256 content identifier over the exact domain-separated canonical signable Manifest representation.


Revision Chain
Sequence of Manifest revisions linked through previous-manifest hash under the frozen successor rule.


Current Revision
The highest/selected valid non-conflicting revision according to Protocol semantics. Not selected merely by wall-clock timestamp.


Stale Revision
An otherwise valid older revision that cannot replace a newer known valid current revision.


Replay
Presentation of older valid data in an attempt or circumstance that would incorrectly downgrade current state.


Fork
Two cryptographically valid but conflicting successor states that Protocol rules cannot safely collapse into one history, such as same logical identity and same revision with different Manifest hashes.


Conflict
General term for incompatible evidence. A Fork is a specific identity/history conflict.


Quarantine
A state/storage area for data/evidence that must not be treated as normal current valid data but should be preserved in bounded form for diagnosis/review.


Service Key Rotation
Cryptographically authorized transition from one Service Identity key epoch to the next.


Rotation Record
Signed Protocol record binding old and new Service Identity keys and transition context.


Onion Migration
Cryptographically authenticated change of current Onion address while retaining the same logical Service Identity continuity.


Migration Record
Signed Protocol record binding from-Onion, to-Onion and Service Identity continuity context.


Migration Confirmation
Additional direct evidence that old and/or new Onion endpoint acknowledges/serves the migration state. Distinct from the Service Identity signature itself.


Identity Continuity
Conclusion that a later state belongs to the same logical Service Identity according to valid revision/key-rotation/migration evidence.


Identity Changed
State indicating a record uses a different Service Identity without valid continuity evidence from the previously known identity.


Onion Binding Verified
Exact technical statement: a valid signed Manifest declaring Onion X was directly retrieved through the defined verification path from Onion X and passed the Protocol binding checks.


Manifest Signature Valid
The Service Manifest’s signature is valid for its declared Service Identity public key and exact domain-separated canonical payload.


Verified
Ambiguous word. Normative specifications should use a qualified form such as “signature verified”, “Onion binding verified” or “migration evidence verified”. Unqualified “verified” should be avoided in user-facing trust claims.


Trusted
Not a Protocol v1 technical identity state. Do not use as a generic badge. A user may locally trust/pin an identity, but this is personal/local policy.


Safe
Not guaranteed by Onion Service Discovery. Do not use as a technical result.


Legitimate
Not guaranteed by technical Service identity verification.


Official
May only be used if a separately defined external identity proof justifies it and UI explains that proof. A name or self-signature is insufficient.


Discoverable
Service has valid/current opt-in publication state according to Protocol and local freshness/export policy.


Publish
Operator function causing own Service to opt into discovery through self-published Protocol metadata.


Mirror
Node function that stores and serves Discovery index/protocol data.


Discover
Node function that admits/finds Candidates and schedules controlled Direct Verification.


Sync
Pull-oriented process for retrieving bounded state/deltas/snapshots from other Nodes.


Index
Structured set/view of currently searchable public Service records plus required evidence/state.


Local Index
Client-side stored index used for local search.


Snapshot
Deterministic bounded representation of an index state described by a signed Snapshot Descriptor.


Chunk
Content-addressed bounded part of a Snapshot/index transfer.


Delta / Change Feed
Bounded incremental synchronization mechanism from a previous peer cursor/state.


Cursor
Opaque peer-specific continuation position for incremental sync. It is not identity evidence.


State Root
Content-derived identifier summarizing a node/index synchronization state under the frozen algorithm.


Content Address
Hash-derived identifier for immutable protocol content.


Canonicalization
Deterministic conversion of the signable JSON data to exact bytes, based on the selected standard/rules.


Domain Separation
Use of distinct signed-message prefixes/contexts so a signature for one record type cannot be validly reused as another.


Evidence
Cryptographic or observed data supporting a technical state. Evidence must retain provenance.


Trust Signal
Potentially user-visible fact/indicator such as valid signature, identity continuity, known pin, direct observation age or external cross-link. Signals are not collapsed into a universal trust score.


Identity Pin
User-local binding recording a known Service Identity/fingerprint (and contextual Onion), used to warn on unexpected changes.


TOFU
Trust On First Use pattern as applied only to user-local identity pinning/re-recognition. It does not make the first service objectively trustworthy.


Favorite
User convenience marker. Not automatically equivalent to an Identity Pin unless UI explicitly combines these concepts.


Observer
A Node/source whose signed observations the user/client chooses to inspect or give local relevance. Observer count is not proof of independence.


Selected Observer
Observer explicitly configured/selected by local policy/user.


Sybil
Multiple identities/nodes controlled by one actor, potentially used to fake apparent consensus.


Moderation Source
Separately selected source of signed/provenance-bearing warnings/categories. Not a Service identity authority.


Moderation Signal
A source-specific warning/classification record. It does not alter cryptographic validity.


Clearnet Proof / Link
Optional independently verified linkage between a normal HTTPS domain and an Onion Service according to a specified method. It proves technical linkage under that method, not honesty/legal ownership in general.


Liveness
Recent reachability/observation dimension. Separate from Identity validity.


Recently Seen
Local policy state reflecting recent successful observation.


Temporarily Unreachable
Recent failed attempts after prior validity; not deletion or compromise proof.


Stale
Data/liveness has aged beyond a defined freshness threshold. Stale identity evidence can remain cryptographically valid.


Long Unseen
Liveness category after a longer period with no direct observation. Still not proof the identity ceased to exist.


Withdrawn
A valid Service state explicitly setting publication/discoverability off, as distinguished from simple outage.


Rejected
Record/Candidate fails non-transient admission/protocol validation according to defined reason.


Backoff
Temporary scheduling state delaying further work after retryable conditions.


Rate Limited
Work/request denied/delayed due bounded resource policy.


Work Budget
Finite amount of attacker-influenced work allowed per period/job/source.


Fail Closed
On ambiguity or failed required security validation, the system does not silently grant the successful/verified state.


Read-Only Degraded Mode
Operational mode serving previously verified local data when safe while disabling writes/sync due integrity/config/storage problems.


Protocol Version
Version governing wire semantics, independent from software release version.


Software Version
Version of one implementation/release.


Capability
Advertised optional feature compatibility; cannot disable mandatory Protocol validation.


Conformance
Demonstrated behavior matching normative Protocol specs/test vectors.


Interoperability
Different implementations can exchange/interpret records consistently.


Production Ready
Allowed claim only when all Definition-of-Done release gates marked PASS with evidence and required human approvals completed. It does not mean bug-free.


Audit Ready
Documentation/code/evidence are sufficiently organized for independent security review. It does not mean an audit occurred.


Audited
May be said only when a genuinely independent audit/review was actually performed with evidence and scope.


Privacy
Project-specific minimization/data-flow guarantees explicitly tested, not a blanket anonymity claim.


Anonymous
Do not use as unconditional property of Onion Service Discovery. Tor provides anonymity-related network properties; the project must state its narrower privacy guarantees.


Decentralized
No single required runtime registry/server/maintainer service controls operation. Bootstrap and software distribution can still have governance dependencies which must be documented.


Root Authority
An entity whose decision alone defines global identity truth. Protocol v1 intentionally has no Discovery Node root authority.


Bootstrap
Initial process by which a fresh client/node learns some peers.


Eventual Consistency
Nodes/clients may temporarily have different valid freshness/views and converge through sync, while cryptographic conflict semantics remain deterministic.


Current View
Derived searchable state selected from preserved evidence under Protocol/local policy.


Immutable Evidence
Stored record/content that is not rewritten in place; new evidence is added and current view is derived.


Retention
Policy for how long data/evidence/cache is kept locally.


Withdrawal
Operator opt-out of active publication. Does not imply guaranteed erasure from all decentralized historical copies.


Protocol Error
Defined semantic/validation failure with stable error class.


Transport Error
Failure to establish/complete the Tor/HTTP exchange. Does not by itself invalidate existing cryptographic identity history.


Security Exception
Time-bounded, human-approved deviation from a security gate with explicit residual risk and remediation.


Human Approval Gate
Decision that the AI coding agent cannot self-authorize.


External Audit
Review by a genuinely independent qualified party distinct from the authoring agent/person for the relevant scope.


Normative
Binding requirement.


Advisory
Guidance that does not alter wire/security semantics.


MUST / MUST NOT
Hard requirement.


SHOULD / SHOULD NOT
Strong recommendation; deviation requires rationale where security/interoperability relevant.


MAY
Optional behavior that must not break mandatory semantics.


Terminology Enforcement
The coding agent must:
- reuse these terms in class/type/API/docs naming where practical;
- flag conflicting definitions;
- avoid unqualified trust words;
- maintain a repository copy at docs/protocol/glossary.md;
- add new terms through reviewed documentation when they carry security semantics.


Acceptance
Public UX copy, Protocol specs and implementation state names must be traceable to this glossary without contradictory meanings.
