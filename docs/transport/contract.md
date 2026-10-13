# Draft Tor transport and Node runtime

The outbound adapter accepts only a validated Tor v3 `OnionAddress` and a closed
`ProtocolEndpoint` value. It constructs fixed HTTP port-80 destinations. SOCKS5
hostname resolution is mandatory. Only local loopback proxy addresses may be
configured; an unavailable proxy causes failure without direct fallback.
`CURLOPT_NOPROXY` is explicitly empty so ambient `NO_PROXY=*` cannot bypass Tor.
Redirects, URL credentials, netrc, compressed responses and arbitrary paths are
unsupported. Each request uses Tor extension format zero (`<torS0X>0`) with a fresh
random SOCKS isolation parameter unrelated to
users. Configure Tor's SOCKSPort with `IsolateSOCKSAuth`.

Headers are limited to 8 KiB total and 2 KiB per line. Responses use the endpoint
registry limits, including 16 KiB manifests and 512 KiB chunks. Timeouts are
bounded; JSON content type is mandatory. Transport errors expose normalized
codes, not destinations, response bodies or search terms. Controlled SOCKS tests
check domain-name addressing, credentials, ambient proxy bypass resistance,
redirect refusal, encoding refusal, header ambiguity and byte limits. These are
not tests against a running Tor daemon or an external Onion Service.

The CLI configures `node`, `client-host` and `combined` roles explicitly. All
network feature switches default off. A Client Host config contains no Node
signing key. `node:init` creates a separate signed Node descriptor and chains
capability changes. `candidate:add` queues a valid address only when discovery
is enabled. `node:work-one` performs at most one manifest request, retains
valid immutable evidence, checks continuity/forks and creates local direct
observation evidence only after the controlled direct fetch. Imported Node
observations never populate the local-observation table. Unavailable Services
do not erase identity history. Successful candidates are scheduled for another
check after roughly a day, without manual Service renewal; transient failures
use positive exponential backoff and jitter. Quarantine requires explicit
resolution. A crashed worker lease is recovered with backoff.

`node:snapshot` builds deterministic hash-sorted chunks of immutable signed
records, excluding snapshot descriptors to avoid self-referential content.
Snapshot generations have signed predecessor hashes. The cursor is the highest
included admission sequence; repeated publication with unchanged content does
not create another checkpoint. Chunks use SHA-256 over ASCII
`Onion Service Discovery/snapshot-chunk/v1`, NUL and canonical array bytes.
The root hashes ASCII `Onion Service Discovery/snapshot-root/v1`, NUL and the
canonical ordered chunk descriptors. These are draft byte choices requiring
cross-language vectors and human protocol review.

Peer sync is resumable with at most six generic requests and two chunks per step. It retains global evidence through source omissions and activates a source cursor only after complete chunk verification. Signed checkpoint equivocation is retained, rollback is rejected and one lease per source prevents concurrent jobs. Imported observations never become local checks. Public export excludes never-published Service identities while retaining needed public history and withdrawal evidence. Unchanged direct checks update local liveness without accumulating duplicate observation records; source checkpoints can refresh their signatures daily.

The PHP public entrypoint requires `ONION_SERVICE_DISCOVERY_CONFIG` containing
only the configuration path. Serve only `node-php/public` as the document root;
keep config, dependencies, data, exports and all keys outside it. Its protocol
surface is GET/HEAD only and has no signer/admin dependency. A global token
bucket stores only token count and time, with no IP or user identifiers. Default
burst is 20 and refill is two requests per second; deployment worker/connection
limits are additional requirements. Disable access logs and raw exception logs
in the serving stack. API cookies and credentials are unused. CORS defaults
closed and permits only explicit `allowed_client_origins` Onion origins for
public generic data; no wildcard or credentialed CORS.

The service-history endpoint uses a derived immutable lookup index, with a
256-reference/32 KiB individual response cap. Larger histories use generic signed
snapshots. Bounded reference-preserving export cleanup is operator-only while
workers are stopped. Actual live Tor/desktop Android profiles, long-running
retention and deployed hardening remain release gates. No production approval is implied by this draft runtime.

Canonical genesis-key and historical-address lookups are described in
[service-ID lookup](../operations/service-id-lookup.md). Successful real-Tor
application tests use a private isolated network; the official signature-verified
Tor Browser also passes scoped local headless checks. See
[executed evidence](../conformance/private-tor-onion-report.json) and
[browser evidence](../conformance/tor-browser-local-report.json). These do not
complete the deployed public-Tor/browser privacy gates above.
