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

The package smoke check explicitly executes only a trusted local candidate:
`python tools/run_candidate_smoke.py ARTIFACT --expected-source COMMIT
--execute-trusted-local-development-candidate`. It first validates archive
structure, provisions the Composer runtime lock with plugins/scripts disabled,
and exercises packaged protocol validation, Client Host and the operator
initialize/diagnose/publish/backup/restore workflow in disposable containers.
Dependency provisioning needs HTTPS; application checks use `--network none`.
A source-commit match is not authentication: verify trust before permitting code
execution. These synthetic checks do not certify a deployed host or live Tor.
