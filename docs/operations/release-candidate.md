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
