<?php

namespace App\Console\Commands;

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only readiness gate for local Phase 07. No migrations or test payments.
 */
final class AuditV2PlotReceiptReadiness extends Command
{
    protected $signature = 'finance:audit-v2-plot-receipt {project : Local test project ID}';
    protected $description = 'Read-only Phase 07 plot receipt storage, mapping and single-/multi-booking readiness';

    public function handle(): int
    {
        $id = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->error('A positive project ID is required.');
            return self::FAILURE;
        }
        try {
            V2IsolatedConnectionGuard::assertAllowed();
        } catch (\RuntimeException $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        $project = (int) $id;
        $required = [
            'projects', 'leads', 'plots', 'bookings', 'project_head_subheads',
            'head_accountings', 'subhead_accountings',
            'finance_posting_batches', 'finance_posting_source_claims',
            'finance_posting_lines', 'finance_plot_receipts',
            'finance_plot_receipt_allocations', 'finance_plot_receipt_postings',
        ];
        $missing = array_values(array_filter($required, static fn (string $table): bool => !Schema::hasTable($table)));
        if ($missing !== []) {
            $this->error('Missing storage: '.implode(', ', $missing));
            $this->line('Apply the reviewed local-only Phase 07 migration if only the three finance_plot_receipt* tables are missing.');
            return self::FAILURE;
        }
        // MySQL can retain all tables after a failed FK ALTER. A table-only
        // readiness check would incorrectly report PASS.
        if (DB::getDriverName() === 'mysql') {
            $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'finance_plot_receipt_postings')
                ->where('REFERENCED_TABLE_NAME', 'finance_posting_source_claims')
                ->whereNotNull('REFERENCED_COLUMN_NAME')
                ->select('CONSTRAINT_NAME', 'COLUMN_NAME', 'REFERENCED_COLUMN_NAME')
                ->get()->groupBy('CONSTRAINT_NAME');
            $hasClaim = $constraints->contains(static function ($columns): bool {
                $actual = $columns->mapWithKeys(static fn ($column): array =>
                    [$column->COLUMN_NAME => $column->REFERENCED_COLUMN_NAME])->all();
                return count($actual) === 2
                    && ($actual['posting_batch_id'] ?? null) === 'posting_batch_id'
                    && ($actual['source_kind'] ?? null) === 'source_kind';
            });
            if (!$hasClaim) {
                $this->error('V2_PLOT_RECEIPT_INCOMPLETE_FK: missing composite posting claim constraint.');
                $this->line('Run the reviewed local-only 000101 claim-FK repair migration, then rerun this audit.');
                return self::FAILURE;
            }
        }
        if (!DB::table('projects')->where('id', $project)->exists()) {
            $this->error('Project not found: '.$project);
            return self::FAILURE;
        }
        if (!Schema::hasColumn('project_head_subheads', 'customer_id')
            || !Schema::hasColumn('project_head_subheads', 'plot_id')) {
            $this->error('Missing customer/plot posting mappings in project_head_subheads.');
            return self::FAILURE;
        }

        $active = DB::table('bookings as b')
            ->join('plots as p', function ($join) use ($project): void {
                $join->on('p.id', '=', 'b.plot_id')->where('p.project_id', $project);
            })
            ->join('leads as c', function ($join) use ($project): void {
                $join->on('c.id', '=', 'b.customer_id')->where('c.project_id', $project);
            })
            ->join('project_head_subheads as a', function ($join) use ($project): void {
                $join->on('a.project_id', '=', 'b.project_id')
                    ->on('a.customer_id', '=', 'b.customer_id')
                    ->on('a.plot_id', '=', 'b.plot_id')
                    ->where('a.project_id', $project);
            })
            ->join('head_accountings as h', function ($join): void {
                $join->on('h.id', '=', 'a.head_accounting_id')->where('h.is_active', 1);
            })
            ->join('subhead_accountings as sh', function ($join): void {
                $join->on('sh.id', '=', 'a.subhead_accounting_id')->where('sh.is_active', 1);
            })
            ->where('b.project_id', $project)
            ->where('b.status', 'active')->where('b.cancel_status', '0')
            ->select('b.customer_id', 'b.plot_id')->distinct()->get();

        $byCustomer = $active->groupBy('customer_id');
        $eligible = $byCustomer->count();
        $multi = $byCustomer->filter(static fn ($rows): bool => $rows->count() >= 2)->count();
        $receivingAccounts = \App\Models\ProjectHeadSubhead::availableForPosting()
            ->where('project_id', $project)->count();
        $receipts = DB::table('finance_plot_receipts')->where('project_id', $project)->count();

        $this->table(['Check', 'Result'], [
            ['Required storage', 'PASS'],
            ['Active mapped plot bookings', $active->count()],
            ['Customers with >=1 mapped active plot', $eligible],
            ['Of these: customers with >=2 mapped plots', $multi],
            ['Available project posting accounts', $receivingAccounts],
            ['Existing isolated plot receipts', $receipts],
        ]);
        if ($eligible === 0 || $receivingAccounts === 0) {
            $this->warn('Storage PASS, but posting needs one eligible customer with >=1 mapped active plot and an available project account.');
            return self::FAILURE;
        }
        $this->info('Phase 07 local readiness PASS. No records were changed.');
        return self::SUCCESS;
    }
}
