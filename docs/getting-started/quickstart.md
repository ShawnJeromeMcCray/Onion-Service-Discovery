# Try the local demo

This walkthrough runs the real bundled browser verifier and search UI against
clearly marked synthetic signed records. It requires no accounts, Tor connection,
PHP runtime, public Node or application keys. It does not find real services.

## Requirements

Use Node.js 24 or newer with npm, Git, and a current browser with JavaScript and
Web Workers available. Check `node --version` and `npm --version`. The executed
reference runtime is Node 24.19.0. Install Node using your operating system's
supported method; no global npm packages are required.

## Install and start

Run in a terminal:

```sh
git clone https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery.git
cd Onion-Service-Discovery/web-local-search-client
npm ci --ignore-scripts
npm run demo
```

The terminal prints a loopback URL. Open
`http://127.0.0.1:8080/onion-service-discovery/`. Keep the terminal running.
`npm ci` installs the exact package-lock dependencies; `demo` builds assets and
starts a loopback-only fixed-asset server. It creates ignored `node_modules/`
and `dist/` directories, not keys or an operational Node database.

Do not open `index.html` using `file://`: workers and module delivery need the
HTTP origin. Do not expose this preview server as your public deployment.

## Verify the result

1. Click **Try offline demo**. Expect three result cards and a visible synthetic-data notice.
2. Enter `library`. Expect one matching **Synthetic Library**.
3. Click **Pin this identity**. Expect one pin and signed continuity matching it.
4. Change the query. The browser searches the verified local index without sending the query to the preview server.
5. Leave **Remember pins** unchecked for session-only pins. Opting in stores pins in this browser profile; blocked profile storage keeps them in memory.

Demo results deliberately provide no service visit links. The demo's source
coverage is zero live Nodes; the signed synthetic bundle is not public infrastructure.
The UI handles malicious-looking metadata as text and does not load remote images.

## Stop, retry and remove

Press **Ctrl+C** in the terminal. To use another port:

```sh
npm run demo -- --port 8081
```

Reopen the URL with port 8081. A different port is a different browser origin,
so saved pins do not carry over automatically. **Forget local data** clears the
application's local state through an explicit confirmation. Exported pin backups
remain files under your control and may reveal interests if shared.

To update a development checkout, inspect the new commit and changelog, then run
`npm ci --ignore-scripts` and `npm run demo` again. Record `git rev-parse HEAD`
when reporting a problem. To remove this demo, stop it and remove your checkout;
separately clear its browser site data and any pin exports you no longer need.

For a real installation choose [Publisher](publisher.md), [Discovery Node](node.md)
or [Client Host](installation.md#client-host). See [troubleshooting](troubleshooting.md)
if the expected result differs.
