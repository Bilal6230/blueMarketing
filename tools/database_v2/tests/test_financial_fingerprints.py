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

    def test_wrong_sha_refuses_financial_evidence(self):
        with patch('financial_source_fingerprints.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, self.sha
        )):
            with self.assertRaisesRegex(ValueError, 'fingerprint differs'):
                fingerprints.source_evidence(self.file, '0' * 64, 57)


if __name__ == '__main__':
    unittest.main()
