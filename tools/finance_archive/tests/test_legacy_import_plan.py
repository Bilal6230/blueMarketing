"""Isolated standard-library tests; no application database or private SQL required."""
import hashlib
import importlib.util
import pathlib
import subprocess
import sys
import tempfile
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[1] / "legacy_import_plan.py"
SPEC = importlib.util.spec_from_file_location("v2_legacy_import", SCRIPT)
MOD = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MOD)

EXAMPLE = """CREATE TABLE `parent` (
  `id` bigint unsigned NOT NULL
) ENGINE=InnoDB;

CREATE TABLE `child` (
  `id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned NOT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB;

ALTER TABLE `child`
  ADD CONSTRAINT `c_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`);

INSERT INTO `child` (`id`, `parent_id`, `note`) VALUES
(2, 1, 'semi;colon'),(3, 1, 'escaped\\'quote');

INSERT INTO `parent` (`id`) VALUES
(1);
"""


class ImportPlannerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = pathlib.Path(self.temp.name)
        self.source = self.root / "legacy.sql"
        self.source.write_text(EXAMPLE, encoding="utf-8")

    def test_parent_order_and_lossless_chunk(self):
        manifest, chunks = MOD.inspect(self.source)
        self.assertEqual(manifest["table_count"], 2)
        self.assertEqual(manifest["declared_fk_count"], 1)
        self.assertEqual(manifest["total_insert_rows"], 3)
        self.assertLess(manifest["table_import_order"].index("parent"),
                        manifest["table_import_order"].index("child"))
        self.assertIn("semi;colon", chunks["child"][0])
        self.assertIn("escaped\\'quote", chunks["child"][0])

    def test_bad_sha_blocks_output_without_writes(self):
        out = self.root / "output"
        result = subprocess.run([sys.executable, str(SCRIPT), "--source", str(self.source),
                                 "--out-dir", str(out), "--expected-sha256", "0" * 64],
                                capture_output=True, text=True, check=False)
        self.assertEqual(result.returncode, 2)
        self.assertFalse(out.exists())

    def test_wrong_row_count_blocks_output(self):
        result = subprocess.run([sys.executable, str(SCRIPT), "--source", str(self.source),
                                 "--expected-rows", "4"], capture_output=True, text=True)
        self.assertEqual(result.returncode, 2)

    def test_cycle_fails_closed(self):
        with self.assertRaisesRegex(ValueError, "Cyclic dependencies"):
            MOD.ordered_tables({"a", "b"}, {"a": {"b"}, "b": {"a"}})

    def test_output_is_data_only_and_preserves_source_statements(self):
        out = self.root / "stage"
        checksum = hashlib.sha256(self.source.read_bytes()).hexdigest()
        result = subprocess.run([sys.executable, str(SCRIPT), "--source", str(self.source),
                                 "--expected-sha256", checksum, "--expected-rows", "3",
                                 "--out-dir", str(out)], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue((out / "manifest.json").is_file())
        self.assertIn("INSERT INTO `child`", (out / "child.sql").read_text())
        self.assertNotIn("CREATE TABLE", (out / "child.sql").read_text())


if __name__ == "__main__":
    unittest.main()
