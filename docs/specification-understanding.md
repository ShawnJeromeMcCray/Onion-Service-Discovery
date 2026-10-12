# Specification understanding

Status: implementation baseline, not production approval

Onion Service Discovery enables opt-in public metadata discovery through self-published signed manifests, independent mirrors and a browser-local index. It modifies no Tor code and reads no Tor private keys. It is not a content-safety service, central naming authority, general website crawler or global Sybil solution.

Components: versioned protocol/core, PHP Node/CLI, FilesystemStore and SQLiteStore, offline Service signer and separate Node signer, bounded Tor-only transport, pull sync and candidate scheduler, static Client Host with multi-source validation and local search, independent Python core, formal model, conformance/chaos lab, operations and release tooling.

Trust boundaries: Service-signed metadata; Node-signed claimed observations; locally direct observations; untrusted peer data; executable Client Host; private local pins; offline release trust root. These classes remain distinct throughout storage, export and UI.

Required invariants: no Tor private key access; exact direct host binding; no peer-to-local-observation upgrade; no revision downgrade; preserved forks; proof-required rotation/migration; zero local-query network effects; moderation separate from identity; finite resource use; no Node majority truth; withdrawal separate from outage.

The complete source corpus is read and retained with integrity hashes. ADR-001 resolves inherited obsolete clauses. `docs/requirements.json` extracts candidate normative blocks; manual mapping is needed and extraction alone is not complete traceability. All engineering decisions are draft proposals until their required review exists, except owner-authorized CC0 licensing.

Release gates include exact schemas/bytes and three-language core, formal checks/review, Node/client interoperability, parser/crypto/network/privacy security tests, storage crash/recovery, multi-node chaos, measured scale/low-end behavior, Tor Browser desktop/Android tests, manual accessibility and comprehension, clean installs/upgrades/restores/rollback, locked supply chain/SBOM/provenance and mature update trust, independent review status and final human release approval.

General planning is closed after the concrete baseline is recorded; remaining defaults require measured prototypes, benchmarks and tests. No additional speculative architecture is needed to start implementation. Missing external/human evidence remains a release blocker, not a reason to stop independent implementation work.
