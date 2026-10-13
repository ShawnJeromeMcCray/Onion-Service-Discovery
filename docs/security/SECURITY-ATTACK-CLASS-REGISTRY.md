# Security attack-class registry

Status: implementation review inventory. Scoped automated evidence is not full exploit-class closure or independent audit.

| Attack class | Control / review scope | Evidence to inspect | Status |
| --- | --- | --- | --- |
| XSS/DOM XSS | Plaintext rendering and escaped static templates | `node-php/src/Http/StatusPage.php`, `web-local-search-client/tests/security-lint.test.mjs` | PARTIAL — inspect exact coverage and residual variants |
| SQL injection | Prepared values, developer-defined query shapes | `node-php/src/Storage/SQLiteStore.php`, `node-php/tests/storage-conformance.php` | PARTIAL — inspect exact coverage and residual variants |
| Command/code injection | No request-path shell; token sink policies | `node-php/bin/security-lint.php`, `web-local-search-client/tests/security-lint.test.mjs` | PARTIAL — inspect exact coverage and residual variants |
| Path traversal/symlink/TOCTOU | Canonical private configuration and content-addressed storage | `node-php/src/Configuration/NodeConfig.php`, `node-php/tests/configuration.php` | PARTIAL — inspect exact coverage and residual variants |
| SSRF/destination confusion | Closed Onion endpoint transport with no redirect/direct fallback | `node-php/src/Transport/TorTransport.php`, `node-php/tests/tor-transport.php` | PARTIAL — inspect exact coverage and residual variants |
| Resource exhaustion | Bounded input/work/storage and target queue | `node-php/src/Node/CandidateQueue.php`, `node-php/tests/candidate-queue.php` | PARTIAL — inspect exact coverage and residual variants |
| Exceptional conditions | Durable storage error handling and last committed state | `node-php/src/Storage/SQLiteStore.php`, `node-php/tests/storage-disk-full.php` | PARTIAL — inspect exact coverage and residual variants |
| Key/signature confusion/replay/downgrade | Separated domains and proof-required continuity | `node-php/src/Protocol/RecordVerifier.php`, `node-php/tests/protocol-core.php` | PARTIAL — inspect exact coverage and residual variants |
| Rollback/freeze/update compromise | Mature python-tuf verification and high-water metadata | `release-tools/verify_release.py`, `release-tools/test_release_verifier.py` | PARTIAL — inspect exact coverage and residual variants |
| Race/atomic commit | Filesystem lock/journal and SQL transactions | `node-php/src/Storage/FilesystemStore.php`, `node-php/tests/storage-crash.php` | PARTIAL — inspect exact coverage and residual variants |
| Parser differential/Unicode | Duplicate-aware bounded JSON and three-language exact vectors | `node-php/src/Validation/StrictJson.php`, `tools/fuzz_protocol_inputs.py` | PARTIAL — inspect exact coverage and residual variants |
| Result-flood/Sybil | Quantity-neutral local filtering and presentation groups | `web-local-search-client/src/client/result-groups.ts`, `web-local-search-client/tests/result-groups.test.mjs` | PARTIAL — inspect exact coverage and residual variants |
| Archive/decompression bomb | Bounded candidate inspection and no automatic execution | `tools/verify_candidate.py`, `tools/run_candidate_smoke.py` | PARTIAL — inspect exact coverage and residual variants |
| CSRF | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Authentication bypass | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Authorization/IDOR/BOLA | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| XXE | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Insecure native deserialization | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Open redirect | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| CRLF/header injection | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Request smuggling | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Cache poisoning/deception | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Prototype pollution | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| ReDoS | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Log injection | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Session fixation | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Clickjacking | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Supply-chain compromise | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Malicious Node equivocation | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Eclipse/source concentration | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
| Dangerous dynamic include | Assess current endpoints, deployment and dependency/native scope; absent optional features require exact non-applicability rationale | Current source inventory and ASVS-MAP.json | REVIEW_REQUIRED |
