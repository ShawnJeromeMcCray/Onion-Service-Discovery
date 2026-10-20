"""Generate the error-code registry (spec 32 section 35) from the codes the source actually raises.

The source code is the single source of truth for which codes exist; DESCRIPTIONS gives every raised
code a plain-language meaning, which the CLI prints with each failure. Both modes fail when a raised
code has no description or a description names a code that is no longer raised, and `--check` also
fails when the committed registry is stale, so a new code cannot ship undocumented.
"""
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
SOURCES = ['node-php/src', 'node-php/bin/onion-service-discovery', 'node-php/public/index.php', 'web-local-search-client/src']
THROW = re.compile(r"new \\?(ProtocolException|StorageException|TransportException|SignerException|InvalidArgumentException|Error)\(([^;]*?)\)\s*[;}]")
LITERAL = re.compile(r"'([A-Z][A-Z0-9_]{2,})'")
RESPONSE = re.compile(r"""(?:'error'\s*=>\s*'|"error":\s*")([A-Z][A-Z0-9_]{2,})""")
DOCTOR = re.compile(r"\$(failures|warnings)\[\]\s*=\s*'([A-Z][A-Z0-9_]{2,})'")
CLI_LITERAL_EXITS = {'RUNTIME_DEPENDENCIES_MISSING': '3', 'INTERNAL_ERROR': '8'}
EXITS = {'StorageException': '4', 'SignerException': '4', 'TransportException': '5', 'ProtocolException': '3', 'InvalidArgumentException': '3'}
COMPONENTS = {
    'node-php/src/Protocol': 'protocol', 'node-php/src/Storage': 'storage', 'node-php/src/Transport': 'Tor transport',
    'node-php/src/Node': 'Node worker', 'node-php/src/Http': 'web routes', 'node-php/src/Configuration': 'configuration and status',
    'node-php/src/Signing': 'signing', 'node-php/src/Search': 'server search', 'node-php/src/Domain': 'input parsing',
    'node-php/src/Validation': 'JSON parsing', 'node-php/src/Crypto': 'encoding', 'node-php/bin': 'CLI',
    'node-php/public': 'front controller', 'web-local-search-client/src': 'browser client',
}
DESCRIPTIONS = {
    'ADDRESS_INVALID': 'Not a canonical, checksum-valid v3 Onion address (56 lowercase characters plus .onion).',
    'CANDIDATE_BUSY': 'A worker is checking this candidate right now; run candidate:retry again after its lease ends.',
    'CANDIDATE_NOT_FOUND': 'The candidate is not queued on this Node; candidate:add queues it first.',
    'COMMAND_UNKNOWN': 'help knows no command of that name; run help without arguments for the list.',
    'CONFIGURATION_INVALID': 'The configuration file has a missing, unknown or wrongly typed key; see the configuration reference.',
    'CONFIGURATION_UNAVAILABLE': 'The configuration file is missing, unreadable, a symlink or larger than 16 KiB.',
    'CONTENT_TYPE_REJECTED': 'A Node or Service answered without application/json; nothing was verified.',
    'DOCUMENT_TOO_LARGE': 'An input file exceeds the limit for its record type.',
    'EVIDENCE_WRITE_FAILED': 'Saved status evidence could not be written beside the configuration; check directory permissions.',
    'ANNOUNCEMENT_RATE_LIMITED': 'The Node answered 429: its shared announcement budget is exhausted. The bounded schedule retries no earlier than a day later.',
    'ANNOUNCEMENT_RESPONSE_INVALID': 'The Node did not answer with exactly HINT_RECEIVED_UNCONFIRMED; no other claim is accepted as an announcement result.',
    'PUBLISHER_CHECKED_NODE_CHECKPOINT_CONFLICT': 'doctor warning: a Node in the last publisher check contradicted the checkpoint it served before (D-052); see the status page.',
    'PUBLISHER_NODES_NOT_CONFIGURED': 'No --node was given and the configuration has no publisher_nodes; add the Discovery Nodes you chose, each with its independently obtained key.',
    'EXPORT_BUDGET_NEARLY_EXHAUSTED': 'doctor warning: the export directory uses at least 80 % of its 4,096-file or 256 MiB limit; check that the maintenance timer runs (node:maintain prunes old blobs).',
    'SNAPSHOT_TIMER_STALE': 'doctor warning: the published checkpoint is older than two days although snapshots refresh daily; check the snapshot timer and run node:snapshot.',
    'PUBLISHER_TIMER_STALE': 'doctor warning: the last saved publisher check is older than 36 hours although the timer runs every six hours; check osd-publisher-check.timer.',
    'EVIDENCE_UNAVAILABLE': 'The browser keeps no pair of conflicting signed records for this Node, so there is nothing to save.',
    'PUBLISHER_BUSY': 'Another publisher command holds the lock on the same files; run it again when that command has finished.',
    'EXPECTED_NODE_KEY_REQUIRED': 'A visitor-chosen Node needs its expected public key.',
    'EXPORT_CORRUPT': 'Published snapshot files do not match their signed hashes; rebuild with node:snapshot --rebuild-derived.',
    'HISTORY_INCOMPLETE': 'The current Manifest does not connect to its history: the Service serves no or an incomplete history bundle (history:install), or a proof is missing.',
    'HISTORY_BUNDLE_INVALID': 'The PCP-004 history bundle is malformed, too large or contains a record type it must not contain.',
    'HTTP_STATUS_REJECTED': 'The remote answered with an unexpected HTTP status other than 404, a redirect or 5xx; nothing was verified.',
    'HTTP_NOT_FOUND': 'The Onion answered 404: its web server does not pass this path to the OSD front controller, or the Node does not offer the endpoint.',
    'HTTP_REDIRECT_REJECTED': 'The Onion answered with a redirect. OSD never follows redirects; serve the path directly over HTTP on the Onion.',
    'HTTP_SERVER_ERROR': 'The Onion answered 5xx. For OSD itself this usually means the web server user cannot read the configuration.',
    'ONION_UNREACHABLE': 'Tor answered with a SOCKS error: it could not reach the Onion (still bootstrapping, Service offline or descriptor not published).',
    'TOR_PROXY_UNREACHABLE': 'Nothing accepted a connection on the configured Tor SOCKS port: Tor is not running or transport.proxy_port is wrong.',
    'TOR_REQUEST_TIMEOUT': 'The Onion did not answer within transport.timeout_ms; first contact can be slow, retry or raise the limit (at most 60000).',
    'IDENTITY_CHANGED': 'Another Service identity now answers at an address this Node already knew; the old evidence is preserved.',
    'IDENTITY_FORKED': 'Two different signed revisions with the same number exist; the fork stays visible.',
    'KEY_ROTATION_INVALID': 'A key change without a valid rotation proof for exactly this predecessor and revision.',
    'KEY_UNLOCK_FAILED': 'Wrong passphrase or damaged key envelope.',
    'LOCAL_CONTINUITY_INCOMPLETE': 'Your own history files do not form one complete identity; include every revision and proof.',
    'LOCAL_IDENTITY_CONFLICT': 'Your own history contains a fork; resolve which branch you published before checking Nodes.',
    'MIGRATION_INVALID': 'An address change without a valid migration proof for exactly this predecessor and revision.',
    'NODE_KEY_ROTATION_UNSUPPORTED': 'Protocol v1 cannot rotate a Node key; create a new Node identity and have peers pin it explicitly.',
    'NODE_NOT_INITIALIZED': 'Run node:init before this command.',
    'NOT_FOUND': 'The route or record does not exist on this installation.',
    'ONION_BINDING_MISMATCH': 'The signed Manifest belongs to a different Onion than the one it was fetched from or installed for.',
    'OUTPUT_FILE_EXISTS_OR_UNAVAILABLE': 'The draft file already exists or cannot be created; drafts are never overwritten.',
    'PASSPHRASE_FILE_UNSAFE': 'The passphrase file must be a regular owner-only (0600) file.',
    'PRIVATE_PATH_IN_WEBROOT': 'A private file or directory is inside the public web root; move it out.',
    'PUBLIC_HOSTNAME_FILE_REQUIRED': 'manifest:install needs the public Tor hostname file, named hostname.',
    'PUBLIC_KEY_INVALID': 'Not a canonical ed25519: public key (43 base64url characters after the prefix).',
    'PUBLISHING_DISABLED': 'Publishing is off in the configuration or the Manifest withdraws publication.',
    'REQUEST_BUDGET_EXHAUSTED': 'The shared request budget is used up; retry after a second.',
    'RESPONSE_TOO_LARGE': 'A response exceeded its byte limit and was discarded.',
    'RESTORE_REQUIRES_EMPTY_STAGING_STORE': 'Restores and migrations write only into a new, empty store path.',
    'REVISION_CHAIN_BROKEN': 'The new Manifest is not the next revision of the installed one.',
    'ROLE_CONFIGURATION_INVALID': 'The command or feature is not available for this configuration role.',
    'RUNTIME_DEPENDENCIES_MISSING': 'PHP dependencies are missing: use the development runtime package, which bundles them, or run the locked Composer install.',
    'INTERNAL_ERROR': 'An unexpected internal failure; details are withheld on purpose. Run storage:check if a store was involved and attach the reviewed diagnostics:export output when reporting it.',
    'SCHEMA_INVALID': 'The record does not match its draft schema.',
    'SELF_VERIFICATION_ONION_MISMATCH': '--save-status self-checks only the Onion configured for this installation.',
    'SIGNATURE_INVALID': 'The signature does not verify; the record was changed after signing or signed by another key.',
    'SNAPSHOT_INCOMPLETE': 'A snapshot chunk is missing; the snapshot was not used.',
    'SOURCE_ALREADY_ADDED': 'You already added this Node.',
    'SOURCE_ALREADY_CONFIGURED': 'This Node is already listed by the Client Host.',
    'SOURCE_CHECKPOINT_EQUIVOCATION': 'The Node signed two different checkpoints for the same generation; a peer is quarantined, a publisher check rejects that Node.',
    'SOURCE_CHECKPOINT_ROLLBACK': 'The Node served an older checkpoint than already seen; a peer is quarantined, a publisher check rejects that Node.',
    'SOURCE_CHECKPOINT_CHAIN_BROKEN': 'The Node\'s checkpoints do not form one chain back to the one seen before (D-052); its evidence is not used.',
    'SOURCE_CHECKPOINT_BINDING_MISMATCH': 'The Node\'s state and its signed checkpoint disagree (hash, generation or cursor); nothing from it was used.',
    'SOURCE_DUPLICATE': 'The same Node was listed twice.',
    'SOURCE_FETCH_FAILED': 'The Node could not be reached through the relay or Tor.',
    'SOURCE_HISTORY_PROGRESS': 'Long-absence history is verified in bounded steps; load again to continue.',
    'SOURCE_KEY_MISMATCH': 'The Node signs with a different key than the one you supplied.',
    'SOURCE_NODE_KEY_CHANGED': 'The Node signed with a different key than expected; check the key or treat it as another Node.',
    'SOURCE_NOT_IN_HOST_ALLOWLIST': 'The Client Host does not relay this Node.',
    'SOURCE_TIMEOUT': 'The Node did not answer in time; the existing local index is unchanged.',
    'STORAGE_BUDGET_EXHAUSTED': 'The local store reached its record or byte quota; existing evidence is preserved.',
    'STORAGE_BUSY': 'Another worker holds the store lock; let it finish.',
    'STORAGE_CORRUPT': 'Stored data failed verification; run storage:check and restore from backup if needed.',
    'STORAGE_MIGRATION_REQUIRED': 'This store uses an older format; stop workers, back up, run storage:migrate.',
    'STORAGE_RECOVERY_REQUIRED': 'An interrupted write must be recovered with storage:check before read-only use.',
    'TOR_KEY_ACCESS_FORBIDDEN': 'Commands never read Tor private key files.',
    'TOR_REQUEST_FAILED': 'Tor connected, but the HTTP exchange with the Onion failed (connection reset, empty or malformed reply).',
    'USAGE_ERROR': 'Wrong arguments; run help for the exact syntax.',
    'VISITOR_SOURCES_DISABLED': 'This Client Host does not relay visitor-chosen Nodes.',
    'VISITOR_SOURCE_LIMIT': 'At most 8 visitor-chosen Nodes can be added.',
    'WORK_BUDGET_EXHAUSTED': 'The input exceeds a bounded work limit and was refused.',
    'FILESYSTEM_FORMAT_MIGRATION_REQUIRED': 'doctor: the filesystem store uses format 1; plan storage:migrate.',
    'NODE_KEY_MISSING_OR_UNSAFE': 'doctor: the Node key file is missing or readable by others (needs 0600).',
    'RUNTIME_OUTSIDE_EXECUTED_MINOR_MATRIX': 'doctor: this PHP version is newer than the tested 8.3, 8.4 and 8.5; it may work, but no test has run on it.',
    'SERVER_SEARCH_ENABLED_QUERIES_SENT_TO_NODE': 'doctor: server search is on, so search terms reach this Node.',
    'SNAPSHOT_NOT_PUBLISHED': 'doctor: directory mirroring is on but no snapshot was published yet (node:snapshot).',
    'SOURCE_SET_CONCENTRATED': 'doctor: most configured peers share one source group; diversity is low.',
    'VISITOR_SOURCE_RELAY_ENABLED_OUTBOUND_TO_VISITOR_CHOSEN_NODES': 'doctor: the Client Host relays Nodes chosen by visitors.',
    'ARCHIVE_BUDGET_EXHAUSTED': 'The archive given to storage:import is larger than the 256 MiB import limit, so nothing was imported.',
    'ARCHIVE_INVALID': 'The public record archive is malformed (a wrong header or more than 10000 records, a non-canonical, duplicate, overlong or unterminated record line, or a wrong footer checksum or count), so nothing was imported.',
    'ARCHIVE_PATH_INVALID': 'The archive path is a URL or names a Tor private key file, or for storage:import it is a symlink or not an existing regular file.',
    'ARCHIVE_UNAVAILABLE': 'The archive file could not be created, written, synced or opened (storage:export never overwrites an existing file), or storage:import could not write a temporary file; use a new path or check permissions and free space.',
    'ASSERTION_STATE_INVALID': 'node:maintain found a stored remote-provenance assertion without its expected kind or source list, so it stopped without changing anything.',
    'BACKUP_BUDGET_EXHAUSTED': 'The private backup exceeds its size limits of 32 MiB for the store state or 256 MiB in total.',
    'BACKUP_INVALID': 'The private backup is malformed (a wrong header or store state, a record that does not match its listed hash or type, an overlong or unterminated line, or a wrong footer checksum or count), so nothing was restored.',
    'BACKUP_PATH_INVALID': 'The backup path is a URL, a symlink or a Tor private key file name, or the backup to verify or restore is not an owner-only regular file of at most 256 MiB.',
    'BACKUP_RESTORE_MISMATCH': 'After storage:restore, the staging store did not match the records and operational state recorded in the backup, so the restore failed; discard that staging store.',
    'BACKUP_UNAVAILABLE': 'The backup file could not be created, written, synced or opened (storage:backup never overwrites an existing file), or a temporary file for checking it could not be written; use a new path or check permissions and free space.',
    'CANDIDATE_CLAIM_LOST': 'This worker’s 2-minute claim on the candidate expired and another worker run released it before the result was saved, so the result was dropped and the candidate is checked again later.',
    'CHECKPOINT_NODE_MISMATCH': 'The snapshot checkpoint used for a membership check is not a snapshot descriptor signed with the expected Node key given for that Node; check the expected Node key.',
    'CLIENT_BUILD_UNAVAILABLE': 'The Client Host answered 503 because a browser client file is missing from web-local-search-client/dist, is a symlink or exceeds 2 MiB; build it with npm --prefix web-local-search-client run build.',
    'CLIENT_BUSY': 'Another load, import or clear operation is still running in this page, so the new one was not started; wait for it or cancel it first.',
    'CLIENT_CONFIGURATION_INVALID': 'The browser client got an unusable source setup (over 16 Nodes, an unknown delivery profile or a change during a load) or a load request with no Node, over 16 Nodes or one Node twice.',
    'CLIENT_MARKUP_INVALID': 'The client page lacks an element its script expects, so the served index.html does not match client.js; serve both from the same client build.',
    'CLIENT_ORIGIN_INVALID': 'An allowed_client_origins entry is not exactly http:// followed by a v3 Onion address (no https, port, path or trailing slash); the detail names the entry.',
    'CLIENT_RECORD_BUDGET_EXHAUSTED': 'Admitting these records would grow the browser’s local index beyond 10000 signed records, so none of them were added; clear the index before loading more.',
    'CLOCK_INVALID': 'The system clock reports a time before 1970 or beyond the supported range (the year 9999 for signed timestamps), so the operation was refused; correct the system clock.',
    'CLOCK_ROLLBACK': 'The system clock is earlier than the creation time of the last published snapshot, so node:snapshot refused to publish; correct the clock, then run node:snapshot again.',
    'CONFIGURATION_CREATE_REFUSED': 'init could not create the configuration file because it already exists or the directory does not allow it; init never overwrites a configuration; choose a new path.',
    'CONFIGURATION_PATH_INVALID': 'storage.path, export_path or node.key_path is not an absolute path of at most 1024 bytes without . or .. segments, control characters or ://, or it cannot be resolved; the detail names the key.',
    'CONFIGURATION_PATH_UNSAFE': 'init refused the configuration path: it must be an absolute path of at most 1024 bytes in an existing, non-symlinked, owner-only (0700) directory outside node-php/public.',
    'CONFIGURATION_WRITE_FAILED': 'init created the configuration file but could not write or sync it or sync its directory, so the file may be incomplete; check free space, remove the file and run init again.',
    'CONFUSABLE_DATA_INVALID': 'The confusable table built into the browser client does not decode to its recorded entry count, so look-alike names cannot be checked; serve an unmodified client build.',
    'CONTENT_ENCODING_REJECTED': 'A Node or Service answered with a Content-Encoding other than identity (a compressed answer), which OSD does not accept, so nothing from it was verified; serve the OSD paths without compression.',
    'CRYPTO_ALGORITHM_UNSUPPORTED': 'An internal browser check failed: without Web Crypto, the client’s built-in hash fallback supports only SHA-256, and another algorithm was requested.',
    'CURSOR_INVALID': 'A changes cursor is out of range: beyond this Node’s last record sequence number (HTTP 400 on the changes route), or negative or above the largest safe integer; use a cursor the Node returned, or 0.',
    'DISCOVERY_DISABLED': 'Service discovery is off for this configuration (features.discover_services is false or the role is client-host), so candidate:add, candidate:retry and node:work-one refuse to run; use a node or combined configuration with it enabled.',
    'ENCODING_INVALID': 'JSON input was rejected for its encoding: a byte order mark, invalid UTF-8, a lone surrogate or a NUL character in a property name; save it as UTF-8 without a byte order mark.',
    'ERROR_CODE_INVALID': 'An internal check failed: a candidate check ended with an error that is not a plain uppercase code, so the failure could not be recorded on the candidate.',
    'EVIDENCE_CHANGED_RETRY': 'Other evidence was committed to the store while node:init, node:snapshot or node:work-one prepared its result, so that result was not saved (node:work-one backs off and retries the candidate); run node:init or node:snapshot again.',
    'EVIDENCE_PATH_UNSAFE': 'The saved status evidence file beside the configuration (NAME.discoverability.json) is a symlink, so --save-status and --if-due runs refused to write it; remove the symlink.',
    'EVIDENCE_TOO_LARGE': 'Saving this status evidence would make the evidence file beside the configuration larger than 16 KiB, so the update was not saved.',
    'EXISTING_MANIFEST_INVALID': 'The Manifest installed at WEBROOT/.well-known/onion-service-discovery.json is missing (for history commands), larger than 16 KiB, unreadable or not a Service Manifest; check it with manifest:verify.',
    'EXPORT_BUDGET_EXHAUSTED': 'The public export directory reached its limit of 4096 files or 256 MiB, or a single export file would exceed 512 KiB; remove unreferenced files with exports:prune or node:maintain.',
    'EXPORT_BUSY': 'Another process held the export directory’s writer lock for more than 2 seconds, so this write or prune did not run; run it again when the other command has finished.',
    'EXPORT_DIRECTORY_UNSAFE': 'The export directory (export_path) is a URL, a symlink, missing or open to group or others, or it contains a symlink, a subdirectory or a file that is not an OSD export file; node:init or client:init creates it with mode 0700.',
    'EXPORT_UNAVAILABLE': 'An export file or the export directory’s lock file could not be created, written, synced, renamed, read or deleted; check the ownership and free space of export_path.',
    'FILESYSTEM_SEMANTICS_UNSUPPORTED': 'After an atomic rename, the directory holding the store, the exports or the installed public records could not be opened or synced, so the write is not known to be durable; use a local filesystem that supports syncing directories.',
    'FILE_TOO_LARGE': 'The chosen file is larger than the browser client accepts (1 MiB for a signed record bundle, 256 KiB for a pin backup), so it was not read.',
    'FORKED': 'Not an error: FORKED is the state of an identity, search result or pin when two different signed Manifests share a revision number or rotation or migration proofs conflict, so no current Manifest is selected.',
    'HASH_INVALID': 'A value that must be a content hash (for example a checkpoint or chunk hash a Node served) is not sha256: followed by 64 lowercase hex digits.',
    'HEADERS_TOO_LARGE': 'A Node or Service answered with more than 8 KiB of response headers (or, for the PHP transport, a header line over 2 KiB), so nothing from it was used.',
    'IMPORT_FORMAT_INVALID': 'The imported file is neither a single signed record nor a record bundle (bundle_version 1 with a records list), so nothing was imported.',
    'IMPORT_REQUIRES_EMPTY_STAGING_STORE': 'storage:import writes only into a new, empty store, and the given staging store already holds records or changes; give a new staging path.',
    'INPUT_FILE_UNAVAILABLE': 'An input file is missing, not a regular file, unreadable or given as a URL; check the path and read permission.',
    'JSON_DEPTH_EXCEEDED': 'The JSON nests objects or arrays more than 16 levels deep, beyond what the protocol allows.',
    'KEY_DIRECTORY_UNSAFE': 'key:create refused the key path because it is a URL or its directory is a symlink, missing or open to group or others; create the directory with mode 0700 first.',
    'KEY_ENVELOPE_INVALID': 'The key file is not a valid key envelope for this role (for example a service key used as a Node key) or is damaged, so the key was not unlocked; use the key file key:create made for this role.',
    'KEY_FILE_UNAVAILABLE': 'The key file is missing, a symlink, not a regular file, open to group or others, larger than 4 KiB or unreadable, or key:create found an existing file or could not write it; keys must be owner-only (0600) regular files.',
    'KEY_UNAVAILABLE': 'An internal check failed: the unlocked signing key was no longer in memory when a record was to be signed, so nothing was signed.',
    'LOCAL_RESOURCE_TOO_LARGE': 'The Client Host’s config.json exceeds 16 KiB or its demo.json exceeds 1 MiB, so the browser client stopped reading it.',
    'LOCAL_RESOURCE_UNAVAILABLE': 'The browser client could not load config.json or demo.json from its Client Host as a successful JSON response; without config.json no configured Nodes are offered.',
    'MANIFEST_INVALID': 'The installed public Manifest file holds a validly signed record that is not a Service Manifest, so the status page and publisher:status report the Manifest as invalid; install a signed Service Manifest with manifest:install.',
    'METADATA_INVALID': 'The metadata file for manifest:init or manifest:revise is not a JSON object with a service member and no members other than service, links and discovery.',
    'METHOD_NOT_ALLOWED': 'The route does not accept the request method (HTTP 405): the data, relay and client routes take GET or HEAD, and the announcement route takes only POST.',
    'MIRROR_DISABLED': 'node:snapshot refused to run because features.mirror_directory and features.server_search are both off in this configuration; enable the feature it needs in a node or combined configuration.',
    'NODE_DESCRIPTOR_HISTORY_INCOMPLETE': 'A peer’s new Node descriptor skips a revision or does not name the previously seen descriptor as its predecessor, so the peer was quarantined; after checking, release it with peers:retry.',
    'NODE_DESCRIPTOR_INVALID': 'The Node descriptor in the store does not verify or names another Onion than node.onion (doctor also reports a published snapshot that fails verification this way); check that storage.path and node.onion belong to this Node.',
    'NODE_DESCRIPTOR_ROLLBACK_OR_FORK': 'A peer served an older Node descriptor than seen before, or two different descriptors with the same revision, so the peer was quarantined and the descriptors were kept as evidence; quarantine:list shows the evidence.',
    'NODE_IDENTITY_CHANGED': 'The Node descriptor in the store names another Node key or Onion than the unlocked key file and node.onion, so node:snapshot or node:work-one refused to run; use the original key and Onion.',
    'NODE_IDENTITY_CHANGE_REQUIRES_MIGRATION': 'node:init found a Node descriptor in the store with another Node key or Onion than this key file and node.onion, and protocol v1 cannot change a Node identity; use the original key and Onion or set up a new Node.',
    'NODE_KEY_ENVELOPE_INVALID': 'doctor: the Node key file at node.key_path is unreadable or its unencrypted header is not a valid Node key envelope (for example a service key); the passphrase was not checked.',
    'NODE_UNAVAILABLE': 'The front controller answered 503 because the request failed, for example with a missing or invalid configuration, an unreadable store or export directory, or a relayed Node that could not be fetched; operators run doctor.',
    'OUTPUT_DIRECTORY_UNAVAILABLE': 'The --output-dir given to manifest:rotate or manifest:migrate is a URL, a symlink, missing or not writable; create a writable directory first.',
    'PASSPHRASE_POLICY': 'The passphrase file holds an empty passphrase or one longer than 1024 bytes (one trailing newline is ignored), or key:create got one shorter than 12 bytes.',
    'PEER_BINDING_MISMATCH': 'The peer’s Node descriptor names another Onion or is signed by another key than the one pinned with peers:add, so the peer was quarantined; check the key, then release it with peers:retry.',
    'PEER_BUSY': 'A sync step has claimed this peer, so peers:group did not change its group; run peers:group again when the step has finished.',
    'PEER_CLAIM_LOST': 'This sync step lost its claim on the peer because its 5-minute lease ended and another step took over, or peers:disable or peers:retry reset the peer, so it stopped without saving more.',
    'PEER_KEY_CHANGE_REQUIRES_EXPLICIT_RESET': 'This Onion is already a peer pinned to another Node key, and peers:add never replaces a pinned key, so nothing was changed; a Node with a new key is added under its new Onion (incident runbook).',
    'PEER_NOT_CONFIGURED': 'The Onion is not in this Node’s peer list, so the peer command or sync step had nothing to act on; add it with peers:add first.',
    'PEER_POOL_FULL': 'This Node already lists 256 peers, the maximum, counting disabled peers too, so no peer was added.',
    'PIN_BACKUP_INVALID': 'The file is not a valid pin backup from this client: it needs pin_backup_version 1 and at most 256 distinct pins, each with a valid key, Onion, hashes, revision and a label of at most 128 characters.',
    'PIN_BUDGET_EXHAUSTED': 'Restoring these pins would exceed the limit of 256 pins, so none were restored; remove some pins before importing more.',
    'PIN_CONTEXT_UNAVAILABLE': 'This identity cannot be pinned: it is not in the loaded index, is withdrawn or forked, or has no current Onion, or 256 pins already exist.',
    'PROTOCOL_UNSUPPORTED': 'The record does not declare protocol Onion Service Discovery with protocol_version 1, the only version this software supports.',
    'PUBLIC_MANIFEST_PATH_UNSAFE': 'The public Manifest file or the publisher lock file in WEBROOT/.well-known is a symlink, so the publisher command refused to write there; remove the symlink and install again.',
    'PUBLIC_MANIFEST_WRITE_FAILED': 'The publisher lock, the new Manifest or the history bundle could not be created, written, synced or renamed in WEBROOT/.well-known; check that the installing user can write there.',
    'RATE_LIMIT_CONFIGURATION_INVALID': 'A request budget file in the export directory (.request-budget, .relay-budget or .visitor-relay-budget) is a symlink or its limits are invalid, so requests fail with HTTP 503; remove the symlink.',
    'RATE_LIMIT_CORRUPT': 'A request budget file in the export directory is larger than 1 KiB or holds no valid budget state, so requests fail with HTTP 503; delete the file; it is recreated with a full budget.',
    'RATE_LIMIT_UNAVAILABLE': 'A request budget file in the export directory cannot be created or written or is open to group or others, so requests fail with HTTP 503; let the web server user own it with mode 0600.',
    'REQUEST_INVALID': 'The request is malformed (HTTP 400): a path over 256 bytes or with spaces or control characters, an invalid Onion address in the path, or an announcement that is not the exact hint JSON.',
    'REQUEST_TOO_LARGE': 'An announcement request declared or sent more than 256 bytes, or its Content-Length is malformed (HTTP 413).',
    'REVISION_DOWNGRADE': 'A Service or source served an older Manifest revision than one already known for that identity (node:work-one quarantines the candidate; a browser pin keeps the newer revision); load other sources.',
    'REVISION_EXHAUSTED': 'The current Manifest already has the highest possible revision (9007199254740991), so no successor revision can be drafted.',
    'SEARCH_INDEX_CORRUPT': 'The server search index files in the export directory are missing, altered or malformed or do not match the published snapshot, so server search is unavailable; rebuild them with node:snapshot --rebuild-derived.',
    'SEARCH_INDEX_STALE': 'The server search index was built for another snapshot than the one now published, or by an earlier release in an older format, so server search is unavailable; rebuild it with node:snapshot --rebuild-derived.',
    'SEARCH_INDEX_UNAVAILABLE': 'Server search has no index because no snapshot was published or it was published with server search off, so server search is unavailable; run node:snapshot with server_search enabled.',
    'SEARCH_REQUEST_INVALID': 'The server search form submission is malformed (wrong media type, unknown or repeated fields, a query over 256 UTF-16 units, or an invalid language, category or adult value), so it was rejected; use the search form.',
    'SEARCH_TEXT_INVALID': 'Text given to search normalization is not valid UTF-8, exceeds 1 MiB or contains an invalid code point, so it was refused.',
    'SEARCH_WORK_BUDGET_EXHAUSTED': 'Server search exceeded a work limit: a search ran longer than 0.5 seconds and returned no partial result, or node:snapshot found more than 10000 services, 16 MiB or 128 chunks for the index.',
    'SEARCH_WORK_LIMIT': 'Search text exceeds the Unicode normalization limit of 65536 UTF-16 units, or 262144 code points before or after decomposition, so it was refused; shorten the text.',
    'SERVER_SEARCH_INDEX_UNAVAILABLE_OR_CORRUPT': 'doctor: server search is on, but its index cannot be loaded because it is missing, stale or corrupt, so the search page answers 503; rebuild it with node:snapshot --rebuild-derived.',
    'SERVICE_INDEX_NOT_READY': 'This Node has not yet published a snapshot with the current (version 2) service lookup index, so service lookups answer 503; the operator runs node:snapshot.',
    'SIGNER_BINDING_MISMATCH': 'The last published snapshot was signed by another Node key than the one unlocked, so node:snapshot refused to publish; use the Node key that signed this store’s snapshots.',
    'SIGNER_KEY_MISMATCH': 'The record names another public key for its signer (new_public_key with --rotation-confirmation) than the key in this key file, so record:sign signed nothing; sign with the key the record names.',
    'SIGNER_ROLE_INVALID': 'The key role is neither service nor node, so key:create or record:sign did nothing.',
    'SIGNER_ROLE_MISMATCH': 'The key role cannot sign this record type (service keys sign Manifests, rotations and migrations; Node keys sign Node descriptors, observations and snapshots), or --rotation-confirmation was used on another record.',
    'SNAPSHOT_CHUNK_HASH_INVALID': 'A snapshot chunk’s size or hash does not match the signed checkpoint, so the chunk was rejected.',
    'SNAPSHOT_RECORD_ORDER_INVALID': 'A snapshot chunk contains a snapshot descriptor, or its records are not in strictly ascending content-hash order across the snapshot, so the chunk was rejected.',
    'SNAPSHOT_UNAVAILABLE': 'No snapshot has been published yet, so the Node has no state to serve (HTTP 503 on the state route) and no exports to prune; run node:snapshot first.',
    'SOURCE_CANCELLED': 'The visitor cancelled loading, so nothing from the Node being loaded was used and Nodes not yet started are shown as cancelled.',
    'SOURCE_DESCRIPTOR_EQUIVOCATION': 'The Node signed two different Node descriptors with the same revision, so the browser set its data aside and kept both signed records as evidence; save them with Save the conflicting signed records.',
    'SOURCE_DESCRIPTOR_HISTORY_INCOMPLETE': 'The Node’s newer descriptor does not connect revision by revision to the one seen before through the descriptors in its snapshot, so its data from this load was not used.',
    'SOURCE_DESCRIPTOR_ROLLBACK_OR_FORK': 'The Node served an older Node descriptor than this browser saw before, or a different one with the same revision, so its data was set aside.',
    'SOURCE_EQUIVOCATION_PRESERVED': 'This browser already holds proof that the Node signed two conflicting states, so the Node was not loaded again and the evidence was kept.',
    'SOURCE_GROUP_ASSIGNMENTS_INCONSISTENT': 'doctor warning: active peers that share one Node key are in different local source groups, or only some of them have a group; give them one group with peers:group.',
    'SOURCE_GROUP_INVALID': 'A source group name is not 1 to 32 lowercase letters, digits or inner hyphens, or a stored peer has an invalid group or Node key.',
    'SOURCE_HTTP_STATUS_REJECTED': 'The Node or the Client Host relay answered with an HTTP status other than 200, so nothing from it was used; try again later.',
    'SOURCE_INVALID': 'An internal check failed: the label recording where a candidate came from is not 1 to 96 letters, digits, underscores, dots, colons or hyphens, so the candidate was not queued.',
    'SOURCE_NODE_BINDING_MISMATCH': 'The record served at the Node’s node route is not a Node descriptor for the Onion address that was loaded, so nothing from that Node was used.',
    'SOURCE_RECORD_COUNT_INVALID': 'The Node’s snapshot chunks held a different number of records than its signed checkpoint declares (the browser discards the load; a Node worker quarantines the peer).',
    'SOURCE_STATE_INVALID': 'The Node’s state answer has no valid sha256 checkpoint hash, so service:membership rejected that Node’s evidence.',
    'SOURCE_TIME_BUDGET_EXHAUSTED': 'Downloads from this Node took more than the browser’s 90 seconds of network time for one source, so nothing partial was used; try again later. Signature checking does not count against this limit.',
    'SOURCE_WORK_BUDGET_EXHAUSTED': 'The Node’s signed snapshot exceeds the checking limits (over 10000 records or 32 MiB of chunks, or over 64 chunks in the browser), so the browser discards the load and a Node worker quarantines the peer.',
    'SQLITE_DRIVER_UNAVAILABLE': 'The sqlite storage backend needs PHP PDO with the SQLite driver, which the PHP runtime running this code lacks; enable pdo_sqlite in that runtime or use the filesystem backend.',
    'STORAGE_ADDRESS_INVALID': 'A record or export address is not sha256: followed by 64 lowercase hex digits, or an operational table or key name is invalid, so the store refused the request.',
    'STORAGE_BACKEND_INVALID': 'The storage backend, given as a command argument or as storage.backend in the configuration, is neither filesystem nor sqlite.',
    'STORAGE_ENCODING_INVALID': 'A value could not be put into the store’s strict internal JSON form (nesting over 32 levels, invalid UTF-8, a string over 64 KiB, an unsafe key or an unsupported type), so it was not written.',
    'STORAGE_ERROR': 'A store operation failed at the operating-system or SQLite level (for example a write or sync on a full disk); data committed before is kept; free space or fix permissions and run the command again.',
    'STORAGE_MODE_INVALID': 'An internal check failed: a store cannot be opened for creation and read-only at the same time, so it was not opened.',
    'STORAGE_NOT_REQUIRED_FOR_PUBLISHER': 'The command needs a Node store, but this is a publisher configuration, which keeps none; use a node or combined configuration.',
    'STORAGE_PATH_UNSAFE': 'The filesystem store path is a URL or a symlink; point it at the real directory.',
    'STORAGE_PERMISSIONS_UNSAFE': 'The store directory, one of its subdirectories or the SQLite database file is open to group or others; chmod 700 the directories and 600 the SQLite file.',
    'STORAGE_READ_ONLY': 'An internal check failed: a write was attempted on a store opened read-only, so nothing was changed.',
    'STORAGE_UNAVAILABLE': 'The store path does not exist (and this command does not create it), is a symlink or URL, or the store or one of its files or directories cannot be created or opened; check storage.path; node:init or storage:init creates a store.',
    'STORAGE_VERSION_UNSUPPORTED': 'The store has a format this release cannot open: a filesystem storage-version other than 1 or 2, or a SQLite schema version other than 1 or SQLite without foreign-key support.',
    'STREAMING_UNAVAILABLE': 'The browser returned the Node’s answer without a readable body stream, which the bounded download requires, so nothing from it was used.',
    'SYNC_DISABLED': 'Peer sync is off in this configuration (features.sync_with_peers is false), so sync:run and sync:peer-step refused to run; enable it to sync with peers.',
    'TOO_LARGE': 'Not an error: manifest:install reports the history state TOO_LARGE when the connected history exceeds 256 records or 1 MiB, so the history bundle was not updated.',
    'TRANSPORT_CONFIGURATION_INVALID': 'The Tor transport could not start: the PHP curl extension is missing, or the SOCKS host, port or timeout is outside 127.0.0.1 or ::1, 1 to 65535, or 100 to 60000 ms.',
    'TRANSPORT_UNAVAILABLE': 'PHP curl could not prepare the request through Tor (its handle or a required option could not be set), so nothing was sent; check the curl extension of this PHP runtime.',
    'UNVERIFIED_RECORD': 'An internal browser check failed: a record reached a step that accepts only records this page verified itself, so it was refused.',
    'WEBROOT_INVALID': 'The webroot is a URL, a symlink or not a directory, or its .well-known directory is a symlink, missing (history:install) or cannot be created (manifest:install).',
    'WITNESS_BUDGET_EXHAUSTED': 'The browser’s memory for Node evidence is full (512 signed checkpoints and descriptors, or fork evidence for 16 Nodes) and never drops proofs, so further Node data was refused; save any evidence you need, then reload the page.',
    'WITNESS_CONFIGURATION_INVALID': 'An internal browser check failed: the memory for Node evidence was created with limits outside 1 to 512 records or 1 to 16 forks.',
    'WITNESS_TYPE_INVALID': 'An internal browser check failed: only signed checkpoints and Node descriptors can be kept as Node evidence, and another record type was offered.',
}


def collect() -> dict:
    codes: dict = {}
    for source in SOURCES:
        base = ROOT / source
        files = [base] if base.is_file() else sorted(path for path in base.rglob('*') if path.suffix in {'.php', '.ts'})
        for path in files:
            relative = path.relative_to(ROOT).as_posix()
            component = next(name for prefix, name in COMPONENTS.items() if relative.startswith(prefix))
            text = path.read_text(encoding='utf-8')
            found = [(exception, code) for exception, arguments in THROW.findall(text) for code in LITERAL.findall(arguments)]
            found += [('HTTP', code) for code in RESPONSE.findall(text)]
            found += [('doctor-' + kind, code) for kind, code in DOCTOR.findall(text)]
            for exception, code in found:
                entry = codes.setdefault(code, {'components': set(), 'cli_exit': set()})
                entry['components'].add('doctor' if exception.startswith('doctor-') else component)
                if component == 'browser client':
                    continue
                if exception in EXITS:
                    entry['cli_exit'].add('2' if code == 'USAGE_ERROR' else EXITS[exception])
                elif exception.startswith('doctor-'):
                    entry['cli_exit'].add('4' if exception == 'doctor-failures' else '6')
    # The CLI entry point prints these as literal JSON outside any exception.
    for code, exit_code in CLI_LITERAL_EXITS.items():
        entry = codes.setdefault(code, {'components': set(), 'cli_exit': set()})
        entry['components'].add('CLI')
        entry['cli_exit'].add(exit_code)
    return {code: {'components': sorted(value['components']), 'cli_exit': sorted(value['cli_exit']), 'meaning': DESCRIPTIONS.get(code)}
            for code, value in sorted(codes.items())}


def render(registry: dict) -> tuple[str, str]:
    lines = ['# Error codes', '', 'Generated by `tools/generate_error_registry.py` from the codes the PHP and browser',
             'sources raise; do not edit by hand. CLI errors print `{"status":"FAIL","error":CODE}`, plus',
             '`detail` (where, for example a configuration key path; never a value), `meaning` (from this',
             'registry) and `help` (the page to read), with the',
             'exit code shown (2 usage, 3 input/validation, 4 storage/signing or failed `doctor` check, 5 Tor',
             'transport, 6 `doctor` warning, 8 internal error). HTTP routes return codes as `{"error":CODE}`. Browser codes',
             'appear in the source or operation status. See [troubleshooting](../getting-started/troubleshooting.md)',
             'for remedies.', '', f'{len(registry)} codes.', '', '| Code | Raised by | CLI exit | Meaning |', '| --- | --- | --- | --- |']
    for code, entry in registry.items():
        lines.append(f"| `{code}` | {', '.join(entry['components'])} | {', '.join(entry['cli_exit']) or '–'} | {entry['meaning']} |")
    return '\n'.join(lines) + '\n', json.dumps({'registry_version': 1, 'codes': registry}, indent=1, sort_keys=True) + '\n'


def main() -> int:
    registry = collect()
    undocumented = [code for code, entry in registry.items() if not entry['meaning']]
    unused = sorted(set(DESCRIPTIONS) - set(registry))
    if undocumented:
        print('Error codes without a meaning: add them to DESCRIPTIONS in tools/generate_error_registry.py (' + ', '.join(undocumented) + ')')
    if unused:
        print('DESCRIPTIONS for codes that are no longer raised: remove them (' + ', '.join(unused) + ')')
    if undocumented or unused:
        return 1
    markdown, machine = render(registry)
    targets = {ROOT / 'docs/operations/error-codes.md': markdown, ROOT / 'docs/operations/error-codes.json': machine}
    if sys.argv[1:] == ['--check']:
        stale = [path.relative_to(ROOT).as_posix() for path, text in targets.items() if not path.is_file() or path.read_text(encoding='utf-8') != text]
        if stale:
            print('Stale error registry: run python3 tools/generate_error_registry.py (' + ', '.join(stale) + ')')
            return 1
        print(f'PASS: error registry matches {len(json.loads(machine)["codes"])} raised codes')
        return 0
    for path, text in targets.items():
        path.write_text(text, encoding='utf-8')
    return 0


if __name__ == '__main__':
    sys.exit(main())
