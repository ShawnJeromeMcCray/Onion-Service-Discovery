# Local source groups and concentration diagnostics

```sh
node-php/bin/onion-service-discovery peers:group CONFIG ONION selected-family-a
node-php/bin/onion-service-discovery peers:group CONFIG ONION --clear
```

Local group IDs are lowercase ASCII tokens of 1–32 characters. The command
requires an existing pinned peer, rejects an in-flight sync claim and makes no
network request. It changes no key, signature, identity validity, admission
priority or Service ranking. Grouping persists in both supported storage backends
and ordinary sync updates.

`doctor`/`security:check` expose `source_concentration`: active peer addresses,
distinct Node keys, configured groups, ungrouped keys, inconsistent assignments
and a concentration warning. Multiple addresses sharing one key cannot inflate
group credit. Unknown grouping remains unknown. An explicitly grouped single
family or single active Node produces a warning.

Groups represent local knowledge, not cryptographic ownership proof. Diagnostics
say `operator_independence=NOT_PROVEN` and `trust_authority_from_count=false`.
Learned-peer/probation admission, referral-origin budgets and gradual source
replacement remain separate incomplete requirements. This does not complete
eclipse resistance.
