# Privacy requirements and current status

No application runtime exists yet. These are requirements, not claims of validated behavior.

Local-mode search terms must stay inside the browser. Generic index loading must be independent of search input. No analytics, browsing-history reporting or third-party runtime assets are permitted by default.

Optional server search must disclose that its server receives the query, use POST, avoid query logging/URLs, cause no query-triggered upstream work, and return bounded no-store responses.

Persistence is optional. A malicious Client Host can replace served code; multi-source validation cannot eliminate this boundary. Public distributed records cannot be globally erased.

See source documents 16, 19, 26 and 42. Data inventory and network instrumentation remain outstanding.
