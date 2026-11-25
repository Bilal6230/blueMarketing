<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL/MariaDB row capture for the JV/GL family. An AFTER trigger writes
 * audit evidence in the SAME transaction as the business mutation: rollback
 * removes the evidence too. No business rows/amounts are modified.
 */
return new class extends Migration {
    private const TABLES = [
        'journal_vouchers',
        'journal_voucher_details',
        'ledgers',
        'customer_ledger',
        'finance_posting_batches',
        'finance_posting_lines',
        'finance_posting_source_claims',
        'finance_posting_journal_sources',
        'finance_journal_line_sources',
    ];

    private const FIELDS = [
        'id', 'project_id', 'booking_id', 'plot_id', 'customer_id',
        'journal_voucher_id', 'journal_voucher_detail_id', 'customer_ledger_id',
        'project_head_subheads_id', 'account_id', 'type_id', 'type',
        'voucher_number', 'status', 'is_active', 'is_approve',
        'date', 'booking_date', 'deleted_at', 'total_debit', 'total_credit',
        'debit', 'credit', 'amount_in', 'amount_out', 'total_price',
        'created_by', 'updated_by', 'create_by', 'update_by',
        'posting_batch_id', 'posting_line_id', 'project_head_subhead_id',
        'source_kind', 'source_id', 'source_type', 'reversal_of_batch_id',
    ];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // SQLite tests still use Eloquent and SQL-operation capture.
            return;
        }
        if (!Schema::hasTable('activity_audit_impacts')) {
            throw new \RuntimeException('Install the Activity Audit impacts migration before JV row triggers.');
        }

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_intersect(Schema::getColumnListing($table), self::FIELDS));
            if (!in_array('id', $columns, true)) {
                continue;
            }
            foreach (['INSERT' => 'ai', 'UPDATE' => 'au', 'DELETE' => 'ad'] as $action => $suffix) {
                $trigger = 'bm_audit_'.$table.'_'.$suffix;
                // Idempotent for a partially applied migration; only our
                // own named triggers are replaced.
                DB::unprepared('DROP TRIGGER IF EXISTS `'.$trigger.'`');
                $before = $action === 'INSERT' ? 'NULL' : $this->json('OLD', $columns);
                $after = $action === 'DELETE' ? 'NULL' : $this->json('NEW', $columns);
                $source = $action === 'DELETE' ? 'OLD' : 'NEW';
                $tableName = str_replace("'", "''", $table);
                $statement = "CREATE TRIGGER `{$trigger}` AFTER {$action} ON `{$table}`
                    FOR EACH ROW
                    BEGIN
                        IF @activity_audit_token IS NOT NULL AND @activity_audit_token <> '' THEN
                            INSERT INTO activity_audit_impacts
                                (activity_audit_id, request_token, table_name, operation,
                                 record_id, capture_type, before_values, after_values,
                                 statement_count, created_at)
                            VALUES
                                (NULL, @activity_audit_token, '{$tableName}', '{$action}',
                                 CAST({$source}.`id` AS CHAR), 'db_trigger_row',
                                 {$before}, {$after}, 1, NOW());
                        END IF;
                    END";
                DB::unprepared($statement);
            }
        }
    }

    private function json(string $prefix, array $columns): string
    {
        $items = [];
        foreach ($columns as $column) {
            $items[] = "'".str_replace("'", "''", $column)."'";
            $items[] = $prefix.'.`'.$column.'`';
        }
        return 'JSON_OBJECT('.implode(', ', $items).')';
    }

    public function down(): void
    {
        // Never silently delete compliance evidence or reconfigure triggers
        // during unrelated application rollbacks.
    }
};
