# 21 - Software Supply Chain Reproducible Build and Release Provenance Standard

Source: https://docs.google.com/document/d/1xG4VpHZZhoW_pbdNipxqTXT4OUb9oJ20P7OqPeFyoj8/edit?usp=drivesdk

Source modified: 2026-10-11T07:12:00.377Z

---

ONION SERVICE DISCOVERY
SOFTWARE SUPPLY CHAIN, REPRODUCIBLE BUILD AND RELEASE PROVENANCE STANDARD


1. Purpose
A security protocol is only as trustworthy as the software distributed to implement it. This document defines source, dependency, build, CI, SBOM, artifact signing, provenance, release and verification controls.


2. Reference Standards
Current baseline at October 2026:
SLSA 1.2 concepts for provenance and supply-chain assurance.
CycloneDX for SBOM.
These are references, not automatic certifications.


3. Source of Truth
One canonical source repository.
Release artifacts must map to:
exact source revision
version/tag
clean working tree
documented toolchain
locked dependencies.


4. Source Protections
Where hosting supports:
MFA for maintainers
branch protection
review for security-critical areas
restricted release/tag permissions
audit logs
secret scanning.


5. Code Ownership
Define reviewers for:
protocol/schemas
crypto
PHP transport/security
Web Client CSP/network behavior
CI/release.
A single AI-generated approval does not count as human review.


6. Dependency Locking
PHP:
composer.lock committed.


Client:
package manager lockfile committed.


No production build performs unconstrained “latest” dependency resolution.


7. Dependency Inventory
For each direct dependency:
name
version range/pin
purpose
runtime/dev
license
homepage/source
security status
transitive count
replacement cost
review date.


8. Dependency Minimization
Reject dependencies that only save trivial code when they add significant attack/supply-chain surface.
Never reimplement cryptography to avoid a proper dependency.


9. Composer Plugins/Scripts
Composer plugins and scripts can execute code.
Inventory and review.
Production CI must not blindly enable newly introduced plugin execution.


10. JavaScript Install Scripts
postinstall/preinstall scripts reviewed.
CI can use package-manager controls to reduce unexpected scripts where compatible.
Any required script documented.


11. Package Registries
Use official/approved registries.
No arbitrary package source introduced by untrusted config.
Registry mirrors, if used, documented.


12. Typosquatting
Dependency name changes reviewed manually.
Lockfile diff part of review.


13. Dependency Updates
Automated update tools may create proposals, but:
tests
security review for major updates
lockfile diff
release note when behavior/permissions changes.


14. Vulnerability Scanning
CI:
Composer audit or equivalent
JS dependency audit/SCA
OS/container scan if images shipped
manual review of high/critical findings.


Scanner absence/failure cannot be interpreted as “no vulnerabilities”.


15. SBOM
Generate per release:
server.cdx.json
client.cdx.json


Include direct/transitive dependencies where tooling supports.
Record CycloneDX spec/tool version.
Validate generated SBOM.


16. Build Toolchain
Pin/document:
PHP version
Composer version
Node runtime
package manager
TypeScript
bundler
test tools
OS/container build image
SQLite where material.


17. Hermeticity Goal
Build should minimize undeclared network/input dependencies.
Release build ideally uses locked cached dependencies and explicit sources.
Document unavoidable network dependencies.


18. Reproducible Build Goal
A second clean environment should produce functionally identical and, where feasible, byte-identical artifacts.
If byte identity is not feasible due signing/timestamps:
document sources of nondeterminism and verify extracted payload equivalence.


19. Timestamps
Avoid embedding wall-clock build time into unsigned content unless required.
Release metadata can carry timestamp outside deterministic payload.


20. Source Maps
Policy explicit.
If distributed:
ensure no secret/local path leakage.
If excluded:
debug process still supports auditability.


21. Generated Code
Schemas/types/client code generators:
generator version pinned.
Generated files reproducible.
Do not hand-edit generated output without documented mechanism.


22. Protocol Test Vectors
Release build verifies vectors before packaging.
A release with vector mismatch is blocked.


23. CI Least Privilege
Default CI jobs read-only source.
Only release job gets artifact/signing permissions.
Fork/untrusted PR jobs never receive release secrets.


24. CI Action Pinning
Third-party CI actions pinned to immutable commit digest where platform allows.
Review updates.


25. Secret Handling
Secrets in platform secret store.
No secrets in:
source
artifacts
logs
test snapshots
SBOM
source maps.


26. Secret Scan
Pre-commit optional; CI mandatory.
Scan source and release artifacts where appropriate.


27. Build Isolation
Release build separated from untrusted PR execution.
Never reuse compromised mutable workspace without clean checkout.


28. Artifact List
Server:
source/package archive if used
composer.lock
schemas
migrations
docs/version manifest
SBOM
checksums
provenance/attestation.


Client:
static Web Client bundle
source archive
manifest
lockfile
SBOM
checksums
permission inventory
provenance.


29. Checksums
SHA-256 checksum file for all release artifacts.
Checksum file itself included in signed/provenance release process.


30. Artifact Signing
Use appropriate project release signing infrastructure selected by human maintainers.
Signing private keys:
not stored in repository
restricted
rotation/recovery documented.


Do not claim cryptographic signing exists if only hosting platform metadata exists.


31. Provenance
Aim to generate verifiable build provenance aligned with current SLSA specification.
At minimum evidence:
artifact digest
source URI/revision
build workflow identity
builder environment
parameters
dependencies/materials where supported.


32. SLSA Claims
Never state “SLSA Level X” unless requirements have been evaluated and evidence retained.
Use wording:
“SLSA-aligned provenance generated” if that is the true limited property.


33. Release Manifest
release-manifest.json:
project
software version
supported protocol versions
source revision
build tool versions
artifact filenames/hashes
SBOM filenames/hashes
schema version
DB schema
client storage schema
release date
known exceptions IDs.


Sign or include under release provenance.


34. Version Injection
Single source of software version.
Server and client display/report build version derived deterministically.
No conflicting manually edited version files.


35. Permission Inventory
Client release includes static-asset, CSP and network-destination diff against the previous release.
Added permission blocks release until human review.


36. Dependency Diff
Release evidence includes direct dependency changes from previous release.
Crypto/network/storage dependency changes highlighted.


37. Schema Diff
Protocol JSON Schemas compared.
Any normative difference maps to accepted Protocol Change Proposal/version analysis.


38. Database Migration Diff
Release notes list migrations.
Migration files immutable after release.
Checksums validated.


39. Clean Build
Release process begins from clean source checkout.
CI fails if untracked/generated unexpected files affect package.


40. Test Gate
Release build only after:
unit
integration
conformance
E2E
security regression
static analysis
dependency scans
required fuzz campaign/results
pass.


41. Fuzz Evidence
Fuzzing may run separate scheduled jobs.
RC requires recent clean fuzz report under defined duration/corpus.
Crash => release blocker until triage.


42. Reproducibility Verification
At RC:
Build A official environment.
Build B independent clean environment.
Compare:
file list
hashes where deterministic
normalized contents otherwise.
Document differences.


43. Third-Party Binary Avoidance
Do not commit opaque compiled crypto/network binaries without source and provenance.
If a necessary third-party binary/tool is used only at build time, pin checksum/source.


44. Container Images
If distributed:
minimal base
pinned digest
SBOM including OS packages
non-root
image scan
signed digest/provenance where available.


45. Base Images
No floating latest tag in reproducible release definition.


46. Release Credentials
Separate from development credentials.
Rotation.
Incident response.
Two-person approval if feasible for high-assurance releases.


47. Tag Protection
Version tag immutable by policy.
If release withdrawn, create advisory; do not silently retag different code under same version.


48. Release Corrections
Bad artifact:
withdraw/mark compromised
new version
incident record
do not replace bytes under same version filename unnoticed.


49. Archive
Retain:
source tag
artifacts
hashes
SBOM
provenance
test report
gate matrix
known issues
security exceptions.
Long-term enough to investigate incidents.


50. Verification Instructions
Users/operators get instructions to verify:
downloaded artifact hash/signature/provenance according to chosen mechanism.
Keep instructions realistic for Android/desktop/server contexts.


51. Dependency End-of-Life
Support matrix flags EOL dependencies/runtime.
Upgrade before unsupported security window ends.


52. Build Network Inventory
Document domains/registries contacted during dependency install/build.
Unexpected network calls fail/flag in high-assurance CI where practical.


53. License Compliance
Generate dependency license report.
Human review incompatible/unknown license.
Project license decision explicit.


54. Security Advisory Mapping
When dependency vulnerability appears:
determine reachable/affected?
document.
Do not blindly dismiss because scanner says transitive.
Do not automatically panic if unreachable; evidence-based triage.


55. AI-Generated Code
AI output treated like any untrusted contribution:
review
tests
static analysis
license/provenance awareness.
No “AI wrote it” exemption.


56. Generated Documentation
Release documentation tied to source revision.
No post-release untracked manual edits to normative protocol without versioned source update.


57. Release Dry Run
Before first production:
perform full release process without publishing.
Find missing secrets/permissions/artifacts.


58. Compromised CI Incident
Freeze releases.
Rotate credentials.
Audit workflow changes.
Rebuild from trusted revision/environment.
Compare artifacts.
Issue advisory if malicious artifact possible.


59. Maintainer Account Incident
Revoke sessions/tokens.
Review commits/tags/releases.
Re-establish trusted source revision.
Rotate signing/release credentials.
Record incident.


60. Supply Chain Gate Matrix
PASS evidence for:
SCM protection
lockfiles
dependency inventory
SCA
secret scan
SBOM
artifact hashes
provenance
clean build
reproducibility check
release credential review
license review
release archive.


61. Current External Refresh
Before release re-check:
current SLSA version
CycloneDX supported spec/tool
PHP support
Node/package manager support
security advisories
CI action revisions.


62. Acceptance
Supply-chain design is complete only when a third party can take the tagged source, documented toolchain and lockfiles, build the software, validate protocol tests, compare expected artifact structure, inspect SBOM and verify published hashes/provenance witho


63. WEB CLIENT RELEASE ARTIFACTS — ARCHITECTURE REVISION
The project no longer builds or publishes a browser extension package.


Client release artifacts are:
- static Web Local Search HTML/CSS/JavaScript assets;
- asset-manifest with SHA-256 hashes;
- source archive;
- lockfile;
- SBOM;
- checksums;
- provenance/attestation;
- CSP/security-header reference;
- browser compatibility report.


64. STATIC-ASSET DIFF GATE
Release evidence compares:
- asset file list;
- hashes;
- remote URL references;
- CSP;
- network destination inventory;
- dependencies;
against previous stable release.


Unexpected remote runtime dependency or query-transmission code is a release blocker.


65. NODE DEPLOYMENT VERIFICATION
Reference Node security self-check verifies installed/served Web Client assets match the release asset manifest unless operator intentionally uses a documented custom client.


Custom client deployments cannot claim unmodified Reference Web Client privacy behavior without separate review.


66. NO EXTENSION SIGNING CLAIM
Remove all extension signing/store/distribution requirements.
Release signing/provenance applies to static Web Client bundle and server artifacts.
ut trusting an undocumented developer machine.
