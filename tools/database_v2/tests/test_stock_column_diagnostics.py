"""No private SQL/DB needed: column-wise source SHA diagnostic regression."""
import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
from stock_column_diagnostics import column_fingerprints


class StockColumnDiagnosticTests(unittest.TestCase):
    def setUp(self):
        self.cols = {
            "id": "bigint(20) UNSIGNED NOT NULL",
            "flow": "enum('IN','OUT') NOT NULL",
            "qty": "decimal(18,3) NOT NULL",
            "description": "text DEFAULT NULL",
        }
        self.rows = [
            {"id": "1", "flow": "IN", "qty": "1.200", "description": None},
            {"id": "2", "flow": "OUT", "qty": "3.000", "description": "note"},
            {"id": "2", "flow": "OUT", "qty": "3.000", "description": "note"},
        ]

    def test_order_invariance_and_duplicates(self):
        self.assertEqual(column_fingerprints(self.rows, self.cols),
                         column_fingerprints(list(reversed(self.rows)), self.cols))
        self.assertNotEqual(
            column_fingerprints(self.rows, self.cols),
            column_fingerprints(self.rows[:-1], self.cols),
        )

    def test_single_field_mutation_is_localized_and_does_not_expose_values(self):
        baseline = column_fingerprints(self.rows, self.cols)
        changed = [dict(row) for row in self.rows]
        changed[0]["description"] = ""
        after = column_fingerprints(changed, self.cols)
        self.assertEqual(
            [col for col in self.cols if baseline[col] != after[col]],
            ["description"],
        )

    def test_mysql_decimal_storage_format_is_normalized(self):
        baseline = column_fingerprints(self.rows, self.cols)
        changed = [dict(row) for row in self.rows]
        changed[0]["qty"] = "1.2"
        self.assertEqual(baseline, column_fingerprints(changed, self.cols))

    def test_missing_column_fails_closed(self):
        with self.assertRaisesRegex(ValueError, "COLUMNS_MISMATCH"):
            column_fingerprints([{"id": "1"}], self.cols)


if __name__ == "__main__":
    unittest.main()
