# Explicit server-side search — development profile

This optional Node/combined-role feature defaults to `server_search=false`.
It is an explicit alternative to local browser search. The page displays:

> Server-side search: your search terms are sent to this Onion Service Discovery Node. The local-search privacy property does not apply in this mode.

The Node controls the HTML and its index claims. The browser does not independently
verify these results. An empty result does not prove absence. Never treat this
profile as a substitute for client-side verification or independent Node review.

## Preparation and recovery

Set `server_search=true` in the private configuration and publish with
`node:snapshot CONFIG --passphrase-file PRIVATE_FILE`. This prepares the bounded
search cache from verified immutable records alongside the signed snapshot.
GET `/onion-service-discovery/search/` serves the form; POST
`/onion-service-discovery/search/server` performs search. There is no automatic
fallback or query transfer from the local-search field. A separate recovery link
also remains available when browser scripting or verifier startup fails.

Missing, stale or corrupted cache data yields 503 without partial results.
`doctor CONFIG` checks the prepared cache without repairing or signing it.
An operator can reconstruct derived exports explicitly using
`node:snapshot CONFIG --passphrase-file PRIVATE_FILE --rebuild-derived`.
This verifies source records, publishes a new snapshot generation and atomically
replaces reconstructed export bytes; it does not change immutable signed records.
Stop workers before `exports:prune CONFIG --workers-stopped`.

## Fixed semantics and limits

Browser and PHP search use the same generated Unicode 17 NFKC/default lowercase
tables and shared ranking fixtures. They do not use locale-dependent collation.
Literal whitespace-separated terms must all match; there are at most 16 terms.
Filters cover category, lowercase language tag and adult visibility. Ordering is
raw name in UTF-16 code-unit order, then stable genesis ID. Node claim counts
do not determine trust or ranking. Withdrawal and unresolved fork handling match
the local presentation model. Signed bytes are never normalized for verification.

Requests accept only four form fields: required `query`, optional `language`,
`category` and `adult`. Duplicate fields, invalid UTF-8/percent escapes and URL
queries are rejected. Bounds are 8,192 body bytes, 256 query UTF-16 code units,
10,000 prepared rows, 16 MiB cache, 500 ms wall time, 50 results and 262 KiB HTML.
The shared HTTP bucket limits requests to a burst of 20 and two per second.
The index timestamp is a Node claim, not proof of current Service presence.

Queries neither mutate the record store nor start transport, signing, discovery
or synchronization. The response omits the submitted query, uses no cookies,
and sets no-store, no-referrer and restrictive CSP headers. Metadata is escaped
text; forks have no visit link. The reference application does not log queries.
Deployment proxies and monitoring must also avoid request-body capture.

## Deployment boundary

The staging PHP-FPM template does not establish signer isolation. Before deployed
use, isolate the web process from signing keys, passphrase files and outbound
network access; provide only the read access required for the prepared index and
the private local HTTP budget. Apply an 8 KiB request-body ceiling, finite PHP-FPM
worker/concurrency/time/memory limits, and disable access/body logging on these
routes. Optional staging snippets are in
`deployment/nginx/server-search-location.conf.example` and
`deployment/php-fpm-search-pool.conf.example`; these have not been validated on
a deployed Nginx/FPM host. Keep signing and periodic discovery in separate worker processes.
The application deadline is a checked wall-time budget, not an OS CPU sandbox.

Reports: [PHP 8.5](../conformance/nojs-search-report.json),
[PHP 8.4](../conformance/nojs-search-php84-report.json),
[actual headless NoJS Tor Browser](../conformance/tor-browser-nojs-report.json),
[signed synthetic S1000 measurement](../performance/s1000-server-search-report.json).
Public Tor, deployed isolation, physical recovery, accessibility and independent
security/privacy reviews remain unverified. Unicode data licensing and exact
source hashes are recorded in `THIRD_PARTY_NOTICES.md` and the generated provenance.
