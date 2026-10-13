from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import shutil
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("checks", ROOT / "tools/check_repository.py")
assert SPEC is not None and SPEC.loader is not None
CHECKS = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CHECKS)


class RepositoryChecks(unittest.TestCase):
    def test_current_corpus_integrity(self) -> None:
        self.assertEqual([], CHECKS.check_manifest(ROOT))

    def test_credential_patterns_without_real_secrets(self) -> None:
        synthetic = b"github" + b"_pat_" + b"x" * 40
        self.assertTrue(CHECKS.contains_credential(synthetic))
        self.assertTrue(CHECKS.contains_credential(b"gh" + b"p_" + b"x" * 40))
        self.assertTrue(CHECKS.contains_credential(b"-----BEGIN " + b"PRIVATE KEY-----"))
        self.assertFalse(CHECKS.contains_credential(b"Read GH_TOKEN from runtime secrets"))

    def test_integrity_and_path_escape_fail_closed(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shutil.copytree(ROOT / "docs", root / "docs")
            manifest_path = root / "docs/specification-manifest.json"
            manifest = json.loads(manifest_path.read_text())
            document = root / manifest["documents"][0]["path"]
            document.write_bytes(document.read_bytes() + b"tampered")
            self.assertTrue(any("digest mismatch" in e for e in CHECKS.check_manifest(root)))
            manifest["documents"][0]["path"] = "../outside.md"
            manifest_path.write_text(json.dumps(manifest))
            self.assertTrue(any("escapes" in e for e in CHECKS.check_manifest(root)))

    def test_duplicate_sources_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shutil.copytree(ROOT / "docs", root / "docs")
            manifest_path = root / "docs/specification-manifest.json"
            manifest = json.loads(manifest_path.read_text())
            manifest["documents"][1]["url"] = manifest["documents"][0]["url"]
            manifest_path.write_text(json.dumps(manifest))
            self.assertTrue(any("Duplicate" in e for e in CHECKS.check_manifest(root)))


if __name__ == "__main__":
    unittest.main()
