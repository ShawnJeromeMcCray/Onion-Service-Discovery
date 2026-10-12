# Controlled developer lab

Install the locked PHP/Node/Python development dependencies as documented in the
repository. Then run `.venv/bin/python lab/run.py --browser` with local PHP,
or add `--php-container` to use the digest-pinned convenience container.
Docker is not required when the supported PHP/extensions are installed locally.
For system Chromium set `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`; otherwise install
the pinned Playwright browser. Add `--formal` for the bounded TLC model checks.

The versioned topology points to deterministic public TEST fixtures. Transport
workers talk only to local mock SOCKS servers, not the synthetic .onion names.
The lab runs protocol, storage/signing, transport/direct/peer sync, privacy/client,
TUF and optional formal scenarios. Test subprocesses use private temporary data,
bounded requests, timeouts and cleanup; there is no persistent lab daemon to
tear down and no production key/data import. A mock proxy is never a production
or live Tor transport implementation. Browser tests freeze synthetic fixture time
so expiration does not accidentally make a deterministic fixture unusable.

Failure scenarios include omissions/rollback/equivocation, invalid final chunks,
service forks, eight process-exit points, aggregate export quota races, unavailable
profile storage and source refresh, and release replay/expiry/threshold failures.
This does not establish physical power-loss/disk-full recovery, packet loss or
live independent Nodes. Multi-host Tor, Android, performance/operational-heavy
soak and operator recovery profiles remain required external/larger-scale gates.
No container network or Tor private keys are accessed by this controlled lab.
