# PCP-003 — checkpoint longevity and active-index research

Status: RESEARCH / NOT APPROVED. No production wire format, record schema,
endpoint, signature domain or runtime behavior is changed by this proposal.
D-052 explicitly requires human Protocol/Security review; its genesis hash
representation and exact signed bytes remain unresolved.

## Observed problems

Both browser and PHP peer refresh originally rejected a gap exceeding 32 signed
snapshot generations before the bounded catch-up corrections. This preserves a finite work budget but prevents a pinned
client from recovering after an ordinary sufficiently long absence. Resetting
the accepted anchor would discard continuity protection. Raising the limit alone
would postpone the same failure and increase work. The PHP correction walks the
full existing chain in resumable steps (32 verified ancestors and six network
requests maximum per step), retaining the accepted anchor until complete. It
changes no signed bytes and remains subject to storage quotas and proof availability.
The browser likewise retains a verified session cursor bound to the same source,
target and accepted anchor, with at most 32 predecessor verifications per explicit
refresh. Neither path treats a late first-contact baseline as genesis proof after
a successful follow-up. The compact-proof experiment below is separate. A fresh initial baseline is
explicitly marked as lacking historical proof; it is not genesis verification.

The generic snapshot is a complete evidence export, not the required active index.
Its creation time cannot establish a recent direct Service check. The existing
direct verifier retains the signed observation for an unchanged manifest and
separately updates `local_checked_at`; exporting the old observation under a new
snapshot must not turn that timestamp into a fresh observation claim.

## Candidate separation

Keep immutable owner continuity records and conflict evidence independent of an
active-index representation. A candidate active tuple binds the owner-approved
genesis ID, authorized current key and epoch, canonical onion, current revision,
manifest hash, protocol version and active state. A direct-check time must be an
explicit Node assertion derived only from a successful local transport check of
that exact current manifest. Imported observations cannot establish a local check.
Withdrawn, unresolved-fork, incomplete-continuity and changed-address bindings
must not receive a current active tuple. Historical evidence remains retained.

An eventual signed active-index checkpoint must bind the D-052 fields: Node key,
exact successor sequence, previous checkpoint hash, active root, count, index
format/version and generated time. Root construction, leaf bytes/order, timestamp
semantics, count interpretation, genesis and domain separation need explicit
review and shared adversarial fixtures before an accepted implementation.

## Research experiment: bounded prefix consistency

`tools/experiments/checkpoint_consistency.py` experiments with RFC 6962 tree hashing
and consistency proofs over opaque leaves. Its verifier uses at most 64 hashes
and finite integer sizes; its generator is a local research tool, not a network
or capacity implementation. Finite tests exercise every prefix through 129 leaves,
proof-hash corruption, truncation, extra nodes, divergent histories and malformed
boundaries. This is neither an independent cryptographic review nor a liveness proof.

Such a proof can compare an authenticated previous history commitment with a
later authenticated commitment without downloading every intermediate checkpoint.
It does not prove the exact +1 linkage of undisclosed signed checkpoints. A useful
protocol must decide which statement is required and how chain validation and
authenticated append commitments jointly establish it. A Merkle prefix proof
alone is insufficient to claim full validation of all hidden checkpoint fields.

Committing to the complete currently signed checkpoint inside that same checkpoint
would create a circular hash dependency. A candidate design can commit only to
completed prior checkpoints, with explicit size/sequence binding. The old/current
checkpoint leaf, old/new tree sizes and the previous-hash link then require joint
verification. Exact leaf and signed field definitions remain deliberately absent
until reviewed; this experiment cannot be served as an OSD proof.

Observed incompatible authenticated commitments can establish divergence. A proof
does not discover a malicious Node's hidden second view, establish independent
operators, or authenticate its transport observation. Gossip/witness availability,
freshness policy, withholding, retention and bounded retry scheduling remain
separate requirements.

## Acceptance work still required

1. Freeze concrete signed bytes, leaf commitments and index semantics through the
   required human Protocol/Crypto/Security review.
2. Specify full initial onboarding, long absence and missing-proof behavior with
   budgets per operation; preserve the last accepted anchor on every failure.
3. Implement independent PHP/TypeScript/Python exact-output fixtures including
   mismatched sizes, keys, sequence/link/time/root/count and split-view evidence.
4. Exercise genuine local direct checks, stale checks, peer-only claims, rotation,
   migration, forks, withdrawal and owner-known-but-Node-omitted history.
5. Measure retained-history storage, proof generation, sustained publication,
   interrupted catch-up and adversarial denial of service on both backends.

This research does not close the active-index, onboarding or production gates.
