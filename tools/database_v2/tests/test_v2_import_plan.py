"""Synthetic V2 import rehearsal; zero customer records, no MySQL connection."""
from __future__ import annotations

import hashlib
import importlib.util
import pathlib
import sys
import tempfile
import unittest
from unittest.mock import patch

TOOL_DIR = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(TOOL_DIR))
import v2_import_plan as v2


class V2ImportPlannerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.file = pathlib.Path(self.tmp.name) / 'synthetic_source.sql'
        # The source metadata is real structural evidence but these rows are
        # disposable ID-only synthetic values; no historic/customer rows.
        self.cols, self.original_fks, self.extra_fks, _ = v2.verified_schema()
        statements = []
        for table in self.cols:
            # Some legacy tables (password_resets, permission pivots,
            # stock_counters) genuinely have no `id` column.
            fixture_column = 'id' if 'id' in self.cols[table] else next(iter(self.cols[table]))
            statements.append(
                'CREATE TABLE `' + table + '` (\\n'
                '  `' + fixture_column + '` bigint unsigned NOT NULL\\n'
                ') ENGINE=InnoDB;\\n'
            )
            if table == 'journal_vouchers':
                statements.append(
                    'INSERT INTO `journal_vouchers` (`id`, `created_at`, `updated_at`) VALUES\n'
                    "(1, '2021-02-03 04:05:06', '2021-04-05 06:07:08');\n"
                )
            elif table == 'migrations':
                statements.append(
                    'INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES\n'
                    "(1, 'original_migration_name', 2);\n"
                )
            else:
                statements.append('INSERT INTO `' + table + '` (`id`) VALUES\n(1);\n')
        self.file.write_text('\n'.join(statements), encoding='utf-8')
        self.sha = hashlib.sha256(self.file.read_bytes()).hexdigest()

    def planned(self):
        with patch('v2_import_plan.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, self.sha
        )):
            return v2.stage_plan(self.file, self.sha, 57)

    def test_all_57_tables_ordered_by_current_86_fks_and_history_is_archived(self):
        plan, rows = self.planned()
        self.assertEqual(plan['source_table_count'], 57)
        self.assertEqual(plan['source_row_count'], 57)
        self.assertEqual(plan['old_fk_count'], 40)
        self.assertEqual(plan['new_fk_candidates_applied'], 46)
        self.assertEqual(plan['legacy_migration_history_rows'], 1)
        self.assertFalse(plan['framework_migrations_imported'])
        self.assertNotIn('migrations', plan['import_order'])
        self.assertIn('legacy_migration_history', plan['import_order'])
        self.assertTrue(rows['legacy_migration_history'][0].startswith(
            'INSERT INTO `legacy_migration_history`'))
        self.assertLess(plan['import_order'].index('projects'),
                        plan['import_order'].index('journal_vouchers'))
        self.assertLess(plan['import_order'].index('project_head_subheads'),
                        plan['import_order'].index('sites'))

    def test_timestamp_and_original_column_values_are_not_regenerated(self):
        _, rows = self.planned()
        text = rows['journal_vouchers'][0]
        self.assertIn('2021-02-03 04:05:06', text)
        self.assertIn('2021-04-05 06:07:08', text)
        self.assertIn('`created_at`', text)
        self.assertIn('`updated_at`', text)

    def test_bad_source_checksum_fails_closed_before_emitting_files(self):
        with patch('v2_import_plan.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, self.sha
        )):
            with self.assertRaisesRegex(ValueError, 'checksum mismatch'):
                v2.stage_plan(self.file, '0' * 64, 57)

    def test_changed_row_count_fails_closed(self):
        with patch('v2_import_plan.verified_schema', return_value=(
            self.cols, self.original_fks, self.extra_fks, self.sha
        )):
            with self.assertRaisesRegex(ValueError, 'row-count mismatch'):
                v2.stage_plan(self.file, self.sha, 56)


if __name__ == '__main__':
    unittest.main()
