# OWASP ASVS applicability review

The [machine-readable matrix](ASVS-MAP.json) contains all 345 requirement IDs
from the official ASVS 5.0.0 English release CSV. Candidate component scopes are
review starting points. Every requirement is `REVIEW_REQUIRED`; no automated
extraction counts as applicability acceptance, complete verification or certification.

The CSV was fetched from the official project release tag on 2026-10-13. Its
digest and the current OWASP/CWE reference URLs are recorded in
[standards-refresh.json](standards-refresh.json). The moving ASVS `latest` tag
was observed separately and is not treated as a stable-version approval.

A reviewer must classify each exact requirement, inspect its implementation and
tests, document any non-applicability, and approve the resulting assessment.
Public administration, password login, sessions, OAuth/OIDC and WebRTC are absent;
that fact alone does not automatically classify every related requirement.
