# Configuration reference

One JSON file configures every role. Keep it outside the web root (the loader
refuses `PRIVATE_PATH_IN_WEBROOT`), owner-private, and point the web runtime at
it with `ONION_SERVICE_DISCOVERY_CONFIG`. It contains no passwords or keys.
Validate every change with `config:validate CONFIG`. Unknown keys, missing keys
and wrong types are rejected (`CONFIGURATION_INVALID`); the only keys that may be
absent are the two later additions marked *optional*, which then default to
`false`. Examples: [`example.json`](../../node-php/config/example.json) (Client
Host), [`node.example.json`](../../node-php/config/node.example.json) and
[`combined.example.json`](../../node-php/config/combined.example.json);
`init CONFIG ONION [--publish]` writes a publisher file.

`node-php/tests/configuration-reference.php` fails if a key in the example files
is missing from this page.

## Keys

| Key | Type and limits | Meaning |
| --- | --- | --- |
| `configuration_version` | `1` | Format version of this file. |
| `role` | `publisher`, `node`, `client-host` or `combined` | What this installation does; see role rules below. |
| `storage.backend` | `filesystem` or `sqlite` | Store for a Node's signed records and operational state. |
| `storage.path` | absolute path, at most 1024 bytes, no `.`/`..` segments | Store location. Required by the format for every role; publishers and Client Hosts never create it. |
| `export_path` | absolute path | Directory for public snapshot files and the request-budget files. |
| `node.onion` | canonical v3 Onion or `null` | This installation's own Onion: the published Service (publisher) or the Node address (node, combined). |
| `node.key_path` | absolute path or `null` | Encrypted Node key file (node, combined). Never a Service key or a Tor key. |
| `features.publish_service` | boolean | Serve this installation's signed Manifest as an opted-in Service. |
| `features.mirror_directory` | boolean | Serve signed directory snapshots under `/onion-service-discovery/v1/`. |
| `features.discover_services` | boolean | Directly check admitted candidate Services over Tor. |
| `features.sync_with_peers` | boolean | Import signed snapshots from explicitly pinned peers. |
| `features.server_search` | boolean | Optional no-JavaScript server search; queries are sent to this Node ([contract](server-search.md)). |
| `features.accept_announcements` | boolean, *optional* | Accept bounded candidate hints ([PCP-002](../protocol/proposals/PCP-002-bounded-announcement-draft.md)); requires `discover_services`. |
| `features.visitor_sources` | boolean, *optional* | Client Host relays Nodes that visitors add themselves ([details](../getting-started/installation.md#let-visitors-add-their-own-nodes-optional)). |
| `transport.proxy_host` | `127.0.0.1` or `::1` | Loopback Tor SOCKS listener. Every outbound request uses it; there is no direct fallback. |
| `transport.proxy_port` | 1–65535 | Tor SOCKS port, normally 9050. |
| `transport.timeout_ms` | 100–60000 | Per-request time limit. |
| `sources` | up to 16 distinct canonical v3 Onions | Nodes a Client Host or combined installation offers and relays. |
| `allowed_client_origins` | up to 16 `http://<v3 onion>` origins | Client Hosts allowed to read this Node's data cross-origin (direct-fetch profile only). |

## Role rules

| Role | `node.onion` | `node.key_path` | Features that may be `true` |
| --- | --- | --- | --- |
| `publisher` | required | `null` | `publish_service` only; `allowed_client_origins` must be empty |
| `node` | required | required | all except `visitor_sources` |
| `combined` | required | required | all |
| `client-host` | `null` | `null` | `visitor_sources` only |

`accept_announcements` needs `discover_services` and the node or combined role;
`server_search` needs the node or combined role. Violations fail with
`ROLE_CONFIGURATION_INVALID`.
