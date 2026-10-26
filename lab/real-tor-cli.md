# Real Tor CLI checks in isolation

These checks exercise the earlier development transport, not the refreshed
Protocol's frozen conformance. Install PHP/locked Composer dependencies first.
With an already installed, trusted Tor executable:

```sh
php lab/tor-cli-disabled-network.php /usr/bin/tor
php lab/tor-cli-isolated-socks.php /usr/bin/tor
```

Run the second check **only inside an isolated network namespace/container with
no external route**, as executed here with Docker `--network none`. It configures
one synthetic loopback-only directory authority and disables default fallback
directories. It is not a public Tor bootstrap or an Onion success test.

For this Debian 13 workspace the authenticated Tor package was extracted under
`.tools/tor-apt/runtime` without installing system services. The existing pinned
PHP container uses the Tor executable and host Debian shared libraries through
read-only mounts; only the Tor child gets the optional library-path argument.
This host-library arrangement is development evidence for this host, not a
portable release runtime image. An installed native Tor needs no library override.

```sh
docker run --rm --network none \
  --mount type=bind,src="$PWD",dst=/app,readonly \
  --mount type=bind,src="$PWD/.tools/tor-apt/runtime/usr/bin/tor",dst=/tor-bin/tor,readonly \
  --mount type=bind,src=/lib/x86_64-linux-gnu,dst=/tor-host-libs,readonly \
  --workdir /app \
  php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3 \
  php lab/tor-cli-isolated-socks.php /tor-bin/tor /tor-host-libs
```

Replace the script name with `tor-cli-disabled-network.php` for the first mode.
The first mode disables all non-control connections, including SOCKS listeners.
The second confirms a real SOCKS Onion stream through controller events, then
checks bounded failure with no consensus and no application-state mutation.
Neither creates an Onion Service or accesses a Tor Hidden Service private key.
All fixture state is temporary; no daemon remains after cleanup.

A Tor CLI can perform future real Onion fetch and peer-sync tests without Tor
Browser once approved network access exists. Browser privacy/rendering/mobile
checks remain separate. Do not relabel these isolated tests as successful live
Tor or actual Tor Browser/Android evidence.

## Successful private Onion application traffic

The continuation tested Tor 0.4.9.11 from Debian's signed APT metadata (package
SHA-256 `2381888dc083316fa59a675434decdf94e3869c34eb1e17a6b54fc9d5a041b98`),
with reviewed upstream Chutney commit
`ae3a33c63a10ab29453225242c9c5985e6f2cf1c` and locked PHP 8.5 image above.
The private `hs-v3-min` network has four authorities, three relays, one client
and one v3 Onion Service. Its virtual HTTP port is changed from Chutney's test
5858 to 80, forwarded to a loopback-only temporary HTTP server on 4747.
Run the checked-in driver inside Docker `--network none`:

```sh
CHUTNEY_PATH=/path/to/reviewed/chutney \
CHUTNEY_TOR=/path/to/trusted/tor \
CHUTNEY_TOR_GENCERT=/path/to/trusted/tor-gencert \
sh lab/private-tor-network.sh
```

Provision trusted Tor, Chutney and its reviewed Python dependencies before
entering the isolated container. `PYTHON` can select Chutney's dedicated Python
environment. The driver uses a fresh private temporary network, finite bootstrap
windows, loopback listeners and cleanup traps. These are lab dependencies, not
new application runtime dependencies. Do not run this private topology as a
public relay network. The Docker network namespace is the isolation boundary;
the additional route checks are defense in depth, not an alternative to it.

`private-tor-onion.php` reads only Tor's **public hostname**, never its private
key. It creates separate temporary Service/Node keys and tests successful signed
manifest retrieval, a bounded Tor POST hint through the production HTTP entrypoint
(with no signed evidence or observation immediately afterward), transport-backed
direct observation, a pinned complete signed
snapshot, exact owner-state membership and PeerSync on both backends. Imported
observations never become local observations. Application and Tor fixture state
are removed on exit. No production root or live seed is created.

See [executed private-network report](../docs/conformance/private-tor-onion-report.json).
It proves successful application traffic through real local Tor circuits, not
public Tor access or production deployment. This workspace's public TCP grants
remain empty; no unsupported tunnel or network-policy bypass was used.

## Actual Tor Browser local functional checks

Official Tor Browser 15.0.25 Linux x86_64 and matching geckodriver were fetched
from `https://dist.torproject.org/torbrowser/15.0.25/`. Both detached signatures
were verified against the Tor Browser Developers primary fingerprint
`EF6E286DDA85EA2A4BA7DE684E2C6E8793298290`, independently matched against Tor's
[verification instructions](https://support.torproject.org/tor-browser/getting-started/verifying-tor-browser/).
The exact browser digest is in the [report](../docs/conformance/tor-browser-local-report.json).
No unverified archive was executed.

```sh
python lab/tor-browser-local.py \
  --browser /path/to/verified/tor-browser/Browser/firefox \
  --driver /path/to/verified/geckodriver
```

The executed run used Docker `--network none`, a temporary WebDriver profile,
headless actual Tor Browser, a loopback-only HTTP harness, disabled external Tor
launcher and direct **local** connections. It verifies the real browser renders
the app, its worker validates signed demo records, search creates no additional
HTTP requests, synthetic content has no active visit/media links and pins work.
It does not test public Onion navigation, the installed user's profile,
Standard/Safer/Safest privacy behavior, mobile UI or Android. WebDriver preferences
are test harness choices and must not be copied into deployment browser guidance.

## Optional real private-Onion Tor Browser integration

The private-network driver can additionally run `tor-browser-private-onion.py`
with already signature-verified Tor Browser/geckodriver executables and a trusted
Python environment. Set these variables inside the isolated container before
running `private-tor-network.sh`:

```sh
export OSD_PRIVATE_TOR_BROWSER_BINARY=/path/to/verified/Browser/firefox
export OSD_PRIVATE_TOR_BROWSER_DRIVER=/path/to/verified/geckodriver
export OSD_PRIVATE_TOR_BROWSER_PYTHON=/path/to/python
```

This opts the temporary fixture into the combined role and prepared NoJS search.
The lab explicitly serves a direct-source browser configuration to avoid a
self-relay deadlock in PHP’s single-worker development HTTP server. This config
override is part of the lab, not a production PHP-FPM deployment claim. The
browser uses the private Tor SOCKS client with remote hostname resolution.
The normal-script workflow explicitly acknowledges the one-Node coverage limit,
binds the expected Node key, verifies signed records in the worker, searches
locally without a new HTTP request, and pins the identity. A separate JavaScript-
disabled session exercises the disclosed HTML POST form over the same Onion
address. It uses only W3C DOM operations in that session. The driver verifies
query exclusion from URL/response/reference logs and absence of cookies.

These are headless private-network functional checks. Public Tor, ordinary
Standard/Safer/Safest desktop settings, Android, deployed process isolation and
independent privacy/accessibility reviews remain separate gates.
