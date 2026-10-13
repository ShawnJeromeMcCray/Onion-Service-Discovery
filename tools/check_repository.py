"""Check imported specifications and tracked files without printing their contents.

This is a foundation check, not an application security audit. Credential patterns
are a narrow supplemental check; release requires a dedicated secret scanner.
"""

from __future__ import annotations

import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[1]
SECRET_PATTERNS = (
    re.compile(rb"github_pat_[A-Za-z0-9_]{20,}"),
    re.compile(rb"gh[pousr]_[A-Za-z0-9]{20,}"),
    re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----"),
)


def contains_credential(data: bytes) -> bool:
    return any(pattern.search(data) is not None for pattern in SECRET_PATTERNS)


def check_manifest(root: Path) -> list[str]:
    errors: list[str] = []
    try:
        manifest = json.loads((root / "docs/specification-manifest.json").read_text())
        documents = manifest["documents"]
        if manifest["format_version"] != 1 or not isinstance(documents, list):
            return ["Unsupported specification manifest"]
    except (OSError, ValueError, KeyError, TypeError):
        return ["Specification manifest unreadable or malformed"]
    orders = [entry.get('required_reading_order') for entry in documents if isinstance(entry, dict)]
    if manifest.get('source_document_count') != 55 or manifest.get('mandatory_reading_count') != 54 or len(orders) != 55 or any(type(number) is not int for number in orders) or sorted(orders) != list(range(55)):
        errors.append('Refreshed corpus count or required reading order invalid')
    if len(documents) != 55:
        errors.append("Expected the 55-document refreshed source corpus")
    paths: set[str] = set()
    urls: set[str] = set()
    for entry in documents:
        try:
            relative = entry["path"]
            url = entry["url"]
            if not isinstance(relative, str) or not isinstance(url, str):
                raise ValueError
            path = root / relative
            source_root = (root / "docs/specification-source").resolve()
            if path.is_symlink() or not path.resolve().is_relative_to(source_root):
                errors.append("Specification path escapes source directory")
                continue
            if relative in paths or url in urls:
                errors.append("Duplicate specification path or source")
            paths.add(relative)
            urls.add(url)
            data = path.read_bytes()
            if hashlib.sha256(data).hexdigest() != entry["sha256"]:
                errors.append(f"Specification digest mismatch: {relative}")
            text = data.decode("utf-8")
            if not text.strip() or "\r" in text:
                errors.append(f"Invalid normalized source: {relative}")
            if not url.startswith("https://docs.google.com/document/d/"):
                errors.append(f"Unexpected specification origin: {relative}")
            if entry["review_status"] not in {
                "IMPORTED_NOT_FULLY_REVIEWED", "REVIEWED"
            }:
                errors.append(f"Unknown review state: {relative}")
        except (KeyError, OSError, UnicodeError, TypeError, ValueError):
            errors.append("Malformed or missing specification entry")
    actual = {
        str(path.relative_to(root))
        for path in (root / "docs/specification-source").glob("*.md")
    }
    if actual != paths:
        errors.append("Source directory and manifest disagree")
    return errors


def main() -> int:
    errors = check_manifest(ROOT)
    try:
        files = subprocess.check_output(
            ["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"],
            cwd=ROOT,
        ).split(b"\0")
        for filename in files:
            if not filename:
                continue
            path = ROOT / filename.decode("utf-8")
            if not path.is_file():
                errors.append("Tracked file missing or unreadable")
            elif contains_credential(path.read_bytes()):
                # Do not echo the matched value or its containing line.
                errors.append("Possible credential detected in a repository file")
    except (OSError, UnicodeError, subprocess.CalledProcessError):
        errors.append("Unable to inspect repository files")
    if errors:
        for error in errors:
            print(f"FAIL: {error}", file=sys.stderr)
        return 1
    print("PASS: 55 source documents match their manifest; no configured credential pattern found.")
    print("Scope: source hashes and supplemental credential patterns only; full release/security gates remain open.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
