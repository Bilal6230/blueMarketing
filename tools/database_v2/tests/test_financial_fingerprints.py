"""No-private-data regression tests for exact source money/time evidence."""
from __future__ import annotations

import importlib.util
from pathlib import Path
import tempfile
import hashlib
import unittest
from unittest.mock import patch
import sys

TOOLS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(TOOLS))
import financial_source_fingerprints as fingerprints
from test_v2_import_plan import V2ImportPlannerTests


class FinancialFingerprintTests(V2ImportPlannerTests):
    def test_timestamp_evidence_and_57_table_row_totals_match_synthetic_source(self):
        with patch('financial_source_fingerprints.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, self.sha
        )):
            result = fingerprints.source_evidence(self.file, self.sha, 57)
        self.assertEqual(result['source_rows'], 57)
        self.assertEqual(result['table_count'], 57)
        self.assertEqual(result['tables']['migrations']['rows'], 1)
        self.assertEqual(result['tables']['journal_vouchers']['timestamps']['created_at']['min'],
                         '2021-02-03 04:05:06')
        self.assertEqual(result['tables']['journal_vouchers']['timestamps']['updated_at']['max'],
                         '2021-04-05 06:07:08')

    def test_sql_zero_timestamp_is_identified_in_real_column_not_lost(self):
        raw = self.file.read_text(encoding='utf-8')
        original = 'INSERT INTO \`leads\` (\`id\`) VALUES\n(1);'
        replacement = ("INSERT INTO \`leads\` (\`id\`, \`follow_up\`) VALUES\n"
                       "(1, '0000-00-00 00:00:00');")
        self.assertIn(original, raw)
        self.file.write_text(raw.replace(original, replacement), encoding='utf-8')
        new_sha = hashlib.sha256(self.file.read_bytes()).hexdigest()
        with patch('financial_source_fingerprints.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, new_sha
        )):
            result = fingerprints.source_evidence(self.file, new_sha, 57)
        self.assertEqual(
            result["mysql8_zero_date_exceptions"],
            {"leads": {"follow_up": 1}},
        )

    def test_wrong_sha_refuses_financial_evidence(self):
        with patch('financial_source_fingerprints.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, self.sha
        )):
            with self.assertRaisesRegex(ValueError, 'fingerprint differs'):
                fingerprints.source_evidence(self.file, '0' * 64, 57)


if __name__ == '__main__':
    unittest.main()
