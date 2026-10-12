# Onion Service Discovery

Opt-in decentralized discovery for public Tor Onion Services, with self-published metadata and local browser search.

**Status: runnable development Node and browser client. Production release reviews, live Tor/Android evidence and independent audit remain open.**

Technical identity evidence must not be presented as an assurance about content or operator honesty. Tor itself is not modified and its private keys must never be accessed.

## Current work

- Import of 50 current specification documents with observed source links, modification times and integrity hashes.
- [Decision register](docs/governance/decision-register.md) preserving all 50 owner/reviewer decisions without inferred approvals.
- [Implementation status](docs/implementation-status.md) distinguishing requirements from verified behavior.
- Strict Onion v3, bounded JSON, canonical bytes, signed-record schemas and Ed25519 verification in PHP, TypeScript and independent Python.
- Deterministic evidence graphs for forks, exact revision chains, countersigned rotation and signed migration.
- [133 black-box core cases per language](docs/conformance/core-report.json), with zero failures/skips in the current offline draft suite.
- Required FilesystemStore plus SQLiteStore, shared transaction/restore tests and eight process-exit fault points.
- Encrypted offline Service signing, role-separated Node signing, manifest revision/withdrawal/rotation/migration and atomic public installation.
- Bounded Tor-only transport, direct verification, resumable peer snapshots and read-only protocol API.
- Bundled local-search client, query-independent Client Host relay, explicit source allowlist and origin-local pins.
- Finite TLA+ models, PHPStan Level 8 and controlled browser/privacy tests.

After installing the hash-locked validator dependencies in a virtual environment:

```sh
python tools/check_repository.py
.venv/bin/python -m unittest discover -s tests -v
```

For the PHP/TypeScript/Python input subset, see [reproduction instructions](docs/conformance/input-subset.md). These checks do not establish full protocol or browser conformance.

## Components

- `protocol/`: versioned schemas, limits, exact-byte vectors and independent conformance.
- `node-php/`: PHP reference Node, offline signing, bounded Tor transport, verification, CLI and read-only API.
- `web-local-search-client/`: static local-search client, multi-source validation and explicit origin trust boundary.
- Storage: required FilesystemStore and SQLiteStore behind a backend-neutral contract; server database adapters need independent conformance evidence.
- Formal models, synthetic corpus, chaos lab, operational runbooks and release evidence.

See the [source manifest](docs/specification-manifest.json). Later precise requirements supersede earlier generic descriptions; contradictions need recorded resolution.

## Running the development build

```sh
cd web-local-search-client
npm ci --ignore-scripts
npm run build
```

The default static client has a clearly synthetic offline demonstration and no production seed Nodes. The PHP Client Host uses a strict configuration and a Tor-only generic relay; it requires no signing keys. See the [browser contract](docs/browser/client-contract.md), [Node/transport contract](docs/transport/contract.md), [offline signing workflow](docs/signing/offline.md) and [storage contract](docs/storage/contract.md). Start with `node-php/bin/onion-service-discovery help`. Serve only `node-php/public` and keep data, configuration and keys outside it. Deployment/operations packaging is still being completed.

## Release conditions

No stable protocol or production claim is authorized by the current repository. Exact protocol bytes, cryptographic lifecycle, browser profile, release trust root and final release need evidence and human reviews. An external audit has not been performed.

## License

Dedicated to the public domain under [CC0-1.0](LICENSE), as authorized by the project owner on 2026-10-12. Use, modification and redistribution are unrestricted to the extent permitted by applicable law. No warranty is provided. Third-party dependencies retain their own licenses.

For the expanded draft core after installing locked dependencies:

```sh
.venv/bin/python protocol-conformance/runner.py --php-container
```

The runner imports no reference implementation code. It compares separate-process outputs with exact shared vectors. See docs/adr/002-integer-json-and-signed-byte-construction.md for the explicit draft byte rules and pending review.
