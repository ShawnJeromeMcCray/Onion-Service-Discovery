# 37 - Independent Minimal Validator SDK and Multi-Language Adoption Strategy

Source: https://docs.google.com/document/d/1OilZe0tPQL3vjsIWgW-5ZNYhXJkmLeUAk6zWsf88eeM/edit?usp=drivesdk

Source modified: 2026-10-11T07:14:28.323Z

---

ONION SERVICE DISCOVERY
INDEPENDENT MINIMAL VALIDATOR, SDK AND MULTI-LANGUAGE ADOPTION STRATEGY


1. Purpose
Onion Service Discovery must remain a protocol, not a PHP/Web Local Search product monopoly. Third-party implementations should be able to validate records and interoperate without importing the reference applications.


2. First Independent Validator
Create a deliberately small third-language Protocol Core implementation after Protocol v1 core semantics are frozen enough for vectors.


Preferred languages:
Rust, Go or Python.
Human/agent selects via ADR based on:
independence from PHP/TypeScript;
easy deterministic byte handling;
crypto library maturity;
reviewability;
distribution simplicity.


3. Scope of Minimal Validator
MUST implement:
OnionAddress parsing/checksum;
JSON Schema validation or equivalent exact validation;
JCS canonicalization;
key/signature parsing;
domain-separated Ed25519 verification;
Manifest hash;
revision chain;
fork detection;
key rotation;
Onion migration;
Node Descriptor verification;
Direct Observation verification.


MUST NOT need:
database
web UI
Tor network
Node sync
search engine.


4. Independence Rule
Validator cannot call:
PHP reference code
TypeScript protocol code
their compiled binaries
remote validation service.


Shared inputs:
normative schemas/test vectors only.


5. CLI
Example:
onion-service-discovery-validator validate manifest.json
... hash manifest.json
... verify-chain directory/
... verify-rotation rotation.json
... verify-migration migration.json
... run-vectors path/


Output:
human + --json.


6. Expected Value
If PHP, TypeScript and independent validator produce identical canonical bytes/hashes/signature results, protocol ambiguity risk decreases materially.


7. Protocol Core Libraries
After freeze, extract/release language-specific protocol-core libraries:
PHP package
TypeScript package
possibly Rust/Go/Python package.


Libraries should expose protocol primitives, not app-specific global state.


8. Library Responsibilities
types/value objects
schemas
canonicalization
crypto verification
record validation
state transition pure logic
error codes.


Do NOT include:
application logging policy
database
Web-serving-origin/CSP/query-privacy concerns
webserver
operator configuration
ranking.


9. API Stability
Protocol-core package public API versioned separately from Protocol wire version.
Breaking library API can occur without wire break, documented via SemVer.


10. Generated Types
Where schemas generate types:
generation tool pinned;
types cannot replace runtime validation;
generation outputs tested against golden vectors.


11. Package Naming
Public package names use full project name or clear slug:
onion-service-discovery
No public acronym branding.


12. Dependency Discipline
SDKs remain minimal.
Crypto uses mature language ecosystem implementation.
Canonicalization implementation must pass exact vectors.


13. Error Model
Expose stable Protocol error enum/code plus library-specific diagnostic context.
Do not force consumers to parse human strings.


14. Pure Validation
Core validate functions:
no network;
no filesystem;
no database;
deterministic.
Makes embedding/audit easier.


15. State Reducer
Provide pure state reducer:
(previous evidence state, validated event) -> new derived state/conflict
with deterministic tests.
Useful across implementations.


16. Network Adapters
Separate optional package/module if later provided.
Protocol Core itself never hides outbound calls.


17. Compatibility Policy
Library release matrix:
library version
Protocol versions
minimum runtime
schema version
conformance suite version.


18. Documentation
For each SDK:
quick start;
security model;
what validation proves;
what it does not prove;
test vectors;
examples using synthetic keys only.


19. Third-Party Implementation Guide
Document:
minimum implementation steps;
common pitfalls;
canonicalization traps;
unknown-field behavior;
integer limits;
time semantics;
fork behavior;
Tor transport requirements for Nodes.


20. Conformance First
No implementation may claim Protocol v1 support solely because it uses official SDK.
It must pass relevant Conformance profile.


21. Alternate Node Implementations
Future Go/Rust Node encouraged only after stable Protocol.
Reference PHP remains reference, not authority.


22. Alternate Clients
Possible:
command-line client
desktop app
future native Tor Browser integration
research tools
index analysis tools.


They reuse Protocol spec/vectors.


23. Embedded Operator Integration
Provide integration guide for existing Onion Service stacks:
generate/serve Manifest using library;
optional Node endpoints separately.
Do not require full Node for publish-only operator.


24. Manifest Generator Library
An operator integration MAY use protocol-core signer plus local key management.
Key-management examples emphasize isolation and file permissions.


25. Browser Package
Client protocol library built for offline validation/search ingestion.
No remote code.


26. Language Interop Matrix
Before stable:
PHP
TypeScript
third-language validator.
After adoption:
track community implementations with:
version
profile
last conformance report.
Do not endorse security without audit.


27. Community Conformance Reports
Accept reproducible report submissions.
Clearly distinguish:
self-reported
CI-verified
project-tested.
No “certification” ambiguity.


28. Reference Code Quality
SDK public API includes docs/tests.
Avoid exposing raw arrays/maps where typed record classes reduce misuse.


29. Misuse Resistance
Signer API should make it difficult to:
sign wrong record type/domain;
include signature field in payload;
use Node key as Service key accidentally.
Use distinct key/types/functions.


30. Secret Handling
SDK signing requires explicit private-key object.
Verifier libraries do not need private keys.
No hidden key persistence.


31. Version Negotiation
Parser returns explicit UnsupportedProtocolVersion rather than best-effort parse.


32. Extension Registry
SDK exposes registry data but does not silently accept unknown permanent identifiers outside Protocol policy.


33. Package Integrity
Release packages included in SBOM/provenance.
Publish checksums/signing according to release process.


34. Deprecation
SDK deprecations documented; no silent semantic behavior shift for same Protocol.


35. Examples
All example Onion addresses/keys are synthetic test fixtures.
No real target probing in tutorials.


36. Educational Tool
Validator can provide:
--explain
showing:
binding status
signature
revision
rotation/migration.
Useful for auditors/operators.


37. Future FFI
If one audited core becomes attractive for multiple languages, do not introduce native FFI automatically; it can add distribution attack surface. Evaluate through ADR.


38. Adoption Principle
Specification remains portable enough that independent reimplementation is feasible. If implementers cannot pass without reading PHP internals, protocol documentation is considered defective.


39. Acceptance
Multi-language readiness PASS requires:
independent third-language core;
all core vectors pass;
public implementation guide;
SDK boundaries documented;
package compatibility matrix;
no shared hidden logic between independent validator and reference implementations.
