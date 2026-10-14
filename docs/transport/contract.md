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
not create another checkpoint within the daily refresh window. An explicit
derived-export rebuild also creates a new generation. Chunks use SHA-256 over ASCII
`Onion Service Discovery/snapshot-chunk/v1`, NUL and canonical array bytes.
The root hashes ASCII `Onion Service Discovery/snapshot-root/v1`, NUL and the
canonical ordered chunk descriptors. These are draft byte choices requiring
cross-language vectors and human protocol review.

Peer sync is resumable with at most six generic requests and two chunks per step. It retains global evidence through source omissions and activates a source cursor only after complete chunk verification. Signed checkpoint equivocation is retained, rollback is rejected and one lease per source prevents concurrent jobs. Imported observations never become local checks. Public export excludes never-published Service identities while retaining needed public history and withdrawal evidence. Unchanged direct checks update local liveness without accumulating duplicate observation records; source checkpoints can refresh their signatures daily.

The PHP public entrypoint requires `ONION_SERVICE_DISCOVERY_CONFIG` containing
only the configuration path. Serve only `node-php/public` as the document root;
keep config, dependencies, data, exports and all keys outside it. Its data protocol
surface is GET/HEAD only and has no signer/admin dependency. The separately
opted-in experimental candidate receiver accepts one bounded POST hint and
never performs synchronous outbound work; see [PCP-002](../protocol/proposals/PCP-002-bounded-announcement-draft.md). A global token
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

`service:announce` makes one fixed-endpoint Tor POST per selected Node for an
explicitly published signed Service manifest. The exact unconfirmed response is
required; no signing or storage side effect occurs. With `--if-due` it follows
the bounded D-018 schedule (new revision or missing observation only, at most
once per day per Node, backoff up to 32 days) and saves only hashed schedule
state. A received hint never supplies an identity, observation or active-index
claim.

## Long-absence PHP checkpoint catch-up

The Node peer workflow resumes full signed ancestry over multiple steps instead
of rejecting a gap beyond 32 generations. Each step still permits at most six
network requests and 32 verified ancestors, including cached records. Every
ancestor must match its signed hash, expected Node key and exact predecessor
generation. Partial progress persists the walk position but never advances the
accepted cursor/anchor. Storage quotas, withheld ancestry and a continually changing
remote target can still prevent catch-up; no availability guarantee is inferred.
Tests cover 35 missed generations, cache-only progress boundaries, rollback,
equivocation and broken chunks. The browser now has a separate session-only resumable path with 32 verified
predecessors per explicit refresh; see [browser contract](../browser/client-contract.md). PCP-003 researches alternatives without changing accepted wire
formats or claiming active-index proof.

The `baseline_history_proven` operational flag is versioned as 2. It records
actual genesis-anchored checkpoint history and stays false for late initial
baselines after subsequent valid links. Old unversioned flags at generations
beyond one are conservatively downgraded; they are not trusted as genesis proofs.
This changes operational diagnostics, not signed records or accepted anchors.
