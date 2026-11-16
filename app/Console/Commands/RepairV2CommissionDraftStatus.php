<?php

namespace App\Console\Commands;

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explicit one-time local schema gate. Never runs during migrate/deploy.
 * ALTER ENUM is MySQL DDL (implicit commit): require an owner backup first.
 */
final class RepairV2CommissionDraftStatus extends Command
{
    protected $signature = 'finance:repair-v2-commission-status {--apply : Explicitly add draft to the isolated local enum}';
    protected $description = 'Inspect or safely extend isolated V2 commission status enum; read-only by default';

    public function handle(): int
    {
        if (!V2IsolatedConnectionGuard::isAllowed()
            || !in_array(config('app.env'), ['local', 'testing'], true)
            || !config('finance.v2_pilot_api_enabled')
            || !config('finance.v2_pilot_write_enabled')
            || DB::getDriverName() !== 'mysql') {
            $this->error('Refusing: approved isolated local MySQL V2 pilot is required.');
            return self::FAILURE;
        }

        $schema = DB::getDatabaseName();
        $column = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', 'commision_vouchers')
            ->where('COLUMN_NAME', 'status')
            ->first(['COLUMN_TYPE','COLUMN_DEFAULT','IS_NULLABLE']);
        if (!$column) {
            $this->error('Commission status column missing; no changes made.');
            return self::FAILURE;
        }
        $type = strtolower((string) $column->COLUMN_TYPE);
        $this->line('Database: '.$schema.'; status: '.$type);
        if ($type === "enum('pending','approved','rejected','draft')"
            || $type === "enum('draft','pending','approved','rejected')") {
            $this->info('PASS: draft is supported already; no changes required.');
            return self::SUCCESS;
        }
        // Never reconstruct an unexpected enum, nor silently shrink its values.
        if ($type !== "enum('pending','approved','rejected')"
            || (string) $column->COLUMN_DEFAULT !== 'pending'
            || (string) $column->IS_NULLABLE !== 'NO') {
            $this->error('Unexpected status schema; refusing an automatic change.');
            return self::FAILURE;
        }
        if (!$this->option('apply')) {
            $this->warn('Needs additive enum repair. No writes performed.');
            $this->line('Back up bluenew first; then rerun with --apply on this isolated local DB.');
            return self::SUCCESS;
        }
        // ENUM append preserves the original numeric indexes and default.
        DB::statement("ALTER TABLE `commision_vouchers` MODIFY COLUMN `status` ENUM('pending','approved','rejected','draft') NOT NULL DEFAULT 'pending'");
        $this->info('Draft status added; no voucher or ledger rows updated.');
        return self::SUCCESS;
    }
}
