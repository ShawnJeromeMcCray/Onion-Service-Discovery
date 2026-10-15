# Add OSD to an existing website

OSD does not need its own website (specification 47, sections 64 to 66). If your
Onion already serves a website, keep it. Add three routes that go to the OSD
front controller through its own PHP-FPM pool. Every other request stays with
your website.

## The routes

| Route | Methods | What it serves |
| --- | --- | --- |
| `/.well-known/onion-service-discovery.json` | GET, HEAD | The signed Manifest |
| `/.well-known/onion-service-discovery-history.json` | GET, HEAD | The PCP-004 history bundle |
| `/onion-service-discovery/` and everything below it | depends on the route | Status page; browser client and relay (Client Host and combined roles); data API `/onion-service-discovery/v1/…` (Node roles); announcement POST and server search, when enabled |

`manifest:install` writes the Manifest and the history bundle into OSD's own
`node-php/public/.well-known/`, not into your website's document root. The front
controller reads them from there.

## What stays untouched

- Other paths never reach OSD. Your website's routing, PHP pool and logs keep
  working as before.
- OSD runs only in its own pool, as `osd-worker`. Your website's PHP never runs
  OSD code.
- OSD configuration, keys, store and backups stay outside both document roots.
- If OSD fails, for example because its configuration is unreadable, only its
  routes answer 503. The website keeps serving (specification 47, section 66).
- Cookies that your website sets for the whole Onion are also sent with OSD
  requests. OSD does not read or store them.

## nginx

1. Install OSD in `/opt/onion-service-discovery` with its own pool from
   [`php-fpm-pool.conf`](../../deployment/php-fpm-pool.conf), as described in
   [deployment and recovery](deployment-and-recovery.md).
2. Paste the three `location` blocks from
   [`existing-site-locations.conf.example`](../../deployment/nginx/existing-site-locations.conf.example)
   into the `server` block that serves your Onion. Adjust the paths if you
   installed elsewhere.
3. Make sure that no server-level `rewrite`, `return` or `if` catches these paths:
   they run before nginx chooses a location. A site-wide `add_header
   Content-Security-Policy` also applies to OSD pages; browsers enforce every
   policy they receive.
4. Run `nginx -t`, then reload nginx.

Each block switches the access log off, limits the request body to 1 KiB with a
5-second body timeout and gives PHP 65 seconds, like the dedicated template.
Header limits are server-wide in nginx: keep or tighten your site's values. The
dedicated template uses `client_header_timeout 5s` and
`large_client_header_buffers 4 4k`.

## Apache httpd

1. Install OSD and its pool as above.
2. Enable the modules: `a2enmod proxy proxy_fcgi setenvif`.
3. Include
   [`existing-site-locations.conf.example`](../../deployment/apache/existing-site-locations.conf.example)
   inside the `<VirtualHost>` of your Onion, before the site's own `ProxyPass`
   and `ProxyPassMatch` rules: the first matching rule wins. If the site has a
   catch-all `RewriteRule`, add the exclusion shown in the file first.
4. Add `env=!osd_no_log` to the site's `CustomLog` line. Apache cannot switch
   the access log off for one location.
5. Run `apachectl -t`, then reload Apache.

The snippet refuses bodies over 1 KiB and chunked bodies before they reach PHP,
because `LimitRequestBody` alone is not enforced for proxied FastCGI requests. It
passes `ONION_SERVICE_DISCOVERY_CONFIG` to OSD requests only.

## Optional server search

Server search takes request bodies up to 8 KiB and needs its own pool. With
nginx, add the exact location from
[`server-search-location.conf.example`](../../deployment/nginx/server-search-location.conf.example)
next to the three blocks; an exact location wins over the prefix block. There
is no Apache example. Read [server search](server-search.md) first.

## Check the result

Through your Onion:

- your website's pages still load;
- `service:verify CONFIG --save-status` passes, if this Onion publishes a Manifest;
- `/onion-service-discovery/status/` shows the status page;
- likely secret paths, such as `/config.json`, `/node.key` or `/.git/config`, are
  not served. [`secret-paths-http.php`](../../node-php/tests/secret-paths-http.php)
  checks this for the OSD front controller. The files of your own website are
  your responsibility.

## What was validated

nginx 1.24.0 (`nginx -t`) and Apache httpd 2.4.58 (`apache2 -t`) accepted both
snippets inside a test server block or virtual host. A local loopback run then
served them with PHP-FPM 8.3 (pool user `osd-worker`), with only paths and ports
changed. PHP 8.3 was the only FPM available there; OSD supports PHP 8.4 and
newer. In that run:

- all three routes reached OSD, and the test website's own page kept working;
- 30 likely secret paths, each with GET and HEAD, never returned 200 or the
  content of a private, repository or system file;
- OSD requests did not appear in the site's access log. Requests that nginx
  refuses before it chooses a location, such as `..` above the root or TRACE,
  still do.

Not validated: a real Onion through Tor, other server versions, and a website
with its own PHP application in front of these routes.
