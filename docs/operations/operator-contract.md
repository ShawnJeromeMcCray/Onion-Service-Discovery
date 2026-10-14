# Five-minute operator contract

Read this before you enable publishing. It summarizes what becomes public, which
key matters and what you can and cannot undo. It follows specification document
40, adapted to this implementation. Commands are run from the repository root
as `php node-php/bin/onion-service-discovery COMMAND`.

## What you enable

Publishing a signed Manifest opts your existing Onion Service into a
decentralized public directory. Independent Discovery Nodes may fetch it, verify
its signature and Onion binding, store and mirror it, include it in their public
snapshots and keep its history as continuity evidence.

The Manifest is served at `/.well-known/onion-service-discovery.json` and
contains your Onion address, your Service Identity public key, the revision and
its link to the previous one, the publish setting and the metadata you chose.

## What you never hand over

- Your Tor private key: Tor keeps it. Commands refuse files named
  `hs_ed25519_secret_key` or `private_key`.
- Your Tor control password, user accounts or search logs: the protocol has none.
- Recurring registration credentials: there is no central registry to renew.

## The key you must protect

Your **Service Identity key** is separate from Tor and signs every revision.
Create it offline (`key:create service`), keep an encrypted backup and the
passphrase apart, and test the backup. If it is lost before a valid rotation, no
one, including this project, can prove continuity to a new key; v1 has no master
recovery key. See [offline key custody](../signing/offline.md).

## What is public, and for how long

Everything in the signed Manifest is public and can be copied indefinitely.
Do not put private contact details, secrets, credentials, internal URLs or
personal data you do not mean to publish into it. Display names are not unique;
your Logical Service Identifier (the genesis public key) and history are.

## Changes you will make

| You want to… | Do this | Never do this |
| --- | --- | --- |
| Change metadata | `manifest:revise`, sign offline, `manifest:install` | Reuse a revision number with other content (that is a fork) |
| Withdraw | `manifest:withdraw`, sign, install | Expect every independent copy to disappear |
| Rotate your Service key | `manifest:rotate` with both keys, test, install | Start a new genesis and call it the same service |
| Move to a new Onion | `manifest:migrate`, sign, install on both addresses | Just change the Onion field |
| React to a key compromise | Stop publishing, preserve evidence, follow [recovery](deployment-and-recovery.md) | Delete or rewrite old revisions |

Going offline is not withdrawing: Nodes keep your last valid state and may show
it as stale or unreachable.

## What verification means

A valid signature and Onion binding show that the key evidence checks out. They
do not show that your content is endorsed or safe, that you are who your name
says, or that the Tor Project approves the Service. Clients search locally, so
your Service does not learn what visitors searched for.

## Seeing whether you can be found

Open `/onion-service-discovery/status/` on your own Onion. It answers YES, NOT
YET, NO, DEGRADED or PROBLEM and lists ten steps from installation to
redundancy. Feed it evidence with:

```sh
service:verify CONFIG YOUR.onion --save-status
network:announce CONFIG SIGNED_MANIFEST NODE.onion --save-status
network:discoverability-check CONFIG HISTORY --node NODE.onion ed25519:NODE_KEY --save-status
```

**DISCOVERABLE** means a checked Node's verified, complete signed snapshot
contains your exact current revision together with that Node's own direct
observation. It never means every Node has it. Evidence older than seven days
shows **DEGRADED**. The optional `osd-publisher-check.timer` repeats the checks
every six hours; it never announces automatically.

## Running a Node is optional

You can be discoverable without running a Node. If you enable Node roles, your
server makes outbound Onion requests only through the configured Tor SOCKS port
(no direct fallback), stores public records, uses bandwidth and disk, needs a
scheduler, limits and backups, and receives untrusted input. Share your Node with
`node:identity CONFIG`: others need its Onion **and** its public key.

## Checklists

Publish only:

- [ ] Read this contract and choose public metadata.
- [ ] Create the Service key offline (`key:create service`) and back it up.
- [ ] `manifest:init`, sign offline with `record:sign`, `manifest:verify`.
- [ ] `init CONFIG YOUR.onion --publish`, `manifest:install`, `publisher:status`.
- [ ] `service:verify CONFIG YOUR.onion --save-status` through Tor.
- [ ] Announce or ask Node operators to admit you; then `network:discoverability-check --save-status`.

Additional for a Node ([Node guide](../getting-started/node.md)):

- [ ] Set the Tor SOCKS port; there is no direct fallback.
- [ ] Create a separate Node key (`key:create node`) and run `node:init`.
- [ ] Enable only the roles you need; keep the default limits unless measured.
- [ ] Install the systemd timers or another scheduler.
- [ ] Back up the store and keys separately; test `storage:restore`.
- [ ] Run `doctor` and `security:check`; share `node:identity` with peers.

## If something looks wrong

Do not rotate or delete blindly. Note the software version, current revision and
hash, Logical Service Identifier, current key and key epoch, and the exact error
code, then run `diagnostics:export CONFIG` and follow
[troubleshooting](../getting-started/troubleshooting.md).

By publishing, you deliberately make your Service's discovery metadata public
and mirrorable. You keep control of your Service key and future revisions, but
not of copies other independent Nodes already hold.
