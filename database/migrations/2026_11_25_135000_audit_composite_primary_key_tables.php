<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capture row-level changes in existing linkage/balance tables with composite
 * PRIMARY KEYs. No surrogate ID, unique index or table alteration is added.
 *
 * record_id is a stable SHA-256 fingerprint of the existing primary-key tuple.
 * Before/after snapshots contain the key columns and a restricted set of
 * numeric/status fields, so a reviewer can identify the actual linked record.
 * Tables without a declared primary key deliberately remain untracked at row
 * level; raw SQL observations still appear separately.
 */
return new class extends Migration {
    private const TABLES = [
        'finance_posting_source_claims',
        'finance_posting_journal_sources',
        'finance_journal_line_sources',
        'stock_item_balances',
        'stock_project_item_balances',
        'stock_project_item_definitions',
        'finance_plot_receipt_postings',
    ];

    private const SAFE_FIELDS = [
        'project_id', 'item_id', 'stock_item_id', 'posting_batch_id',
        'posting_line_id', 'journal_voucher_id',
        'journal_voucher_detail_id', 'receipt_id',
        'plot_receipt_id', 'plot_id', 'source_id', 'source_kind',
        'source_type', 'source_line_id', 'project_head_subhead_id',
        'stock_party_id', 'quantity', 'qty', 'balance', 'amount',
        'amount_in', 'amount_out', 'debit', 'credit',
        'is_active', 'status', 'updated_at', 'created_at',
    ];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        if (!Schema::hasTable('activity_audit_impacts')) {
            throw new \RuntimeException('Apply the Activity Audit impacts migration first.');
        }

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'id')) {
                continue;
            }
            // Only a real database PRIMARY KEY is a trustworthy row identity.
            $primary = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', 'PRIMARY')
                ->orderBy('ORDINAL_POSITION')
                ->pluck('COLUMN_NAME')->all();
            if ($primary === []) {
                continue;
            }
            $all = Schema::getColumnListing($table);
            $columns = array_values(array_unique(array_merge(
                $primary, array_intersect($all, self::SAFE_FIELDS)
            )));
            // No free-text business fields, credentials or contacts may be
            // included just because a table was added to this migration.
            if (array_diff($primary, self::SAFE_FIELDS) !== []) {
                continue;
            }
            foreach (['INSERT' => 'ai', 'UPDATE' => 'au', 'DELETE' => 'ad'] as $operation => $suffix) {
                $name = 'bm_audit_'.$table.'_'.$suffix;
                DB::unprepared('DROP TRIGGER IF EXISTS `'.$name.'`');
                $row = $operation === 'DELETE' ? 'OLD' : 'NEW';
                $before = $operation === 'INSERT' ? 'NULL' : $this->json('OLD', $columns);
                $after = $operation === 'DELETE' ? 'NULL' : $this->json('NEW', $columns);
                $identity = "CONCAT('pk:', SHA2(".$this->keyJson($row, $primary).", 256))";
                DB::unprepared("CREATE TRIGGER `{$name}` AFTER {$operation} ON `{$table}`
                    FOR EACH ROW BEGIN
                        IF @activity_audit_token IS NOT NULL AND @activity_audit_token <> '' THEN
                            INSERT INTO activity_audit_impacts (
                                activity_audit_id, request_token, table_name,
                                operation, record_id, capture_type, before_values,
                                after_values, statement_count, created_at
                            ) VALUES (
                                NULL, @activity_audit_token, '{$table}', '{$operation}',
                                {$identity}, 'db_trigger_row',
                                {$before}, {$after}, 1, NOW()
                            );
                        END IF;
                    END");
            }
        }
    }

    private function keyJson(string $side, array $columns): string
    {
        return 'JSON_ARRAY('.implode(', ', array_map(
            static fn (string $column): string => $side.'.`'.$column.'`',
            $columns
        )).')';
    }

    private function json(string $side, array $columns): string
    {
        $pairs = [];
        foreach ($columns as $column) {
            $pairs[] = "'".$column."'";
            $pairs[] = $side.'.`'.$column.'`';
        }
        return 'JSON_OBJECT('.implode(', ', $pairs).')';
    }

    public function down(): void
    {
        // Keep audit evidence and trigger coverage intact on rollback.
    }
};
