# Development client contract

Build with `npm ci --ignore-scripts && npm run build` in
`web-local-search-client`. The default static build has no source Nodes and
provides a clearly synthetic offline demonstration. It contains no production
seeds. Use `npm run build -- --config <public-config-file>` to supply a static
allowlist. All libraries are bundled; there are no CDN/runtime module imports.
The build manifest records file sizes and SHA-256 hashes. Retain the bundled
third-party legal notices.

The reference PHP Client Host serves `/onion-service-discovery/`, a public
configuration with its explicit source allowlist, and a query-independent relay.
It requires no Node or Service signing key. Its relay accepts only generic
node/state/snapshot/chunk GET/HEAD requests to configured Tor v3 Nodes. The
server's single Tor-only adapter performs those requests. Search terms, pins,
credentials, client IDs and Service URLs are not relay inputs. With the optional
`visitor_sources` Client Host switch, the public configuration carries
`"visitor_sources": true` and the relay also accepts any checksum-valid canonical
v3 Onion on the same four fixed paths, under a separate request budget. The
worker admits a visitor-chosen Node only together with the exact expected key the
visitor entered (at most 8), so the loaded Node descriptor must match that key.
Visitor-chosen Nodes are kept in session memory unless the visitor opts into
profile storage; Forget local data removes them. This same-origin
reference profile keeps browser CORS closed. A static direct-cross-origin
profile is separately supported with exact source origins in its CSP; each
Data Node must explicitly permit the Client Host origin (see
[static self-hosting](#static-self-hosting)). Real Tor Browser
behavior for both profiles remains a release gate.

The page's search/filter operations run locally. No query appears in a request,
URL, worker command or saved profile. Loading a source downloads generic bounded
signed chunks, verifies all records and completes the source snapshot before
adding Service evidence to the local union. A failed source deletes nothing; a
successful reload of a Node replaces only the records that Node alone supplied
before, so records another source or a local file also supplied stay. Records of
a Node set aside for conflicting signed data stay marked as such until that Node
loads successfully again or the index is cleared. Checkpoint and descriptor
rollback/equivocation and Node key changes are rejected; fork warnings cannot be cleared by source counts. Several Node
keys do not establish independent operators or global completeness.

Metadata uses text-only DOM operations and isolated ASCII onion/key displays.
There are no Service images, automatic Service visits, click reports or
analytics. User navigation uses validated onion addresses and no referrer.
Synthetic demonstration addresses cannot be opened through the result links.
The page explicitly states the Client Host code-origin trust boundary and the
weaker combined-host mode. Signatures do not prove a person's identity or safety.

Look-alike names (decision D-024) are compared by UTS #39 confusable skeletons
from frozen Unicode 17 data (`confusables.txt` prototypes, the client's own NFD,
Default_Ignorable_Code_Point removed), never by the browser's Unicode version. A
result whose name looks like the label or current name of a pinned identity
under another key gets a warning; published entries whose names look like each
other under different keys each get a note, as do pins with look-alike labels
and imported pins imitating a held one. The comparison only adds notices: names
are shown exactly as signed and are never rewritten, hidden, merged or
reordered by it. The optional server search does not run it.

Pins remember a genesis key, onion context, anchor/latest hashes, high-water
revision and sticky conflict state. Authorized rotations/migrations preserve the
logical root; a bare key replacement never does. Profile persistence is opt-in
and best effort. Memory mode works if storage is unavailable. Pin backups are
local downloads/imports with a concrete review screen; they can reveal interests.
No remote pin sync exists. Clearing index data preserves pins/witnesses; an
explicit separate confirmation is required to forget continuity safeguards.

Default limits: 16 allowlisted sources and 8 visitor-added Nodes, 10,000 local
evidence records across all sources (a reloaded Node replaces the records only it
supplied before), 64 chunks and 32 MiB per source, 90 seconds of network time per
source plus one second per 20 KB of declared chunk bytes (at most 30 minutes, which
covers the 32 MiB maximum at 20 KB/s), 15
seconds per fetch plus one second per 20 KB of the response limit, 256 pins, a
256-character local query with at most 16 terms, 1 MiB per imported record bundle
(up to 10,000 records in its list) and 256 KiB per pin backup. Loading demo data
replaces real records and importing real records replaces demo data. Results are not
capped: matching identities with the same normalized name, description and state
form one group, groups are shown 25 per page with previous and next buttons, and
a group's similar entries are paged 25 at a time. Signature checking is not
timed: the record and byte limits bound it, each source shows how many records
it has checked, and Cancel takes effect within 64 records. A record already
verified in this session, for example one a second Node serves too, is not
checked again; any differing byte is checked in full. Resource refusal preserves
the prior index. These are implementation profile limits, not a claim of unlimited
network coverage. Node statements are displayed as claims; this browser does not
perform a direct Service check or establish liveness.

Controlled Chromium tests check three-source loading, local query network
invariance, text-only hostile metadata, withdrawal filtering, local pin restore,
explicit forgetting and unavailable-storage memory fallback. They are not Tor
Browser Desktop/Android or independent accessibility/security UX approval.
Those reviews and the external audit remain OPEN.

## Supported browsers (D-003)

Owner decision D-003 (2026-10-22) sets the supported baseline:

| Browser | Security level | Support |
| --- | --- | --- |
| Current stable Tor Browser, desktop | Standard, Safer | Full local search, loading, pins and verification |
| Current stable Tor Browser for Android | Standard, Safer | Full local search, loading, pins and verification |
| Current Firefox and Chromium | Default | Full; the automated browser tests run in Chromium |
| Any Tor Browser | Safest (JavaScript off) | No local search. The page explains this; the optional NoJS server search, if the host offers it, is the documented fallback |

The build targets Firefox ESR 128 language features (`firefox128`), so the
Firefox ESR line that the current Tor Browser is based on is covered. Older Tor
Browser or ESR releases are not promised. At Safer, scripts run without a JIT:
large indexes take longer, and the client stays responsive by rebuilding results
only when the data changes. The page uses no WebAssembly, no `eval`, no inline
script or style and only same-origin workers, so neither Safer nor the strict CSP
disables a function. Real-device checks on Tor Browser desktop and Android remain
a release gate (`ACTUAL_TOR_BROWSER_LOCAL_FUNCTIONS` covers the desktop profile).

## Static self-hosting

`dist/` can be served without PHP. Build it with a configuration of exactly five
keys: `"client_config_version": 1`, `"mode": "client-host"`,
`"delivery_profile": "direct-cross-origin"`, `"minimum_distinct_keys": 3` and the
Node Onions in `allowed_sources`. The build writes `http://NODE.onion` for each
source into the page's meta CSP `connect-src`.

The verified way to get these files is the signed static client bundle of a
merge to `main` ([D-028](../operations/release-candidate.md#static-client-bundle-d-028)):
rebuild with your configuration from the same commit and compare `client.js`,
`verifier-worker.js` and `client.css` with the bundle's `build-manifest.json`.

Serve it with [nginx](../../deployment/nginx/static-client.conf.example) or
[Apache](../../deployment/apache/static-client.conf.example). Both examples
serve the six files the PHP host serves, at the same paths, for GET and HEAD
only, without directory listing or symbolic links, and send the PHP host's
headers from `node-php/src/Http/ClientAssets.php`: the CSP, `nosniff`,
`no-referrer`, `X-Frame-Options: DENY`, the Permissions-Policy and
`Cache-Control: no-store`. The header CSP adds what a meta tag cannot set:
`frame-ancestors 'none'`.

- **connect-src.** Copy the `connect-src` of the built `dist/index.html` into
  the header. Browsers enforce the header policy as well as the meta policy, so
  a header without the Node origins blocks the data requests.
- **CORS.** Each Data Node must list the static host's origin
  (`http://CLIENT.onion`) in `allowed_client_origins` and enable
  `mirror_directory`. Only then does it answer with
  `Access-Control-Allow-Origin` for that origin.
- **No relay.** A static host has no `/onion-service-discovery/relay/`. The
  `client-host-proxy` profile, visitor-chosen Nodes and the server-search link
  are not available. Visitors' browsers connect to each Data Node over Tor
  themselves: every Data Node sees these generic requests with the static
  host's origin, never the searches or pins. The static host sees only the
  requests for the page's files.

The examples were checked with nginx 1.24.0 and Apache httpd 2.4.58 on a
loopback listener: for all seven client URLs the content types and security
headers matched the PHP host's, the bodies matched `dist/`, and no other file
was served. Real Tor Browser behavior of the static profile is not verified.

## Optional explicit NoJS profile

The local client never transfers its query to the server-search profile. A separate
recovery link is available before verifier startup and without scripting when the
Node explicitly enables the feature. The HTML form discloses query transfer before
submission. Results are Node-controlled claims, without browser verification.
Browser and PHP presentation share frozen Unicode 17 search semantics and golden
ranking fixtures: results are ordered by text relevance (decision D-023: exact
name, name prefix, every term in the name, other matches), never by how many
Nodes list or observe a Service. See [operations contract](../operations/server-search.md).

## Bounded long-absence refresh

A signed checkpoint gap beyond 32 generations can be verified across explicit
refreshes. Each refresh verifies at most 32 predecessor records. A session-only
cache retains at most 16 verified walk cursors, bound to source key, target hash
and accepted anchor. It is not peer-supplied or serialized and is erased with
Clear All Data. PARTIAL means no newer source snapshot has been admitted; another
explicit refresh can continue and existing verified records remain searchable.
Cancelled/timed-out work cannot advance the accepted anchor. A changed target
must establish its own verified prefix before using a cursor.

A late initial baseline remains marked as lacking earlier checkpoint history
even after valid successors. Retrying a failed first import also cannot invent
a genesis proof. These are checkpoint-continuity claims, not independent Node
trust, direct Service presence or the still-unimplemented active-index scheme.
The session witness/storage caps and withheld/changing history remain availability
limits; a browser restart loses its session walk progress.

Signed ancestors and the Node descriptor are compared with session witnesses as
well as the current checkpoint. Conflicting records at the same Node/type/sequence
position (two checkpoints of one generation, or two descriptors of one revision)
quarantine the Node, preserve the accepted anchor, stay sticky for the session
and can be saved as evidence. The session witness registry retains at most 512
ordinary records plus a reserved signed fork pair for at most 16 Node keys (at
most 528 distinct records). After each successful load, a Node's ordinary
witnesses below its accepted checkpoint and descriptor are dropped, as the PHP
Node's local maintenance does: they no longer detect anything, while fork pairs
are never evicted. Saturation (for example a very long history walk) fails closed
with `WITNESS_BUDGET_EXHAUSTED`. Clear All Data explicitly erases these witnesses. This is a
bounded session policy, not unlimited history retention or automatic fork discovery.
