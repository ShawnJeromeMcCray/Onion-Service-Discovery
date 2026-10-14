# Offline exact-state snapshot membership

Status: implemented against the snapshot bytes the owner froze as protocol v1
(D-014, D-015; D-051 and D-052 owner-approved). The active-index checkpoint
design and independent review remain open.

The verifier checks the selected Node's signature, every signed chunk hash,
byte/count limits, canonical encoding, global record ordering and every Service
signature. It derives continuity from genesis, including authorized rotation and
migration, before comparing the exact current owner state. Retained historical
revisions cannot satisfy membership of a different current revision. A remote
`listed=true` response cannot satisfy this check.

Run without a network connection:

```sh
node-php/bin/onion-service-discovery index:verify-inclusion \
  checkpoint.json private-chunks/ own-history.json ed25519:SELECTED_NODE_PUBLIC_KEY
```

The final argument is an independently selected canonical Node public key;
`SELECTED_NODE_PUBLIC_KEY` is a placeholder. The chunks directory must be private
(`0700`) and contain every signed descriptor chunk as
`<64-lowercase-hex-digest>.json`. The read-only command does not create missing
chunks, contact peers, open application storage or load a private key.

Owner history is a bounded JSON object `{"records":[...]}` containing one complete
Service lineage and signed rotation/migration records. Its highest non-conflicting
revision is the expected state. Lone non-genesis Manifests and forked/ambiguous
owner histories fail. CLI input is limited to 1 MiB and 256 owner records. The
snapshot is limited to 10,000 records and 32 MiB before chunk reading.

Output includes the genesis logical anchor, current key/key epoch, Onion,
revision, Manifest hash, publication state, Node identity, signed checkpoint
hash/generation/root/time and any exact matching Node-signed observation claim.
An imported observation is never the verifier's own direct check.

Results: `CURRENT_STATE_INCLUDED`, `STALE_REVISION_INCLUDED`,
`CONFLICTING_SERVICE_STATE`, `WITHDRAWN_IN_SNAPSHOT`, `DIFFERENT_SERVICE_STATE`,
`IDENTITY_NOT_PRESENT`. Exit 0 means the current owner state is active in this
particular authenticated snapshot. Exit 6 means a valid snapshot does not show
that exact active state. Invalid/missing artifacts fail with exit 3 or 4.

The command reports `freshness=NOT_EVALUATED` and
`checkpoint_consistency=NOT_EVALUATED`. It must not turn an old snapshot into
fresh network-wide discoverability. It neither supplies the new frozen active
checkpoint type nor replaces ancestry/rollback/equivocation checks. The public
status remains unconfirmed until the complete external workflow passes.

Independent PHP/TypeScript/Python implementations share 24 cases for rotation,
migration, withdrawal, historical/stale membership, omission, forks, missing
continuity, wrong Node identity, tampering, missing/extra chunks, ordering and
pre-read work ceilings. The isolated TLA+ model requires authentic, complete,
exact-current, active evidence; a Node-assertion mutation violates its invariant.
Signature soundness and correct complete-validation/derivation are assumptions,
not proofs of the implementation or freshness.
