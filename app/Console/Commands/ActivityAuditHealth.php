<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destructive audit-integrity diagnostics for an existing installation.
 * No data repair, trigger reinstallation or finance mutations.
 */
final class ActivityAuditHealth extends Command
{
    protected $signature = 'activity-audit:health {--strict : Fail when any applicable row-capture trigger is missing}';
    protected $description = 'Check audit tables, transaction-linked rows and MySQL audit trigger coverage without changing records';

    private const TRIGGER_SUFFIXES = ['ai', 'au', 'ad'];
    private const EXPECTED_TABLES = [
        'journal_vouchers', 'journal_voucher_details', 'ledgers',
        'customer_ledger', 'finance_posting_batches', 'finance_posting_lines',
        'finance_posting_source_claims', 'finance_posting_journal_sources',
        'finance_journal_line_sources',
        'users', 'roles', 'permissions', 'leads', 'bookings', 'booking_details',
        'employee_tasks', 'daily_voucher_approvals', 'daily_voucher_approval_events',
        'daily_voucher_edit_snapshots',
        'stock_items', 'stock_parties', 'stock_entries', 'stock_entry_lines',
        'stock_bills', 'stock_bill_lines', 'stock_movements',
        'stock_item_balances', 'stock_project_item_balances',
        'stock_project_item_definitions', 'stock_party_project_accounts',
        'finance_plot_receipts', 'finance_plot_purchases',
        'finance_plot_purchase_items', 'finance_plot_receipt_postings',
        'finance_plot_approval_drafts', 'finance_bank_transfer_sources',
        'finance_bank_transfer_approval_drafts',
        'finance_bank_voucher_allocations', 'finance_original_customer_requests',
        'booking_vouchers', 'labour_ledgers', 'labours', 'project_bank_accounts',
    ];

    // Must match the safe composite-primary-key capture eligibility rules.
    private const COMPOSITE_TABLES = [
        'finance_posting_source_claims', 'finance_posting_journal_sources',
        'finance_journal_line_sources', 'stock_item_balances',
        'stock_project_item_balances', 'stock_project_item_definitions',
        'finance_plot_receipt_postings',
    ];

    private const COMPOSITE_SAFE_FIELDS = [
        'project_id', 'item_id', 'stock_item_id', 'posting_batch_id',
        'posting_line_id', 'journal_voucher_id',
        'journal_voucher_detail_id', 'receipt_id',
        'plot_receipt_id', 'plot_id', 'source_id', 'source_kind',
        'source_type', 'source_line_id', 'project_head_subhead_id',
        'stock_party_id', 'quantity', 'qty', 'balance', 'amount',
        'amount_in', 'amount_out', 'debit', 'credit',
        'is_active', 'status', 'updated_at', 'created_at',
    ];

    public function handle(): int
    {
        $driver = DB::getDriverName();
        $this->info('Activity audit health — READ ONLY');
        $this->line('Connection driver: '.$driver);

        if (!Schema::hasTable('activity_audits') || !Schema::hasTable('activity_audit_impacts')) {
            $this->error('Missing activity_audits or activity_audit_impacts. Run the recorded M06 migrations.');
            return self::FAILURE;
        }

        $events = DB::table('activity_audits')->count();
        $rows = DB::table('activity_audit_impacts')->count();
        $orphans = DB::table('activity_audit_impacts')
            ->whereNull('activity_audit_id')
            ->where('created_at', '<', now()->subMinutes(10))->count();
        $missingParent = DB::table('activity_audit_impacts as impact')
            ->whereNotNull('impact.activity_audit_id')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('activity_audits as activity')
                    ->whereColumn('activity.id', 'impact.activity_audit_id');
            })->count();
        $this->line("Activity events: {$events}");
        $this->line("Impact entries: {$rows}");
        $this->line("Unlinked trigger snapshots older than 10 minutes: {$orphans}");
        $this->line("Impact entries referencing missing parent events: {$missingParent}");

        $issues = $orphans + $missingParent;
        if ($issues > 0) {
            $this->warn('Audit integrity requires investigation. This command does not delete evidence.');
        }

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->warn('Row-trigger checks not available on this driver; only table integrity checked.');
            return $issues > 0 ? self::FAILURE : self::SUCCESS;
        }

        $installed = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', 'like', 'bm_audit_%')
            ->pluck('TRIGGER_NAME')->all();
        $installedSet = array_fill_keys($installed, true);
        $expectedCount = 0;
        $missing = [];
        $skipped = [];
        foreach (self::EXPECTED_TABLES as $table) {
            if (!Schema::hasTable($table)) {
                $skipped[] = $table.' (missing table)';
                continue;
            }
            if (!Schema::hasColumn($table, 'id')) {
                if (!in_array($table, self::COMPOSITE_TABLES, true)) {
                    $skipped[] = $table.' (no id column)';
                    continue;
                }
                $primary = DB::table('information_schema.KEY_COLUMN_USAGE')
                    ->where('TABLE_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', $table)
                    ->where('CONSTRAINT_NAME', 'PRIMARY')
                    ->orderBy('ORDINAL_POSITION')->pluck('COLUMN_NAME')->all();
                if ($primary === [] || array_diff($primary, self::COMPOSITE_SAFE_FIELDS) !== []) {
                    $skipped[] = $table.' (no safe declared primary key)';
                    continue;
                }
                $this->line('COMPOSITE PRIMARY KEY '.$table.' ('.implode(', ', $primary).')');
            }
            foreach (self::TRIGGER_SUFFIXES as $suffix) {
                $trigger = 'bm_audit_'.$table.'_'.$suffix;
                $expectedCount++;
                if (!isset($installedSet[$trigger])) {
                    $missing[] = $trigger;
                }
            }
        }

        $this->line('Expected applicable triggers: '.$expectedCount);
        $this->line('Missing applicable triggers: '.count($missing));
        $this->line('Skipped source tables: '.count($skipped));
        foreach ($missing as $trigger) $this->warn('MISSING '.$trigger);
        foreach ($skipped as $table) $this->line('SKIPPED '.$table);

        if ($issues > 0 || ($this->option('strict') && count($missing) > 0)) {
            return self::FAILURE;
        }
        if ($missing !== []) {
            $this->warn('Partial row coverage: missing trigger names shown above. Use --strict to fail the gate.');
        }
        $this->info('Health check completed without modifying business or audit data.');
        return self::SUCCESS;
    }
}
