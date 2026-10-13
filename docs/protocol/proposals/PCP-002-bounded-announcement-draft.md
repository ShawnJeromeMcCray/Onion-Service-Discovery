# PCP-002: bounded public announcement experiment

State: IMPLEMENTATION EXPERIMENT / NEEDS HUMAN PRODUCT AND SECURITY REVIEW.
Decision: D-018. This proposal is reviewable code and executed development evidence,
not a Protocol Freeze or production approval.

The source requires an untrusted candidate hint followed by the Node's own Tor
fetch and independent validation. It requires bounded request/admission/work,
target suppression/backoff and global resistance to rotated Sybil identities.
The final endpoint, limits and retry policy require human approval in source
[decision register](../../specification-source/24---Pre-Freeze-Decision-Register-and-Human-Approval-Gates.md).

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
```

Requires explicit local publication and a validated signed manifest bound to
its configured Onion address with `publish_service: true`. One invocation makes
one bounded Tor POST, to a canonical Onion destination and fixed endpoint, with
fresh SOCKS isolation. It reads no private key and mutates no local store. No
retry loop is created. Only the exact 202 unconfirmed response is accepted;
claims of inclusion, redirect, media/byte ambiguity and rate limits cannot pass
as success. JSON output explicitly leaves observation/membership unconfirmed.
The operator can separately run the exact snapshot membership verifier.

## Evidence and unresolved review

Receiver/method/body/media/duplicate/budget/clock rollback/target-quarantine tests
run on both backends, including the actual public PHP entrypoint. Controlled
SOCKS tests inspect the one POST/body, the CLI result and rejection of a bare
inclusion assertion. A fresh isolated real-Tor network tests the announcement,
confirms no manifest/observation exists immediately afterward, then performs
independent scheduled verification and snapshot/peer workflows.

The anonymous shared budget is deliberately conservative and may cause capacity
starvation; admission is not fairness or Sybil identity proof. Human reviewers
must approve endpoint/media/numeric limits, deployment request framing and
worker/retry policy before production freeze. This experiment does not complete
automated multi-Node publishing/onboarding, source selection, active-index proofs,
source-independent observability or the overall Production Stable checklist.
