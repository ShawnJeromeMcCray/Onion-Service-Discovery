# Stable service-ID lookup (development protocol)

D-051 uses the canonical genesis Service Identity Ed25519 public key as the
logical service ID. The `ed25519:` prefix and unpadded base64url encoding are
part of the identifier. Rotation changes the current signing key; migration
changes the Onion address. Neither changes this genesis identifier. An orphan
manifest cannot establish a stable identity without its signed genesis chain.
Owner approval is recorded in the [decision register](../governance/decision-register.md);
independent cryptographic review and the overall protocol freeze remain open.

The read-only mirror endpoint accepts either canonical identifier:

```text
GET /onion-service-discovery/v1/services/by-id/ed25519:<43-character-base64url-key>
GET /onion-service-discovery/v1/services/ed25519:<43-character-base64url-key>
GET /onion-service-discovery/v1/services/<56-character-onion>.onion
```

The `by-id` form is the canonical stable-identity route named by specifications
04, 14, 46 and 48 and accepts only the logical service ID; the combined route
returns the same bytes and remains for compatibility and address lookup.
No percent-encoded alternatives, padding, arbitrary URL, query parameters or
mutation methods are accepted. A mirror must publish once with this version
before serving lookups; old version-1 indexes return `SERVICE_INDEX_NOT_READY`
until republished. Publication upgrades the derived lookup index atomically,
without changing signed records or the existing snapshot signature format.

The response contains `protocol`, `protocol_version`, `logical_service_ids` and
`records`. ID lookup returns the complete exported continuity evidence for that
identity, including retained fork and withdrawal evidence. Address lookup returns
all related complete histories; one address can belong to multiple independent
identities. An unknown canonical key returns empty arrays. Historical addresses
resolve to the same genesis identifier after authorized migration. Unanchored
public evidence may appear in address lookup but contributes no claimed ID.

The response is a bounded evidence envelope, not a signed active-index proof.
Clients must validate each record and independently derive identities. Use
[complete-snapshot membership verification](snapshot-membership.md) to compare
an exact owner state against a pinned signed export. Lookup alone proves neither
freshness nor inclusion in the current signed checkpoint.

Both endpoints use local published evidence only: no signing, network requests,
maintenance, query persistence or trust by peer count. Responses are capped at
256 records and 32 KiB; oversized histories return 413 and remain available
through bounded signed snapshot chunks. Unknown/unavailable data never creates
an identity. Mirror-disabled nodes do not expose these routes.

`node-php/tests/service-id-api.php` exercises the shared identity histories on
both storage backends, including complete rotation/migration, persistent forks,
withdrawal, orphan rejection, address collisions, canonical-key validation,
idempotent publication, HEAD and read-only storage behavior.
