# Draft offline signing workflow

Keep the Service Identity key on a separate administrator machine. The public
web host receives only signed public records. Node operational keys and release
signing keys belong to separate trust domains. Tor private keys are never read
by this application.

The CLI supports `key:create <service|node> <key-path> --passphrase-file <file>`
and `record:sign <role> <key-path> <record> --passphrase-file <file>`.
Use a dedicated private directory (0700) and an existing passphrase file (0600).
The passphrase is read from that file, never from a command-line value or
ordinary environment variable. Avoid backing up it alongside the encrypted
key. An interactive terminal prompt remains to be implemented.

The authenticated version-1 envelope uses libsodium Argon2id13 (three operations,
64 MiB), a random 16-byte salt, XChaCha20-Poly1305-IETF and a random 24-byte
nonce. Role, public key, timestamp, algorithm choices and parameters are AAD.
Unlock rejects unsupported parameters before invoking the KDF. The encrypted
64-byte Ed25519 secret has an 80-byte ciphertext. Key material is erased on
provider destruction where PHP/libsodium permit; this is not a guarantee that
runtime copies or swap never retain secrets. There is no generic sign-bytes API.

Service providers allow only manifest, key rotation and onion migration;
Node providers allow only descriptor, direct observation and snapshot descriptor.
The signed key must match the provider. Rotation requires both signatures:
old provider signs normally, new provider signs with `--rotation-confirmation`.
Signing a partial rotation does not validate it; run protocol verification once
both signatures are present. The public host must verify every transferred
record before admission.

Back up the encrypted envelope separately, restore it offline, and verify the
public key plus a signed test record before relying on it. Automated restore,
wrong-passphrase, AAD-tamper, parameter-cap and role-separation tests pass.
Hardware/external signer integration, interactive unlock, deployment benchmarking
and human key-custody review remain open.
