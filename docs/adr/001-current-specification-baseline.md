# ADR-001: Current specification baseline and resolved superseded passages

Status: Accepted for implementation precedence; not human protocol approval
Date: 2026-10-12
Owner: implementation agent

## Context

The 50 imported documents contain appended revisions and older passages. The workspace index explicitly makes later precise constraints binding and requires contradictions to be recorded.

## Decision

1. Documents 41, 46 and the Master Brief amendments supersede global SQLite requirements. FilesystemStore and SQLiteStore must share the same backend-neutral evidence/transaction contract; missing unused drivers are not failures.
2. Documents 19, 42 and the current Master Brief supersede residual extension references. No extension package, permissions or content scripts are implemented. Combined and separated static Client Host roles remain distinct.
3. Document 27 and current Definition of Done supersede document 26 section 48's older optional-formal wording. A model and review are required before Protocol Stable. Full implementation verification is still not claimed.
4. Document 43 supersedes online Service-key assumptions: preferred publication uses offline signing, and the web/worker paths do not require Service private material.
5. Document 44 supersedes simple hosting/signature-only release assumptions. Mature update metadata and an owner-approved release trust root remain production gates.
6. Document 12 requires duplicate semantic set values to be rejected in signed input, superseding the early protocol example's deduplication wording. No rewriting before verification.
7. The explicit optional server-search sections amend the earlier API-only prohibition. Human POST-only fallback stays outside the machine API and is never silently substituted for local search.

## Security and privacy impact

Preserve evidence, key separation, query locality, origin trust and finite limits. No requirement is waived. Same-name records are separate identities; Node counts cannot resolve identity conflicts.

## Testing and migration

Trace each resolved behavior to shared protocol, storage, browser or release tests. No previously stable release or database migration exists yet. Source copies remain unchanged so historical wording is inspectable.

## Human review

Technical approvals, independent audit, browser testing, actual production seeds, release-key custody and final Production Stable remain OPEN. Owner authorization to implement is not recorded as crypto review.
