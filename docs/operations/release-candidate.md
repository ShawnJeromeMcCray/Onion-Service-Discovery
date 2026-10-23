# Reproducible release candidate

`python3 tools/build_release_candidate.py --output /private/candidate-a` builds
an unsigned **development source/browser artifact** from a clean committed
checkout and the locked browser toolchain. PHP and Python dependencies are
provisioned separately from their locks; this is not a turnkey runtime image.
The public synthetic demo records are TEST ONLY and never production identities.

The artifact contains tracked source, deterministic built browser assets,
CycloneDX 1.6 dependency inventory, build metadata and a file SHA-256 manifest.
It excludes .git, runtime, private keys, vendor, node_modules and local profiles.
Timestamp, owner, permissions, archive order and gzip header are fixed. Generate
it twice in independent build directories/toolchain environments and compare
SHA256SUMS. One local double build shows local reproducibility, not independence.
The inventory includes locked development/build components as optional and
runtime components as required; it is not a vulnerability scan or a claim that
all listed components are bundled into the browser.

The accompanying in-toto/SLSA-format candidate statement records the source
commit and artifact hash. It is explicitly **unsigned and unattested**: it does
not establish an authenticated CI builder identity or satisfy production
provenance acceptance. Mature TUF verification is available separately, but a
production offline threshold root/targets ceremony and human decisions remain
open. Never interpret the generated metadata or file checksums as a signature.

Before public release, close the gate matrix: supported runtime/platform and live
Tor Browser/Android evidence; independent protocol/crypto/model/security reviews;
operational recovery and incident drill; approved root and release key custody;
authenticated builder provenance, dependency vulnerability/license review;
immutable version/tag and signed TUF target metadata. Do not invent missing
reviewers or use ephemeral test signers as a production authority. This tool
never signs, tags, deploys or publishes an artifact automatically.


Trusted push builds additionally request GitHub's established keyless provenance
and SBOM attestations through digest-pinned actions. Pull-request builds do not
sign attestations. The local candidate statement remains unsigned; authenticated
CI evidence is separate and must actually succeed before claiming availability.
These attestations identify a development build, not production approval or TUF
release authorization. Verify the artifact with `gh attestation verify ARTIFACT
--repo ShawnJeromeMcCray/Onion-Service-Discovery`, checking the expected workflow,
source commit/ref and invocation against the intended reviewed release. The
production acceptance policy must reject an unreviewed development ref even if
it has a valid GitHub build attestation. An offline/verifiable Sigstore bundle and
approved trust/policy are required when verification happens without connectivity;
do not treat an unsigned local sidecar as such a bundle.

For structural checks install the hash-locked Python validator and audit-tool
requirements, then run `python tools/verify_candidate.py ARTIFACT`. It checks
bounded archive entries without extraction, file-manifest hashes and the strict
CycloneDX 1.6 schema. It never executes the package and explicitly reports that
artifact authentication was not performed. TUF/signature verification is separate.

Trusted-push artifacts preserve `provenance.sigstore.json` and
`sbom.sigstore.json` from the attestation actions alongside the candidate.
Preservation alone does not verify their certificates, transparency proofs or
workflow identity. Use a supported GitHub CLI with attestation verification;
older installations without `gh attestation` are insufficient.

## Development runtime package (D-029)

`python3 tools/build_release_candidate.py --profile runtime --output DIR` adds the
PHP runtime dependencies to the same deterministic archive
(`onion-service-discovery-development-runtime.tar.gz`, profile `runtime-v1`), so
an operator needs neither Composer nor npm. It requires `node-php/vendor` from
`composer install --no-dev --prefer-dist` of the committed lock, with
`COMPOSER_ROOT_VERSION=dev-main` so the install does not depend on the checkout,
and refuses source installs, development packages and any difference from the
lock. `release/DEVELOPMENT-PREVIEW.txt` and the build metadata mark it as an
unsigned development preview; `tools/verify_candidate.py` rejects a runtime
archive without that marking or with development packages.

CI builds it twice from two independent dependency installs, compares both
archives byte for byte, checks the structure and runs the packaged protocol,
Client Host, operator and walkthrough checks as shipped, without Composer or
network. For every merge to `main` (owner decision D-029, 2026-10-14) it also
requests GitHub build-provenance and SBOM attestations, verifies them and keeps
the package with its evidence as the workflow artifact
`development-runtime-preview-COMMIT` for 90 days. Pull requests and other
branches publish nothing.

To use a preview, pick the merge commit you reviewed and run, with a supported
GitHub CLI signed in to GitHub:

```sh
gh run download --repo ShawnJeromeMcCray/Onion-Service-Discovery --name development-runtime-preview-COMMIT --dir osd-preview
cd osd-preview
gh attestation verify onion-service-discovery-development-runtime.tar.gz --repo ShawnJeromeMcCray/Onion-Service-Discovery \
  --signer-workflow ShawnJeromeMcCray/Onion-Service-Discovery/.github/workflows/foundation.yml --source-ref refs/heads/main --source-digest COMMIT
sha256sum -c SHA256SUMS
tar -xzf onion-service-discovery-development-runtime.tar.gz
php onion-service-discovery/node-php/bin/onion-service-discovery help
```

A passing verification proves which workflow built the archive from which
commit. It does not prove that the code was reviewed or is production-ready;
stable releases use the same keyless Sigstore signing through GitHub
attestations (owner decisions D-030 and D-031); production approval (D-050)
remains a separate gate.

## Static client bundle (D-028)

`python3 tools/build_release_candidate.py --profile static-client --output DIR`
packs only the built browser client (`web-local-search-client/dist/` with its
`build-manifest.json`), the licence notices and the client contract
(`onion-service-discovery-static-client.tar.gz`, profile `static-client-v1`). Its
SBOM lists only what the browser bundle contains. `tools/verify_candidate.py`
rejects such an archive if it holds server code or lacks the preview marking.

CI builds it twice, compares both archives byte for byte and checks the structure.
For every merge to `main` (owner decision D-028, 2026-10-22) it requests a GitHub
build-provenance attestation, verifies it and keeps the bundle as the workflow
artifact `static-client-preview-COMMIT` for 90 days. Verify it like the runtime
package, with `onion-service-discovery-static-client.tar.gz` as the subject.

The bundle's `config.json` has no sources, so a static host builds the client
again from the same commit with its own configuration. Only `config.json` and the
CSP line in `index.html` change. `client.js`, `verifier-worker.js` and
`client.css` must keep the hashes in the bundle's `build-manifest.json`; compare
them before you serve the page. On a PHP Client Host, `doctor` checks the served
files against the build manifest (`client_assets`; `CLIENT_ASSETS_MODIFIED`
fails it).

## Package smoke check

The package smoke check explicitly executes only a trusted local candidate:
`python tools/run_candidate_smoke.py ARTIFACT --expected-source COMMIT
--execute-trusted-local-development-candidate`. It first validates archive
structure, provisions the Composer runtime lock with plugins/scripts disabled,
and exercises packaged protocol validation, Client Host and the operator
initialize/diagnose/publish/backup/restore workflow in disposable containers.
Dependency provisioning needs HTTPS; application checks use `--network none`.
A runtime package is not provisioned: it must answer `help` and pass the same
checks as shipped.
A source-commit match is not authentication: verify trust before permitting code
execution. These synthetic checks do not certify a deployed host or live Tor.
