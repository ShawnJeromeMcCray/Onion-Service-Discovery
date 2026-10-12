# 39 - Public Protocol Handbook RFC-Style Publication Blueprint

Source: https://docs.google.com/document/d/1ko-SYPTrgJCA-HfyHzYl_ibNK5HSQwZPa1aInXD_MKI/edit?usp=drivesdk

Source modified: 2026-10-11T07:14:26.636Z

---

ONION SERVICE DISCOVERY
PUBLIC PROTOCOL HANDBOOK — RFC-STYLE PUBLICATION BLUEPRINT


Purpose
Transform the internal specification set into a coherent public protocol document that a third-party implementer can read independently.


Required Chapters


1. Abstract
Concise statement of decentralized, opt-in Onion Service discovery with local search.


2. Status of This Document
Draft maturity.
No IETF/Tor Project endorsement claim unless factual.


3. Requirements Language
Define MUST/MUST NOT/SHOULD/MAY usage.


4. Terminology
Import normative glossary.


5. Design Goals
Opt-in
no central runtime authority
local search
Tor-key separation
eventual consistency
bounded resource use.


6. Non-Goals
No content safety guarantee
no human-identity guarantee
no global naming authority
no global Sybil solution
no mandatory moderation authority.


7. Architecture
Service Publisher
Discovery Node
Client
Peer synchronization
Seeds/Bootstrap.


8. Onion Address Processing
Canonical v3 validation
checksum/version
normalization.


9. Service Manifest
Path
schema
field semantics
limits
signing.


10. Canonicalization and Cryptography
JCS
domain separation
Ed25519
SHA-256
key encoding
signature encoding.


11. Identity Continuity
Revision chain
stale/replay
fork
key rotation
Onion migration.


12. Node Identity and Observations
Node Descriptor
Direct Observation
Peer Assertion.


13. Discovery and Candidate Handling
admission
queues
direct verification
limits
backoff.


14. Node API
/node
/state
/peers
/services
/changes
/snapshots
/chunks.


15. Synchronization
pull
cursors
snapshots
chunks
conflict application
idempotency.


16. Client Processing
local index
merge
pins
local search
warnings.


17. Moderation Separation
identity vs moderation.


18. Versioning
Protocol vs software versions
capabilities
extensions
unknown fields.


19. Error Handling
Stable symbolic codes.
RFC 9457 Problem Details decision if adopted.


20. IANA Considerations
Well-Known URI registration plan.
Any future media/link registration.


21. Security Considerations
Full threat summary:
malicious Service
malicious Node
Sybil
replay/fork
key compromise
migration hijack
SSRF
DoS
browser risks
supply chain.


22. Privacy Considerations
local query
generic sync
logging
timing
fingerprinting
withdrawal persistence.


23. Operational Considerations
backups
key loss
liveness
bootstrap failure
retention.


24. Accessibility and Human Factors
technical identity labels
no false safety claims.


25. Conformance
profiles
test vectors
independent validator.


26. Formal Verification
model scope/invariants/assumptions.


27. Implementation Status
Reference PHP Node
Web Local Search Reference Client
independent core validator.


28. References
Normative and informative separately.


29. Appendices
A. Complete schemas
B. Test vectors
C. State transition tables
D. Error registry
E. Capability registry
F. Limit registry
G. Example records
H. Migration/rotation walkthroughs
I. Operator contract.


Editorial Rules
- One normative term = one glossary meaning.
- Every MUST has implementation/test traceability.
- Examples do not override schemas.
- No behavior exists only in reference code.
- Every wire field is described once authoritatively.
- Cross references use stable section IDs.
- Security/privacy sections are substantive, not boilerplate.
- Known limitations are linked prominently.


Publication Formats
Generate from one version-controlled source:
HTML
Markdown
PDF optional
plain text optional.


The generated public specification MUST be accessible offline with releases.


Version Header
Document:
Protocol version
draft number
date
source revision
schema bundle hash
test-vector set hash.


Review Checklist
Before Public Draft:
[ ] no unresolved undefined normative terms
[ ] all record schemas linked
[ ] all error/capability codes registered
[ ] all byte constructions exact
[ ] all limits included
[ ] IANA section accurate
[ ] security/privacy review
[ ] conformance vectors pass
[ ] external facts refreshed.


Public Review Process
Publish issues by section.
Tag:
EDITORIAL
INTEROP
SECURITY
PRIVACY
BREAKING
IANA
ACCESSIBILITY.


Stable Protocol Rule
Public Protocol v1 is not declared Stable until:
- normative ambiguities closed;
- conformance passes three implementations for core;
- formal model has no unresolved counterexample;
- Red Team review complete;
- Human Protocol/Crypto review PASS.


Non-Claim
A polished RFC-style document does not itself make Onion Service Discovery an IETF RFC. It is a publication style and preparation path until any formal standards process occurs.
