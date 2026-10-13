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
