# Troubleshooting

Start with the exact commit (`git rev-parse HEAD`), intended role and error code.
Every code is listed with its meaning and CLI exit code in the [error code registry](../operations/error-codes.md).
CLI output is JSON; exit 0 is command success, not blanket production approval.
Keep private keys, passphrases, search history and raw private backups out of reports.

| Symptom | What to check / do |
| --- | --- |
| `node`/`npm` unavailable or unsupported | Install Node 24+ with npm; reopen the terminal and check `node --version`. |
| Demo listener `EADDRINUSE` | Stop the existing listener or use `npm run demo -- --port 8081`. |
| Demo has no results initially | Click **Try offline demo**. Zero live sources is intentional; demo data is not a seed network. |
| `file://` page fails / worker unavailable | Use the printed loopback HTTP URL. Do not open generated HTML directly. |
| Port change loses saved pins | A port change changes the browser origin. Pins are origin-local; use an explicit private export/import if needed. |
| Profile storage blocked | Search and pins work in session memory. Persistent pins require explicitly available profile storage. |
| `RUNTIME_DEPENDENCIES_MISSING` (exit 3) | Use the [development runtime package](../operations/release-candidate.md#development-runtime-package-d-029), which bundles the PHP dependencies, or run the locked runtime Composer install in the [installation guide](installation.md). Source checkouts and the source/browser package do not bundle them. |
| Missing curl/sodium/iconv/PDO driver | Check the PHP CLI and FPM runtimes separately; enable the required extension in the actual runtime. |
| `CONFIGURATION_PATH_UNSAFE` / permission error | Use real absolute directories outside the webroot, owned by the service user, mode 0700; private files 0600. Avoid symlink/private-key filename aliases. Do not fix by making data world-readable. |
| Synthetic Onion or key rejected | Replace the example with a checksum-valid v3 Onion / canonical `ed25519:` public key; placeholders are not real values. |
| `MIRROR_DISABLED` / `DISCOVERY_DISABLED` | Enable only the feature needed in the matching Node/combined configuration. Publisher and Client Host roles cannot perform Node jobs. |
| `PHP 8.4+ required` / Ubuntu 24.04 | Ubuntu 24.04 LTS ships PHP 8.3, which is not supported. Use a release that ships PHP 8.4+ or a PHP build you trust. |
| Status page answers **NOT YET** | Run `service:verify CONFIG YOUR.onion --save-status`, then `network:discoverability-check … --save-status` against independently selected Nodes; the *Steps* list shows the first step that is still pending. |
| Self-check `FAIL_TOR_REQUEST_FAILED` | Tor or the SOCKS port is unavailable, or your Onion does not answer. Check Tor first. |
| Self-check `SERVED_MANIFEST_DIFFERS` | Your Onion serves another signed Manifest than the one installed here: check web routing and the install directory. |
| Discoverability check: `stale` | The Node still exposes an older revision. Ordinary propagation delay; recheck after its next snapshot. |
| Discoverability check: `not_present` | The Node has not admitted your Service. Announce it (`network:announce`) or ask its operator to admit it. |
| Discoverability check: `rejected` / `SOURCE_CHECKPOINT_ROLLBACK`, `_EQUIVOCATION` or `_CHAIN_BROKEN` | The Node served a checkpoint that does not descend from the one it served at your previous check, and its evidence is ignored. Ask its operator: a Node that lost its chain (for example by restoring an old backup) must start over with a new Node key, which you then use as the expected key. Deleting `CONFIG_NAME.discoverability.json` forgets all saved evidence at once: checkpoints, the self-check, the membership results and the announcement schedule. The status page then shows no evidence until the next checks, and the next `--if-due` run treats every Node as new. Do that only after you understood the cause. |
| Discoverability check: `rejected` / `SOURCE_KEY_MISMATCH` | The expected Node key you supplied does not match. Re-obtain it through an independent channel; never copy it from the Node's own response. |
| **PROBLEM** / `IDENTITY_CONFLICT` | A checked Node holds a conflicting signed state for your identity (fork, unexpected key or migration). Preserve the evidence; do not publish a new genesis to "fix" it. |
| **DEGRADED** | Saved evidence is older than seven days. Rerun the check or enable `osd-publisher-check.timer`. |
| `SIGNATURE_INVALID` / `ONION_BINDING_MISMATCH` | The record was changed after signing or belongs to another Onion. Re-sign the unchanged draft offline; never edit signed JSON. |
| Revision conflict / fork while signing | Two different revisions with the same number exist. Continue from the one already published; see the update workflow in the publisher guide. |
| `STORAGE_BUSY` / store locked | Another worker holds the writer lock. Let scheduled jobs finish; do not delete lock files while workers run. |
| `ENOSPC` / disk full | Free space; the stores resume from the last durable commit. Run `storage:check` afterwards. See [recovery](../operations/deployment-and-recovery.md). |
| Browser client shows old results | Reload sources explicitly. A failed refresh keeps the last verified view on purpose. |
| Pin mismatch warning | A loaded identity conflicts with your pin. Treat it as a possible impersonation; keep the pin until you verified the change through the Service's signed rotation/migration evidence. |
| Node reports `HISTORY_INCOMPLETE` for your Service | Run `history:verify WEBROOT`. If it is `MISSING` or `INCOMPLETE`, rebuild it with `history:install WEBROOT` and all your signed revision and proof files. Nodes retry with bounded backoff. |
| Status page does not show saved evidence, or says so | The checks ran as another user than the web server. Run every command on the web host as the service user of the PHP-FPM pool (`sudo -u osd-worker php node-php/bin/onion-service-discovery …`); the publisher timer template already does. |
| Manifest URL answers 503 / status page says `NOT_CONFIGURED` although the configuration exists | The web server user cannot read the configuration. Create it as that service user, or `chown -R osd-worker: DIRECTORY` with modes 0700/0600; do not make it world-readable. |
| Need to report a problem | Attach the reviewed output of `diagnostics:export CONFIG` instead of configuration files or logs. |
| `READY_LOCALLY`, external status unconfirmed | Your local signed publication is valid. Another Node has not been independently proven to include your fresh current state. Follow the [Publisher verification steps](publisher.md#5-verify-over-tor-and-check-selected-nodes). |
| `TOR_REQUEST_FAILED` | Check the configured loopback SOCKS listener, its port, Tor bootstrap and actual Onion availability. No direct clearnet or ambient-proxy fallback is provided. |
| `HINT_RECEIVED_UNCONFIRMED` | Announcement was accepted as an untrusted hint. It is not direct observation, identity continuity or index inclusion. |
| `SELF_VERIFICATION_REQUIRED` | Automatic announcement (`--if-due`) needs `service:verify CONFIG YOUR.onion --save-status` to pass for exactly the installed Manifest within the last 24 hours. Fix the self-check first. |
| `ANNOUNCEMENT_RATE_LIMITED` | The Node's shared announcement budget is exhausted (HTTP 429). The timer retries no earlier than a day later with growing backoff; nothing else is needed. |
| Announcement `DAILY_LIMIT` / `BACKOFF` | Bounded on purpose: at most one hint per Node per day, then 1, 2, 4 … 32 days while the Node lacks your revision. A new revision is announced once a day has passed. |
| Announcement `NODE_EVIDENCE_NEEDS_REVIEW` | The membership check found a conflicting state or rejected that Node's evidence (often a wrong expected key). A hint cannot fix that; see the rows above. |
| Fewer than three sources | Enter independently selected expected keys; explicitly acknowledge limited coverage if you intentionally use fewer. Key count does not establish operator independence. |
| `PROGRESS` / partial source history | Retry through the bounded refresh/scheduler after the source retry time. Do not delete anchors to bypass missing ancestry. |
| `BACKOFF` with `STORAGE_BUDGET_EXHAUSTED` | Local quota is exhausted. Inspect capacity; preserve evidence and use reviewed staging migration/recovery. Local failures do not add peer penalties. |
| Source rollback, equivocation, broken history or quarantine | Preserve authenticated evidence, inspect the documented error and source binding. Do not bypass the validator or clear history just to obtain green status. |
| Stale/corrupt NoJS prepared cache | Queries fail closed. Review the cause, then explicitly run `node:snapshot CONFIG --passphrase-file FILE --rebuild-derived`; no HTTP query repairs/signs data. |
| `doctor` returns `WARN` / exit 6 | Read individual checks. Unobserved Tor, scheduler, backup or deployment properties are not automatically verified. |
| Node key/signing or restore fails | Check role, private modes, passphrase and exact Node binding. Follow [recovery](../operations/deployment-and-recovery.md); never substitute an unrelated key or older high-water state. |
| Blank UI with restricted JavaScript | Use the explicit recovery link if the Node enables NoJS search. That mode sends queries to the Node and supplies no local browser proof. |

For a reproducible ordinary bug, use [support](../../SUPPORT.md). For sensitive
security findings follow [SECURITY.md](../../SECURITY.md). A successful workaround
must not silently weaken signature checks, source pinning, Tor-only transport or
query privacy.
