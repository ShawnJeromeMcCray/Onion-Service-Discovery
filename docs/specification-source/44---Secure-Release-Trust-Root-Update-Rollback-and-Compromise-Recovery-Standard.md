# 44 - Secure Release Trust Root Update Rollback and Compromise Recovery Standard

Source: https://docs.google.com/document/d/19x6FUX2YoWs7IFA2cbKDVt7gNf7wB3FAklcYmz7hlII/edit?usp=drivesdk

Source modified: 2026-10-12T12:30:40.493Z

---

ONION SERVICE DISCOVERY
SECURE RELEASE TRUST ROOT, UPDATE ROLLBACK AND COMPROMISE RECOVERY STANDARD


1. Purpose
Protect software distribution against malicious artifact replacement, rollback, freeze, signing-key compromise and maintainer-account compromise.


2. Separation
Runtime Service/Node keys MUST NOT be reused for software release signing.


3. Threats
- malicious release artifact;
- compromised Git hosting account;
- compromised CI;
- stolen release signing key;
- rollback to vulnerable version;
- indefinite freeze on old metadata;
- partial repository view;
- artifact substitution after review.


4. Baseline
Every release:
- immutable source revision/tag;
- reproducible build evidence;
- artifact SHA-256;
- SBOM;
- provenance;
- signed release metadata;
- archived gate matrix.


5. Update Framework
Before Production Stable, implement or adopt a mature update-metadata framework rather than inventing one.


Preferred reference:
The Update Framework (TUF) or a comparably reviewed system that provides:
- root trust metadata;
- target metadata;
- snapshot consistency;
- freshness/timestamp metadata;
- expiry;
- threshold signatures;
- rollback/freeze protection.


6. No Custom Update Crypto
Do not design an ad-hoc “latest.json + signature” update security mechanism if mature update-framework tooling is available.


7. Trust Roles
Recommended TUF-style separation:
Root — offline, threshold-protected.
Targets — signs approved release targets; offline/strongly protected.
Snapshot — consistency metadata.
Timestamp — short-lived freshness metadata; may be online.


8. Threshold
Root and high-impact release roles SHOULD use threshold signatures with keys held separately where operationally feasible.
Exact threshold is a Human Security Gate.


9. Offline Root
Root trust keys remain offline except intentional rotation/recovery ceremonies.


10. Release Provenance
Sigstore/Cosign-style keyless transparency MAY supplement release evidence/provenance.
It MUST NOT silently replace the project’s explicit update trust-root policy without review.


11. Git Host Independence
A GitHub compromise alone should not be sufficient to make a malicious release trusted by a correctly verifying operator/client.


12. Artifact Verification
Operator tooling:
release:verify <artifact>
checks:
- trusted metadata chain;
- artifact hash/size;
- signature/threshold;
- version freshness;
- provenance where configured.


13. Rollback
Verifier stores highest trusted metadata/software version where persistent state exists.
Older vulnerable release MUST NOT be accepted as current merely because correctly signed historically.


14. Freeze
Freshness metadata has expiry.
If fresh metadata cannot be obtained:
report/update failure;
do not silently claim current.


15. Root Rotation
Document:
- planned rotation;
- compromised-key rotation;
- threshold recovery;
- old/new trust transition;
- offline recovery copy.


16. Compromise
If CI or signing identity compromised:
- freeze publication;
- revoke/rotate affected release credentials;
- determine first/last potentially affected release;
- rebuild from trusted revision/environment;
- issue advisory;
- preserve evidence.


17. Release Tag Immutability
Never replace artifact bytes under same version/tag.
Correction requires new release version.


18. Mirrors
Release artifacts and metadata MAY be mirrored.
Trust derives from metadata/signatures, not mirror identity.


19. No Auto-Execute
Onion Service Discovery MUST NOT download and automatically execute unverified code.
Operator-controlled update remains default unless a separately reviewed updater exists.


20. Bootstrap Root
Initial trusted root/verification material is distributed through a documented out-of-band path bundled with a trusted release/source.
Bootstrap is explicitly part of threat model.


21. Expiry and Clock
Freshness checks require reasonable clock.
Clock failure produces explicit degraded verification rather than accepting arbitrarily expired metadata.


22. Release Ceremony
For stable release:
- clean source;
- gates PASS;
- build;
- independent rebuild;
- artifact hash;
- SBOM/provenance;
- target metadata signing;
- threshold verification;
- publication;
- post-publication verification from public mirror.


23. CI Privilege
Untrusted pull requests never access release credentials.
Release job uses least privilege and protected environment approvals.


24. Recovery Drill
Before first Production Stable:
simulate:
- compromised GitHub release;
- stolen online timestamp key;
- lost one root key;
- rollback mirror;
- stale/frozen metadata.
Verify expected behavior.


25. Acceptance
PASS requires:
- explicit release trust root;
- rollback/freeze protection;
- offline/threshold high-value signing roles;
- operator verification command;
- compromise/recovery runbook;
- Git host not sole trust anchor.
