# External reference register

Checked sources are separate from pending compatibility tests. This initial refresh does not satisfy the full standards gate.

| Reference | Observed baseline | Official source | Purpose | Last checked | Next gate | Impact |
| --- | --- | --- | --- | --- | --- | --- |
| Tor address encoding | Tor v3, SHA3-256 two-byte checksum | https://spec.torproject.org/rend-spec/encoding-onion-addresses.html | Normative address parser | 2026-10-12 | Protocol freeze | Matches specification; tested against published examples |
| PHP support lifecycle | 8.4 active through 2026-12-31, security through 2028-12-31; 8.5 active through 2027-12-31, security through 2029-12-31 | https://www.php.net/supported-versions.php | Proposed runtime matrix | 2026-10-12 | Runtime ADR and release | PHP 8.5 test container; minimum support not yet approved |
| TypeScript compiler | Registry reports 7.0.2 | https://www.npmjs.com/package/typescript | Experimental development-only typechecking | 2026-10-12 | Toolchain/dependency review | Exact version and dependency integrity locked; not a browser dependency |
| Tor SOCKS and isolation | NOT REFRESHED | https://spec.torproject.org/socks-extensions.html | Transport ADR | Pending | Before transport | Release blocker |
| Tor Browser platform/security levels | NOT REFRESHED OR TESTED | https://support.torproject.org/tor-browser/features/security-levels/ | Browser profile | Pending | Before browser freeze | Release blocker |
| RFC 8785 | NOT YET REVIEWED FOR IMPLEMENTATION | https://www.rfc-editor.org/rfc/rfc8785.html | Canonical bytes | Pending | Before canonicalization | Release blocker |
| ASVS | NOT REFRESHED | https://owasp.org/www-project-application-security-verification-standard/ | Review applicability | Pending | Audit package | Release blocker |
| SLSA | NOT REFRESHED | https://slsa.dev/spec/ | Provenance | Pending | Release candidate | No level claim |
| CycloneDX | NOT REFRESHED | https://cyclonedx.org/specification/overview/ | Release inventory | Pending | Release candidate | No SBOM claim |

The full source-document 11 checklist remains authoritative. A fetched source is not evidence of actual browser behavior or security conformance.
