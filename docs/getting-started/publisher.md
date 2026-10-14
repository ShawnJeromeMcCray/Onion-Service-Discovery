# Publish your existing Onion Service

This walkthrough creates and installs a genesis Manifest and verifies local
publication. It does not promise automatic admission to public Discovery Nodes.
Provision [PHP dependencies](installation.md) first. Your existing v3 Onion is
managed by Tor; the Service identity key created here is a separate application
key. Work on a staging copy until the release gates pass.

## 1. Record your public Onion

Copy only the existing service's public `hostname` to an administrator-controlled
file named `hostname`. Its content is one canonical 56-character v3 address plus
`.onion`, optionally followed by a newline. Do not grant OSD access to Tor's
private key directory. In the following commands, replace the absolute paths and
`YOUR_CANONICAL_V3_ADDRESS.onion` with your real values.

## 2. Create and back up a Service key offline

On your separate administrator machine, in an owner-private directory:

```sh
umask 077
mkdir -p /absolute/private/osd-service
```

Create `/absolute/private/osd-service/passphrase` with your password manager or
another private input method. Give it mode 0600; do not put the passphrase in a
shell command, ordinary environment variable, Git or an issue. Keep an encrypted
key backup and the passphrase separately. Then run from the repository root:

```sh
php node-php/bin/onion-service-discovery key:create service /absolute/private/osd-service/service.key --passphrase-file /absolute/private/osd-service/passphrase > /absolute/private/osd-service/key-result.json
```

Expect JSON `status: PASS` and `public_key: ed25519:...`. This output is the public
key, not the encrypted key file. Copy that public-key string for the next step.
Key creation refuses an existing destination. Test your encrypted backup before
relying on it; see [offline key custody](../signing/offline.md).

## 3. Describe the service and sign its first Manifest

Create `/absolute/private/osd-service/metadata.json`:

```json
{
  "service": {
    "name": "My public service",
    "description": "A concise description of the public service.",
    "languages": ["en"],
    "categories": []
  },
  "discovery": {
    "publish_service": true,
    "mirror_directory": false,
    "discover_services": false,
    "sync_with_peers": false
  }
}
```

This explicitly opts the metadata into public discovery. Use only metadata you
intend to publish. Category identifiers come from the
[versioned registry](../../protocol/registries/categories-v1.json); language tags
use the draft lowercase RFC 5646 grammar. Empty categories are permitted.
With your real Onion and the **public** Service key string:

```sh
php node-php/bin/onion-service-discovery manifest:init YOUR_CANONICAL_V3_ADDRESS.onion ed25519:YOUR_SERVICE_PUBLIC_KEY /absolute/private/osd-service/metadata.json > /absolute/private/osd-service/draft.json
php node-php/bin/onion-service-discovery record:sign service /absolute/private/osd-service/service.key /absolute/private/osd-service/draft.json --passphrase-file /absolute/private/osd-service/passphrase > /absolute/private/osd-service/manifest.json
php node-php/bin/onion-service-discovery manifest:verify /absolute/private/osd-service/manifest.json
```

The placeholder public key must be replaced with the exact generated string.
Expect successful verification of revision 1. A draft alone is not a signed
publication. Retain every signed revision and continuity proof on the administrator
machine; later offline membership checks need the complete owner history.

## 4. Install only public records on the web host

Transfer the signed `manifest.json` and public `hostname` to the staging web
host. Never transfer the Service key or its passphrase. Create a private
configuration directory, then run from that host's repository root:

```sh
umask 077
mkdir -p /absolute/private/osd-publisher
php node-php/bin/onion-service-discovery init /absolute/private/osd-publisher/config.json YOUR_CANONICAL_V3_ADDRESS.onion --publish
php node-php/bin/onion-service-discovery manifest:install /absolute/public-transfer/manifest.json /absolute/public-transfer/hostname /absolute/app/Onion-Service-Discovery/node-php/public
php node-php/bin/onion-service-discovery publisher:status /absolute/private/osd-publisher/config.json
```

`init` creates configuration only; expected status is `CONFIGURED`. Installation
verifies the signature and exact public-hostname binding before atomic replacement;
expect `INSTALLED`. The status command reads the Manifest under this checkout's
`node-php/public`, so install into that exact directory. Expect `READY_LOCALLY`
and `manifest.state: SIGNATURE_VALID`; external discoverability remains unconfirmed.

Configure the front controller with `ONION_SERVICE_DISCOVERY_CONFIG` pointing to
this private JSON file, following [web serving](installation.md#web-serving-and-operations).
Open these routes through your actual Onion:

- `/.well-known/onion-service-discovery.json`: signed public Manifest.
- `/onion-service-discovery/status/`: read-only status, usable without JavaScript.

Do not replace the rest of your existing website with the OSD front controller.
Integrate these routes with its existing routing, keeping configuration and keys
outside the document root. The reference templates assume a dedicated OSD listener.

## 5. Verify over Tor and check selected Nodes

Set the actual loopback SOCKS port in the private configuration. Explicitly fetch
and verify your own published Manifest through Tor:

```sh
php node-php/bin/onion-service-discovery service:verify /absolute/private/osd-publisher/config.json YOUR_CANONICAL_V3_ADDRESS.onion
```

Expect `SIGNATURE_AND_BINDING_VALID`. This establishes the fetched signature and
Onion binding, not full owner continuity or inclusion at another Node.
A cooperating Discovery Node can explicitly admit your address and run its direct
verification worker. An optional receiving Node can enable the bounded draft
announcement endpoint; `service:announce CONFIG SIGNED_MANIFEST NODE_ONION`
returns `HINT_RECEIVED_UNCONFIRMED`, never proof of inclusion.

For each independently selected Node, collect its complete signed snapshot,
all referenced chunks and the expected Node public key; verify against your
complete owner history using [offline exact-state membership](../operations/snapshot-membership.md).
An included current revision is stronger than a `listed=true` response, but the
current command separately leaves freshness/ancestry unevaluated. There is no
finished automated status-page “2 of 3 fresh Nodes” workflow yet. See the
[acceptance scenario and remaining work](project-status.md#end-to-end-acceptance-scenario).

## Updates and withdrawal

Use `manifest:revise SIGNED_MANIFEST METADATA`, sign the returned record offline,
verify it, and install it. An unchanged description needs no renewal/revision.
Use `manifest:withdraw SIGNED_MANIFEST` and sign/install the successor to withdraw.
Use the documented countersigned rotation and signed migration workflows for key
or address changes; never generate an unrelated genesis and call it continuity.
Keep the current predecessor and required proofs available during installation.
Independent Nodes may retain historical public records after withdrawal.
