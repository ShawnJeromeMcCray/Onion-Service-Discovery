# Third-party notices

The project authors' original code remains dedicated under CC0-1.0. That
dedication does not replace the licenses of incorporated third-party material.
Locked npm/Composer/Python components and their licenses appear in the release
SBOM and retain their original licenses.

The generated Unicode 17 search tables and compressed official normalization
test vectors derive from Unicode, Inc.'s Unicode Character Database 17.0.0.
They are distributed under the [Unicode License V3](third-party/Unicode-3.0.txt),
SPDX `Unicode-3.0`. Copyright © 1991–2026 Unicode, Inc. The permission notice
is included with the repository and release artifacts. The original search
algorithm code is project-authored CC0 code; it changes presentation text only.

Source archive/member checksums, source URL, generated table/vector digests and
Unicode version are recorded in
[search data provenance](protocol/registries/search-unicode-v1.provenance.json).
The generator rejects source substitutions before generating artifacts.

The generated confusable prototype table of the browser client
(`web-local-search-client/src/client/confusables-data.ts`) derives from
`confusables.txt` of Unicode Technical Standard #39, Unicode Security
Mechanisms, version 17.0.0, as distributed in ICU tag `release-78.3`. It is
under the same Unicode License V3, SPDX `Unicode-3.0`, Copyright © 1991–2026
Unicode, Inc. The source checksum, ICU tag and path, and the generated-table
digest are recorded in
[confusable data provenance](protocol/registries/confusables-v1.provenance.json);
`tools/generate_confusables.py` refuses any other source file.
