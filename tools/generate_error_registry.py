"""Generate the error-code registry (spec 32 section 35) from the codes the source actually raises.

The source code is the single source of truth for which codes exist; DESCRIPTIONS adds plain-language
meanings for the codes operators and visitors most often see. `--check` fails when the committed
registry is stale, so a new code cannot ship undocumented.
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
    'CONFIGURATION_INVALID': 'The configuration file has a missing, unknown or wrongly typed key; see the configuration reference.',
    'CONFIGURATION_UNAVAILABLE': 'The configuration file is missing, unreadable, a symlink or larger than 16 KiB.',
    'CONTENT_TYPE_REJECTED': 'A Node or Service answered without application/json; nothing was verified.',
    'DOCUMENT_TOO_LARGE': 'An input file exceeds the limit for its record type.',
    'EVIDENCE_WRITE_FAILED': 'Saved status evidence could not be written beside the configuration; check directory permissions.',
    'ANNOUNCEMENT_RATE_LIMITED': 'The Node answered 429: its shared announcement budget is exhausted. The bounded schedule retries no earlier than a day later.',
    'ANNOUNCEMENT_RESPONSE_INVALID': 'The Node did not answer with exactly HINT_RECEIVED_UNCONFIRMED; no other claim is accepted as an announcement result.',
    'PUBLISHER_BUSY': 'Another publisher command holds the lock on the same files; run it again when that command has finished.',
    'EXPECTED_NODE_KEY_REQUIRED': 'A visitor-chosen Node needs its expected public key.',
    'EXPORT_CORRUPT': 'Published snapshot files do not match their signed hashes; rebuild with node:snapshot --rebuild-derived.',
    'HISTORY_INCOMPLETE': 'The current Manifest does not connect to its history: the Service serves no or an incomplete history bundle (history:install), or a proof is missing.',
    'HISTORY_BUNDLE_INVALID': 'The PCP-004 history bundle is malformed, too large or contains a record type it must not contain.',
    'HTTP_STATUS_REJECTED': 'The remote answered with an unexpected HTTP status; nothing was verified.',
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
    'PIN_IMPORT_CONFLICT': 'An imported pin disagrees with a pin you already have; nothing was replaced.',
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
    'TOR_REQUEST_FAILED': 'Tor or its SOCKS port is unavailable, or the Onion did not answer.',
    'USAGE_ERROR': 'Wrong arguments; run help for the exact syntax.',
    'VISITOR_SOURCES_DISABLED': 'This Client Host does not relay visitor-chosen Nodes.',
    'VISITOR_SOURCE_LIMIT': 'At most 8 visitor-chosen Nodes can be added.',
    'WORK_BUDGET_EXHAUSTED': 'The input exceeds a bounded work limit and was refused.',
    'FILESYSTEM_FORMAT_MIGRATION_REQUIRED': 'doctor: the filesystem store uses format 1; plan storage:migrate.',
    'NODE_KEY_MISSING_OR_UNSAFE': 'doctor: the Node key file is missing or readable by others (needs 0600).',
    'RUNTIME_OUTSIDE_EXECUTED_MINOR_MATRIX': 'doctor: this PHP minor version is outside the tested 8.4/8.5 matrix.',
    'SERVER_SEARCH_ENABLED_QUERIES_SENT_TO_NODE': 'doctor: server search is on, so search terms reach this Node.',
    'SNAPSHOT_NOT_PUBLISHED': 'doctor: directory mirroring is on but no snapshot was published yet (node:snapshot).',
    'SOURCE_SET_CONCENTRATED': 'doctor: most configured peers share one source group; diversity is low.',
    'VISITOR_SOURCE_RELAY_ENABLED_OUTBOUND_TO_VISITOR_CHOSEN_NODES': 'doctor: the Client Host relays Nodes chosen by visitors.',
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
             'sources raise; do not edit by hand. CLI errors print `{"status":"FAIL","error":CODE}` with the',
             'exit code shown (2 usage, 3 input/validation, 4 storage/signing or failed `doctor` check, 5 Tor',
             'transport, 6 `doctor` warning, 8 internal error). HTTP routes return codes as `{"error":CODE}`. Browser codes',
             'appear in the source or operation status. See [troubleshooting](../getting-started/troubleshooting.md)',
             'for remedies.', '', f'{len(registry)} codes.', '', '| Code | Raised by | CLI exit | Meaning |', '| --- | --- | --- | --- |']
    for code, entry in registry.items():
        lines.append(f"| `{code}` | {', '.join(entry['components'])} | {', '.join(entry['cli_exit']) or '–'} | {entry['meaning'] or 'The code name states the condition; see the raising component.'} |")
    return '\n'.join(lines) + '\n', json.dumps({'registry_version': 1, 'codes': registry}, indent=1, sort_keys=True) + '\n'


def main() -> int:
    markdown, machine = render(collect())
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
