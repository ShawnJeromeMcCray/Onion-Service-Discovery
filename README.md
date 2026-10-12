# Onion Service Discovery

Opt-in decentralized discovery for public Tor Onion Services, with self-published metadata and local browser search.

**Status: experimental offline protocol core. No runnable Node or browser client yet. Not a production release.**

Technical identity evidence must not be presented as an assurance about content or operator honesty. Tor itself is not modified and its private keys must never be accessed.

## Current work

- Import of 50 current specification documents with observed source links, modification times and integrity hashes.
- [Decision register](docs/governance/decision-register.md) preserving all 50 owner/reviewer decisions without inferred approvals.
- [Implementation status](docs/implementation-status.md) distinguishing requirements from verified behavior.
- Strict Onion v3 and bounded JSON parsing in PHP, TypeScript and independent Python.
- [66 shared input vectors](docs/conformance/input-subset.md) and a reproducible three-language conformance subset.
- Dependency-free foundation integrity and credential checks.

Run with Python 3.11 or newer:

```sh
python tools/check_repository.py
python -m unittest discover -s tests -v
```

For the PHP/TypeScript/Python input subset, see [reproduction instructions](docs/conformance/input-subset.md). These checks do not establish full protocol or browser conformance.

## Planned components

- `protocol/`: versioned schemas, limits, exact-byte vectors and independent conformance.
- `node-php/`: PHP reference Node, offline signing, bounded Tor transport, verification, CLI and read-only API.
- `web-local-search-client/`: static local-search client, multi-source validation and explicit origin trust boundary.
- Storage: required FilesystemStore and SQLiteStore behind a backend-neutral contract; server database adapters need independent conformance evidence.
- Formal models, synthetic corpus, chaos lab, operational runbooks and release evidence.

See the [source manifest](docs/specification-manifest.json). Later precise requirements supersede earlier generic descriptions; contradictions need recorded resolution.

## Release conditions

No stable protocol or production claim is authorized by the current repository. Exact protocol bytes, cryptographic lifecycle, browser profile, release trust root and final release need evidence and human reviews. An external audit has not been performed.

## License

Dedicated to the public domain under [CC0-1.0](LICENSE), as authorized by the project owner on 2026-10-12. Use, modification and redistribution are unrestricted to the extent permitted by applicable law. No warranty is provided. Third-party dependencies retain their own licenses.
