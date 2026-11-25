<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\Audit\TriggerDefinitionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destructive audit-integrity diagnostics for an existing installation.
 * No data repair, trigger reinstallation or finance mutations.
 */
final class ActivityAuditHealth extends Command
{
    protected $signature = 'activity-audit:health {--strict : Fail for missing/invalid triggers, nontransactional storage or broken audit evidence}';
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

        // Checking names alone is insufficient: a trigger with the right
        // name could point at a different table/event, run BEFORE a write, or
        // silently omit the request token / audit evidence insertion.
        $installed = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', 'like', 'bm_audit_%')
            ->get(['TRIGGER_NAME', 'EVENT_OBJECT_TABLE', 'EVENT_MANIPULATION',
                'ACTION_TIMING', 'ACTION_STATEMENT']);
        $installedByName = $installed->keyBy('TRIGGER_NAME');
        $engines = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->whereIn('TABLE_NAME', array_merge(self::EXPECTED_TABLES, ['activity_audits', 'activity_audit_impacts']))
            ->pluck('ENGINE', 'TABLE_NAME')->all();
        $badEngines = [];
        foreach (['activity_audits', 'activity_audit_impacts'] as $table) {
            if (strcasecmp((string) ($engines[$table] ?? ''), 'InnoDB') !== 0) {
                $badEngines[] = $table.' ('.($engines[$table] ?? 'unknown').')';
            }
        }
        $expectedCount = 0;
        $missing = [];
        $invalid = [];
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
            if (strcasecmp((string) ($engines[$table] ?? ''), 'InnoDB') !== 0) {
                $badEngines[] = $table.' ('.($engines[$table] ?? 'unknown').')';
            }
            foreach (['ai' => 'INSERT', 'au' => 'UPDATE', 'ad' => 'DELETE'] as $suffix => $event) {
                $trigger = 'bm_audit_'.$table.'_'.$suffix;
                $expectedCount++;
                $definition = $installedByName->get($trigger);
                if (!$definition) {
                    $missing[] = $trigger;
                    continue;
                }
                if (!TriggerDefinitionValidator::valid($definition, $table, $event)) {
                    $invalid[] = $trigger.' (wrong table/event/timing or missing audit capture)';
                }
            }
        }

        $this->line('Expected applicable triggers: '.$expectedCount);
        $this->line('Missing applicable triggers: '.count($missing));
        $this->line('Invalid trigger definitions: '.count($invalid));
        $this->line('Nontransactional/unknown engines: '.count($badEngines));
        $this->line('Skipped source tables: '.count($skipped));
        foreach ($missing as $trigger) $this->warn('MISSING '.$trigger);
        foreach ($invalid as $trigger) $this->warn('INVALID '.$trigger);
        foreach ($badEngines as $engine) $this->warn('ENGINE '.$engine.' (transactional auditing requires InnoDB)');
        foreach ($skipped as $table) $this->line('SKIPPED '.$table);

        if ($issues > 0 || ($this->option('strict') && ($missing !== [] || $invalid !== [] || $badEngines !== []))) {
            return self::FAILURE;
        }
        if ($missing !== [] || $invalid !== [] || $badEngines !== []) {
            $this->warn('Audit capture integrity is incomplete. Use --strict to fail this gate.');
        }
        $this->info('Health check completed without modifying business or audit data.');
        return self::SUCCESS;
    }
}
