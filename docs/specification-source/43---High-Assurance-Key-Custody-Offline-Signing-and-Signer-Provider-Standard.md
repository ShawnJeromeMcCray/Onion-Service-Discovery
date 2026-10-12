# 43 - High-Assurance Key Custody Offline Signing and Signer Provider Standard

Source: https://docs.google.com/document/d/1jZ238Q7f5v2aFBnvR4HN-005fcW4e_ZhCtdZ7XvXpgU/edit?usp=drivesdk

Source modified: 2026-10-12T12:30:35.466Z

---

ONION SERVICE DISCOVERY
HIGH-ASSURANCE KEY CUSTODY, OFFLINE SIGNING AND SIGNER PROVIDER STANDARD


1. Purpose
Reduce the probability and blast radius of private-key theft, especially for the long-lived Service Identity key.


2. Key Classes
A. Tor Onion private key — owned exclusively by Tor.
B. Service Identity private key — highest-value Onion Service Discovery key.
C. Node Identity private key — online operational key.
D. Release/update signing keys — separate supply-chain trust domain.


3. Core Rule
The Service Identity private key SHOULD NOT reside on the public web-serving host in normal production operation.


4. Preferred Service-Key Profile
OFFLINE-SERVICE-SIGNING:
- generate Service Identity key in a dedicated admin environment;
- encrypt backup immediately;
- create/sign Manifest revisions, key rotations and Onion migrations offline;
- transfer only signed public records to the Onion host;
- web/PHP runtime never receives Service private key.


5. Online Service-Key Exception
If an operator explicitly chooses online automatic signing:
- mark deployment lower assurance;
- use a SignerProvider interface;
- never expose raw key to application modules that do not need signing;
- restrict signer to specific record domains/types.


6. SignerProvider Interface
Backends:
- OfflineSigner (manual/admin workflow);
- ProtectedFileSigner;
- HSM/PKCS11Signer;
- TPM/OS-keystore-backed signer where practical;
- ExternalSigner over authenticated local IPC;
- TestSigner only for fixtures.


7. Protected Software Key File
If private key is stored encrypted in software:
- authenticated encryption;
- explicit versioned envelope;
- random salt;
- random nonce;
- integrity-protected metadata;
- no raw key in config/database/logs.


8. Recommended Software-Key Envelope
For PHP/libsodium deployments:
KDF: Argon2id via sodium_crypto_pwhash.
AEAD: XChaCha20-Poly1305-IETF via sodium_crypto_aead_xchacha20poly1305_ietf_*.
Nonce: random 24 bytes.
Salt: random SODIUM_CRYPTO_PWHASH_SALTBYTES.
AAD includes:
format version;
key type;
public-key fingerprint;
creation timestamp;
KDF/AEAD identifiers.


Exact parameters MUST be benchmarked and frozen in the key-file format specification before release.


9. Passphrase Handling
Do not:
- store passphrase next to ciphertext;
- hard-code it;
- put it in source;
- print it;
- place it in ordinary environment variables by default.


Preferred unlock:
- interactive operator input;
- system credential facility;
- secret manager;
- TPM/HSM/KMS;
- protected local IPC.


10. Unattended Restart Reality
If the same compromised host can automatically retrieve both ciphertext and KEK, encryption at rest does not protect against a fully compromised running host.
Documentation MUST state this.


11. Node Identity Key
Node Identity must usually sign online.
Default profiles:
STANDARD: 0600/least-privilege file owned by dedicated account.
HARDENED: ProtectedFileSigner or OS secret store.
HIGH-ASSURANCE: HSM/TPM/KMS/external signer.


12. Service vs Node Blast Radius
Compromised Node key:
can forge Node observations/checkpoints until revoked;
cannot forge Service Identity records.


Compromised Service key:
can forge Service continuity state and is therefore treated as significantly higher severity.


13. Privilege Separation
Web request handling MUST NOT need Service Identity private key in the preferred profile.
Sync/discovery workers MUST NOT need Service Identity private key.
Only signing workflow accesses it.


14. Local Signer IPC
If ExternalSigner is used:
- Unix-domain socket or equally restricted local IPC;
- peer credential validation;
- allowlisted record types;
- domain separation enforced inside signer;
- signer canonicalizes/validates payload before signing;
- no arbitrary “sign these bytes” RPC in production.


15. HSM/TPM/KMS
Optional high-assurance providers MAY be supported.
The application consumes a signer abstraction, not exported private key bytes.
No provider becomes a Protocol dependency.


16. Key Generation
Use mature cryptographic library CSPRNG/key-generation API.
For PHP reference:
sodium_crypto_sign_keypair or equivalent vetted API.
Never derive signing keys from passwords or deterministic application metadata.


17. Backup Encryption
Offline backup:
- authenticated encryption;
- independent passphrase or KEK;
- at least two tested copies where operator threat model permits;
- geographically/administratively separated if appropriate.


18. Backup Verification
Periodically verify:
- decryptability;
- public-key match;
- record-signing test using synthetic/non-production payload;
- media integrity.
Never print private key.


19. Recovery Drill
Before Production Stable:
simulate:
- lost web host;
- lost Node key;
- lost one Service-key backup;
- restore from remaining encrypted backup;
- sign a new valid test revision.


20. Key Compromise Detection
Signals:
- unexpected revision/fork;
- unexpected rotation/migration;
- unknown release signature;
- signer audit anomaly.


Detection cannot guarantee discovery before attacker use.


21. Compromise Response
Service key:
- stop publication/signing;
- preserve evidence;
- if legitimate key still controlled, issue reviewed rotation;
- expect possible Fork if attacker can also sign;
- do not suppress conflicting evidence.


Node key:
- stop signing;
- rotate Node identity according to Protocol policy;
- publish incident timeline;
- invalidate source trust locally as appropriate.


22. Memory Handling
Minimize plaintext key lifetime and copies.
Use sodium_memzero where meaningful.
Do not claim perfect zeroization in PHP/managed runtimes.


23. Swap/Core Dumps
High-assurance deployment SHOULD:
- disable/limit core dumps for signer process;
- restrict ptrace/debug access;
- use OS memory protections where practical;
- evaluate encrypted swap/locked memory support.


24. Secret Scanning
CI scans source/build artifacts.
Private key fixtures are isolated, explicitly test-only and never production-derived.


25. Audit
External security review MUST inspect:
key file format;
KDF parameters;
AEAD usage;
nonce generation;
signer isolation;
backup/recovery;
IPC signer authorization.


26. Security Profiles
PUBLISH-ONLY-HIGH:
offline Service signer; no private Service key on web host.


NODE-STANDARD:
online Node key file with least privilege.


NODE-HARDENED:
protected Node key + separated worker/signer.


NODE-HIGH:
hardware/external signer.


27. Acceptance
PASS requires:
- offline Service signing supported and documented as preferred;
- SignerProvider abstraction;
- encrypted software-key format if online encrypted storage supported;
- HSM/external signer interface boundary;
- no web process dependency on Service private key in preferred profile;
- compromise/recovery tests;
- honest residual-risk documentation.
