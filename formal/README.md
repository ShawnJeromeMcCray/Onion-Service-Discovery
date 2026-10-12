# Finite draft model

Run `python3 tools/check_formal_model.py --report docs/conformance/formal-report.json`
with Java 21 installed. The runner retrieves the official TLA+ Tools 1.7.4 JAR,
verifies the pinned byte count and SHA-256, and checks both network outcomes.
It also requires a deliberately unsafe rollback mutation to fail the
`NoRevisionDowngrade` invariant. The mutation is not an application option.

The model explores three abstract Service keys, three onion names, revisions
1–3, at most four evidence records, a two-row sync prefix and one candidate.
Both network outcomes checked on 2026-10-12 generated 4,286,590 states and
224,280 distinct reachable states, with depth 15 and no invariant violation.
The deliberate mutation failed at depth 4. These are finite safety results;
they are neither an unbounded proof nor a liveness guarantee.

Assumptions: signatures, hashes and Tor address binding behave correctly;
records marked valid already passed bounded decoding and cryptographic
verification; durable storage offers an atomic commit point for evidence and
cursor; the local pin root is controlled by the user. Cryptographic failures,
filesystem ordering and real browser behavior are checked separately.

The identity graph corresponds to `Protocol/IdentityDeriver.php`, the
TypeScript identity core and independent Python validator. Shared vectors
exercise forks, out-of-order history and authorization of combined key/address
changes. Atomic sync corresponds to `Storage/StorageTransaction.php` and both
backends; storage tests exercise pre/post-commit process exits. Candidate,
transport and browser pin transitions remain implementation obligations;
model assertions alone do not establish application coverage.

The tool checksum was observed on initial official-release retrieval and is
not independent release-signature verification. Human model review remains
OPEN. Any change to protocol semantics, model assumptions or bounds requires
review and rerunning the checks.
