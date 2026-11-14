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
    protected $description = 'Read-only Phase 07 plot receipt storage, mapping and multi-booking readiness';

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
            ->where('b.project_id', $project)
            ->where('b.status', 'active')->where('b.cancel_status', '0')
            ->select('b.customer_id', 'b.plot_id')->distinct()->get();

        $byCustomer = $active->groupBy('customer_id');
        $multi = $byCustomer->filter(static fn ($rows): bool => $rows->count() >= 2)->count();
        $receipts = DB::table('finance_plot_receipts')->where('project_id', $project)->count();

        $this->table(['Check', 'Result'], [
            ['Required storage', 'PASS'],
            ['Active mapped plot bookings', $active->count()],
            ['Customers with >=2 mapped active plots', $multi],
            ['Existing isolated plot receipts', $receipts],
        ]);
        if ($multi === 0) {
            $this->warn('Storage PASS, but browser multi-plot posting has no eligible customer with 2 mapped active plots.');
            return self::FAILURE;
        }
        $this->info('Phase 07 local readiness PASS. No records were changed.');
        return self::SUCCESS;
    }
}
