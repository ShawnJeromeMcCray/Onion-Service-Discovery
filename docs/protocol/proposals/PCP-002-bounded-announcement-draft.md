# PCP-002: bounded public announcement experiment

State: OWNER-APPROVED AS D-018 (2026-10-14), including bounded automatic
re-announcement (implemented, see below) / NEEDS INDEPENDENT SECURITY REVIEW.
Decision: D-018. This proposal is reviewable code and executed development evidence,
not a Protocol Freeze or production approval.

The source requires an untrusted candidate hint followed by the Node's own Tor
fetch and independent validation. It requires bounded request/admission/work,
target suppression/backoff and global resistance to rotated Sybil identities.
The source [decision register](../../specification-source/24---Pre-Freeze-Decision-Register-and-Human-Approval-Gates.md)
required human approval of the endpoint, limits and retry policy; the owner gave
it as D-018 on 2026-10-14.

## Exact implemented draft

`POST /onion-service-discovery/v1/candidates/announce`, with
`Content-Type: application/json` (optional UTF-8 charset parameter). At most
256 body bytes, strict duplicate-rejecting UTF-8 JSON object, exactly:

```json
{"protocol_version":1,"onion":"<canonical 56-character label>.onion"}
```

An absolute URL, path, port, metadata, key, signature, source identity, extra
field, duplicate field or noncanonical address is rejected. The hint is not a
signed protocol record. It contributes neither a identity nor an observation.

- Receiver enabled only with local `accept_announcements: true` **and**
  `discover_services: true` on Node/combined roles; both default false. Existing
  configurations without the optional receiver field keep it false. It is not
  silently enabled by discovery or mirroring.
- Global HTTP bucket: burst 20, refill two requests/second, shared with public
  data API. No IP, cookie, User-Agent, search or user identifier is stored.
- All public anonymous callers share the fixed admission source `announcement`:
  32 new targets/hour, within the existing 256/hour global new-target budget,
  queue cap 4,096. Claimed keys, caller identity rotation and address churn cannot
  grant a new anonymous source bucket. Manual/sync admission shares global caps.
- Target duplicates preserve existing state, retry time, attempts, lease and
  quarantine. A known target receives the same unconfirmed reply; it does not
  reopen work. A full queue or exhausted admission budget returns 429 with
  `Retry-After: 3600`. In-flight HTTP concurrency/body/header/time limits must
  also be enforced by the deployment server.
- The receiver runs no network, crypto verification, signing, direct observation,
  snapshot publication or peer sync. It only writes the bounded untrusted queue.
- Independent scheduled work remains capped at 60/hour and two in flight. The
  existing worker fetches the canonical manifest through Tor and independently
  verifies its signature/address/full continuity before a local observation.
  Failure retries remain positive exponential backoff plus jitter; announcements
  cannot reset them. No permanent positive state follows from accepting a hint.

202 response, exactly:

```json
{"status":"HINT_RECEIVED_UNCONFIRMED"}
```

This does not promise verification, index inclusion, direct observation, queue
execution time or retention. Other statuses: 400 malformed hint, 405 method,
413 oversize, 415 media, 429 budget; operational failures normalize to 503.
No redirect, wildcard CORS, credential or authentication assertion is added.
No new signed Node capability token is invented by this experiment.

## Publisher/operator path

```text
service:announce CONFIG SIGNED_MANIFEST_FILE NODE_ONION
service:announce CONFIG SIGNED_MANIFEST_FILE --node ONION KEY [--node ...] [--if-due | --save-status]
```

Requires explicit local publication and a validated signed manifest bound to
its configured Onion address with `publish_service: true`. Each selected Node
(at most 16) gets one bounded Tor POST, to a canonical Onion destination and
fixed endpoint, with fresh SOCKS isolation. It reads no private key and mutates
no Node store. Only the exact 202 unconfirmed response is accepted; claims of
inclusion, redirect, media/byte ambiguity and rate limits (`ANNOUNCEMENT_RATE_LIMITED`)
cannot pass as success. JSON output explicitly leaves observation/membership
unconfirmed. The operator can separately run the exact snapshot membership verifier.

## Bounded automatic re-announcement (owner-approved D-018)

`--if-due`, run by the publisher timer after the self-check and the membership
check, announces to a Node only when:

- the Manifest was verified through the own Onion within the last 24 hours
  (saved `service:verify --save-status`), otherwise nothing is sent; and
- the Node never received a hint (`INITIAL`), the last hint announced another
  revision (`NEW_REVISION`), or it still lacks the current revision and the
  backoff has elapsed (`MISSING_OBSERVATION`); and
- at least 24 hours passed since the last hint to that Node, whatever the
  revision (a timestamp in the future also blocks).

Consecutive hints for one revision wait 1, 2, 4, 8, 16 and then 32 days. Saved
membership showing the Node `included` stops the schedule; `contradictory` or
`rejected` stops it too, because a hint cannot fix them. A received hint never
does. A never-observing Node therefore gets at most 8 hints in 120 days.
Decisions and reservations happen under one lock on the configuration file, so
concurrent runs cannot double an attempt; a reservation whose run dies counts as
an attempt. Only SHA-256 hashes of Node addresses, the revision hash, the attempt
count and times are saved, beside the private configuration.

## Evidence and unresolved review

Receiver/method/body/media/duplicate/budget/clock rollback/target-quarantine tests
run on both backends, including the actual public PHP entrypoint. Controlled
SOCKS tests inspect the one POST/body, the CLI result and rejection of a bare
inclusion assertion. A fresh isolated real-Tor network tests the announcement,
confirms no manifest/observation exists immediately afterward, then performs
independent scheduled verification and snapshot/peer workflows.

The anonymous shared budget is deliberately conservative and may cause capacity
starvation; admission is not fairness or Sybil identity proof. The owner
approved the endpoint, limits and retry policy (D-018); independent security
review of them and of deployment request framing remains open. This does not
complete automatic Node selection, active-index proofs, source-independent
observability or the overall Production Stable checklist.
