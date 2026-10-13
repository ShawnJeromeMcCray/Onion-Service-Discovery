# 13 - Cryptographic Construction Key Lifecycle and Domain Separation

Source: https://docs.google.com/document/d/1YlAsPNdAwUmY6EjJDSRSEuo0pmuYSol84gwxezAmqlI/edit?usp=drivesdk

Source modified: 2026-10-12T19:28:05.077Z

---

ONION SERVICE DISCOVERY
CRYPTOGRAPHIC CONSTRUCTION, KEY LIFECYCLE AND DOMAIN SEPARATION


Status
Normative security companion for Protocol v1. Cryptographic construction MUST be independently reviewed before Protocol v1 freeze.


1. Goals
Cryptography provides:
- integrity/authenticity of self-published Service records;
- application-level identity continuity independent of the private Tor Hidden-Service key;
- authenticated Node descriptors and observations;
- authenticated key rotation;
- authenticated Onion-address migration;
- content integrity for snapshots/chunks.


Cryptography does NOT establish operator honesty or content safety.


2. Key Separation
Three conceptually distinct identities:
A. Tor Onion identity — owned by Tor; private key inaccessible to Onion Service Discovery.
B. Service Identity key — application-level Ed25519 key owned by service operator.
C. Node Identity key — application-level Ed25519 key owned by Discovery-node operator.


B and C MUST be distinct keypairs even if same server/operator.


3. Algorithms
Protocol v1:
Signatures: Ed25519.
Content hashes: SHA-256.
Onion-address validation: Tor’s specified SHA3-256 checksum mechanism.
Canonical signed JSON: RFC 8785 JCS, subject to final cross-language vector validation.


No algorithm negotiation in Protocol v1 unless explicitly added before freeze.
Algorithm agility must not create downgrade ambiguity.


4. Key Encoding
Service/Node public key:
ed25519:<Base64url without padding>
decoded exactly 32 bytes.


Signature:
ed25519sig:<Base64url without padding>
decoded exactly 64 bytes.


Implementations reject:
- padding;
- non-URL-safe alphabet;
- wrong decoded length;
- whitespace;
- alternate ambiguous encodings.


5. Signed Envelope Design
Every signed record has:
protocol
protocol_version
record_type
signer identity/public key as defined
record-specific payload
signature


The signature field itself is excluded from signed bytes.
The exact exclusion/serialization is defined once and test-vectored.


Preferred model:
signed_payload = canonicalize(all normative fields except "signature")
signature = Ed25519.Sign(private_key, domain_prefix || 0x00 || signed_payload)


6. Domain Separation
Each record type MUST use a distinct ASCII domain prefix.


Proposed Protocol v1 prefixes:
Onion Service Discovery/service-manifest/v1
Onion Service Discovery/service-key-rotation/v1
Onion Service Discovery/onion-migration/v1
Onion Service Discovery/node-descriptor/v1
Onion Service Discovery/direct-observation/v1
Onion Service Discovery/moderation-signal/v1
Onion Service Discovery/snapshot-descriptor/v1


Final byte encoding:
UTF-8 domain string
single zero byte separator
canonical payload bytes


The exact strings are normative and MUST be included in test vectors.
A signature valid for one record class MUST NOT validate as another class.


7. Manifest Hash
Define one exact manifest hash object.
Recommended:
manifest_content_hash = SHA-256(domain_hash_prefix || 0x00 || canonical signable manifest payload)


Proposed hash prefix:
Onion Service Discovery/service-manifest-hash/v1


Do not hash an implementation’s pretty-printed full JSON including signature unless Protocol v1 explicitly chooses that representation.
Hash semantics must be identical across languages.


8. Revision Chain
Each revision after genesis includes previous_manifest_hash referring to the exact protocol-defined content hash of previous revision.
Verification:
- signature current;
- previous hash correct against stored predecessor;
- revision increments according to frozen rule.


Recommended rule:
revision increases by exactly 1 for each successor in a continuity chain.
If gaps are intentionally allowed, semantics become more complex; Protocol v1 SHOULD require exact +1 unless a strong reason is documented before freeze.


9. Genesis
Genesis revision:
revision = 1.
previous_manifest_hash representation must be frozen as either:
- JSON null; or
- field absent.
Choose exactly one before Protocol freeze.
Existing draft assumes null; retain unless cross-language/schema review finds a compelling reason to change.


10. Service Key Generation
Use libsodium/CSPRNG.
Never derive Service Identity key from:
- password;
- Onion private key;
- hostname;
- timestamp;
- predictable seed.


CLI:
- refuses overwrite by default;
- sets secure file permissions;
- emits public fingerprint separately;
- never prints private key unless explicit secure export workflow exists.


11. Node Key Generation
Same cryptographic requirements.
Node key compromise does not rotate Service key automatically.


12. Private Key Custody
Tor Onion private keys remain exclusively under Tor and are never read by Onion Service Discovery.


Service Identity private key:
- preferred production profile is offline signing or an isolated SignerProvider;
- the public web/PHP runtime MUST NOT require the Service private key in the preferred profile;
- protected online signing is an explicit lower-assurance/hardened profile;
- never stored in public webroot, ordinary logs, Protocol storage or source control.


Node Identity private key:
- normally required online for Node descriptors/observations/checkpoints;
- kept outside webroot under least privilege or isolated SignerProvider;
- never reused as Service or release key.


Backup/custody requirements are defined in the High-Assurance Key Custody standard.
Hardware/TPM/KMS/external-signer providers are implementation options behind SignerProvider and do not change Protocol bytes.






13. Key Fingerprints
For human comparison, derive:
fingerprint = SHA-256(raw public key)
display as grouped lowercase hex or a clearly specified Base32 form.


Full public key remains authoritative.
Fingerprint presentation MUST be deterministic and version-independent.
If a short fingerprint is displayed, UI must allow full fingerprint/key access.


14. Service Key Rotation
Rotation record binds:
old key
new key
service identity context/onion
effective revision
previous state hash
record version


Required authorization:
old key signature.


Recommended defense:
new key also countersigns same rotation payload, proving possession.


Acceptance:
- old key must match currently trusted continuity state;
- rotation revision must be legal successor;
- old signature valid;
- new countersignature valid if Protocol v1 mandates it;
- no conflicting rotation already accepted for same predecessor.


15. Key Rotation Atomicity
Local node/client update:
- validate entire rotation;
- validate first new-key manifest;
- commit key transition and corresponding manifest in one transaction where practical.
No state where key changes without evidence.


16. Rotation Failure
New manifest with unknown new key and no valid rotation:
IDENTITY_CHANGED or QUARANTINED according to local state.
Never “probably rotated”.


17. Emergency Service Key Loss
If old Service Identity private key is lost before a rotation record is created, protocol continuity cannot be cryptographically proven.
Do not invent recovery authority in v1.
New key becomes a different application identity unless a future separately specified recovery mechanism exists.


This is a deliberate security property.
Operator docs must emphasize backup.


18. Onion Migration
Migration record binds:
Service Identity public key
from_onion
to_onion
effective revision/state
previous state hash
optional reason code metadata
Service Identity signature


The Service Identity key is the application continuity anchor across Onion-address change.


19. Migration Confirmation Levels
Level A:
Service Identity key signs from -> to.
Meaning: application identity claims move.


Level B:
old Onion endpoint directly serves/acknowledges migration record.
Meaning: old endpoint also confirms.


Level C:
new Onion endpoint directly serves manifest/acknowledges continuity.
Meaning: destination is active under same Service Identity chain.


UI must reflect evidence, not collapse levels.


20. Tor Key Independence
Onion Service Discovery MUST NOT ask operator to sign with Tor Hidden-Service private key.
Reasons:
- violates key-separation boundary;
- increases compromise surface;
- unnecessary because direct connection to the Onion address plus self-hosted manifest provides the Tor-level binding observation.


21. Node Descriptor Signing
Node descriptor signature proves descriptor continuity for that Node Identity key.
It does not elevate node to authority.
Descriptor changes use monotonically increasing descriptor_revision plus previous descriptor hash if adopted; freeze exact model in Protocol v1 vectors.


22. Observation Signing
DirectObservation signed by Node Identity includes:
service Onion
Service Identity key
manifest revision
manifest hash
observation result
observed_at
bounded protocol fields


Observation MUST NOT sign arbitrary raw HTTP body.
It references validated manifest hash.


23. Time Semantics
Timestamps are signed to prevent alteration of an observation’s claimed time.
However:
- node controls its own clock;
- malicious node can lie about time;
- therefore timestamp is evidence from that node, not global truth.


24. Snapshot Signing
If a node signs snapshot descriptors:
descriptor includes:
protocol/version
snapshot id/root
record count
created_at
chunk list/root metadata
Node Identity
signature


Client verifies content hashes independent of transport.


25. Content-Addressed Chunks
Chunk identifier:
sha256:<hash of exact chunk bytes or canonical chunk content as frozen>


If raw byte hashing:
compression must be outside identity or explicitly frozen, otherwise different encoding changes hash.
Preferred:
canonical uncompressed protocol chunk bytes hashed; HTTP compression only transport layer.


26. Moderation Signing
Moderation source, if present, uses a separate signed record domain.
Do not reuse Node Observation signature domain.
Moderation authority is selected locally by client, not protocol-global.


27. Constant-Time and Libraries
Use library verification APIs.
Never implement scalar arithmetic or signature comparison manually.
Sensitive byte comparisons that matter outside library SHOULD use constant-time helpers when available.


28. Zeroization
PHP and JS runtimes do not guarantee perfect memory erasure.
Where libsodium provides memory utilities, use appropriately.
Do not make unrealistic “keys are guaranteed wiped from RAM” claims.
Minimize private-key lifetime and copies.


29. Key Parsing
Key parser must return a strongly typed value only if:
prefix correct;
Base64url canonical;
decoded length exact.
Do not auto-correct malformed network keys.


30. Signature Parsing
Same strict canonical parsing.
Reject non-canonical equivalent Base64 encodings.


31. Canonicalization Hazards
Test:
property ordering
Unicode characters
escapes
nested objects
arrays
numbers
null/absent
slash/control escapes
characters near UTF-16 sort boundaries


PHP and TypeScript vectors MUST compare exact UTF-8 bytes.


32. JSON Number Restriction
Signed schemas avoid floating point.
Use only bounded integers in exact JS range.
No timestamps as Unix floating point.
No scientific-notation semantic fields.


33. Algorithm Confusion
Fields do not accept arbitrary algorithm strings in v1.
Prefixes are fixed.
A record cannot claim “ed25519” while verifier falls back to another scheme.


34. Downgrade Resistance
Protocol v1 verifier does not reinterpret unknown protocol versions as v1.
Unknown crypto format => unsupported/reject.
Do not ignore signature merely because a peer says old compatibility mode.


35. Key Rotation Chain
Client/node maintains:
K1 -> K2 -> K3
with rotation evidence for every link.
A peer cannot jump directly to K3 unless the protocol explicitly provides verifiable chain material; reference implementation should require complete or reconstructable chain.


36. Conflicting Rotations
If same old state authorizes two different successor keys at the same effective point:
FORK/KEY_CONFLICT.
Do not choose newest timestamp.


37. Conflicting Migrations
Same continuity state -> two competing Onion destinations:
MIGRATION_CONFLICT / FORK.
Preserve both evidences.
No arbitrary majority.


38. Backup Policy
Service Identity private key:
offline encrypted backup strongly recommended.
At least two tested recovery copies if operator threat model permits.
Backup compromise risk documented.


Node key:
replaceable with loss of Node continuity, not Service identity.


39. Key Compromise Response
Service key suspected compromised:
- stop automatic manifest publishing;
- preserve evidence;
- follow incident runbook;
- if old trusted key still controlled, create reviewed rotation;
- if attacker may also sign, clients may observe fork; do not hide conflict.


Node key compromised:
- cease signing;
- rotate Node key;
- publish descriptor transition if protocol supports;
- treat past observations according to incident timeline.


40. Crypto Test Vectors
Required:
fixed seed/private/public pair for tests only
canonical manifest payload
domain-prefixed message bytes in hex
signature
hash
rotation payload/signatures
migration payload/signature
node descriptor signature
observation signature
invalid bit-flipped signatures
wrong-domain verification cases


41. Cross-Implementation Gate
PHP and TypeScript:
- produce same canonical bytes;
- same manifest hash;
- verify same signatures;
- reject same malformed key encodings;
- reject cross-domain signature reuse.


42. Independent Review Questions
Reviewer must answer:
- Is domain separation unambiguous?
- Is exact signed byte sequence fully specified?
- Can any field be altered outside signature?
- Can unknown fields be ignored inconsistently?
- Are revision/hash semantics unambiguous?
- Can key rotation be replayed or forked silently?
- Can migration be hijacked?
- Is any private Tor key exposed?
- Are all crypto claims








43. Protocol Freeze Gate
No production network implementation may freeze Protocol v1 until:
- domain prefixes accepted;
- exact manifest hash algorithm accepted;
- genesis representation accepted;
- revision successor rule accepted;
- key rotation countersignature policy accepted;
- migration evidence levels accepted;
- independent human crypto review marked PASS.


44. No “Perfect Crypto” Claim
Documentation states exact guarantees and limitations.
Cryptography authenticates keys/records; it does not authenticate human brand ownership or service honesty


45. Logical Service Identifier Domain
Protocol state MUST distinguish:
- logical_service_id: stable genesis identity anchor;
- current Service Identity public key;
- key epoch.


The proposed Protocol v1 logical_service_id representation reuses the canonical encoded genesis Service Identity public key, subject to D-051 Human Protocol/Crypto approval.
No hash of the current rotated key may be substituted as the stable identity anchor.


46. Signed Checkpoint Construction
Signed Node checkpoints are a distinct signed record type and MUST use a distinct domain-separation prefix from Node Descriptor and Direct Observation.


Checkpoint payload includes, once frozen under D-052:
- Node Identity;
- Protocol/index format version;
- checkpoint sequence;
- previous_checkpoint_hash;
- active index root;
- record count;
- generated timestamp.


Successor checkpoints form a signed hash chain.
Exact genesis representation, checkpoint hash bytes and domain prefix require cross-language vectors and Human Protocol/Crypto review before Protocol v1 Freeze.
