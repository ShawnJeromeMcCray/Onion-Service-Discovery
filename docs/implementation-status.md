# Implementation status

Baseline: `ee9be0d` contained only a one-line README. Branch: `implementation/repository-foundation`.

## Available evidence

The source manifest contains 50 current non-archived documents retrieved on 2026-10-12, each with a SHA-256 digest and observed source URL/modification time. Import is not a completed engineering review: entries are `IMPORTED_NOT_FULLY_REVIEWED`.

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
| Specification review | PARTIAL | Full reading, traceability, conflict resolution, standards refresh |
| Foundation | PARTIAL | Reviewed PHP/TypeScript toolchains, CI/runtime scans |
| Protocol/cryptography | NOT IMPLEMENTED | Schemas, exact vectors, independent implementations, review |
| Formal assurance | NOT IMPLEMENTED | Model and reproducible checks |
| Storage/signing | NOT IMPLEMENTED | Backends, crash/recovery conformance, offline signer |
| Tor transport/Node | NOT IMPLEMENTED | CLI/API, direct verification, sync, bounded transport |
| Browser client | NOT IMPLEMENTED | Search, multi-source integrity, browser/privacy/accessibility evidence |
| Operations/release | BLOCKED | Install/restore/rollback, chaos/performance, provenance/trust root |
| Human approvals | BLOCKED | Required decision and release reviews |
| External audit | NOT PERFORMED | Independent reviewer evidence |

No gate passes solely because documents exist. The first push was rejected for missing workflow permission. The workflow remains a non-active template under tools/ci; no production release has been made. The owner explicitly authorized use of the supplied GitHub credential. It must stay outside project files and Git URLs.
