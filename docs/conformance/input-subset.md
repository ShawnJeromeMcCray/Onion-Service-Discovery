# Draft input conformance subset

The harness checks the executable development implementation, not a frozen or
production-approved protocol. It includes 36 build, parser, signature, identity,
configuration, publisher, storage/migration, transport, sync and browser-algorithm
check groups. Controlled SOCKS fixtures are not live Tor evidence.

## Shared vectors

202 black-box cases ran independently against PHP, TypeScript and Python for
this report (CI now runs 248, including the PCP-004 history cases),
including strict Onion/JSON validation, six signed record types, canonical bytes,
Ed25519 edge cases, draft category/language rules and identity history. Fixture
hashes and per-case outcomes are recorded in core-report.json. Valid parsed JSON
is compared with golden values. No synthetic address is contacted publicly.

## Concrete portability decisions requiring protocol review

- Signed wire addresses are rejected if uppercase; no silent rewriting of signed fields. User-entered configuration normalization can be a separate boundary.
- Negative-zero numeric tokens are rejected in the experimental integer-only profile.
- NUL-containing object member names are rejected across implementations. PHP cannot represent NUL-prefixed stdClass properties; rejecting rather than silently rewriting preserves portable semantics. This does not reject escaped NUL in every generic string; record schemas still need their own content rules.
- Compound-invalid input can produce different first-error classifications. The shared corpus currently fixes inputs with one primary violation; exhaustive parser ambiguity/fuzzing remains open.
- The browser bundles pinned mature cryptographic providers. Controlled Chromium evidence does not establish supported Tor Browser desktop/Android compatibility.

These are explicit proposals, not approved protocol-freeze decisions.

## Reproduce

Python 3.11+ and Node 24+ are used for this experimental harness. TypeScript 7.0.2 is locked as a development-only dependency, with pinned bundled browser cryptographic dependencies. PHP tests use a pinned official PHP CLI container digest; Docker is test tooling, not a deployment requirement.

```sh
cd web-local-search-client
npm ci --ignore-scripts
cd ..
docker pull php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3
python tools/run_input_conformance.py --php-container --report docs/conformance/input-subset-report.json
```

Without Docker, an installed compatible PHP CLI can run `python tools/run_input_conformance.py`. Missing tools fail the harness rather than count as a skipped pass.

Actual PHP/runtime support-matrix approval, static analysis, full dependency audit, fuzz campaigns and current Tor Browser desktop/Android testing remain outstanding.
