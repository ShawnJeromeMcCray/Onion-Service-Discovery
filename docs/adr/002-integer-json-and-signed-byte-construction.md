# ADR-002: Integer-only canonical JSON and signed-byte construction

Status: Needs Human Review; implementation draft
Date: 2026-10-12
Owner: implementation agent

## Context

Documents 12/13 require exact portable bytes. All signed schemas restrict numeric fields to safe integers. The existing strict parser already rejects fractions/scientific tokens, unsafe integers, duplicate keys and lone surrogates.

## Decision proposed

Use RFC 8785 string/literal/array serialization and recursive UTF-16 code-unit property ordering for the permitted integer-only schema subset. No claim of a general floating-point RFC 8785 implementation is made. Preserve signed Unicode exactly; do not normalize signed input.

Signed input is UTF-8 ASCII domain string, one zero byte, then canonical payload bytes. Each record type has a fixed allowlisted domain. Exclude only its declared signature fields; everything else is signed. No general-purpose arbitrary-domain signing API is public.

Manifest hash uses `Onion Service Discovery/service-manifest-hash/v1`, a zero byte, and canonical signable manifest payload; SHA-256 encoded as `sha256:` plus lowercase hex.

The draft manifest includes `record_type: service_manifest`. Rotation retains the early Protocol's `old_key_signature` and `new_key_confirmation`, excludes both from the shared signed payload, and requires both signatures over the same rotation domain. Migration retains `service_identity_signature`. Other signed records use `signature`. These choices are explicit draft schema decisions, not a frozen Protocol.

Genesis is revision 1 with explicit null predecessor. Successors require exact predecessor+1. Countersigned rotation and signed migration may be combined only with complete, independently valid evidence linked to the same predecessor.

## Alternatives

A general JCS dependency handles floats but adds unneeded runtime surface for integer-only records. Default serializers alone have incompatible object ordering. Custom signature/hash primitives are prohibited; use libsodium, locally bundled Noble, and Python cryptography with independent PyNaCl point checks. See ADR-003 for the later strict point/provider decision.

## Security and privacy impact

No network or storage side effects in validation. Different domains prevent cross-record signature reuse. Verification does not assert content honesty, direct Onion observation or human ownership.

## Testing

Exact cross-language Unicode sorting, escaping, message bytes, signatures, hashes, malformed encodings, mutations and wrong-domain negatives. RFC 8032 test material is explicitly synthetic/test-only.

## Human review

D-005 through D-012 and protocol crypto freeze remain NEEDS-HUMAN-REVIEW. No Production Stable approval is inferred. Runtime/browser provider compatibility remains a separate gate.
