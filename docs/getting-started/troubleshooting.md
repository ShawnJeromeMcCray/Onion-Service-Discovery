# Troubleshooting

Start with the exact commit (`git rev-parse HEAD`), intended role and error code.
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
| `RUNTIME_DEPENDENCIES_MISSING` (exit 3) | Run the locked runtime Composer install in the [installation guide](installation.md). Source packages do not bundle PHP vendor dependencies. |
| Missing curl/sodium/iconv/PDO driver | Check the PHP CLI and FPM runtimes separately; enable the required extension in the actual runtime. |
| `CONFIGURATION_PATH_UNSAFE` / permission error | Use real absolute directories outside the webroot, owned by the service user, mode 0700; private files 0600. Avoid symlink/private-key filename aliases. Do not fix by making data world-readable. |
| Synthetic Onion or key rejected | Replace the example with a checksum-valid v3 Onion / canonical `ed25519:` public key; placeholders are not real values. |
| `MIRROR_DISABLED` / `DISCOVERY_DISABLED` | Enable only the feature needed in the matching Node/combined configuration. Publisher and Client Host roles cannot perform Node jobs. |
| `READY_LOCALLY`, external status unconfirmed | Your local signed publication is valid. Another Node has not been independently proven to include your fresh current state. Follow the [Publisher verification steps](publisher.md#5-verify-over-tor-and-check-selected-nodes). |
| `TOR_REQUEST_FAILED` | Check the configured loopback SOCKS listener, its port, Tor bootstrap and actual Onion availability. No direct clearnet or ambient-proxy fallback is provided. |
| `HINT_RECEIVED_UNCONFIRMED` | Announcement was accepted as an untrusted hint. It is not direct observation, identity continuity or index inclusion. |
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
