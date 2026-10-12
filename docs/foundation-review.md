# Repository foundation self-review

Date: 2026-10-12
Baseline: `ee9be0d`
Status: PARTIAL; source ingestion and repository checks complete, production implementation outstanding.

## Change

Import the current non-archived specification corpus, preserve decision and review state, establish source integrity checks, and add a least-privilege foundation workflow. No production network, cryptographic, storage or browser code is added.

## Verification

- `python tools/check_repository.py`: PASS for 50 source digests and the configured credential patterns.
- `python -m unittest discover -s tests -v`: four tests PASS, including source tampering, path escape and duplicate-source rejection.
- `git diff --check`: PASS before staging; repeated against staged content at commit time.
- Workflow checkout action commit resolved through the public GitHub API on 2026-10-12. Credentials are not persisted by checkout; job permission is contents/read.
- GitHub Actions execution has not occurred; local checks do not imply remote CI success.

## Security and privacy impact

Only public GitHub repository/action metadata and the authorized Drive specification documents were retrieved. No Onion services were contacted, no application private keys were generated or read, and no user queries were processed. The owner-authorized GitHub credential is supplied only to the temporary Git process environment; it is not written to project files or Git URLs.

The narrow credential-pattern check is supplemental. It does not replace the required release secret scanning, static analysis, dependency audit or independent review.

## Open risks and human review

The full specification review and standards refresh remain incomplete. Source document import is explicitly distinct from requirement validation. D-001 is approved under the owner’s unrestricted-use instruction: CC0-1.0. Other decision records remain unresolved. No license grant, crypto freeze or production approval is fabricated.

All application and release gates remain open. GitHub rejected the first push because this token cannot modify Actions workflows. The workflow is retained as tools/ci/foundation-workflow.yml and is not active; other changes can be pushed without changing the token. No production release was performed.

## Next work

Complete specification reading/traceability and current standards refresh, then implement the reviewed protocol core and exact shared vectors before transport or browser UI. Keep filesystem storage, offline signing, data-node isolation and release trust-root amendments in the baseline.
