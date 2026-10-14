# Local candidate and peer resource policy

The development implementation applies one transactional admission helper to
manual candidate enqueue, candidates found by PeerSync and the owner-approved
(D-018) bounded announcement endpoint, which shares the global budgets below
through the fixed anonymous source `announcement`.

Current provisional local defaults, requiring D-041 sizing/review:

| Resource | Bound |
| --- | --- |
| Retained candidate targets | 4,096 |
| New targets per shared rolling 3,600-second window | 256 |
| New targets per source bucket/window | 32 |
| Fixed source buckets | 16 |
| Direct-check claims per shared window | 60 |
| Simultaneous unexpired direct-check leases | 2 |
| Retained pinned peer rows | 256 |

Source identifiers hash into a fixed number of buckets. Collisions can reduce
availability; buckets do not establish distinct operators or independent
observers. The shared global budget applies even if an attacker rotates
identities. Counters live in the same store transaction as admission/claim,
survive process restart, and refuse clock rollback. They measure claims, not
successful requests. Failed checks consume work budget too.

Repeated targets preserve backoff, attempt count, lease and quarantine. At most
eight attribution strings are retained, without voting or ranking authority.
Reaching a limit rejects admission/claim without evicting established entries.
The CLI reports NOT_INSERTED when no new candidate was admitted, including
duplicates and quota denial; it does not describe either as a new queued job.
PeerSync still records verified evidence/cursors when candidate admission is
denied, and never starts synchronous recursive fetching.

Tests use 10,000 rotating source identities and 10,000 duplicate announcements,
queue saturation, two simultaneous claims, restart/clock rollback and 256 pinned
peers. These are synthetic local fixtures, not public-network Sybil resistance,
source-family diversity, active-peer scheduling or production capacity evidence.
Operators must retain the old store when changing policy. No queue-reset or
identity-based quota-reset mechanism is provided.
