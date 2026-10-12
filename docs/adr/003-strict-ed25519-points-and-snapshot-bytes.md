# ADR 003: strict Ed25519 points and snapshot byte construction

Status: IMPLEMENTATION DRAFT — NEEDS HUMAN CRYPTO REVIEW.

All Service/Node public keys and signature R points must be canonical,
non-identity points in the prime-order Ed25519 subgroup. Signature scalars must
be canonical. PHP uses mature libsodium validation/verification; its public
Ed25519-to-X25519 conversion API serves only as a point-validity check and no
converted value is used as an identity. Python uses PyNaCl/libsodium point
validation plus independent cryptography/OpenSSL signature verification.
The browser uses pinned @noble/curves with ZIP215 disabled, explicit subgroup
checks and pinned @noble/hashes. It needs no WASM, eval or native Ed25519 support.
Native SHA-256 is preferred, with a mature bundled implementation when WebCrypto
is unavailable. The browser never holds signing keys.

An additional test showed OpenSSL accepted an identity public key/R combination
that libsodium rejected. Common vectors now include identity, zero,
noncanonical-y and mixed-torsion public keys, noncanonical signature scalar and
small-order R. PHP, TypeScript and Python agree on all 133 current black-box
cases. This is bounded conformance evidence, not a cryptographic audit.

Snapshot chunk bytes are a canonical array of independently signed records,
strictly increasing by domain-separated record content hash across chunks.
Duplicate chunks/records and inconsistent counts are rejected. Chunk hash:
SHA-256 of ASCII `Onion Service Discovery/snapshot-chunk/v1`, NUL and exact
canonical array bytes. Root hash: SHA-256 of ASCII
`Onion Service Discovery/snapshot-root/v1`, NUL and the canonical ordered array
of chunk descriptors (hash, bytes, record_count). Descriptor signatures bind
root, generation, predecessor, cursor and counts. Both byte constructors have
independent Python-generated tests in all three languages.

No protocol freeze or human approval is inferred. New dependencies, strict
point rules, provider compatibility and these bytes require review before a
production release.
