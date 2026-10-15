# Data flow

Who sends what to whom (specification 16, sections 48 and 57). The
[data inventory](data-inventory.md) lists what each role stores. Every arrow
between hosts runs over Tor. Hosts reach Tor only through their loopback SOCKS
listener, with a fresh isolation parameter per request and no clearnet
fallback.

```text
                         (1) page files, config.json
  Visitor's Tor Browser <------------------------------ Client Host or combined Node
     |   |   |                                                |
     |   |   | (2) generic data requests                      | (2a) relay: node, state,
     |   |   |     (no query, no pins)                        |      snapshot, chunk
     |   |   +----------------------------------------------->|------------------------> Data Nodes
     |   |                                                    |
     |   | (2b) direct-cross-origin profile: the browser fetches the same
     |   |      generic data from the Data Nodes itself -----------------------> Data Nodes
     |   |
     |   | (3) optional server search: POST with the query, after disclosure
     |   +----------------------------------------------------> serving Node (no onward request)
     |
     | (4) explicit click on a result: ordinary navigation, no report to any Node
     +-------------------------------------------------------> result Onion

  Discovery Node --(5) direct check: Manifest, history bundle--------------> Service Onion
  Discovery Node <-(6) peer sync: node, state, snapshots, chunks-----------> peer Nodes
  Publisher -----(7) self-check: own Manifest, history bundle-------------> own Onion
  Publisher -----(8) membership check: state, checkpoints, chunks---------> chosen Nodes
  Publisher -----(9) announcement POST: own Onion only--------------------> chosen Nodes
```

| Flow | Trigger | What is sent | What the receiver learns |
| --- | --- | --- | --- |
| 1 | Opening the page | Requests for fixed file names | That the page was loaded |
| 2, 2a | "Load directory" | Fixed paths with a Node Onion and a content hash; no cookie, referrer or credentials | Client Host: which Node data a visitor loads, and when. Data Node: a request from the Client Host's Tor client |
| 2b | "Load directory" (static or direct profile) | The same paths, sent by the browser to each Data Node, with the host's `Origin` | Each Data Node: that its data was loaded through that host's page |
| 3 | Submitting the disclosed server-search form | The query and filters in a POST body, never in a URL | The serving Node: the query. The reference application neither stores nor logs it and makes no request because of it |
| 4 | An explicit click | Ordinary Tor Browser navigation without a referrer | The result Onion: an ordinary visit |
| 5 | A queued candidate | One fixed GET request, a second one for the history bundle when needed | The Service: an OSD client fetched its public Manifest |
| 6 | The peer timer | Fixed GET requests | The peer: its public data was downloaded |
| 7 | `service:verify`, the publisher timer | Two fixed GET requests | Your own web server: a request through Tor |
| 8 | `network:discoverability-check`, the publisher timer | Fixed GET requests | Each chosen Node: its public data was downloaded |
| 9 | `network:announce`, `--if-due` | `{"protocol_version": 1, "onion": YOUR.onion}` | Each chosen Node: your public Onion and the time |

What never leaves the browser in the reference build: the search query (except
in flow 3), pins, the Nodes a visitor added (except as relay paths in flow 2a)
and the local index. A modified or malicious serving host can replace the page
code and change all of this; multi-source checks cannot prevent that.

Request timing can still form patterns. Loading starts only on an explicit
action and does not depend on the query, but this design makes no claim against
global timing analysis.
