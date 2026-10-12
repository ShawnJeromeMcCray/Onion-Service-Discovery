# 11 - Current Standards Baseline and External References

Source: https://docs.google.com/document/d/1UEeaBpx6Wnnjujk5dzELF3rxYgDc0Ci2eLomxzc6_10/edit?usp=drivesdk

Source modified: 2026-10-11T07:12:50.162Z

---

ONION SERVICE DISCOVERY
CURRENT STANDARDS BASELINE AND EXTERNAL REFERENCES


Status
Normative external-reference baseline, verified 11 October 2026. This document does not freeze third-party standards forever. The coding agent MUST re-check all time-sensitive dependencies and platform behavior at implementation start, at protocol freeze, and before every production release.


1. Purpose
Onion Service Discovery intentionally relies on established standards and official platform behavior instead of inventing equivalents. This document records which external specifications are authoritative, which facts are stable protocol assumptions, and which facts are time-sensitive and MUST be refreshed.


2. Refresh Policy
At these gates:
- Milestone 0;
- Protocol v1 Freeze;
- first Release Candidate;
- every Major release;
the agent MUST review every entry marked REFRESH and record:
Checked date
Source version/date
Change found yes/no
Impact
ADR/Protocol Change Proposal if required


3. Tor Onion Address Encoding — AUTHORITATIVE
Official source:
https://spec.torproject.org/rend-spec/encoding-onion-addresses.html


Stable facts:
A v3 Onion address encodes:
PUBKEY | CHECKSUM | VERSION
then Base32 plus “.onion”.


PUBKEY:
32-byte Ed25519 master identity public key.


CHECKSUM:
first two bytes of:
SHA3-256(".onion checksum" | PUBKEY | VERSION)


VERSION:
one byte, v3 value 0x03.


Implementation consequence:
Onion Service Discovery MUST NOT treat “56 characters ending in .onion” as sufficient validation.
A strict OnionAddress parser SHOULD:
- normalize ASCII case according to the Tor encoding rules;
- validate the Base32 alphabet and exact encoded length;
- decode bytes;
- validate version;
- recompute checksum;
- reject malformed encoding;
- retain canonical hostname form.


The Tor SOCKS implementation can also report invalid Onion-address errors, but local validation SHOULD occur before expensive network work.


4. Tor SOCKS — AUTHORITATIVE
Official source:
https://spec.torproject.org/socks-extensions.html


Stable facts:
Tor supports SOCKS5 hostnames. A core privacy concern is ensuring name resolution occurs through Tor rather than via a local resolver.
Tor also defines Onion-service-specific extended error codes.


Implementation consequence:
The PHP transport adapter MUST pass the Onion hostname through the Tor SOCKS connection rather than resolving it locally.
It MUST NOT use a local DNS lookup as fallback.
It MUST NOT fall back to a direct TCP connection if Tor is unavailable.
Transport error mapping SHOULD preserve useful Onion-service failure classes internally when available without making application correctness depend on implementation-specific extended codes.


5. Tor Stream Isolation — REVIEW REQUIRED
Official source:
https://spec.torproject.org/socks-extensions.html


The current Tor specification documents SOCKS username/password-based stream-isolation parameters.


Design requirement:
The coding agent MUST evaluate whether separate isolation contexts should be used for:
- peer synchronization;
- direct candidate verification;
- optional clearnet proof work if ever supported.


This decision requires ADR and integration tests.
Do not invent a custom Tor-control dependency merely to obtain isolation if ordinary SOCKS semantics satisfy the defined threat model.


6. Tor Browser Web Client Constraint — AUTHORITATIVE PRODUCT CONSTRAINT
Official source:
https://support.torproject.org/tor-browser/features/plugins/
https://support.torproject.org/tor-browser/features/security-levels/


Architecture decision:
Onion Service Discovery does not require a separate Tor Browser extension.


Consequences:
- no extension permission model;
- no content scripts;
- no Native Messaging;
- direct use in Tor Browser desktop and Android;
- JavaScript availability depends on browser/security settings;
- a No-JavaScript Server Search fallback is permitted with explicit privacy disclosure.


Important:
Tor Project’s warning about extra extensions remains relevant as one reason this architecture avoids requiring one.


7. Tor Browser Fingerprinting and Web Privacy Model
Official source:
https://support.torproject.org/tor-browser/features/fingerprinting-protections/


Consequences:
- no remote fonts or third-party assets;
- no persistent installation identifier;
- no analytics;
- avoid unnecessary feature fingerprinting;
- no content-page instrumentation;
- same-origin static Web Client;
- browser storage is optional, not a stable identity mechanism.


New trust boundary:
the Node that serves the Web Client can modify that executable code. The project MUST document this and MUST NOT claim unconditional query secrecy against the serving origin.


8. Tor Browser Web Platform Behavior — REFRESH
Before implementation freeze and every major release, verify on current supported Tor Browser desktop and Android:
- JavaScript behavior under relevant security levels;
- IndexedDB/Cache Storage availability and persistence characteristics;
- CSP behavior;
- WebCrypto Ed25519 availability/compatibility;
- same-origin fetch behavior;
- responsive/mobile behavior.


The Web Local Search client MUST work without assuming durable browser storage.
If JavaScript is unavailable, the optional server-search fallback MUST remain usable without requiring users to lower Tor Browser security settings.


9. PHP Support Lifecycle — REFRESH
Official source:
https://www.php.net/supported-versions.php


Verified October 2026:
PHP branches 8.3, 8.4 and 8.5 are supported, with different active/security support windows.
PHP 8.5 remains under active support through 31 December 2027 according to the official support table.


Project policy:
- Preferred initial production target: PHP 8.5 unless deployment constraints justify another supported branch.
- Do not choose a version already outside active security support for a new production baseline.
- CI SHOULD test at least the production minimum and latest supported compatible branch.
- Support matrix MUST contain explicit EOL dates.
- Release gate fails if a targeted runtime has reached end of security support without a migration plan.


10. JSON Canonicalization — AUTHORITATIVE
Official source:
https://www.rfc-editor.org/rfc/rfc8785.html


Baseline:
RFC 8785 JSON Canonicalization Scheme is the preferred canonicalization standard for signed JSON payloads.


Protocol requirement:
Before Protocol v1 freeze, produce cross-language known-answer vectors that prove PHP and TypeScript emit identical canonical bytes.
The project MUST NOT rely on language-default object iteration order or ordinary json_encode/JSON.stringify output as an implicit signature format.


11. Ed25519
The application-level identity design uses Ed25519 via established platform libraries.
PHP reference implementation: sodium extension.
Client: WebCrypto if the exact required Ed25519 functionality is available and interoperable in the targeted Firefox/Tor Browser version; otherwise an audited/minimal dependency selected by ADR.


Rules:
- no custom Ed25519 implementation;
- explicit key encoding;
- explicit signature encoding;
- domain-separated signed record types;
- known-answer vectors shared across implementations.


12. OWASP Application Security Verification Standard — EXTERNAL REVIEW BASELINE
Official source:
https://owasp.org/www-project-application-security-verification-standard/


Verified baseline:
OWASP ASVS 5.0.0 is the latest stable version according to the official project page at verification time.


Use:
Create an applicability matrix mapping relevant ASVS requirements to:
- PHP node;
- public Node API;
- local administrative CLI;
- browser extension where relevant.


Not every ASVS item applies. Each N/A needs a reason.
The project’s own stronger privacy/protocol invariants remain authoritative where ASVS is silent.


13. OWASP SSRF Guidance
Official source:
https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html


Onion Service Discovery is inherently exposed to SSRF-like risk because it performs network requests based on discovered identifiers.


Project-specific stronger rule:
The Direct Verification subsystem does not accept an arbitrary URL.
It accepts a parsed OnionAddress value object only and constructs the fixed protocol endpoint internally.
Peer-configured proxy destinations, schemes, arbitrary paths, IP addresses, localhost names, file URLs, redirects to different hosts, and arbitrary callback URLs are prohibited.


14. OWASP Logging Guidance
Official source:
https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html


Project policy:
Security-relevant validation failures can be logged as event classes, but logs MUST avoid:
- private keys;
- search queries;
- browsing history;
- credentials;
- arbitrary raw attacker-controlled strings where a normalized error class is sufficient.


Logging design MUST balance security detection with privacy and minimization.


15. SLSA Supply-Chain Baseline — REFRESH
Official source:
https://slsa.dev/spec/v1.2/


Verified baseline:
SLSA specification version 1.2 is current at verification time.


Project objective:
Adopt SLSA-aligned build provenance where the chosen CI/release platform makes it practical.
At minimum record:
- source revision;
- build definition;
- build environment/tool versions;
- artifact hashes;
- reproducible commands;
- provenance/attestation where supported.


Do not claim a SLSA level unless all requirements of that level have actually been met and verified.


16. CycloneDX SBOM — REFRESH
Official source:
https://cyclonedx.org/specification/overview/


Project policy:
Generate machine-readable SBOMs for:
- PHP/server release;
- browser extension release;
preferably CycloneDX JSON using a currently supported schema version.


SBOM generation MUST be automated and attached/referenced in release evidence.
SBOM is not itself a vulnerability scan.


17. Semantic Versioning
Software versioning may use Semantic Versioning if confirmed by ADR.
Protocol versioning is separate and stricter.
A software release may change without changing Protocol v1.
A breaking wire-semantic change requires a new protocol version regardless of software SemVer.


18. RFC 3339 / Time
If protocol timestamps are used, UTC RFC 3339-compatible representation SHOULD be used after ADR confirmation.
Identity ordering MUST NOT rely on wall-clock time where revision/hash-chain semantics provide stronger ordering.
Clock skew MUST NOT repair cryptographic conflict.


19. Unicode
Unicode handling must be version-aware and deterministic where security decisions depend on it.
The protocol MUST NOT sign a transformed display string unless that transformation is explicitly part of the canonical data model.
Display-layer confusable analysis is advisory and separate from cryptographic identity.


20. SQLite
SQLite is the reference node datastore, not a wire-level requirement.
The agent MUST pin/test a minimum SQLite capability set through the PHP runtime.
Foreign keys, transactions, integrity checks, locking behavior, and backup procedure require explicit tests.
Do not rely on unspecified filesystem semantics.


21. HTTP
The protocol uses HTTP semantics over Onion Services for reference endpoints.
Requirements:
- explicit methods;
- Content-Type;
- bounded response size;
- defined cache behavior;
- no identity-changing redirect behavior;
- error semantics documented.


HTTP status alone never establishes cryptographic trust.


22. Content Security Policy
Any HTML UI served by node or extension uses restrictive CSP.
Remote executable code is prohibited in production.
Extension packaging must not depend on CDN scripts.


23. Licensing
Before first public release:
- choose an explicit project license through human decision;
- inventory dependency licenses;
- ensure distribution compatibility;
- place SPDX identifiers where appropriate.
The coding agent MUST NOT silently select a license that has not been approved by the project owner.


24. Security Claims
External standards are review aids, not certification.
Do not claim:
- “OWASP certified”;
- “SLSA compliant” at a level not demonstrated;
- “Tor approved”;
- “audited” before an actual independent audit;
- “anonymous” as an unconditional guarantee.


25. Mandatory External-Reference Register
Create docs/standards/external-reference-register.md with columns:
Reference
Pinned/current version
Official URL
Reason used
Normative or advisory
Last checked
Next check gate
Change impact


26. Time-Sensitive Facts
Mark REFRESH:
- PHP supported branches;
- Firefox/Tor Browser extension manifest behavior;
- SLSA version;
- CycloneDX schema version;
- OWASP ASVS stable version;
- dependency/library versions;
- CI attestation capabilities.


27. Stable Facts
Treat as protocol foundations unless Tor itself changes:
- v3 Onion address encoding as specified by Tor;
- no need/use for Tor private key in application;
- local-search privacy objective;
- Service and Node application-key separation;
- cryptographic state conflict not resolved by arbitrary peer majority.


28. Change Handling
If an external standard changes:
Advisory-only change:
update documentation/tests as appropriate.


Behavioral platform change:
ADR + compatibility tests.


Wire/crypto semantic change:
Protocol Change Proposal; if breaking, new Protocol version.


29. Evidence
At each production release, release evidence MUST record the versions actually used:
PHP
Composer
SQLite
Tor tested versions
Tor Browser/Firefox basis
Node/TypeScript toolchain
canonicalization implementation
crypto provider
SBOM format
CI/provenance format
OWASP/verification baseline


30. Final Rule
The coding agent is forbidden from treating this document as a substitute for checking current official documentation. It is a baseline and a checklist. For anything marked REFRESH, stale knowledge is a release defect


31. RFC 8615 — WELL-KNOWN URIs
Official source:
https://www.rfc-editor.org/rfc/rfc8615.html


RFC 8615 defines the /.well-known/ URI path prefix and the associated registration framework.
Onion Service Discovery uses a Well-Known Manifest path and MUST maintain an IANA-registration readiness plan.
Before any registration request, refresh the current IANA registry and registration procedure.


32. RFC 9457 — PROBLEM DETAILS FOR HTTP APIs
Official source:
https://www.rfc-editor.org/rfc/rfc9457.html


RFC 9457 defines machine-readable Problem Details for HTTP APIs and obsoletes RFC 7807.
The Node API SHOULD evaluate adopting application/problem+json rather than inventing a wholly proprietary public error envelope.
If adopted, Onion Service Discovery retains stable project-specific error codes as documented extensions/problem types.


33. JSON SCHEMA DRAFT 2020-12
Official source:
https://json-schema.org/draft/2020-12
https://json-schema.org/specification


Verified planning baseline:
JSON Schema Draft 2020-12 is the current published specification baseline.
Protocol v1 schemas MUST explicitly declare the dialect, use stable $id values, define unknown-field semantics, and be validated by multiple implementations where practical.


34. WCAG 2.2
Official source:
https://www.w3.org/TR/WCAG22/


Verified planning baseline:
WCAG 2.2 is a W3C Recommendation. It is also recognized as ISO/IEC 40500:2025.
Reference-client UI and public human-facing documentation target applicable WCAG 2.2 Level AA requirements.
A project claim of conformance requires actual assessment evidence; no external certification is implied.


35. FORMAL METHODS
Formal verification tooling is not an Internet standard dependency, but the project requires an explicit reproducible formal state model for critical Protocol state semantics.
Tool/version is recorded in release evidence.


36. STANDARDS SUBMISSION CLAIM RULE
The project may prepare:
IANA registration drafts
RFC-style publications
standards-body discussion material
but MUST NOT call Onion Service Discovery an IETF standard, RFC, IANA-approved protocol or Tor Project standard until the corresponding external process actually produces that status.
.
