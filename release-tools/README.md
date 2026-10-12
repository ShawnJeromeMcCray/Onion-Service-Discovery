# Offline release verification

This operator tool uses **python-tuf 7.0.1**, with hash-locked dependencies.
It verifies an already downloaded artifact against an offline mirror of TUF
metadata. It neither downloads nor executes application code.

```sh
python3 -m venv /private/osd-release-verifier
/private/osd-release-verifier/bin/python -m pip install --require-hashes -r release-tools/requirements.lock
/private/osd-release-verifier/bin/python release-tools/verify_release.py package.tar.gz \
  --repository /private/downloaded-release-mirror \
  --trusted-root /private/out-of-band-root.json \
  --state /private/persistent-verifier-state \
  --target onion-service-discovery-source.tar.gz
```

Obtain the initial root through independently authenticated, out-of-band
channels. A root bundled with an untrusted download cannot authenticate that
download. This repository deliberately has no production root or production
release private keys: the governance decisions and key ceremony are open.
The test suite creates ephemeral synthetic keys, never a production authority.

The mirror contains `metadata/timestamp.json`, versioned snapshot/targets/root
metadata and any delegations required by TUF. Targets must authorize the
artifact's exact length and SHA-256 and carry signed custom fields:
`release_sequence` (positive integer), `artifact_profile` (lowercase ASCII
identifier), and `build_epoch` (UTC Unix seconds). Sequence numbers increase
within each profile; identical sequence numbers cannot replace bytes.

Persistent private state is mandatory. It stores the original root anchor,
verified TUF metadata and each artifact profile's software high-water mark.
Retain this state across upgrades and restore it from an authenticated backup;
deleting it discards rollback protection. A different bootstrap file is refused,
including during normal root rotation. TUF authenticates successive roots using
the old and new thresholds. The library's internal versioned root symlink is
allowed only within its metadata directory; arbitrary cache links are rejected.

Metadata expiration, threshold signatures, metadata rollback and signed
snapshot consistency are checked by TUF. Application policy additionally
rejects software sequence rollback/replacement, significant local clock
rollback, and a clock preceding the signed build time. Operators need a
reasonable clock; expiration cannot detect all freezes with an incorrect clock.
Bounds include 128 MiB artifact/cache, 512 cached files, 32 root rotations per
refresh, and 16 artifact profiles. Authentication success does not establish
that an authorized release is free of vulnerabilities.

## Recovery drill

1. Preserve the failed artifact, mirror, error code and verifier state privately.
2. Repair the clock if necessary. Do not erase high-water marks or accept a root
   supplied only by the failing mirror.
3. Obtain an authenticated, current mirror and retry with the original bootstrap
   and existing state. A normal root rotation needs both required thresholds.
4. If trust authorities are lost or compromised, follow the separately approved
   out-of-band rebootstrap process. No such production process is approved yet.
5. Confirm PASS, target, hash, profile and sequence before the operator separately
   deploys the artifact. The verifier never installs or runs it.

Run `python release-tools/test_release_verifier.py`. Fourteen synthetic tests
cover successful verification, artifact substitution, insufficient thresholds,
expiration, metadata and software rollback, same-version replacement, root
rotation, changed bootstrap, clock rollback and filesystem/path boundaries.
Actual multi-person key custody, independent review, production signing,
provenance acceptance and incident drills remain release gates.
