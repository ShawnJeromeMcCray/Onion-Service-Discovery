# Measured development capacity

Reports describe a local x86-64 cloud workspace on 2026-10-12, PHP 8.5.11 in
the digest-pinned test container, Node 24.19.0, and synthetic public TEST data.
The S1000 typical profile has 1,000 independent Services, two revisions each,
five Node descriptors, and five signed observation claims for each current
manifest: **7,005 records, 5,310,652 wire bytes**. This is five signing identities,
not a live five-Node Tor deployment. No real users, queries or services occur.

Reproduce the dataset with `tools/benchmarks/generate_dataset.py`, then run
`tools/benchmarks/storage.php DATASET` with a supported PHP/vendor installation
and `node web-local-search-client/benchmark.mjs DATASET`. The dataset hash in the
report fixes the workload. Both backends enforce validation/normal quotas and
safety checks. Typical metadata includes a 300+ character description, two
languages, three categories and revision continuity. Values are observations of
this host, not promises for another machine.

The storage report includes verification of every signed record, cold admission
in 256-record transactions, bounded full reads, identity derivation, integrity,
private backup, snapshot generation, 100 indexed service API requests and SQLite
WAL maintenance. Quantiles use sorted samples with floor((n-1)*q), with sample
counts disclosed. Ten-sample derivation P99 is coarse; use larger repeated runs
for a service-level objective. Disk sizes include database/WAL/exports and exclude
only the measured private backup. PHP memory is process allocator peak, not RSS.

The client report executes the **browser implementation in Node**, without DOM,
Tor/network or rendering. It records strict signature verification, ten full
view derivations, and 100 local searches. It must not be described as a Tor
Browser or Android benchmark. Point-subgroup validation caches only exact
previously validated public encodings (maximum 10,000); every message signature
is still checked. Cold verification remains material, and the observed Node RSS
is too substantial to infer mobile suitability. Actual mobile/desktop browser
memory, rendering and multi-source transfer measurements are still required.

Remaining capacity gates: independent environment reproduction, TIER-M/L/XL
models and measured applicable limits, MAX-VALID/migration/fork histories,
operational-state-heavy multi-peer sync, live Tor and network degradation,
concurrent web workers, long-lived disk growth and retention, physical disk-full
and power-loss faults, browser mobile evidence. Current record admission caps
at 10,000 records (including history/observations), not 10,000 active Services.
At this five-claim/two-revision profile that caps active Services far below
TIER-M; the reference implementation does not advertise those higher tiers.
Bounded service lookup can return 413 for long/large histories; signed snapshot
sync remains the complete bounded evidence path.


An additional adversarial index report uses 1,000 synthetic valid Onion addresses
whose lookup SHA-256 prefixes all equal `00`. This concentrates all 7,005 signed
records into one derived lookup bucket. Both backends still publish the complete
signed snapshot; an over-budget individual bucket yields 413 and directs callers
to generic snapshots. The derived shortcut cannot block valid snapshot publication.
Generate it with `--lookup-prefix 00`. It is a controlled address-distribution
stress case, not a live malicious-node or Tor load result.

The 2026-10-14 search-cache report measures the current frozen Unicode 17
implementation on the same signed dataset. The pre-cache implementation repeats
metadata normalization for every query; the changed implementation uses weak
row keys and compares the complete metadata input before reusing derived text.
Empty queries skip normalization. The report records the first nonempty query
and maximum separately because percentile summaries can hide the initial cost.
Metadata-mutation tests prevent stale cache matches. This is still Node execution
of client code, not desktop Tor Browser or mobile capacity certification.
