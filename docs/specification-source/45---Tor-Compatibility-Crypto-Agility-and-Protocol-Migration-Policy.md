# 45 - Tor Compatibility Crypto Agility and Protocol Migration Policy

Source: https://docs.google.com/document/d/17rs4a64M6yk-wcGqooM9k71ACj77NdBJQnsWDWtvGTQ/edit?usp=drivesdk

Source modified: 2026-10-12T12:30:46.103Z

---

ONION SERVICE DISCOVERY
TOR COMPATIBILITY, CRYPTO AGILITY AND PROTOCOL MIGRATION POLICY


1. Purpose
Keep Onion Service Discovery maintainable as Tor, cryptographic guidance, browsers and runtimes evolve without weakening Protocol v1 through ambiguous negotiation.


2. Current Baseline
Protocol v1 supports Tor v3 Onion addresses only.
Unknown Onion-address formats are rejected, not guessed.


3. No Speculative Future Format
Do not implement a hypothetical Tor v4 Onion-address parser before Tor defines one.


4. Compatibility Watch
At least before every stable release, review current official:
- Tor Onion Service specifications;
- Tor proposals affecting Onion Service addressing/handshakes;
- Tor Browser desktop/Android behavior;
- PHP/libsodium support;
- JSON canonicalization dependencies;
- security advisories for required cryptographic libraries.


5. Trigger Classes
A. Tor software update with no relevant semantic change:
no Protocol version change.


B. Tor changes connection behavior but v3 address semantics remain:
implementation/adaptation release.


C. New Onion address version or incompatible identity semantics:
new Onion Service Discovery Protocol version or explicitly versioned extension after full review.


D. Cryptographic primitive deprecation:
new Protocol crypto suite/version.


6. Protocol v1 Crypto Suite
Fixed:
Ed25519 signatures;
SHA-256 application content hashes;
RFC 8785 JCS;
Tor-defined v3 checksum validation.


No runtime algorithm negotiation.


7. Why No v1 Algorithm Negotiation
Negotiation adds downgrade/algorithm-confusion risk.
Protocol v1 remains deterministic and simple.


8. Crypto Suite Migration
If a primitive becomes unsuitable:
create Protocol v2+ with a new frozen suite.


Do not reinterpret a v1 field to mean a different algorithm.


9. Continuity Across Crypto Suites
Preferred future transition:
a dedicated identity-transition record containing:
old Protocol/key identity;
new Protocol/key identity;
old-state hash;
new public key/suite;
effective transition point;
old-suite signature;
new-suite countersignature where possible.


Exact format requires new Protocol proposal and vectors.


10. Catastrophic Break
If the old algorithm is cryptographically broken, old signatures may no longer provide reliable continuity.
Documentation MUST NOT pretend a dual-sign transition authenticated by a broken primitive restores certainty.
Fallback may require:
external proofs;
operator/user pins;
multiple historical anchors;
manual migration policy.


11. Future Onion Address Version
A future Protocol version defines exact parser/checksum/key-binding rules from the official Tor spec.
No “length looks right” fallback.


12. Address-Version Transition
A Service migrating from Tor v3 to a future address version can use Service Identity continuity if Protocol rules allow:
signed migration record;
old/new endpoint evidence;
new address parser;
new Protocol version support.


13. Mixed Network
During transition:
Nodes advertise supported Protocol versions/capabilities.
Clients preserve v1 records and v2 records separately.
Unsupported future records are not misparsed as v1.


14. Downgrade
If a client knows a Service has validly moved to a newer Protocol/crypto suite, stale v1 data cannot silently become current.


15. Schema Preservation
Stable Protocol schemas/vectors remain archived forever for historical verification.


16. Software Support Matrix
Every release states:
Tor versions tested;
Tor Browser versions tested;
PHP versions;
libsodium/PHP sodium availability;
supported Protocol versions;
storage schema;
Web Client version.


17. Deprecation
Dropping an old Tor/software/Protocol version requires:
notice;
security rationale;
 migration path;
last supported release;
archived verifier where practical.


18. Post-Quantum
Do not add post-quantum algorithms merely for fashion.
Adopt only when:
- mature standards/libraries;
- practical interoperability;
- threat model benefit;
- migration design;
- human cryptographic review.


19. Hash Migration
Changing application content hash changes content IDs/revision chains and is therefore a Protocol-level change.


20. Signature Migration
Changing signing primitive is a Protocol-level identity transition.


21. Canonicalization Migration
Changing canonicalization affects signed bytes and is a Protocol-level change.


22. Tor Checksum Independence
Application-level hash choices never replace Tor’s officially specified address checksum algorithm for the supported address version.


23. Compatibility CI
Maintain fixtures for:
old stable software;
current supported Tor behavior;
current Browser behavior;
future-proposal prototypes only in experimental branches.


24. External Change Alert
Maintainers SHOULD subscribe/watch official Tor security/specification channels and runtime/library security advisories.


25. Acceptance
PASS requires:
- v1 strict v3 support;
- fail-closed unknown address versions;
- no algorithm negotiation in v1;
- explicit v2 migration process;
- continuity/downgrade policy;
- release compatibility watch.
