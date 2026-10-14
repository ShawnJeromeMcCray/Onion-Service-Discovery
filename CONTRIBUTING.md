# Contributing

For a first look, run the [local demo](docs/getting-started/quickstart.md). For implementation work, start with the [source manifest](docs/specification-manifest.json) and [implementation status](docs/implementation-status.md). Keep changes small and reviewable. Requirements are not satisfied by placeholders or disabled checks.

Python 3.11+ runs repository checks. The experimental harness uses Node 24+, locked TypeScript 7.0.2 and a digest-pinned PHP 8.5 CLI image. See docs/conformance/input-subset.md. Production support-matrix review remains open.

Protocol changes require exact bytes, version impact, limits and positive/negative vectors. Security fixes require regression tests. Storage changes require crash/recovery evidence. Browser network changes require privacy/origin/CSP review.

Never commit credentials or private application/Tor/release keys. Use runtime secret bindings outside the repository. Public fixtures must be deterministic and synthetic.

Do not approve decisions on behalf of human reviewers. Foundation checks are not protocol conformance or production approval.

## Check a change

Install locked development dependencies as described in
[the input-suite reproduction guide](docs/conformance/input-subset.md). The
ordinary runtime Composer installation intentionally excludes PHPStan.

```sh
npm --prefix web-local-search-client run check
npm --prefix web-local-search-client test
npm --prefix web-local-search-client run build
npm --prefix web-local-search-client run test:browser
node web-local-search-client/tools/security-lint.mjs
.venv/bin/python tools/check_repository.py
```

The demo server test builds its own default assets; browser tests require the
explicit build above. Install the pinned Playwright Chromium for browser tests,
or configure the documented system executable. For PHP/signing/storage/transport
changes run the appropriate named checks and the full input suite. Candidate
packaging tests include fresh-copy Publisher/Node/Client Host setup.

Documentation changes should preserve valid relative links, exact CLI arguments,
expected results and the distinction between synthetic/private-lab evidence and
public deployment. Use deterministic public TEST identities in fixtures.

Report ordinary problems using [SUPPORT.md](SUPPORT.md); use the private route in
[SECURITY.md](SECURITY.md) for sensitive findings. Contributions must not silently
turn unapproved protocol decisions or unobserved platforms into passing gates.
