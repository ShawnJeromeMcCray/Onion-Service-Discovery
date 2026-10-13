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
Protocol Freeze, human approval or acceptance. Changed/new sources are marked
`IMPORTED_NOT_FULLY_REVIEWED`; the complete ordered review remains pending.
Previous source bytes and manifest remain available in Git history.

See [manifest](specification-manifest.json) and [per-document comparison](specification-refresh-report.json).

## Material changes and implementation gaps

| Current source | Required behavior | Present implementation / next boundary |
| --- | --- | --- |
| Protocol 02, Crypto 13, Decision 24 D-051 | Stable `logical_service_id` across valid rotation/migration, anchored with genesis continuity evidence | Existing internal genesis/key history helps, but current wire schemas and API do not expose the revised identifier; exact representation is not approved. |
| API 14 and new 48 | Stable `/services/by-id/{logical_service_id}` lookup and verifiable exact active-index inclusion | Current Onion-address shortcut and signed generic snapshots are not the new stable lookup/operator inclusion-proof workflow. |
| Decision 24 D-052, API 14, security 49 | Exact chained checkpoints and non-prefix-compatible-history evidence | Earlier draft already chains snapshot descriptors and retains conflicts; revised index/checkpoint tuple and exact bytes need alignment and human freeze. |
| New 47, operator contract 40 and PHP 04 | Plug-and-play status dashboard, self-service onboarding and truthful sampled discoverability | Current CLI/read-only doctor is not the required dashboard or complete self-service workflow. |
| Revised D-018, Protocol/API/security sources | Bounded Candidate Announcement is part of the reference publish workflow | Earlier omission/manual candidate seeding no longer satisfies the revised requirement. Endpoint/default admission semantics still require approval. |
| New 49 | Identity quantity has zero authority; bounded source admission, diversity/grouping and search-farm handling | Existing no-voting/source-count warnings are partial controls, not full compliance with this new standard. |
| New 50 and D-053/D-054 | Immutable category registry, separate tags and one precise language-tag grammar across implementations | Earlier bounded category/language strings are insufficient. Do not invent final signed-wire rules. |
| New 51 | Explicit applicable exploit-class coverage and secure-coding evidence | Existing parser/signature/storage/transport/browser checks cover part of this standard; a full applicability/evidence matrix remains open. |

D-051 through D-054 are expressly unresolved human release blockers. The
refreshed Closure Audit says implementation may proceed but does not approve
wire semantics, Protocol Freeze or Production Stable. The owner's earlier
explicit CC0 instruction remains D-001 approval; other approvals are not inferred.

The 318 old extracted blocks in `requirements.json` are historical partial
mappings. Each now records its original source hash and whether the source has
changed. Five new sources are not yet covered by that extraction. Re-extraction,
ordered review and implementation/test alignment must precede a current-source
conformance claim. Existing passing cases remain useful earlier-draft evidence.

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
