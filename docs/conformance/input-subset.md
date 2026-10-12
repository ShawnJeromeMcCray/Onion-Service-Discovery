# Draft input conformance subset

This code is an offline experimental protocol-core slice, not a frozen protocol or deployable Node/client. No application network calls, signing or identity-state mutations are implemented here. Full specification reading/traceability and pre-implementation closure remain incomplete.

## Shared vectors

23 OnionAddress vectors and 43 JSON-input vectors run against PHP, TypeScript and an independently written Python validator. Valid parsed JSON is compared with golden values, not just accepted.

Address validation checks exact lowercase wire syntax, Base32 decoding, version 3 and the Tor SHA3-256 checksum. No official example address is contacted. The synthetic address uses an RFC 8032 test public key, not a production key.

Strict JSON rejects duplicate decoded member names, invalid UTF-8, a BOM, lone surrogates, floating/scientific numeric tokens, unsafe integers, excessive depth/member/array/string counts, oversized input and non-object roots. These checks precede record-specific schema validation, which is not implemented yet.

## Concrete portability decisions requiring protocol review

- Signed wire addresses are rejected if uppercase; no silent rewriting of signed fields. User-entered configuration normalization can be a separate boundary.
- Negative-zero numeric tokens are rejected in the experimental integer-only profile.
- NUL-containing object member names are rejected across implementations. PHP cannot represent NUL-prefixed stdClass properties; rejecting rather than silently rewriting preserves portable semantics. This does not reject escaped NUL in every generic string; record schemas still need their own content rules.
- Compound-invalid input can produce different first-error classifications. The shared corpus currently fixes inputs with one primary violation; exhaustive parser ambiguity/fuzzing remains open.
- TypeScript SHA3 is injected through a provider boundary. Node's native provider is used only in offline tests. No browser SHA3 provider or browser compatibility claim is included.

These are explicit proposals, not approved protocol-freeze decisions.

## Reproduce

Python 3.11+ and Node 24+ are used for this experimental harness. TypeScript 7.0.2 is locked as a development-only dependency, with no browser runtime dependencies. PHP tests use a pinned official PHP CLI container digest; Docker is test tooling, not a deployment requirement.

```sh
cd web-local-search-client
npm ci --ignore-scripts
cd ..
docker pull php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3
python tools/run_input_conformance.py --php-container --report docs/conformance/input-subset-report.json
```

Without Docker, an installed compatible PHP CLI can run `python tools/run_input_conformance.py`. Missing tools fail the harness rather than count as a skipped pass.

Actual PHP/runtime support-matrix approval, static analysis, full dependency audit, fuzz campaigns and current Tor Browser desktop/Android testing remain outstanding.
