"""Generate immutable Unicode 17 search tables and independent official NFKC vectors."""
from __future__ import annotations
import argparse
import gzip
import hashlib
import io
import json
from pathlib import Path
import urllib.request
import zipfile
ROOT = Path(__file__).resolve().parents[1]
ZIP_HASH = '2066d1909b2ea93916ce092da1c0ee4808ea3ef8407c94b4f14f5b7eb263d28e'
URL = 'https://www.unicode.org/Public/17.0.0/ucd/UCD.zip'
FILES = {'UnicodeData.txt':'2e1efc1dcb59c575eedf5ccae60f95229f706ee6d031835247d843c11d96470c','SpecialCasing.txt':'efc25faf19de21b92c1194c111c932e03d2a5eaf18194e33f1156e96de4c9588','DerivedCoreProperties.txt':'24c7fed1195c482faaefd5c1e7eb821c5ee1fb6de07ecdbaa64b56a99da22c08','DerivedNormalizationProps.txt':'71fd6a206a2c0cdd41feb6b7f656aa31091db45e9cedc926985d718397f9e488','NormalizationTest.txt':'5019ffd530751a741900c849c0e010332f142a3612234639bd200b82138a87db'}


def ranges(source: str, property_name: str) -> list[list[int]]:
    result: list[list[int]] = []
    for line in source.splitlines():
        fields = line.split('#',1)[0].strip().split(';')
        if len(fields) < 2 or fields[1].strip() != property_name:
            continue
        pair = [int(item,16) for item in fields[0].strip().split('..')]
        result.append([pair[0],pair[-1]])
    result.sort()
    merged: list[list[int]] = []
    for start,end in result:
        if merged and start <= merged[-1][1]+1:
            merged[-1][1] = max(merged[-1][1],end)
        else:
            merged.append([start,end])
    return merged


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--ucd-zip', type=Path)
    args = parser.parse_args()
    if args.ucd_zip:
        raw = args.ucd_zip.read_bytes()
    else:
        with urllib.request.urlopen(URL,timeout=30) as response:
            raw = response.read(16*1024*1024+1)
    if hashlib.sha256(raw).hexdigest() != ZIP_HASH:
        raise SystemExit('Unicode source archive hash mismatch')
    with zipfile.ZipFile(io.BytesIO(raw)) as archive:
        sources = {}
        for name,digest in FILES.items():
            blob = archive.read(name)
            if hashlib.sha256(blob).hexdigest() != digest:
                raise SystemExit('Unicode source member hash mismatch')
            sources[name] = blob.decode('utf-8')
    decompositions = {}; canonical = {}; combining = {}; lower = {}
    for line in sources['UnicodeData.txt'].splitlines():
        fields = line.split(';'); point = int(fields[0],16)
        if int(fields[3]): combining[str(point)] = int(fields[3])
        if fields[5]:
            values = fields[5].split()
            if not values[0].startswith('<'): canonical[point] = [int(x,16) for x in values]
            else: values = values[1:]
            decompositions[str(point)] = [int(x,16) for x in values]
        if fields[13]: lower[str(point)] = [int(fields[13],16)]
    for line in sources['SpecialCasing.txt'].splitlines():
        fields = line.split('#',1)[0].strip().split(';')
        if len(fields) >= 5 and not fields[4].strip():
            point = int(fields[0].strip(),16); mapped = [int(x,16) for x in fields[1].split()]
            if mapped != [point]: lower[str(point)] = mapped
    excluded = {point for start,end in ranges(sources['DerivedNormalizationProps.txt'],'Full_Composition_Exclusion') for point in range(start,end+1)}
    composition = {f'{parts[0]}:{parts[1]}':point for point,parts in canonical.items() if len(parts)==2 and point not in excluded}
    data = {'format_version':1,'unicode_version':'17.0.0','decomposition':decompositions,'combining':combining,'composition':composition,'lower':lower,'cased':ranges(sources['DerivedCoreProperties.txt'],'Cased'),'case_ignorable':ranges(sources['DerivedCoreProperties.txt'],'Case_Ignorable')}
    wire = json.dumps(data,ensure_ascii=True,separators=(',',':'),sort_keys=True)+'\n'
    (ROOT/'protocol/registries/search-unicode-v1.json').write_text(wire)
    (ROOT/'web-local-search-client/src/client/search-unicode-data.ts').write_text('// Generated Unicode 17 data; Unicode-3.0 license, see third-party/Unicode-3.0.txt.\nexport const searchUnicodeData = '+wire.strip()+' as const;\n')
    cases: dict[str,str] = {}
    for line in sources['NormalizationTest.txt'].splitlines():
        line = line.split('#',1)[0].strip()
        if not line or line.startswith('@'): continue
        fields = [value.strip() for value in line.split(';')[:5]]
        for source in fields:
            previous = cases.setdefault(source,fields[3])
            if previous != fields[3]: raise SystemExit('Conflicting official NFKC vectors')
    vector_wire = json.dumps(sorted(cases.items()),separators=(',',':')).encode()
    compressed = io.BytesIO()
    with gzip.GzipFile(filename='',mode='wb',fileobj=compressed,compresslevel=9,mtime=0) as stream:
        stream.write(vector_wire)
    vector = compressed.getvalue()
    (ROOT/'protocol/test-vectors/unicode-nfkc-v1.json.gz').write_bytes(vector)
    manifest = {'format_version':1,'scope':'immutable search presentation only; signed metadata untouched','unicode_version':'17.0.0','source_url':URL,'archive_sha256':ZIP_HASH,'source_members':FILES,'table_sha256':hashlib.sha256(wire.encode()).hexdigest(),'normalization_vectors_sha256':hashlib.sha256(vector).hexdigest(),'normalization_case_count':len(cases),'license':'Unicode-3.0','license_file':'third-party/Unicode-3.0.txt'}
    (ROOT/'protocol/registries/search-unicode-v1.provenance.json').write_text(json.dumps(manifest,indent=2)+'\n')
    print(f'Generated Unicode 17 search tables and {len(cases)} distinct official NFKC inputs')


if __name__ == '__main__': main()
