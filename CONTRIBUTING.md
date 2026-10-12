# Contributing

Start with the source manifest and implementation status. Keep changes small and reviewable. Requirements are not satisfied by placeholders or disabled checks.

Python 3.11+ runs the current checks. PHP/TypeScript toolchain selections remain pending their baseline review; no production toolchain is frozen.

Protocol changes require exact bytes, version impact, limits and positive/negative vectors. Security fixes require regression tests. Storage changes require crash/recovery evidence. Browser network changes require privacy/origin/CSP review.

Never commit credentials or private application/Tor/release keys. Use runtime secret bindings outside the repository. Public fixtures must be deterministic and synthetic.

Do not approve decisions on behalf of human reviewers. Foundation checks are not protocol conformance or production approval.
