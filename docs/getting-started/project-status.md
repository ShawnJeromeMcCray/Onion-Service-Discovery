# Practical use, evidence and remaining work

Assessment date: **14 October 2026**. Release channel: **development preview**.
The owner’s goal is a practical, well-documented public project. Completed local
workflows are usable; Production Stable is a separate, evidence-based claim.
Documentation, a screenshot or passing tests cannot turn an unfinished external
workflow into verified discoverability.

## What visitors can do now

| Workflow | Practical result | Scope |
| --- | --- | --- |
| [Local demo](quickstart.md) | Run the real verifier/UI, search a signed synthetic bundle and pin an identity | Local browser; no public services |
| [Publisher setup](publisher.md) | Generate an offline Service key, sign/install an opted-in Manifest and inspect local status | Operator-managed staging Onion |
| [Node setup](node.md) | Initialize a Node, admit/check a candidate, publish snapshots, synchronize pinned peers | Explicitly configured staging infrastructure |
| [Client Host](installation.md#client-host) | Host local search and relay generic data from selected Nodes | No approved public seed set |
| [Membership verification](../operations/snapshot-membership.md) | Verify the exact current owner state in a complete authenticated snapshot | Freshness and checkpoint consistency separately unevaluated |
| [Backup and recovery](../operations/deployment-and-recovery.md) | Validate a private backup and restore into a fresh staging backend | Real production restore drills still required |

## Update to the supplied October assessment

The supplied assessment correctly identifies the gap between components working
and a normal operator completing external onboarding. Some evidence has advanced:

| Assessment item | Current implementation/evidence | Still open |
| --- | --- | --- |
| 202 core black-box cases | [Expanded report](../conformance/production-completion-core-report.json): 226 exact-output cases per language, including membership cases | Full revised-spec conformance and independent review |
| Tor tests only local/offline | [Real private-Tor application](../conformance/private-tor-onion-report.json) and [actual Tor Browser on a private Onion](../conformance/tor-browser-private-onion-report.json) | Public Tor, Android, desktop Normal/Safer/Safest, slow-network matrix |
| Restricted JavaScript unsupported | [JavaScript-disabled actual-browser check](../conformance/tor-browser-nojs-report.json), private-Onion NoJS form tests, [optional server-search contract](../operations/server-search.md) | Full security-level/platform certification and accessibility review |
| Snapshot membership | Exact-current-state verification in PHP/TypeScript/Python; wrong key, stale state, omissions, forks and corrupt chunks reject | Frozen active-index checkpoint type and automated fresh multi-Node workflow |
| Long absence / contradictory histories | Bounded resumed checkpoint ancestry and authenticated conflicting-witness retention | Longer operational soak, changing-head stress and broader chaos evidence |
| Installation incomplete | One-command loopback demo, role-specific walkthroughs, development source/browser package and packaged runtime smoke tests | Authenticated stable release, host-specific isolation and operator acceptance |
| Recovery unproven | Controlled crash/ENOSPC tests, both backend contracts, private staged restore | Physical hardware/power-loss and deployed restore acceptance |
| Audit / production approval absent | Explicitly remains absent | Independent crypto/security/privacy/accessibility reviews and human release approval |

The private network uses real Tor binaries and native v3 Onion handling, but its
Nodes are controlled by one lab operator. It does not establish independent
operators, a public discovery network or Internet Tor availability.

## End-to-end acceptance scenario

The important remaining product proof is the complete user journey from a fresh
host to independent discoverability. A release reviewer should be able to run:

1. Install the chosen authenticated package on a fresh supported host, configure an existing Onion and publish a separate-key signed genesis Manifest.
2. Select at least three independently controlled Nodes and obtain their expected keys independently. Submit a bounded hint or have each Node explicitly admit the candidate.
3. Have each Node fetch the actual Onion, validate full owner continuity, make its own direct observation and publish the agreed signed active-index evidence.
4. Independently verify current revision/hash/publication state, freshness and consistent checkpoint ancestry for each source. Do not count mere acknowledgements or historical revision retention as success.
5. Show a status summary that distinguishes included, stale, unavailable and contradictory Nodes, with the exact basis for each count.
6. Have another user load those sources through the intended Tor Browser desktop/Android profiles, find the service through local search and preserve a chosen identity pin.
7. Repeat with omitted data, corrupted chunks, stale/rollback/forked checkpoints, network interruption, quota exhaustion, restricted scripting and restore/restart.

This full scenario is **not yet recorded as passing**. The current public status
page deliberately reports local readiness separately and keeps external
membership unconfirmed. Steps 4–5 are partly automated for the publisher:
`service:membership` fetches complete signed snapshots from explicitly selected
Nodes over Tor and reports each as included, stale, not present, contradictory,
unavailable or rejected (controlled tests only; no public-Tor run is recorded).
Step 2's hint is automated within the owner-approved bounds of D-018: the
publisher timer announces only a new revision or a missing observation, at most
once per day per Node. Rotation and migration proofs reach Nodes through the
served PCP-004 history bundle, so no Node operator has to admit them by hand.
With `--save-status` (also for `service:verify` and `service:announce`), the read-only status page now answers YES/NOT YET/NO/DEGRADED/PROBLEM with the ten-step pipeline and derives DISCOVERABLE,
DISCOVERABLE_DEGRADED or IDENTITY_CONFLICT from those saved aggregate counts.
Checkpoint ancestry across the publisher's checks is now evaluated (D-052);
freshness and operator independence are not, so it does not establish “current
revision in 2 of 3 fresh independent Nodes.” Existing complete-snapshot evidence is not silently
renamed to the pending active-index checkpoint protocol.

## Remaining engineering that can be developed

- Freshness evaluation for the publisher's discoverability evidence: collection, checkpoint ancestry (D-052), the status summary and Node/visitor tooling exist; the 7-day evidence age is owner-approved, a checkpoint-age threshold is not.
- Final active-index checkpoint semantics, exact wire bytes and continuity design, then implementation/adversarial vectors after the required protocol decisions are reviewed.
- Learned-source admission and independent-source bootstrap policy; current peers are explicitly pinned and bounded.
- Long-lived record growth/retention, larger applicable tiers, operational-state-heavy sync, concurrency and degraded-network/low-end-browser measurements.
- Strong deployed Node-key isolation from web compromise, with an executable storage/signer separation rather than shared-UID templates alone.
- Complete revised-spec traceability and the remaining applicable conformance/chaos/release evidence.

## External or owner-controlled prerequisites

The coding environment cannot supply independent audit judgments or approve its
own cryptographic design. It cannot invent independent public Node operators,
authenticated production seed/trust-root custody, physical hardware recovery
results, or Tor Browser Android/desktop-profile observations that have not been
performed. Public Tor TCP access is currently restricted in this environment;
a private Tor lab is not a substitute. These are explicit prerequisites, not
engineering tasks marked complete by omission.

The detailed review is [production-completion-review.md](../production-completion-review.md).
The [decision register](../governance/decision-register.md),
[release gates](../release-gates.json) and
[47-section production readiness matrix](../production-readiness.json) retain
unapproved/unobserved requirements as failures. No Production Stable release or
maintenance-free guarantee is implied.
