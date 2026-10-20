"""Generate the shared D-054 grammar constants (owner-approved); independent semantic parsers remain separate."""
from pathlib import Path
import json
ROOT = Path(__file__).resolve().parents[1]
r = json.loads((ROOT/'protocol/registries/language-tags-v1.json').read_text())
(ROOT/'web-local-search-client/src/protocol/language-grammar.ts').write_text(
    '// Generated D-054 language-tag grammar, owner-approved for protocol v1.\n'
    + 'export const LANGUAGE_GRAMMAR = ' + json.dumps(r['pattern']) + ';\n'
    + 'export const GRANDFATHERED_TAGS = new Set<string>(' + json.dumps(r['grandfathered']) + ');\n')
(ROOT/'validator-python/language_grammar.py').write_text(
    '# Generated D-054 language-tag grammar, owner-approved for protocol v1.\n'
    + 'LANGUAGE_GRAMMAR = ' + repr(r['pattern']) + '\n'
    + 'GRANDFATHERED_TAGS = frozenset(' + repr(r['grandfathered']) + ')\n')
php = "<?php\ndeclare(strict_types=1);\nnamespace OnionServiceDiscovery\\Domain;\n"
php += '// Generated D-054 language-tag grammar, owner-approved for protocol v1.\nfinal class LanguageGrammar\n{\n'
php += "    public const PATTERN = '" + r['pattern'] + "';\n"
php += '    public const GRANDFATHERED = [' + ','.join("'"+tag+"'" for tag in r['grandfathered']) + '];\n}\n'
(ROOT/'node-php/src/Domain/LanguageGrammar.php').write_text(php)
