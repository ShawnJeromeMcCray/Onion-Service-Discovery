# 30 - Internet Standards IANA Registration and RFC Publication Plan

Source: https://docs.google.com/document/d/1BtOUvrmldMUcytfqO29GIx6AnCkKaw9ooS9qWgFeNm0/edit?usp=drivesdk

Source modified: 2026-10-11T07:14:35.713Z

---

ONION SERVICE DISCOVERY
INTERNET STANDARDS, IANA REGISTRATION AND RFC PUBLICATION PLAN


Status
Strategic standards document. No registration or standards-body endorsement is claimed until actually completed.


1. Purpose
Onion Service Discovery should be implementable beyond one repository and one maintainer. Where the protocol intentionally uses established Internet mechanisms, its public specification should align with those mechanisms and prepare legitimate registration requests instead of occupying ad-hoc namespaces forever.


2. Well-Known URI
Current manifest path:
/.well-known/onion-service-discovery.json


RFC 8615 defines the /.well-known/ namespace and an IANA registry for well-known URI suffixes.


Project action:
Before Protocol v1 stable publication, evaluate registration of the suffix:
onion-service-discovery.json
or another final suffix chosen before freeze.


Do not submit registration until:
- exact semantics are stable;
- public specification exists;
- security/privacy considerations are complete;
- change control contact/URL is durable.


3. Registration Preparation
Draft IANA registration template with:
URI suffix
Change controller
Specification document
Related information


The exact current IANA/RFC 8615 registration requirements MUST be rechecked at submission time.


4. No Premature Claim
Until IANA actually registers the value:
documentation says:
“uses the proposed well-known path”
not:
“IANA-registered”.


5. RFC-Style Public Specification
Create a publication-quality document with:
Abstract
Status
Requirements Language
Terminology
Architecture
Protocol Objects
Discovery Manifest
Canonicalization
Cryptographic Processing
Revision Semantics
Key Rotation
Onion Migration
Node Protocol
Synchronization
Error Handling
Versioning
Security Considerations
Privacy Considerations
Operational Considerations
IANA Considerations
Implementation Status
References.


6. Requirements Language
Use RFC-style uppercase normative terms consistently.
Reference BCP 14 requirements-language rules in the publication if appropriate.
Do not mix casual “should” with normative SHOULD without editorial discipline.


7. JSON Schema Baseline
Protocol v1 schemas MUST explicitly identify JSON Schema Draft 2020-12 unless a later standards decision deliberately changes it.


Each schema:
- $schema
- stable $id
- title/description
- $defs where reusable
- additionalProperties/unevaluatedProperties policy explicit
- exact min/max constraints
- examples kept non-normative unless designated vectors.


8. Schema URI Strategy
Choose a stable URI namespace for schemas, for example under a durable project domain/repository publication location.
Do not make protocol validity depend on validators fetching the URI at runtime.
Schemas must be distributable offline with releases.


9. Schema Bundling
Provide:
individual schemas;
bundled/compound schema artifact where useful;
schema manifest with hashes and Protocol version.


10. Schema Validator Independence
Before Protocol Freeze, run schemas through at least two independent Draft 2020-12 validators where practical.
Any divergence becomes blocking interoperability investigation.


11. API Problem Details
RFC 9457 defines Problem Details for HTTP APIs and obsoletes RFC 7807.


Project recommendation:
Use application/problem+json for public HTTP API error bodies where it improves interoperability.


Map project errors to:
type
title
status
detail
instance where safe
plus a namespaced stable Onion Service Discovery error code extension.


12. Error Type URIs
If RFC 9457 adopted:
problem type URIs must be stable documentation identifiers.
Example conceptual namespace:
.../problems/signature-invalid
.../problems/protocol-unsupported


Do not require remote dereference for processing, but documentation SHOULD resolve when available.


13. Privacy of Problem Details
Problem response MUST NOT leak:
stack traces
filesystem paths
SQL
secret configuration
private peer internals
raw attacker-controlled blobs.


14. Media Types
Protocol v1 can initially use application/json.
Before inventing a custom media type, evaluate whether registration materially improves interoperability.
If later needed:
prepare proper media-type registration rather than using vendor strings indefinitely.


15. Link Relations
If future discovery/metadata is exposed through HTTP Link headers, assess whether a registered link relation is warranted.
Do not create generic relation names likely to collide.
This is future work, not required for v1.


16. Protocol Registries
Maintain internal registries for extension-prone identifiers:
Record Types
Capability Tokens
Error Codes
Observation Result Codes
Moderation Categories if standardized
Extension Namespace Prefixes
Conformance Profiles


17. Registry Statuses
Each registry entry:
identifier
description
introduced protocol version
status:
PERMANENT
PROVISIONAL
EXPERIMENTAL
DEPRECATED
RESERVED
security notes
reference.


18. Registration Policy
For Protocol v1 project registries:
new permanent identifier requires:
specification text
tests
collision check
security/privacy review
accepted change proposal.


Experimental identifiers use explicit experimental namespace and MUST NOT become silently permanent.


19. Reserved Values
Reserve ranges/prefixes for:
future protocol use
private experimentation
test fixtures
to prevent collisions.


20. Capability Tokens
Capabilities describe optional supported behavior.
A token cannot disable mandatory security checks.
Registry documentation states dependencies and version.


21. Extension Mechanism
Do not allow arbitrary unsigned semantic extension fields.
Future extensibility uses:
- new Protocol version; or
- explicitly specified extension container and namespace policy.


Extensions MUST define signing/canonicalization impact.


22. URI Persistence
Public specification, problem type and schema URIs must be designed for long-term persistence.
Avoid personal temporary URLs as normative identifiers.


23. Standards Reference Classes
Normative:
Tor specifications governing Onion-address/SOCKS behavior;
RFC 8785 if adopted for canonicalization;
JSON Schema draft for schemas;
Protocol’s own public specification.


Advisory:
OWASP guidance;
WCAG implementation techniques;
SLSA/CycloneDX release practices.


Maintain exact distinction.


24. IANA Considerations Section
Even before any registration request, RFC-style Protocol document must contain explicit IANA Considerations:
- requested Well-Known URI registration if applicable;
- future media-type request if applicable;
- otherwise “no additional IANA action” for items not requested.


25. Security Considerations Quality
Not boilerplate.
Must discuss:
Sybil attacks
malicious peers
stale/replay data
forks
key compromise
migration hijack
SSRF/resource exhaustion
malicious metadata
supply chain
Web Local Search serving-origin, JavaScript and browser-storage constraints.


26. Privacy Considerations Quality
Must discuss:
local search
generic sync traffic
Node observations
seed/source bias
logging
withdrawal persistence
fingerprinting caveat
timing limitations.


27. Implementation Status
Public draft can document reference implementations and conformance suite without making them normative.


28. Interoperability Evidence
Before public standards submission:
at least three independently implemented protocol-core validators/parsers SHOULD demonstrate vectors, ideally in distinct languages.


29. Public Draft Lifecycle
INTERNAL DRAFT
PUBLIC DRAFT 00
PUBLIC REVIEW
DRAFT 01...
PROTOCOL V1 CANDIDATE
PROTOCOL V1 STABLE


Draft numbers do not change Protocol version until semantic freeze decision.


30. Change Log
Every public draft records:
semantic changes
wire changes
editorial changes
security changes
open questions.


31. Issue Mapping
Public specification issues use stable IDs.
Unresolved normative issue list included for drafts.


32. Examples
Clearly label:
NORMATIVE TEST VECTOR
or
NON-NORMATIVE EXAMPLE.
Avoid accidental spec-by-example ambiguity.


33. ABNF
Use ABNF where it materially clarifies textual identifiers such as:
category tokens
capability tokens
hash prefix
key/signature encoding wrappers
but do not force JSON structures into unnatural ABNF if JSON Schema is clearer.


34. Version Negotiation
RFC publication explains:
software version vs Protocol version;
unsupported version handling;
no silent downgrade reinterpretation.


35. Internationalization
Document Unicode/BCP 47 behavior and security caveats.
Where standards are referenced, pin their role without relying on online lookup for ordinary operation.


36. Accessibility
Public human-facing specification/documentation SHOULD meet WCAG-oriented publishing accessibility practices where practical.


37. Standards Contact
Before IANA submission, project needs a durable change-control contact that is not tied only to one disposable personal address.
Human governance decision.


38. Domain/Publication Independence
If no durable project domain exists initially, repository-hosted stable publication can be used, but migration plan should preserve identifiers/redirects.
Do not casually change normative schema/problem URIs after release.


39. No Standards Theater
Do not say:
“IETF standard”
“RFC-compliant” generically
“IANA approved”
unless exact status supports the statement.


40. Deliverables
docs/standards/
  standards-register.md
  iana-well-known-registration-draft.md
  problem-details-mapping.md
  protocol-registries.md
  schema-publication-policy.md
  rfc-publication-checklist.md


41. External Facts Verified During Planning
RFC 8615 defines well-known URIs and their registry.
RFC 9457 defines Problem Details for HTTP APIs and obsoletes RFC 7807.
JSON Schema’s published current specification baseline is Draft 2020-12 at the time of this planning.
These facts MUST be rechecked before standards submission/release.


42. Acceptance
Standards-readiness PASS requires:
- final well-known suffix decision;
- registration draft prepared;
- RFC-style Protocol publication generated;
- JSON Schema dialect/IDs frozen;
- API error standard decision made;
- project registries created;
- stable URI/change-controller plan;
- human standards/governance review.
