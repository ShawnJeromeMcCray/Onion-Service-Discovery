# Specification refresh — 2026-10-13

The connected Google Drive folder now contains **55 active Google Docs**:
54 required-reading documents plus the Workspace Index. The index lists exactly
1–54, with unique matching document titles/IDs. Archive folder 99 was excluded.
Compared with the previously imported 50-source baseline, **43 document bodies
changed, five were added, seven are unchanged and none were removed**.

The previous implementation did not incorporate this later source revision.
All current readable text is now imported with observed modification times and
SHA-256 hashes. LF/BOM normalization and document-edge trimming are applied;
internal source whitespace is preserved. Import is not completed reading,
Protocol Freeze, human approval or acceptance. All 55 sources have now been read in the mandatory order and are marked
`REVIEWED`. This records implementation-agent reading, not human approval.
A later metadata recheck found no changed or additional active sources.
Previous source bytes and manifest remain available in Git history.

See [manifest](specification-manifest.json) and [per-document comparison](specification-refresh-report.json).

## Material changes and implementation gaps

| Current source | Required behavior | Present implementation / next boundary |
| --- | --- | --- |
| Protocol 02, Crypto 13, Decision 24 D-051 | Stable `logical_service_id` across valid rotation/migration, anchored with genesis continuity evidence | Existing internal genesis/key history helps, but current wire schemas and API do not expose the revised identifier; exact representation is not approved. |
| API 14 and new 48 | Stable `/services/by-id/{logical_service_id}` lookup and verifiable exact active-index inclusion | Current Onion-address shortcut and signed generic snapshots are not the new stable lookup/operator inclusion-proof workflow. |
| Decision 24 D-052, API 14, security 49 | Exact chained checkpoints and non-prefix-compatible-history evidence | Earlier draft already chains snapshot descriptors and retains conflicts; revised index/checkpoint tuple and exact bytes need alignment and human freeze. |
| New 47, operator contract 40 and PHP 04 | Plug-and-play status dashboard, self-service onboarding and truthful sampled discoverability | Publisher-only init and a server-rendered read-only status page are implemented. External checks, announcement and active-index discoverability remain unconfirmed and the complete self-service workflow is missing. |
| Revised D-018, Protocol/API/security sources | Bounded Candidate Announcement is part of the reference publish workflow | Earlier omission/manual candidate seeding no longer satisfies the revised requirement. Endpoint/default admission semantics still require approval. |
| New 49 | Identity quantity has zero authority; bounded source admission, diversity/grouping and search-farm handling | Shared persistent candidate budgets, a bounded pinned-peer pool and local duplicate-result grouping are implemented. Source-family diversity and complete network Sybil controls remain open. |
| New 50 and D-053/D-054 | Immutable category registry, separate tags and one precise language-tag grammar across implementations | A shared 38-category draft registry and independently checked RFC 5646 grammar are implemented as PCP-001. D-053/D-054 and historical-record compatibility remain unresolved; these are not final frozen wire rules. |
| New 51 | Explicit applicable exploit-class coverage and secure-coding evidence | Sink checks and current OWASP/ASVS/CWE coverage inventories are added. Applicability, exhaustive evidence and independent security review remain open. |

D-051 through D-054 are expressly unresolved human release blockers. The
refreshed Closure Audit says implementation may proceed but does not approve
wire semantics, Protocol Freeze or Production Stable. The owner's earlier
explicit CC0 instruction remains D-001 approval; other approvals are not inferred.

The current requirements inventory contains 2,813 source sections with source
lines and hashes. It supersedes the historical 318-block partial extraction,
which remains in Git history. Section inventory is not individual MUST-level
implementation traceability. The production-readiness report enumerates all
47 current Definition-of-Done sections and records missing complete evidence
as failure. Neither document certifies production conformance.

## Actual Tor CLI evidence and limits

The real Tor 0.4.9.14 executable was obtained through Debian's authenticated APT
metadata, package `0.4.9.14-0+deb13u1`, SHA-256
`ab591371ae6a0e4266f75191fde3483617679b83a56d5d2d748969b75528a325`.
Two disposable checks use pinned PHP 8.5.11 and Docker `--network none`:

- Disabled-network configuration/control and typed CLI transport failure. Tor's
  `DisableNetwork 1` disables SOCKS listeners too; this is not a SOCKS handshake
  or live network test.
- Network-enabled real SOCKS listener, only a synthetic loopback directory
  authority, default fallback directories disabled, and no external route.
  A Tor STREAM event proves the requested synthetic Onion hostname reached the
  real daemon. No-consensus failure is normalized; the CLI returns exit 5 without
  creating application storage/keys or using the ambient HTTP proxy.

See [report](conformance/real-tor-cli-report.json) and
[reproduction instructions](../lab/real-tor-cli.md).

These tests do **not** prove public Tor bootstrap, successful Onion fetch,
multi-Node live sync, Tor Browser/Android compatibility or production readiness.
The managed workspace's HTTP policy is enforced unrestricted, but its TCP domain
and IP grant lists are empty. No proxy bypass, host-network workaround or remote
execution route was used to evade that restriction.

Tor CLI plus SOCKS is sufficient for Node/service transport integration tests
when Tor network access is available. Browser behavior still needs browser
execution: rendering, CSP/CORS, local query traffic, storage/pins and desktop/mobile
compatibility cannot be established by a CLI HTTP client.

The mature secret scanner flagged a plain-English Workspace Index sentence about engineering test
coverage as a generic credential. Manual review
confirmed that the full source line is an engineering coverage description,
not a credential. `.gitleaksignore` contains only its exact historical
commit/path/rule/line fingerprint. No source directory, credential type or
future content is exempted. The supplemental credential-pattern check remains.
