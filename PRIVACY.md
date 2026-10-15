# Privacy

Onion Service Discovery has no central server, accounts, cookies, analytics or
telemetry. This page states the privacy requirements (specification documents
16, 19, 26 and 42) and what the current development implementation does. It is
not a legal statement; operators are responsible for their own obligations.

## What leaves your browser

- Your search terms stay in the browser in the reference client. Loading the
  directory sends only generic requests for signed data, never your query or
  your pins.
- Pins and the Nodes you add stay in this browser. They are saved in the
  browser profile only if you turn that on; Tor Browser deletes them when it
  closes.
- The optional server search is different: it sends your query to the serving
  Node and says so before you submit. The reference application does not store
  or log the query.
- A Client Host that relays data learns which Nodes you load, not your searches
  or pins.

## What is public by design

Everything in a signed Manifest, Node descriptors, Node observations and
snapshots. Independent Nodes can keep copies; nobody can erase them globally.
A withdrawal stops normal listing.

## Requirements and status

| Requirement | Status today |
| --- | --- |
| Local search causes no query-dependent request | Implemented in the reference client; Chromium tests check it. Tor Browser certification is a release gate. |
| Server search: disclosure before submission, POST only, no query in URLs or logs, no query-triggered outbound request | Implemented. Tested with the PHP test server and with a JavaScript-disabled Tor Browser in a local lab ([report](docs/conformance/tor-browser-nojs-report.json)); not on public Tor. |
| No accounts, cookies, analytics, telemetry or remote assets | Implemented: no such code, and a restrictive Content-Security-Policy. |
| Pins and visitor-added Nodes stay local; saving them is opt-in | Implemented. |
| Minimal logs | The deployment templates switch access logs off. Other web server setups are the operator's duty. |
| Tor-only transport without clearnet fallback | Implemented in code. An operating-system egress rule for the web pool is the operator's duty. |
| Data inventory and data flow | [Data inventory](docs/privacy/data-inventory.md) and [data flow](docs/privacy/data-flow.md). |
| Privacy test matrix (specification 16, section 41) | Partial: query network invariance, local pins, forgetting and unavailable storage are tested in Chromium. Tor Browser certification and an independent privacy review are open. |

## Limits

A malicious or compromised serving host can replace the page code and send your
queries anywhere. Self-host a verified build when that matters. Request timing
can still form patterns; no claim is made against global timing analysis.
