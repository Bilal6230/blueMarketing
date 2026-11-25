<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 3: safe exact-row change capture for non-JV workflows.
 *
 * A database trigger runs within the ORIGINAL write transaction, including
 * Query Builder updates that bypass Eloquent model events. No business-table
 * schema or financial posting logic is altered. Never capture passwords,
 * tokens, personally identifying contact details or free-text narration.
 */
return new class extends Migration {
    private const TABLES = [
        'users',
        'roles',
        'permissions',
        'leads',
        'bookings',
        'booking_details',
        'employee_tasks',
        'daily_voucher_approvals',
        'daily_voucher_approval_events',
        'daily_voucher_edit_snapshots',
    ];

    private const FIELDS = [
        'id', 'project_id', 'user_id', 'role_id', 'actor_id', 'status',
        'status_id', 'is_active', 'is_approve', 'assigned_to', 'created_by',
        'updated_by', 'approved_by', 'rejected_by', 'lead_id', 'booking_id',
        'plot_id', 'customer_id', 'type', 'type_id', 'date', 'booking_date',
        'start_date', 'due_date', 'total_price', 'plot_rate',
        'total_debit', 'total_credit', 'amount', 'debit', 'credit',
        'amount_in', 'amount_out', 'created_at', 'updated_at', 'deleted_at',
        'voucher_number', 'journal_voucher_id', 'customer_ledger_id',
        'priority', 'is_deleted', 'approval_status',
    ];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        if (!Schema::hasTable('activity_audit_impacts')) {
            throw new \RuntimeException('Apply the activity_audit_impacts migration first.');
        }
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_intersect(
                Schema::getColumnListing($table), self::FIELDS
            ));
            if (!in_array('id', $columns, true)) {
                // Pivot tables with composite keys need separate explicit
                // identity handling; never invent a row ID.
                continue;
            }
            foreach (['INSERT'=>'ai', 'UPDATE'=>'au', 'DELETE'=>'ad'] as $verb=>$suffix) {
                $trigger = 'bm_audit_'.$table.'_'.$suffix;
                DB::unprepared('DROP TRIGGER IF EXISTS `'.$trigger.'`');
                $before = $verb === 'INSERT' ? 'NULL' : $this->json('OLD', $columns);
                $after = $verb === 'DELETE' ? 'NULL' : $this->json('NEW', $columns);
                $row = $verb === 'DELETE' ? 'OLD' : 'NEW';
                $sql = "CREATE TRIGGER `{$trigger}` AFTER {$verb} ON `{$table}`
                    FOR EACH ROW BEGIN
                        IF @activity_audit_token IS NOT NULL
                          AND @activity_audit_token <> '' THEN
                            INSERT INTO activity_audit_impacts (
                                activity_audit_id, request_token,
                                table_name, operation, record_id, capture_type,
                                before_values, after_values, statement_count, created_at
                            ) VALUES (
                                NULL, @activity_audit_token,
                                '{$table}', '{$verb}', CAST({$row}.`id` AS CHAR),
                                'db_trigger_row', {$before}, {$after}, 1, NOW()
                            );
                        END IF;
                    END";
                DB::unprepared($sql);
            }
        }
    }

    private function json(string $row, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = "'".$column."'";
            $parts[] = $row.'.`'.$column.'`';
        }
        return 'JSON_OBJECT('.implode(', ', $parts).')';
    }

    public function down(): void
    {
        // Audit evidence and its triggers are intentionally retained.
    }
};
