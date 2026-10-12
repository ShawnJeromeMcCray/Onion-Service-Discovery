# 18 - PHP Node Deployment Hardening and Secure Configuration Matrix

Source: https://docs.google.com/document/d/1p07E8Y31GQY-_LQQon8f1mmXthZVVkmYDDsWji13CLA/edit?usp=drivesdk

Source modified: 2026-10-11T07:38:56.735Z

---

ONION SERVICE DISCOVERY
PHP NODE DEPLOYMENT HARDENING AND SECURE CONFIGURATION MATRIX


1. Purpose
Define secure production deployment defaults for the PHP reference node. This is a hardening baseline, not a replacement for host-specific security administration.


2. Runtime Baseline
Preferred initial production branch: PHP 8.5 as of October 2026, subject to refresh at implementation/release.
Only supported PHP branches may be listed in the support matrix.
CI tests minimum supported and latest supported compatible branch.


3. Process Separation
Recommended:
Tor daemon/service account
web server
PHP-FPM pool dedicated to Onion Service Discovery or site
scheduler/CLI under same minimal app account or separate maintenance account if permissions justify
No process runs as root for normal application work.


4. Filesystem Layout
Code: read-only to runtime wherever practical.
public/: only public front controller/static assets.
config/: outside public webroot.
keys/: outside public webroot, restricted.
var/database/: writable app state.
var/cache/: writable rebuildable.
var/quarantine/: bounded.
logs/: system-managed or bounded.
backups/: not under webroot.


5. Ownership
Document exact owner/group matrix.
Web process needs read config/public key and only the minimal write access required by the selected storage/cache backend.
Private key write required only for controlled rotation/generation path; consider split permission so ordinary web request path does not need write.


6. Private Key Permissions
Reference:
owner read/write only where OS supports.
Startup warns/fails on broadly readable private key unless operator explicitly acknowledges unsafe environment.
Never chmod recursively without path checks.


7. Webroot Test
Automated deployment test MUST request likely secret paths and assert inaccessible:
config files
key files
composer files if not intended
database
backup
logs
.git
environment files
test fixtures.


8. PHP Configuration
Production:
display_errors off
log_errors controlled
expose_php off where practical
memory_limit finite
max_execution_time finite for web requests
upload features unused/disabled at application layer
session subsystem not required for public API
allow_url_include off
remote code/include not used


Do not depend solely on php.ini; application maintains its own limits.


9. PHP-FPM
Dedicated pool recommended.
Finite:
pm.max_children
request_terminate_timeout
request size limits via webserver/PHP
slowlog only if privacy-safe
environment whitelisting
clear_env policy reviewed.


10. Web Server
Only public directory served.
Deny hidden/sensitive paths.
Request method restrictions.
Request header/body limits.
Timeouts.
No autoindex.
Server tokens minimized.
Access logs minimized per privacy spec.


11. API Request Body
Reference Protocol public API is GET/HEAD; reject unexpected request bodies/methods.
This reduces attack surface.


12. TLS
Onion Services have Tor-level properties; Protocol v1 does not require modifying Tor deployment.
If HTTPS is additionally used, configure independently and do not let certificate handling weaken Onion identity semantics.


13. Tor SOCKS
SOCKS listener bound locally only.
Do not expose SOCKS port publicly.
Application config specifies host/port.
Health test verifies expected proxy path.
No direct fallback.


14. Tor Isolation
If ADR adopts SOCKS authentication isolation:
generate appropriate per-purpose isolation tokens according to Tor specification.
Do not persist user-specific identifiers.
Separate node work classes, not end-user identities.


15. Outbound Firewall
Where operator can enforce:
PHP/web account should have outbound network access consistent with Tor SOCKS use, not arbitrary Internet.
A strong deployment can firewall direct outbound traffic from app process except local Tor proxy and required system services.
Document platform-specific examples carefully.


16. SSRF Defense in Depth
Application restricts destination to OnionAddress Value Object.
Network policy/firewall is second layer.
No generic curl wrapper exposed to protocol inputs.


17. Storage Backend Hardening
All mutable storage outside webroot.
File and directory permissions minimal.
Backend-specific integrity/invariant enforcement enabled and verified.
Backup procedure tested.
Backend transaction/journal/checkpoint mode documented.
Hard disk-space alerts.


18. Composer
Production install:
composer install using lockfile
no unreviewed plugins/scripts unless explicitly required
prefer --no-dev for production runtime
verify platform requirements
dependency audit in build/release pipeline.


19. Autoloading
Optimized production autoloader optional.
No runtime downloading dependencies.


20. Secrets
No secrets:
in repository
in public directory
in URL/query
in HTML
in error response
in process command line where avoidable
in CI logs.


21. Environment Variables
May be used for deployment secrets but:
do not dump $_ENV in diagnostics.
Document process inheritance.
Prefer secret files/manager where appropriate for long-lived keys.


22. Debug Mode
Production debug false.
No debug toolbar.
No SQL query dumps.
No exception traces to client.
Enabling debug requires explicit local/operator action and visible health warning.


23. Admin Surface
No web admin by default.
Use local CLI.
If future web admin exists, separate product/security spec required; not a quick addition.


24. CLI Safety
Commands that:
rotate keys
reset DB
delete data
change peer pins
require explicit flags/confirmation.
Support --dry-run where useful.
Never print private keys in routine status.


25. Scheduler
systemd timer or cron examples:
non-root
working directory explicit
PATH explicit
timeout
lock
bounded job
logging to controlled facility
no overlapping runs.


26. Job Locking
Use robust file/DB advisory lock.
Stale lock recovery documented.
Lock name per job.


27. Systemd Hardening
If example service units are provided, evaluate:
NoNewPrivileges
PrivateTmp
ProtectSystem
ProtectHome
ReadWritePaths
RestrictAddressFamilies
CapabilityBoundingSet
UMask
MemoryMax/CPUQuota where appropriate.


Examples must be tested, not copied blindly, because Tor/socket/file paths vary.


28. Container Deployment
Optional.
Container is not inherently secure.
If provided:
run non-root
read-only root FS where practical
mounted writable storage/keys
no Docker socket
minimal image
pinned base digest for release build where feasible
health/readiness
backup volume docs.


29. Host Patching
Operator guide requires:
supported OS
security updates
PHP updates
Tor updates according to operator environment
webserver updates.
Project does not bundle/replace system Tor.


30. Network Listener Inventory
Production runbook enumerates:
public Onion web listener behind Tor mapping
local PHP-FPM socket
local Tor SOCKS
optional local metrics/health
No surprise listener.


31. File Descriptor/Resource Limits
Set finite process limits appropriate to expected concurrency.
Low Tor request concurrency by design.


32. Request Timeouts
Inbound web:
header/body/keepalive finite.
Outbound Tor:
connect and total finite.
Scheduler:
job runtime finite.


33. Response Security Headers
For HTML:
Content-Security-Policy
X-Content-Type-Options
Referrer-Policy
frame policy
permissions policy as appropriate


API:
correct Content-Type
nosniff
no unnecessary cookies.


34. Cookies
Public sync API uses no cookies.
If UI uses no session, Set-Cookie absent.
Avoid accidental PHP session startup.


35. CSRF
No state-changing public web endpoints.
Local CLI eliminates most CSRF surface.
If future state-changing web UI exists, explicit auth/CSRF spec.


36. XSS
All service metadata displayed as text.
Output encoding.
No raw manifest HTML.
CSP as defense in depth.


37. SQL Injection
Prepared statements only.
Dynamic ORDER BY/table names from fixed enums, not raw input.


38. Path Traversal
Content-addressed chunk lookup maps validated hash to internal storage key, never directly concatenates arbitrary path.
No user-provided filesystem path endpoint.


39. HTTP Desync/Proxy
If reverse proxy used, test accepted headers and request-size behavior.
Do not trust X-Forwarded-* unless deployment explicitly configures trusted proxy.


40. Host Header
Routing should not depend on arbitrary Host for identity logic.
Manifest self-Onion configured explicitly.
Validate expected host where webserver architecture allows.


41. Error Pages
API returns bounded JSON errors.
Webserver 4xx/5xx must not expose version/paths.
Application logs technical error ID.


42. Health
Public health optional and minimal.
Detailed diagnostics local CLI.
Do not expose DB path, keys, seed details or environment.


43. Metrics
If Prometheus-like endpoint exists:
bind local/private
no search data
no service-interest per client
authentication/network restriction if exposed.
Not required for core.


44. Backups
Private key backup separated from public index backup.
Backup encryption.
Restore drill.
No backup file in webroot.


45. Log Rotation
Disk budget.
No indefinite logs.
Operator-visible retention default.
Security incidents may preserve selected evidence explicitly.


46. Fail-Safe Startup
Startup self-check can block write jobs when:
key permissions unsafe
DB migration pending/failed
config invalid
Onion/key mismatch
unsupported PHP version
required extension missing


Public read-only serving of last-known verified cache may be allowed only if safe and clearly degraded.


47. Dependency Failure
If sodium missing:
do not start verification/signing features.
If curl/Tor unavailable:
read-only local serving may continue; sync/discovery disabled.
Health state explicit.


48. Time
NTP/system clock recommended for observation timestamps and operations.
Clock anomaly detection warning.
Identity revision logic does not trust clock ordering.


49. Locale
Set deterministic locale where serialization/sorting could otherwise vary.
Canonicalization must be locale-independent regardless.


50. Character Encoding
UTF-8 end-to-end.
DB/string functions tested.
No implicit ISO-8859 fallback.


51. Production Configuration Matrix
For every setting document:
name
type
default
minimum
maximum
security impact
restart required
secret yes/no
example


52. Unsafe Overrides
If an operator can widen limits:
flag clearly “unsafe/high resource”.
No default “unlimited”.
Warnings in config validator.


53. Security Self-Test Command
Provide:
onion-service-discovery security:check
checks:
runtime/version
extensions
file permissions
webroot exposure config where observable
key/db consistency
Tor proxy connectivity
direct-fallback prohibition test in controlled form
schema migrations
CSP/config sanity.


54. Deployment Acceptance
Clean server test:
install docs only
non-root
key generation
DB migrate
manifest publish
Tor SOCKS connectivity
peer read API
sync
backup
restore
upgrade simulation
security self-check PASS.


55. Hardening Matrix Artifact
Agent MUST create docs/operations/hardening-matrix.md with rows:
control
default
rationale
automated test
operator action
platform notes
residual risk.


56. No Security Theater
Do not add controls solely for checklist optics.
Each control maps to threat/invariant.
Unsupported claims remain documented limitations.
