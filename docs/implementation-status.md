# Implementation status

Baseline: `ee9be0d` contained only a one-line README. Branch: `implementation/repository-foundation`.

## Available evidence

The source manifest contains 50 current non-archived documents retrieved on 2026-10-12, each with a SHA-256 digest and observed source URL/modification time. The 50 sources have now been read; entries are `REVIEWED`. This is implementation-agent review, not human approval.

D-001 is APPROVED: CC0-1.0 under the owner’s explicit unrestricted-use instruction on 2026-10-12. Other decisions remain unresolved; no technical approval or audit has been inferred.

## Requirement precedence discovered

- Documents 41/46 supersede mandatory-SQLite architecture: FilesystemStore is required and SQLite is an additional backend.
- Documents 42/46 add separate executable Client Host/data Node roles and multi-source integrity. Combined same-origin mode is weaker.
- Document 43 requires signer-provider separation and offline Service signing by default.
- Document 44 requires release trust-root rollback/freeze protection.
- Document 45 restricts protocol version 1 to Tor v3 and fixed cryptographic formats.

## Remaining work

| Gate | Status | Missing evidence |
| --- | --- | --- |
| Specification review | PARTIAL | Sources read and precedence resolved; 318 candidate requirement blocks extracted; full manual traceability/standards gate still open |
| Foundation | PARTIAL | Reviewed PHP/TypeScript toolchains, CI/runtime scans |
| Protocol/cryptography | PARTIAL | Six signed-record schemas, exact canonical bytes, mature signature verification and identity graph pass 123 black-box cases per language; freeze/human review and broader conformance remain open |
| Formal assurance | PARTIAL | Finite TLA+ model passes both network outcomes and rejects rollback mutation; human review/unbounded proof remain open |
| Storage/signing | PARTIAL | Both backends, eight process-exit fault points, portable public-evidence archive and encrypted role-restricted signer implemented; physical power-loss and complete operational backup evidence remain open |
| Tor transport/Node | PARTIAL | Strict roles/config, bounded Tor-only adapter, controlled direct checks, signed snapshots and read-only API implemented; bounded resumable peer sync passes controlled omission/rollback/equivocation tests; efficient indexed lookup and live Tor evidence remain open |
| Browser client | PARTIAL | Bundled worker-based local search, explicit source allowlist, generic Client Host relay, local pins and controlled Chromium tests implemented; actual Tor Browser/Android and human UX review remain open |
| Operations/release | BLOCKED | Install/restore/rollback, chaos/performance, provenance/trust root |
| Human approvals | BLOCKED | Required decision and release reviews |
| External audit | NOT PERFORMED | Independent reviewer evidence |

No gate passes solely because documents exist. The foundation branch was pushed successfully. The owner subsequently enabled workflow permission; .github/workflows/foundation.yml is now active and checks the shared input subset, typechecking, dependency audit and source integrity. No production release has been made. The owner explicitly authorized use of the supplied GitHub credential. It must stay outside project files and Git URLs.

## Experimental input-core evidence

23 shared address vectors and 43 strict JSON vectors pass in PHP, TypeScript and independent Python. The harness report is docs/conformance/input-subset-report.json. TypeScript strict typechecking passes, and npm audit reports zero current advisories for the locked dependency graph. PHP uses the digest-pinned official 8.5 CLI test image.

This offline prototype does not freeze crypto bytes or mutate identity state. Browser hash-provider selection, full specification review, schema validation, signatures, runtime approval and all application/release gates remain open.

## Remote CI evidence

Workflow commit: `439d0346c4b35e0edc72fa308c162ef893447dab`. [GitHub Actions run 38256162045](https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/actions/runs/38256162045) completed all job steps successfully, including three-language input conformance and dependency audit. This proves the draft input subset in CI; it does not close the remaining application or production gates.

## Draft core milestone

Six signed-record schemas are generated from the limits registry. PHP uses libsodium and Opis JSON Schema; TypeScript uses compiled Ajv validators, WebCrypto Ed25519 and locally bundled noble SHA3; Python is independent and uses cryptography/jsonschema. Exact message/hash bytes, 28 signed-record cases, 8 canonicalization cases and 21 identity sequences supplement the initial 66 input cases.

External process conformance: 123 PASS per implementation, 0 FAIL, 0 SKIP. Reports: docs/conformance/core-report.json and core-unit-report.json. Rotations are not committed without their first valid new-key manifest. Competing transition proofs are conflict evidence even before successor manifests arrive. Missing predecessor history remains pending.

New dependencies are locked, Python distributions hash-locked, and generated validators contain no runtime eval. Draft decisions and protocol fields remain pending human review; no Protocol Stable or Production Stable claim is made.
