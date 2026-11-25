<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records real row changes for stock, plot, bank and labour sources.
 * The trigger runs in the same transaction as the original business write.
 * No business table is altered; tables without an ID are intentionally skipped.
 *
 * Never capture secret bank instruments, customer personal information,
 * descriptions, narration, passwords, tokens or uploaded content.
 */
return new class extends Migration {
    private const TABLES = [
        'stock_items',
        'stock_parties',
        'stock_entries',
        'stock_entry_lines',
        'stock_bills',
        'stock_bill_lines',
        'stock_movements',
        'stock_item_balances',
        'stock_project_item_balances',
        'stock_project_item_definitions',
        'stock_party_project_accounts',
        'finance_plot_receipts',
        'finance_plot_purchases',
        'finance_plot_purchase_items',
        'finance_plot_receipt_postings',
        'finance_plot_approval_drafts',
        'finance_bank_transfer_sources',
        'finance_bank_transfer_approval_drafts',
        'finance_bank_voucher_allocations',
        'finance_original_customer_requests',
        'booking_vouchers',
        'labour_ledgers',
        'labours',
        'project_bank_accounts',
    ];

    private const FIELDS = [
        'id', 'project_id', 'item_id', 'party_id', 'entry_id', 'bill_id',
        'line_id', 'movement_id', 'source_id', 'source_type',
        'stock_item_id', 'stock_party_id', 'quantity', 'qty', 'rate',
        'unit_price', 'total', 'amount', 'debit', 'credit',
        'amount_in', 'amount_out', 'total_amount', 'balance',
        'is_active', 'status', 'status_id', 'is_approve',
        'voucher_number', 'slip_no', 'date', 'created_at',
        'updated_at', 'deleted_at', 'project_head_subhead_id',
        'posting_batch_id', 'posting_line_id', 'customer_ledger_id',
        'plot_id', 'purchase_id', 'receipt_id', 'booking_id',
        'bank_account_id', 'cash_account_id', 'ledger_id',
        'labour_id', 'worker_id', 'approved_by', 'updated_by',
    ];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        if (!Schema::hasTable('activity_audit_impacts')) {
            throw new \RuntimeException('Install the Activity Audit impacts migration before source triggers.');
        }
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_intersect(Schema::getColumnListing($table), self::FIELDS));
            if (!in_array('id', $columns, true)) {
                // Preserve composite-key business tables; SQL observation
                // still identifies operations without inventing record IDs.
                continue;
            }
            foreach (['INSERT' => 'ai', 'UPDATE' => 'au', 'DELETE' => 'ad'] as $action => $suffix) {
                $trigger = 'bm_audit_'.$table.'_'.$suffix;
                // Repeated migration runs are safe if partially interrupted.
                DB::unprepared('DROP TRIGGER IF EXISTS `'.$trigger.'`');
                $before = $action === 'INSERT' ? 'NULL' : $this->json('OLD', $columns);
                $after = $action === 'DELETE' ? 'NULL' : $this->json('NEW', $columns);
                $row = $action === 'DELETE' ? 'OLD' : 'NEW';
                $sql = "CREATE TRIGGER `{$trigger}` AFTER {$action} ON `{$table}`
                    FOR EACH ROW BEGIN
                        IF @activity_audit_token IS NOT NULL AND @activity_audit_token <> '' THEN
                            INSERT INTO activity_audit_impacts (
                                activity_audit_id, request_token, table_name, operation,
                                record_id, capture_type, before_values, after_values,
                                statement_count, created_at
                            ) VALUES (
                                NULL, @activity_audit_token, '{$table}', '{$action}',
                                CAST({$row}.`id` AS CHAR), 'db_trigger_row',
                                {$before}, {$after}, 1, NOW()
                            );
                        END IF;
                    END";
                DB::unprepared($sql);
            }
        }
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
        // Retain row-change evidence and installed auditing on rollback.
    }
};
