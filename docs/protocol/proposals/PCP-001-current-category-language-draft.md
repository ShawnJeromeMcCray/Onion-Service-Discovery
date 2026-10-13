# PCP-001: current category and language draft alignment

Status: IMPLEMENTATION_EXPERIMENT / NEEDS-HUMAN-REVIEW. Not accepted, frozen or
Production-supported. D-053 and D-054 remain open.

Problem: the former draft allowed arbitrary category spellings and a loose
language grammar. The refreshed normative sources require registry membership
and one tested language policy. No Protocol v1 Stable release exists.

Proposed behavior: generate all category constants/schema enums from the 38
entries specified in source document 50, including adult. Unknown IDs, wrong case,
duplicates, non-ASCII lookalikes, unsorted arrays and 33 entries reject. Registry
status remains provisional until D-053 approval; it is immutable after freeze.
Tags remain absent from the signed v1 schema, not overloaded into categories.

Language experiment: RFC 5646 well-formed grammar, serialized lowercase ASCII,
maximum 35 characters/16 entries, no registry/alias lookup, exact full match,
no duplicate variants or extension singletons. Private-use and grandfathered
tags follow RFC 5646. The three semantic parsers are independently implemented
over generated grammar constants and common vectors.

Compatibility: these are stricter draft acceptance rules. Previously accepted
free-text categories and loose language values can now fail validation. Existing
signed bytes are never rewritten, re-signed or silently coerced. An ordinary valid successor is insufficient if older required continuity
records fail the new schema. No automatic continuity-preserving migration for
that case is defined or claimed; preserve the historical verifier/evidence for
inspection and backup, and resolve the profile/version transition through
Protocol review. Stable interoperability claims
are blocked until the owner/reviewers accept exact rules and migration analysis.
An already frozen/deployed incompatible v1 would require a new version/explicit
compatible extension rather than silently tightening its semantics.

Security/privacy: limits stay finite; category/observer quantity gives no hidden
ranking boost; adult uses identical rendering isolation and a session-local
visibility preference. Language validation does not establish that a language
subtag is registered or correctly describes content. No online registry request.

Evidence: protocol/registries/categories-v1.json, language-tags-v1.json,
107 signed-record cases, three-language black-box core report, category/count
neutrality tests, malformed-input tests and compiler-token sink policies.

Human approvals: D-053 Protocol/Product and D-054 Protocol/I18N OPEN. No approval
is inferred from implementation, test success or the request to build the product.
