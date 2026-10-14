# Install and choose a deployment

Start with the [local demo](quickstart.md) if you want to explore the interface.
The following instructions provision a **development/staging installation** on
64-bit Linux. They are not a Production Stable release or a certified OS matrix.
A public installation needs the remaining [release gates](project-status.md).

## Runtime prerequisites

| Component | Requirement | When needed |
| --- | --- | --- |
| PHP | 64-bit PHP 8.4+, JSON, sodium, iconv, curl | Publisher, Node and Client Host |
| PDO SQLite | PHP SQLite driver | SQLite backend only |
| Composer | Composer 2; respects committed lock | Install PHP runtime dependencies from source; not needed with the development runtime package |
| Node.js/npm | Node 24+ and bundled npm | Build browser assets from source; local demo; not needed with the development runtime package |
| Tor | Existing v3 Onion Service and loopback SOCKS listener | Real Onion hosting / outbound Node and relay requests |
| Web server | PHP-FPM plus reviewed routing: nginx or Apache httpd 2.4 templates | Persistent staging web hosting |
| Python | 3.11+ and locked virtual environment | Developer validators; not ordinary PHP operation |

Executed reference versions include PHP 8.4.26/8.5.11, Node 24.19.0 and Tor
0.4.9.11. These are evidence points, not a guarantee for every Linux distribution.
Ubuntu 24.04 LTS ships PHP 8.3, which this project does not support; use a
distribution release that ships PHP 8.4 or newer (for example Debian 13) or a
PHP build from a source you trust.
Install supported packages through your OS package manager. Distribution package
names and FPM socket locations differ. Check PHP before provisioning:

```sh
php -r 'foreach (["json","sodium","iconv","curl"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR,"Missing extension: $e\n"); exit(1); } } if (PHP_INT_SIZE !== 8 || PHP_VERSION_ID < 80400) { fwrite(STDERR,"64-bit PHP 8.4+ required, found ".PHP_VERSION."\n"); exit(1); } echo "PHP runtime prerequisites present\n";'
composer --version   # only when you build from source
```

For SQLite, also check `php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'`.

## Get and provision the current source

```sh
git clone https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery.git
cd Onion-Service-Discovery
git rev-parse HEAD
composer --working-dir=node-php install --no-dev --no-plugins --no-scripts --no-interaction --prefer-dist
npm --prefix web-local-search-client ci --ignore-scripts
npm --prefix web-local-search-client run build
php node-php/bin/onion-service-discovery help
```

Expect JSON help with `status: DEVELOPMENT`. Record the commit you inspected.

Alternatively, every merge to `main` produces an unsigned **development runtime
package** with the PHP dependencies and the built browser client already
included, so Composer and npm are not needed; PHP 8.4+ still is. Download it,
verify its GitHub build provenance and extract it as described in
[development runtime package](../operations/release-candidate.md#development-runtime-package-d-029),
then continue below from the extracted `onion-service-discovery` directory.
The runtime Composer dependencies are locked; development dependencies are
excluded here. Source builds require dependency downloads. The browser's default
configuration contains zero approved sources.

An unsigned development checkout is not an authenticated stable release. When a
production release and independently authenticated trust root exist, follow the
[release verification procedure](../../release-tools/README.md) before execution.
The development [source/browser package](../operations/release-candidate.md)
contains built browser assets; it still needs its PHP Composer runtime provisioned.
The development runtime package also contains that runtime.

## Files and permissions

Keep application code outside operational data. Serve **only `node-php/public`**
through its front controller. Store configuration, data, export blobs, Node keys
and passphrase files outside that public directory. Private directories require
0700 and private files 0600; use a dedicated service user and `umask 077`.
The Service identity key belongs on a separate offline administrator machine.

Copy only Tor's **public `hostname`** to a suitable operator-controlled file when
needed. OSD does not need Tor's private keys or access to HiddenServiceDir. Keep
that directory inaccessible to application processes. Configure your existing Tor
service to forward public port 80 to the reviewed loopback web listener.

The basic staging FPM pool shares the Node worker UID for private-store access.
It does not isolate the Node key from a compromised web process. Full deployed
signer/storage isolation remains a production requirement.

## Choose a role

### Publisher

Publish signed metadata for an existing Onion. No Discovery Node database or
Node key is required. Follow the [complete Publisher walkthrough](publisher.md).
The local status page cannot independently certify that another Node indexes you.

### Discovery Node

Collect public evidence, verify services through Tor and publish signed directory
snapshots. Follow the [Node walkthrough](node.md). Start with the filesystem
backend unless you deliberately select and provision SQLite.

### Client Host

Host the browser client and a query-independent Tor relay to selected Nodes.
This role needs no Service or Node signing key. From the repository root:

```sh
umask 077
mkdir -p /absolute/private/osd-client
php node-php/bin/onion-service-discovery init /absolute/private/osd-client/config.json --role client-host --source FIRST_NODE.onion --source SECOND_NODE.onion
```

Replace `/absolute/private/osd-client` with a real absolute owner-private path
and the sources with independently chosen Node addresses (up to 16, or none).
Add `--visitor-sources` to let visitors add their own Nodes (see below). `init`
refuses to overwrite a file. The generated file follows these rules, explained
in the [configuration reference](../operations/configuration.md):

- Keep `role` as `client-host`, `node.onion` and `node.key_path` null, and all features false except, optionally, `visitor_sources` (see below).
- Set `export_path` to `/absolute/private/osd-client/exports` and the unused storage path to `/absolute/private/osd-client/data`.
- Put independently chosen canonical v3 Node addresses in `sources` (maximum 16).
- Set the actual loopback Tor SOCKS port, normally 9050. Tor Browser's separate SOCKS listener is not automatically a persistent server runtime.
- Leave `allowed_client_origins` empty for the same-origin relay deployment.

Then run:

```sh
php node-php/bin/onion-service-discovery config:validate /absolute/private/osd-client/config.json
php node-php/bin/onion-service-discovery client:init /absolute/private/osd-client/config.json
```

Expect `PASS`, then `INITIALIZED` with `signing_keys_required: false`.
For a loopback staging check:

```sh
ONION_SERVICE_DISCOVERY_CONFIG=/absolute/private/osd-client/config.json php -S 127.0.0.1:8080 node-php/public/index.php
```

Open `http://127.0.0.1:8080/onion-service-discovery/`. The PHP development server
is a single-worker local staging tool; do not use it for public operation or a
combined self-relaying Node. Persistent hosting uses the reviewed FPM/nginx path.
Sources are selected in the UI; enter independently obtained expected Node keys
before loading. The default minimum is three distinct signing keys, not three
proven independent operators. Fewer sources require explicit limited coverage.

An empty source list still allows the synthetic demo but provides no live results.
No seed addresses or approval are silently supplied by initialization.

#### Let visitors add their own Nodes (optional)

Set `"visitor_sources": true` in `features` to let visitors load Nodes you did
not list. Visitors then see **Add a Node you chose**, enter the Node's onion
address and its expected public key (both required), and load it like a listed
source. The browser verifies everything exactly as for your own sources; a wrong
key quarantines that source. Visitors may add up to 8 Nodes and keep them only
in their own browser profile if they choose to.

Consequences for you as host: your relay then fetches the four fixed public
protocol paths (node, state, snapshot, chunk) from any valid v3 Onion a visitor
names, through your Tor client. These requests use their own budget (burst 64,
one per second) so they cannot starve your listed sources, and `doctor` keeps
showing `VISITOR_SOURCE_RELAY_ENABLED_OUTBOUND_TO_VISITOR_CHOSEN_NODES` as a
reminder. You learn which Nodes visitors add, never their searches or pins.
Leave it off if your Tor client must not contact visitor-chosen destinations.

## Web serving and operations

Adapt [nginx routing](../../deployment/nginx/onion-service-discovery.conf) or the
[Apache httpd equivalent](../../deployment/apache/onion-service-discovery.conf) and
[the FPM pool](../../deployment/php-fpm-pool.conf) to your service user, paths,
configuration and socket. These files are templates, not enabled services.
Disable request/query logging and expose only the front controller. Configure
Tor and startup scheduling using your OS's service manager. See
[deployment, schedules, backup, upgrade and removal](../operations/deployment-and-recovery.md).

The optional [NoJS search deployment](../operations/server-search.md) has a
separate request-body/rate-limit/privacy contract. Do not enable it by merely
raising limits in the base nginx template.

## Acceptance checks

Before handing a staging installation to someone else, verify:

1. `config:validate` passes; role/path/source values match the intended installation.
2. `doctor` reports applicable local checks accurately. `WARN`/exit 6 can mean unobserved external checks, not verified Tor connectivity.
3. Only public routes and assets are reachable; no configuration/key/data paths are exposed.
4. On the actual Onion, explicit directory loading works with independently selected keys; failed refresh preserves the last verified view.
5. Local queries cause no query-dependent network request; NoJS users see the different privacy disclosure.
6. Backup, restore, worker scheduling and the remaining host-specific checks in the operations guide have been exercised.

Use [troubleshooting](troubleshooting.md) for errors and [current evidence](project-status.md)
for the difference between a successful local check and production certification.
