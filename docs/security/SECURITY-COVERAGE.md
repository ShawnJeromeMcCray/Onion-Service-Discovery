# Security coverage

Current controls/tests are indexed in [attack-class registry](SECURITY-ATTACK-CLASS-REGISTRY.md),
[ASVS matrix](ASVS-MAP.md), and the current source-section inventory in
`docs/requirements.json`. They do not establish complete security coverage.

Source sink checks use the pinned TypeScript compiler scanner and PHP tokens.
Comments/string literals are separated from executable tokens. PHP loose
comparisons and direct dangerous global calls, JavaScript literal HTML sinks,
dynamic code calls and string timers are rejected. These checks do not prove
taint safety, identify every alias/dynamically computed sink, review native
libraries, or replace independent code review. PDO method calls are distinguished
from PHP's global process-execution function.

Current category tests exercise all 38 draft IDs, adult, unknown/case/lookalike/
duplicate/order/length/count rejection and count-neutral local ordering.
Publisher status tests cover no-database operation, no key access, no private
path rendering, escaped output, method policy and no false inclusion claims.
The 100,000-row grouping test exercises presentation models, not 100,000
signature-verified imported identities or live Tor services.

Production requires all applicable controls assessed, zero open Critical/High
findings and the human reviews in the current Definition of Done.
