# 32 - Operator Developer Experience and Diagnostic Command Standard

Source: https://docs.google.com/document/d/1rkfhjdiLlpoHDKmhtJCaq_pbxuXSflGZPkdFARNsTqI/edit?usp=drivesdk

Source modified: 2026-10-11T06:19:38.568Z

---

ONION SERVICE DISCOVERY
OPERATOR, DEVELOPER EXPERIENCE AND DIAGNOSTIC COMMAND STANDARD


1. Purpose
Production quality is not only protocol correctness. Operators and contributors must be able to install, diagnose, update and recover Onion Service Discovery without reverse engineering internals.


2. Experience Goal
A competent Linux operator should be able to:
install
initialize
generate keys
publish manifest
run self-check
enable node roles
sync
inspect health
back up
restore
upgrade
rotate keys
diagnose failures
using documented commands and without editing database rows manually.


3. CLI Design
Single coherent CLI entry point preferred:
onion-service-discovery <command>


Subcommands may be implemented in PHP CLI.


4. Required Commands
init
status
doctor
security:check
config:validate
manifest:generate
manifest:show
manifest:verify
service:verify
peer:list
peer:add
peer:disable
sync:run
sync:status
candidate:list
quarantine:list
db:migrate
db:verify
backup:create
backup:verify
restore:verify
key:service:generate
key:service:rotate
key:node:generate
key:node:rotate
release:info


5. Command Contract
Each command:
- stable exit codes;
- --json machine-readable output where applicable;
- --no-interaction mode for automation;
- human output concise and safe;
- no secrets in routine output;
- --verbose does not reveal private keys.


6. Exit Codes
0 success.
Non-zero typed classes:
configuration
validation
network
storage
security
partial/degraded
usage.
Exact registry documented.


7. init
Guided initialization:
detect supported PHP/extensions;
choose config path;
set DB path;
generate keys only after explicit confirmation;
record Onion address;
configure roles;
validate filesystem permissions;
generate initial Manifest preview;
do not silently publish until operator confirms intended state.


8. doctor
Read-only diagnostics:
runtime versions
required extensions
config parse
key files
key permissions
DB open/integrity
schema version
manifest consistency
Tor SOCKS connectivity
Node API self-state
scheduler freshness
disk budget
latest sync
backup status if configured.


Never mutate state unless separately requested.


9. security:check
Focused hardening:
webroot secret exposure
private-key permissions
debug mode
Tor-only outbound config
direct-fallback test where safe
CSP/security headers
unsupported runtime
dependency audit result age
unsafe limit overrides
remote admin absence.


Output:
PASS
WARN
FAIL
with remediation.


10. Machine-Readable Health Report
Provide schema:
software_version
protocol_versions
db_schema
config_state
crypto_state
tor_transport_state
manifest_state
sync_state
scheduler_state
disk_state
backup_state
security_check_summary
generated_at.


No private data.


11. status
Fast, non-invasive:
roles
current Service identity fingerprint
current Onion
current revision
known Services count
peer count
queue count
last sync
health mode.


12. config:validate
Validate:
types
ranges
unknown keys
paths
role dependencies
Tor settings
limits
logging
scheduler
keys.
Print exact config key error without dumping secret values.


13. manifest:show
Display current decoded Manifest and technical interpretation.
Separate:
signed source fields
derived local checks.
Option to show canonical bytes/hash for debugging.


14. manifest:verify
Offline file validation.
Useful for CI/operator before publish.
No network side effect.


15. service:verify
Explicit operator command for one canonical Onion.
Honors Tor transport and resource limits.
Output every verification stage and final evidence.
Must not accept arbitrary URL.


16. peer:add
Input canonical Node Onion only.
Fetch/verify Node Descriptor before ACTIVE.
Support --pinned explicit.
Explain pin means source selection, not Service trust.


17. sync:run
Bounded single run.
Options:
--peer specific
--dry-run where possible
--max-work lower than configured.
Never disable validation.


18. quarantine:list
Show normalized reason/evidence hash, not raw malicious payload by default.
Detailed export explicit.


19. backup:create
If project implements helper:
produce consistent SQLite backup plus metadata.
Private key backup should be separately protected and never casually bundled into unencrypted archive.


20. backup:verify
Verify:
archive readable
DB integrity
schema
expected files
key public/private consistency if secure test permitted.
Do not require exposing private key text.


21. restore:verify
Dry verification against candidate restore location.
No automatic production overwrite.


22. release:info
Display:
software version
source revision/build ID
Protocol versions
DB schema
SBOM reference/hash
release manifest hash
build provenance availability.


23. Human-Friendly Errors
Every error:
short cause
security-safe explanation
next step
diagnostic ID/error code.


Bad:
“Something went wrong.”


Good:
“Tor SOCKS proxy at configured local endpoint is unreachable. Direct fallback is disabled. Discovery synchronization was not attempted.”


24. Documentation Linking
CLI can print local documentation path or stable project docs URL.
Do not require online docs for basic recovery.


25. Shell Completion
Optional but desirable for command names/options.
Never autocomplete secrets/private keys.


26. Man Page / Help
onion-service-discovery help
per-command examples
security consequences.
Examples use synthetic Onion addresses/test values.


27. Idempotency
Safe commands like:
config:validate
doctor
status
db:verify
manifest:verify
are repeatable without mutation.


28. Dangerous Command UX
Key rotation
full reset
restore overwrite
data purge
require:
explicit command
summary
confirmation or force flag
backup warning
noninteractive explicit acknowledgement.


29. Developer Bootstrap
One documented path:
clone
install locked dependencies
run tests
start LAB-FAST
run conformance
format/static analysis.


Target:
new contributor can obtain green baseline without secret infrastructure.


30. Make/Task Wrapper
Provide top-level task runner:
setup
check
test
test-unit
test-conformance
test-chaos
lint
static
build
package
docs
lab-up/down.


Underlying commands remain documented; wrapper not magic.


31. Environment Doctor
Developer command checks:
PHP
Composer
Node runtime
package manager
Tor optional profile
browser test tooling
TLA+ model checker if formal profile
versions.


32. Fixture Generator
Developer tooling generates synthetic Services/revisions/migrations using test-only keys.
Deterministic seed.
Never generate production keys into repository.


33. Schema Tooling
Commands:
schemas:validate
vectors:verify
protocol:check
ensure schemas themselves and registry uniqueness.


34. Documentation Lint
Detect:
forbidden abbreviated project names
stale links where possible
undefined normative terms
TODO in normative docs
duplicate registry values.


35. Error Registry Tool
Generate/check stable error-code registry from one machine-readable source.


36. Config Reference Generation
Generate documentation table from config schema where practical, avoiding drift.


37. Observability Without Complexity
No mandatory Grafana/Prometheus stack.
Machine-readable status enables optional integrations.
Reference operator can run small server simply.


38. Minimal Deployment Path
Small Service operator:
publish only.
No mirror/discovery/sync workload required.


Community Node operator:
enable full roles knowingly.


39. Self-Update
Application MUST NOT automatically execute downloaded project code.
Updates are operator/package/distribution action with signed/provenance verification.


40. Troubleshooting Decision Trees
Docs include:
Manifest not discovered
Tor unavailable
Signature invalid
Revision conflict
DB locked
disk full
peer stale
client not updating
pin mismatch
permission denied.


41. Support Bundle
CLI:
diagnostics:export
produces privacy-reviewed bundle:
versions
health
generic logs/errors
config with secrets redacted
no queries
no private keys
no pins by default.


42. Redaction Tests
Known secret fixtures inserted in test environment.
Support bundle generator must omit them.


43. Recovery Drills
Developer/operator docs include exercises:
restore backup
rotate Node key
simulate peer outage
rebuild cache
recover from failed DB migration.


44. Documentation Quality Gate
Every public command has:
purpose
syntax
options
examples
exit codes
security effects
side effects.


45. Acceptance
Operator/developer experience PASS requires:
fresh operator walkthrough completed;
fresh developer walkthrough completed;
doctor/security:check machine-readable;
all dangerous actions protected;
support bundle privacy tested;
no undocumented mandatory manual database editing.
