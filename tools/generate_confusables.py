"""Generate the frozen Unicode 17 confusable prototypes for look-alike name warnings (UTS #39 section 4).

The browser client compares self-declared names by their confusable skeletons: NFD, characters with
Default_Ignorable_Code_Point removed, every code point replaced by its MA prototype, NFD again. A
skeleton only decides whether a look-alike note is shown; signed names are never rewritten or hidden.

The input is confusables.txt of Unicode 17.0.0 as shipped in ICU tag release-78.3. Pass its path;
any other bytes are refused before anything is written.

  python3 tools/generate_confusables.py PATH          write the client table and its provenance
  python3 tools/generate_confusables.py PATH --check  fail unless the committed files equal a fresh run
  python3 tools/generate_confusables.py --check       without the source: fail unless the committed table
                                                      matches its recorded digests and its own encoding
"""
from __future__ import annotations
import argparse
import hashlib
import json
from pathlib import Path
import re
import unicodedata

ROOT = Path(__file__).resolve().parents[1]
UNICODE_VERSION = '17.0.0'
SOURCE_SHA256 = '091c7f82fc39ef208faf8f94d29c244de99254675e09de163160c810d13ef22a'
SOURCE = {'file': 'confusables.txt', 'standard': 'UTS #39 Unicode Security Mechanisms', 'header_version': UNICODE_VERSION,
          'repository': 'https://github.com/unicode-org/icu', 'tag': 'release-78.3', 'path': 'icu4c/source/data/unidata/confusables.txt',
          'source_url': 'https://raw.githubusercontent.com/unicode-org/icu/release-78.3/icu4c/source/data/unidata/confusables.txt',
          'sha256': SOURCE_SHA256}
DECOMPOSITION = 'protocol/registries/search-unicode-v1.json'
DECOMPOSITION_PROVENANCE = 'protocol/registries/search-unicode-v1.provenance.json'
OUTPUT = 'web-local-search-client/src/client/confusables-data.ts'
PROVENANCE = 'protocol/registries/confusables-v1.provenance.json'
DIGITS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
HANGUL_SYLLABLES = range(0xAC00, 0xD7A4)
# The shared Unicode 17 search table keeps canonical and compatibility decompositions without their
# type. These are its canonical ones that are not primary composites (singletons, script-specific and
# post-composition-version exclusions, non-starter decompositions); with the composition pairs they
# give NFD. The normalization stability policy keeps them fixed. The generator checks them against the
# interpreter's own Unicode data, and the client tests compare NFD with the JavaScript engine's.
CANONICAL_NOT_COMPOSED = (
    (0x0340, 0x0341), (0x0343, 0x0344), (0x0374, 0x0374), (0x037E, 0x037E), (0x0387, 0x0387),
    (0x0958, 0x095F), (0x09DC, 0x09DD), (0x09DF, 0x09DF), (0x0A33, 0x0A33), (0x0A36, 0x0A36),
    (0x0A59, 0x0A5B), (0x0A5E, 0x0A5E), (0x0B5C, 0x0B5D), (0x0F43, 0x0F43), (0x0F4D, 0x0F4D),
    (0x0F52, 0x0F52), (0x0F57, 0x0F57), (0x0F5C, 0x0F5C), (0x0F69, 0x0F69), (0x0F73, 0x0F73),
    (0x0F75, 0x0F76), (0x0F78, 0x0F78), (0x0F81, 0x0F81), (0x0F93, 0x0F93), (0x0F9D, 0x0F9D),
    (0x0FA2, 0x0FA2), (0x0FA7, 0x0FA7), (0x0FAC, 0x0FAC), (0x0FB9, 0x0FB9), (0x1F71, 0x1F71),
    (0x1F73, 0x1F73), (0x1F75, 0x1F75), (0x1F77, 0x1F77), (0x1F79, 0x1F79), (0x1F7B, 0x1F7B),
    (0x1F7D, 0x1F7D), (0x1FBB, 0x1FBB), (0x1FBE, 0x1FBE), (0x1FC9, 0x1FC9), (0x1FCB, 0x1FCB),
    (0x1FD3, 0x1FD3), (0x1FDB, 0x1FDB), (0x1FE3, 0x1FE3), (0x1FEB, 0x1FEB), (0x1FEE, 0x1FEF),
    (0x1FF9, 0x1FF9), (0x1FFB, 0x1FFB), (0x1FFD, 0x1FFD), (0x2000, 0x2001), (0x2126, 0x2126),
    (0x212A, 0x212B), (0x2329, 0x232A), (0x2ADC, 0x2ADC), (0xF900, 0xFA0D), (0xFA10, 0xFA10),
    (0xFA12, 0xFA12), (0xFA15, 0xFA1E), (0xFA20, 0xFA20), (0xFA22, 0xFA22), (0xFA25, 0xFA26),
    (0xFA2A, 0xFA6D), (0xFA70, 0xFAD9), (0xFB1D, 0xFB1D), (0xFB1F, 0xFB1F), (0xFB2A, 0xFB36),
    (0xFB38, 0xFB3C), (0xFB3E, 0xFB3E), (0xFB40, 0xFB41), (0xFB43, 0xFB44), (0xFB46, 0xFB4E),
    (0x1D15E, 0x1D164), (0x1D1BB, 0x1D1C0), (0x2F800, 0x2FA1D),
)
# Default_Ignorable_Code_Point from DerivedCoreProperties.txt, 4174 code points; the client tests
# compare it with the JavaScript engine's own property.
DEFAULT_IGNORABLE = (
    (0x00AD, 0x00AD), (0x034F, 0x034F), (0x061C, 0x061C), (0x115F, 0x1160), (0x17B4, 0x17B5),
    (0x180B, 0x180F), (0x200B, 0x200F), (0x202A, 0x202E), (0x2060, 0x206F), (0x3164, 0x3164),
    (0xFE00, 0xFE0F), (0xFEFF, 0xFEFF), (0xFFA0, 0xFFA0), (0xFFF0, 0xFFF8), (0x1BCA0, 0x1BCA3),
    (0x1D173, 0x1D17A), (0xE0000, 0xE0FFF),
)


def scalars(field: str, where: str) -> tuple[int, ...]:
    points = tuple(int(item, 16) for item in field.split())
    if any(point > 0x10FFFF or 0xD800 <= point <= 0xDFFF for point in points):
        raise SystemExit(f'confusables.txt {where}: not a Unicode scalar value')
    return points


def parse(raw: bytes) -> dict[int, tuple[int, ...]]:
    """The MA table of the pinned source, after checking its bytes and shape."""
    if hashlib.sha256(raw).hexdigest() != SOURCE_SHA256:
        raise SystemExit('confusables.txt hash mismatch: expected the Unicode 17.0.0 file of ICU release-78.3')
    text = raw.decode('utf-8')
    if f'\n# Version: {UNICODE_VERSION}\n' not in text:
        raise SystemExit(f'confusables.txt is not version {UNICODE_VERSION}')
    table: dict[int, tuple[int, ...]] = {}
    for number, line in enumerate(text.split('\n'), 1):
        body = line.split('#', 1)[0].strip()
        if not body:
            continue
        fields = [field.strip() for field in body.split(';')]
        if len(fields) != 3 or fields[2] != 'MA':
            raise SystemExit(f'confusables.txt line {number}: expected "source ; prototype ; MA"')
        source, target = scalars(fields[0], f'line {number}'), scalars(fields[1], f'line {number}')
        if len(source) != 1 or not target or source[0] in table:
            raise SystemExit(f'confusables.txt line {number}: one new source and a prototype are required')
        table[source[0]] = target
    # A prototype is never mapped again, so one replacement pass is the whole mapping.
    if any(point in table for target in table.values() for point in target):
        raise SystemExit('confusables.txt maps a prototype code point')
    return table


def points_in(ranges: tuple[tuple[int, int], ...]) -> set[int]:
    return {point for start, end in ranges for point in range(start, end + 1)}


def decomposition_digest() -> tuple[str, dict]:
    raw = (ROOT / DECOMPOSITION).read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    if digest != json.loads((ROOT / DECOMPOSITION_PROVENANCE).read_text())['table_sha256']:
        raise SystemExit(f'{DECOMPOSITION} does not match its provenance')
    return digest, json.loads(raw)


def canonical_decomposables(data: dict) -> set[int]:
    """Code points that NFD changes, from the shared Unicode 17 search table."""
    decomposition = {int(point) for point in data['decomposition']}
    composites = set(data['composition'].values())
    listed = points_in(CANONICAL_NOT_COMPOSED)
    if not listed <= decomposition or listed & composites:
        raise SystemExit('CANONICAL_NOT_COMPOSED lists a code point that is not a non-composite decomposition')
    canonical = composites | listed
    for point in sorted(decomposition):
        mapping = unicodedata.decomposition(chr(point))
        if mapping and (not mapping.startswith('<')) != (point in canonical):
            raise SystemExit(f'U+{point:04X}: canonical decomposition disagrees with Unicode {unicodedata.unidata_version}')
    return canonical | set(HANGUL_SYLLABLES)


def vlq(value: int) -> str:
    """Base64 digits of five bits each, least significant first; digit values from 32 continue."""
    text = ''
    while True:
        digit, value = value & 31, value >> 5
        if not value:
            return text + DIGITS[digit]
        text += DIGITS[digit | 32]


def unvlq(text: str) -> list[int]:
    values: list[int] = []
    value = shift = 0
    for character in text:
        digit = DIGITS.index(character)
        value += (digit & 31) << shift
        shift += 5
        if digit < 32:
            values.append(value)
            value = shift = 0
    if shift:
        raise SystemExit(f'{OUTPUT}: truncated prototype table')
    return values


def encode(table: dict[int, tuple[int, ...]]) -> str:
    previous, parts = -1, []
    for source in sorted(table):
        target = table[source]
        parts.append(vlq(source - previous - 1) + vlq(len(target) - 1) + ''.join(vlq(point) for point in target))
        previous = source
    return ''.join(parts)


def decode(encoded: str) -> dict[int, tuple[int, ...]]:
    values, table, source, index = unvlq(encoded), {}, -1, 0
    while index < len(values):
        if index + 1 == len(values):
            raise SystemExit(f'{OUTPUT}: truncated prototype table')
        source += values[index] + 1
        length = values[index + 1] + 1
        table[source] = tuple(values[index + 2:index + 2 + length])
        if len(table[source]) != length:
            raise SystemExit(f'{OUTPUT}: truncated prototype table')
        index += 2 + length
    return table


def ranges_literal(ranges: tuple[tuple[int, int], ...]) -> str:
    return '[' + ','.join(f'[{start},{end}]' for start, end in ranges) + ']'


def module(table: dict[int, tuple[int, ...]]) -> str:
    return ('// Generated by tools/generate_confusables.py from confusables.txt of Unicode 17.0.0 (UTS #39); do not edit.\n'
            '// Unicode-3.0 license, see third-party/Unicode-3.0.txt; provenance: protocol/registries/confusables-v1.provenance.json.\n'
            'export interface ConfusablesData { readonly unicode_version: string; readonly prototypes: string; readonly prototype_count: number;\n'
            '  readonly canonical_not_composed: readonly (readonly [number, number])[]; readonly default_ignorable: readonly (readonly [number, number])[]; }\n'
            'export const confusablesData: ConfusablesData = {\n'
            f"  unicode_version: '{UNICODE_VERSION}',\n"
            '  // MA prototypes in source order. Per entry: distance to the previous source minus one, prototype\n'
            '  // length minus one, then each prototype code point; base64 digits of five bits, values from 32 continue.\n'
            '  // Sources that NFD decomposes or the skeleton removes are left out: they never reach the mapping.\n'
            f"  prototypes: '{encode(table)}',\n"
            f'  prototype_count: {len(table)},\n'
            '  // Canonical decompositions of search-unicode-data.ts that are not primary composites (see generator).\n'
            f'  canonical_not_composed: {ranges_literal(CANONICAL_NOT_COMPOSED)},\n'
            '  // Default_Ignorable_Code_Point: removed before prototypes apply.\n'
            f'  default_ignorable: {ranges_literal(DEFAULT_IGNORABLE)},\n'
            '};\n')


def provenance(source_count: int, table: dict[int, tuple[int, ...]], omitted: dict[str, int], decomposition: str, text: str) -> str:
    manifest = {'format_version': 1, 'scope': 'browser look-alike name warnings only; signed names are never rewritten, hidden or ranked by it',
                'algorithm': 'UTS #39 section 4 skeleton: NFD, remove Default_Ignorable_Code_Point, replace by MA prototypes, NFD',
                'unicode_version': UNICODE_VERSION, 'source': SOURCE, 'source_entry_count': source_count, 'table_entry_count': len(table),
                'omitted_entries': omitted, 'decomposition_table': {'path': DECOMPOSITION, 'sha256': decomposition},
                'table_path': OUTPUT, 'table_sha256': hashlib.sha256(text.encode()).hexdigest(),
                'license': 'Unicode-3.0', 'license_file': 'third-party/Unicode-3.0.txt'}
    return json.dumps(manifest, indent=2) + '\n'


def generate(raw: bytes) -> dict[str, str]:
    source = parse(raw)
    decomposition, data = decomposition_digest()
    canonical, ignorable = canonical_decomposables(data), points_in(DEFAULT_IGNORABLE)
    table = {point: target for point, target in source.items() if point not in canonical and point not in ignorable}
    omitted = {'source_changed_by_nfd': sum(point in canonical for point in source),
               'source_default_ignorable': sum(point in ignorable and point not in canonical for point in source)}
    text = module(table)
    return {OUTPUT: text, PROVENANCE: provenance(len(source), table, omitted, decomposition, text)}


def check_committed() -> list[str]:
    """Without the source: the committed table, its digests and its encoding agree."""
    problems = []
    text = (ROOT / OUTPUT).read_text()
    manifest = json.loads((ROOT / PROVENANCE).read_text())
    decomposition, _ = decomposition_digest()
    if manifest['source'] != SOURCE:
        problems.append(f'{PROVENANCE}: source differs from the pinned confusables.txt')
    if manifest['decomposition_table'] != {'path': DECOMPOSITION, 'sha256': decomposition}:
        problems.append(f'{PROVENANCE}: made from another search table; regenerate')
    if manifest['table_sha256'] != hashlib.sha256(text.encode()).hexdigest():
        problems.append(f'{OUTPUT}: does not match table_sha256 in {PROVENANCE}')
    match = re.search(r"\n  prototypes: '([A-Za-z0-9+/]*)',\n", text)
    table = decode(match.group(1)) if match else {}
    if not match or module(table) != text or len(table) != manifest['table_entry_count']:
        problems.append(f'{OUTPUT}: not in the generated form')
    return problems


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('source', nargs='?', type=Path, help='confusables.txt, Unicode 17.0.0 (ICU release-78.3)')
    parser.add_argument('--check', action='store_true', help='compare instead of writing')
    args = parser.parse_args()
    if args.source is None:
        if not args.check:
            parser.error('the path of confusables.txt is required to generate')
        problems = check_committed()
        for problem in problems:
            print('FAIL: ' + problem)
        if not problems:
            print('PASS: committed confusable table matches its provenance; run with the source path for a full check')
        return 1 if problems else 0
    outputs = generate(args.source.read_bytes())
    if args.check:
        stale = [path for path, text in outputs.items() if not (ROOT / path).is_file() or (ROOT / path).read_text() != text]
        for path in stale:
            print(f'FAIL: {path} differs from a fresh run; run python3 tools/generate_confusables.py {args.source}')
        if not stale:
            print('PASS: committed confusable table equals a fresh run from the pinned source')
        return 1 if stale else 0
    for path, text in outputs.items():
        (ROOT / path).write_text(text)
    manifest = json.loads(outputs[PROVENANCE])
    print(f"Generated {manifest['table_entry_count']} of {manifest['source_entry_count']} Unicode {UNICODE_VERSION} confusable prototypes")
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
