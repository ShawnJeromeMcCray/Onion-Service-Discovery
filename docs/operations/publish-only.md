# Publish-only setup and public status

This is a development profile. Announcement stays an untrusted hint (explicit or
bounded automatic through the publisher timer); it does not claim signed
active-index membership or production readiness.

Create a private configuration directory (owner-only 0700) outside the webroot.
Run `php node-php/bin/onion-service-discovery init /absolute/private/config.json ONION`
with a canonical v3 Onion. Publishing stays off unless the explicit `--publish`
flag is supplied. Existing files are never overwritten. The command creates
no Service/Node key, storage backend or Manifest and makes no network request.

Use the documented offline Service key generation, encrypted backup, Manifest
generation/signing and manifest:install workflow. Transfer only signed public
records to the public host. Configure the web entrypoint's existing
`ONION_SERVICE_DISCOVERY_CONFIG` selector to the private JSON file. `publisher`
role needs neither a Node key nor Discovery storage initialization. Mirror,
Discover and Sync are refused for this role; their full Node profiles are separate.

Open `/onion-service-discovery/status/` on your own Onion. It works without
JavaScript, cookies or remote assets. It shows validated local signed publication
facts and unconfirmed external checks separately. A signature-valid later
revision is not described as having verified genesis continuity without history.
A missing/tampered Manifest cannot produce local-ready status.

`publisher:status CONFIG` uses the same evidence derivation. `service:verify
CONFIG ONION` performs explicitly requested, bounded Tor fetches of the Manifest
and the history bundle, without keys or storage writes. An HTTP status-page visit does not initiate that request,
announcement, peer sync, storage repair or signing.

The Well-Known route serves valid signed withdrawal records even when publication
is disabled; an opted-in Manifest is not served when publishing is disabled.
Historical copies at independent operators cannot be globally erased.

Production blockers include the exact stable identity/checkpoint/announcement
freeze, complete external evidence workflow, actual approved sources, Tor Browser
desktop/Android usability, deployment checks and the remaining release gates.
