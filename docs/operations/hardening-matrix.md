# Deployment hardening matrix

Specification 18, section 55 requires this matrix. Each row names a control and
why it exists, its default and where it is enforced, the automated test, the
operator action with platform notes, its status and the residual risk. Section
numbers (§) refer to
[specification 18](../specification-source/18---PHP-Node-Deployment-Hardening-and-Secure-Configuration-Matrix.md).
Line numbers refer to the files as of this commit.

Status:

- **Code**: the application enforces it in every deployment.
- **Template**: the reference files in [`deployment/`](../../deployment) set it.
  Another setup must set it itself.
- **Operator**: a documented duty. Nothing in OSD checks it.
- **Open**: not provided, or not verified.

In the tables, `nginx:26` means line 26 of
`deployment/nginx/onion-service-discovery.conf`. Likewise `Apache:` refers to
`deployment/apache/onion-service-discovery.conf`, `FPM:` to
`deployment/php-fpm-pool.conf` and `CLI:` to `node-php/bin/onion-service-discovery`;
"systemd" means the units in `deployment/systemd/`. The
[existing-website](existing-website.md) snippets and the
[static client](../browser/client-contract.md#static-self-hosting) examples
repeat the relevant web server rows.

How the templates were checked (development host, 2026-10-15): nginx 1.24.0
`nginx -t`, Apache httpd 2.4.58 `apache2 -t`, `php-fpm8.3 -t`,
`systemd-analyze verify` and the offline `systemd-analyze security` score, and
one loopback run of the nginx, Apache and FPM templates with PHP-FPM 8.3 as
`osd-worker`, only paths and ports changed. Not checked: units running under
systemd, a real Onion through Tor, PHP-FPM 8.4 or newer. "Loopback run" below
means that manual run, not a CI test.

## Web server (§4, §7, §10, §32, §39-41)

| Control and reason | Default and where enforced | Status | Automated test | Operator action, platform notes | Residual risk |
| --- | --- | --- | --- | --- | --- |
| Only the front controller runs (§4, §10): no other file under `node-php/public` can be requested | nginx:26 fixed `SCRIPT_FILENAME`; Apache:41 `ProxyPassMatch` to `index.php`; FPM:18 `security.limit_extensions = .php` | Template | `secret-paths-http.php`; loopback run | Keep the fixed script path. Do not add static file serving or `try_files` for `node-php/public`. | The PHP development server (`php -S`) is not a deployment. |
| Likely secret paths are not served (§7, §20) | `NodeConfig.php:26,66` refuses private paths inside `node-php/public`; the front controller answers fixed routes only (`index.php:33-105`, `ClientAssets.php:12`, hash-only lookups `DataApi.php:62,65`) | Code, Template | `secret-paths-http.php`: 39 paths, GET and HEAD, four roles; loopback run: 30 paths on six listeners | Keep configuration, keys, store and backups outside every document root. An existing website's own files are its operator's duty. | None known; relay paths exist only for the Client Host and combined roles (`index.php:56`). |
| Body limits (§9-11): bound memory and parsing | nginx:16 1 KiB; search location 8 KiB; Apache:37 refuses bodies over 1 KiB and chunked bodies (`LimitRequestBody`, Apache:23, alone is not enforced for proxied FastCGI); code: announcement 256 bytes (`index.php:65,71`, `CandidateAnnouncement.php:15`), search 8192 bytes (`index.php:40,44`, `ServerSearch.php:23`); FPM:25 `post_max_size = 1K` bounds only PHP's own form parsing | Code, Template | `announcement-http.php` (257 bytes: 413); `server-search-http.php` (8193 bytes: 413) | Raise only the search location, never the base limit. | Apache answers oversized bodies with 403, not 413. |
| Header limits (§10) | nginx:21 request line and each header at most 4 KiB; Apache:20-22 4094 bytes, at most 50 headers; code: the data API refuses targets over 256 bytes or with control characters (`DataApi.php:35`) | Template, Code (data API target) | None automated. Loopback run: 5000-byte header 400, 5000-byte target 414, 55 headers 400 (Apache). | In an existing website these are server-wide: set them there. Apache takes them from the listener's default virtual host. | nginx limits header size, not header count. |
| Inbound timeouts (§32) | nginx:17-18,22,29 header and body 5 s, keepalive 10 s, PHP 65 s; Apache:24-26 the same; FPM:14 `request_terminate_timeout = 65s`, FPM:21 `max_execution_time = 30`; search pool 2 s and 1 s; server search 500 ms work budget (`ServerSearch.php:25`) | Template, Code (search) | None automated for the web server. | `max_execution_time` counts CPU time on Linux, not wall time. | Slow-read clients are bounded only by the web server's timeouts. |
| Methods (§10-11) | Every route checks its methods and answers 405 with `Allow` (`DataApi.php:34`, `ClientAssets.php:15`, `StatusPage.php:15`, `ClientHostRelay.php:22`, `index.php:37,39,64`); Apache:16 `TraceEnable off`; nginx refuses TRACE itself | Code, Template | `data-api.php`, `announcement-http.php`, `server-search-http.php`, `client-host.php` | None. | None known. |
| Server tokens and error pages (§10, §41) | nginx:13 `server_tokens off`; Apache:15 `ServerSignature Off`; FPM:33 `expose_php = Off`; code: errors are short JSON codes or fixed pages, never traces or paths (`index.php:3,107-113`) | Template, Code | `secret-paths-http.php` checks that no answer contains the private paths. Loopback run: `Server: nginx` and `Server: Apache` without version, no `X-Powered-By`. | `ServerTokens Prod` is global in Apache: set it in the main configuration (Debian/Ubuntu: `conf-available/security.conf`). | The web servers' own 400, 413 and 414 pages still name nginx or Apache. No custom error pages are provided. |
| Access and error logs (§10; spec 16 §12-14): request paths reveal interest | nginx:14-15 no access log, errors at `crit`; Apache: no `CustomLog`, Apache:14 `LogLevel crit`; FPM:13,32 no worker output, no error log; systemd `StandardOutput=null`, `StandardError=null`; code logs no requests or queries | Template | `server-search-http.php` checks that the query is not in the test server's log. Loopback run: OSD routes absent from an existing site's access log. | Existing Apache website: add `env=!osd_no_log` to its `CustomLog`. Configure rotation for the error logs. | Requests that nginx refuses before choosing a location are still logged by an existing site's server-level log. No log rotation is provided. |
| Host header (§40) | Code never routes on Host or reads `HTTP_HOST` or `X-Forwarded-*`; the Manifest's Onion comes from the configuration (`index.php:81`) | Code | None (code inspection) | Optional: set `server_name` to the Onion and add a `default_server` that returns 444 (nginx:6-10). | Requests with another Host are still answered. |

## PHP and PHP-FPM (§8-9, §22, §34)

| Control and reason | Default and where enforced | Status | Automated test | Operator action, platform notes | Residual risk |
| --- | --- | --- | --- | --- | --- |
| No error display (§8, §22) | FPM:31-32; `index.php:3`; CLI `node-php/bin/onion-service-discovery:6-7`; unexpected errors become `INTERNAL_ERROR` or `NODE_UNAVAILABLE` | Code, Template | `storage-disk-full.php` (CI container) checks typed CLI errors without raw warnings | None. | None known. |
| Finite memory and time (§8) | FPM:19 128 MiB, FPM:21 30 s, FPM:14 65 s; search pool 1 s and 2 s | Template | None automated | Measure before raising. | `max_execution_time` counts CPU time on Linux. |
| No uploads, URL include or URL fopen (§8) | FPM:26,29,30. The runtime code opens only local paths and `php://input`, and makes outbound requests only with curl (`TorTransport.php`) | Template | None automated. Loopback run: values in effect, an `http://` `file_get_contents` refused. | None. | The CLI jobs use the system `php.ini`, not the pool, so `allow_url_fopen` there is the distribution default. |
| No sessions or cookies (§34) | Runtime code calls no session or cookie functions | Code | `server-search-http.php` checks for no `Set-Cookie` | None. | Cookies an existing website sets for the whole Onion still reach OSD routes; OSD ignores them. |
| Dedicated pool (§9) | FPM:1-7 own pool, socket mode 0660 for the web server user, FPM:12 `clear_env = yes`, FPM:16 no core dumps; separate search pool example | Template | None automated | Keep the website's PHP in its own pool. | See "Signer isolation" below. |

## Process, files and systemd (§3, §5-6, §17, §24-27, §31)

| Control and reason | Default and where enforced | Status | Automated test | Operator action, platform notes | Residual risk |
| --- | --- | --- | --- | --- | --- |
| No root (§3) | FPM:2-3 and every unit run as `osd-worker:osd` | Template | None automated | Create the user without a login shell. | — |
| Signer isolation (§5) | Not provided: the web pool and the Node workers share the `osd-worker` UID | Open | — | Review separate users, mount namespaces or a signer broker before production. | A compromised web process can read the Node key file and its passphrase file and sign as the Node. |
| Private file modes (§5-6, §17) | Stores refuse group or other permissions (`FilesystemStore.php:25,36`, `SQLiteStore.php:36`); key files (`ProtectedFileSigner.php:36,54,79`); passphrase files (CLI:93); budget files (`PublicRequestBudget.php:16`); systemd `UMask=0077` (publisher check 0027) | Code, Template | `protected-signer.php` (0644 key refused), `configuration-init.php` (shared directory refused) | Never fix a refusal by widening modes. | None known. |
| No core dumps of key or query memory (§6) | systemd `LimitCORE=0` (direct check and snapshot jobs unlock the Node key); FPM:16 `rlimit_core = 0` | Template | None automated | — | The FPM hard limit stays as the system sets it. |
| systemd sandboxing (§27) | Every unit: `NoNewPrivileges`, `PrivateTmp`, `ProtectSystem=strict`, `ProtectHome`, `ReadWritePaths`, empty `CapabilityBoundingSet` and `AmbientCapabilities`, `PrivateDevices`, `ProtectKernelTunables/Modules/Logs`, `ProtectControlGroups`, `ProtectClock`, `ProtectHostname`, `ProtectProc=invisible`, `RestrictNamespaces`, `RestrictRealtime`, `RestrictSUIDSGID`, `LockPersonality`, `SystemCallArchitectures=native` | Template | None in CI. `systemd-analyze verify` clean; offline exposure score 2.1-2.7 (7.6-7.7 before) | Test the units on your host: paths and Tor sockets differ. | Not run under systemd. No `SystemCallFilter`, `PrivateUsers` or `ProcSubset` (untested). `MemoryDenyWriteExecute` stays off on purpose: it breaks the PCRE JIT. |
| Resource limits (§31) | systemd `MemoryMax=256M`, `TasksMax=16`, `LimitNOFILE=256`; FPM:9 at most 4 workers (search pool 2) | Template | None automated. The CLI jobs ran with 24 open files in a local test. | Measure before raising. | No `CPUQuota`. |
| Bounded, non-overlapping jobs (§25-26) | Timers with jitter; oneshot units, so systemd does not start a second run while one is active; absolute `/usr/bin/php`, explicit `WorkingDirectory`, `TimeoutStartSec`; storage writer lock in code | Template, Code | `storage-crash.php` (recovery after an interrupted write); none for two jobs at once | Instantiate peer jobs only for configured peers. | — |

## Network (§13-16, §30)

| Control and reason | Default and where enforced | Status | Automated test | Operator action, platform notes | Residual risk |
| --- | --- | --- | --- | --- | --- |
| Outbound only through the loopback Tor SOCKS listener (§13, §15-16) | SOCKS host must be `127.0.0.1` or `::1` (`NodeConfig.php:62`, `TorTransport.php:11`); destinations are parsed v3 Onions; remote name resolution, HTTP only, no redirects, empty `NOPROXY` (`TorTransport.php:33-36`); response size caps (`TorTransport.php:43,54`); systemd `IPAddressDeny=any`, `IPAddressAllow=localhost` for the network jobs, `PrivateNetwork=yes` for snapshot and maintenance | Code, Template | `tor-transport.php` (redirects rejected, typed failures), `configuration.php` (non-loopback proxy refused) | Configure Tor `SocksPort` on loopback. Firewall the web pool's user if the host allows it. | The web pool (Client Host relay) has no OS-level egress rule in the templates. `IPAddressAllow=localhost` allows every loopback port, not only the SOCKS port. |
| Listener inventory (§30) | Web listener `127.0.0.1:8080` (static client `127.0.0.1:8081`), FPM Unix sockets; no metrics or health listener | Template | None | Tor forwards the Onion's port 80 to the listener. Do not expose it on other interfaces. | — |
| TLS (§12) | Not used: Tor provides the Onion transport | Operator | — | If you add HTTPS, keep the Onion identity semantics. | — |

## Browser-facing headers and client hosting (§33, §36)

| Control and reason | Default and where enforced | Status | Automated test | Operator action, platform notes | Residual risk |
| --- | --- | --- | --- | --- | --- |
| CSP and security headers (§33) | Every front-controller answer: `nosniff`, `no-referrer`, `no-store`, `default-src 'none'; frame-ancestors 'none'` (`index.php:115`); data API adds a Permissions-Policy (`DataApi.php:33`); client files: script, style, connect and worker `'self'`, `frame-ancestors 'none'`, `X-Frame-Options: DENY` (`ClientAssets.php:14`); status page hashed style CSP (`StatusPage.php:17`); server search (`ServerSearch.php:95`) | Code | `client-host.php`, `publisher-status.php`, `server-search-http.php` | An existing website's `add_header` or `Header` lines add a second policy: review them. | No `Cross-Origin-Opener-Policy` or `Cross-Origin-Resource-Policy`. |
| Static client hosting (§33) | `static-client.conf.example` for nginx and Apache: the six client files only, GET and HEAD, no listing, no symbolic links, the headers of `ClientAssets.php` | Template | None automated. Loopback run: all seven client URLs matched the PHP host's types and headers. | Copy the build's `connect-src` origins into the header; list the static origin in each Data Node's `allowed_client_origins`. | Not verified in Tor Browser. |
| Escaped output (§36) | Client renders text-only DOM; server pages escape with `htmlspecialchars` | Code | `security-lint.php`, `security-lint.mjs`, browser tests with hostile metadata | None. | — |

## Budgets, secrets and operations (§11, §18-24, §42, §44, §53)

| Control and reason | Default and where enforced | Status | Automated test | Operator action, platform notes | Residual risk |
| --- | --- | --- | --- | --- | --- |
| Request budgets (§11) | One aggregate token bucket per installation without client identifiers: burst 20, 2 per second (`PublicRequestBudget.php`, `index.php:41,66,101`); relay the same, visitor-chosen Nodes burst 64, 1 per second (`index.php:59`) | Code | `request-budget.php`, `visitor-relay-http.php` | None. | Anyone can exhaust the shared budget and slow everyone down; there is no per-client budget by design. |
| Secrets (§20-21, §24) | Passphrases only from owner-only files or systemd credentials (CLI:93, `LoadCredential`); Tor key file names refused (CLI:24,30); the support bundle excludes and redacts paths, Onions and keys (`SupportBundle.php`) | Code | `operator-cli.php` (Tor key names), `operator-commands.php` (support bundle) | Keep passphrase files 0600, never in environment variables or command lines. | See "Signer isolation". |
| No debug mode or web administration (§22-23) | Neither exists; administration is the local CLI | Code | — | — | — |
| No runtime downloads (§18-19) | Locked Composer install without development packages, or the runtime package | Operator | CI dependency audits | `composer install --no-dev --no-plugins --no-scripts` | — |
| Health and self-check (§42, §53) | The status page shows public facts only; `doctor` and `security:check` are local and read-only | Code | `read-only-diagnostics.php` | Check deployed headers, Tor and the scheduler yourself: `doctor` reports them as not observed. | No automated check of a deployed host. |
| Backups (§44) | `storage:backup` writes store records and state, never key files (`keys_included: false`) | Operator | `storage-backup.php` (backup and restore round trip) | Encrypt backups, keep them out of document roots, back up keys separately, test a restore. | — |
| Host patching (§29) | Not provided | Operator | — | Keep the OS, PHP, Tor and the web server updated. | — |
