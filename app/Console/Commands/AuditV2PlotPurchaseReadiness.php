<?php

namespace App\Console\Commands;

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 08 local-only read-only readiness gate.
 * No plot, booking, posting, or purchase source is created by this command.
 */
final class AuditV2PlotPurchaseReadiness extends Command
{
    protected $signature = 'finance:audit-v2-plot-purchase {project : Isolated test project ID}';
    protected $description = 'Read-only Plot Purchase storage, accounting and definition readiness';

    public function handle(): int
    {
        $id = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->error('A positive project ID is required.');
            return self::FAILURE;
        }
        try {
            V2IsolatedConnectionGuard::assertAllowed();
        } catch (\Throwable $error) {
            $this->error('Isolated environment required: '.$error->getMessage());
            return self::FAILURE;
        }
        $project = (int) $id;
        $tables = [
            'projects', 'plots', 'project_head_subheads', 'head_accountings',
            'subhead_accountings', 'roads', 'facings',
            'finance_posting_batches', 'finance_posting_source_claims',
            'finance_posting_lines', 'finance_plot_purchases',
            'finance_plot_purchase_items', 'finance_plot_purchase_postings',
        ];
        $missing = array_values(array_filter($tables, static fn ($table): bool => !Schema::hasTable($table)));
        if ($missing !== []) {
            $this->error('Missing storage: '.implode(', ', $missing));
            return self::FAILURE;
        }
        if (DB::getDriverName() === 'mysql') {
            $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'finance_plot_purchase_postings')
                ->where('REFERENCED_TABLE_NAME', 'finance_posting_source_claims')
                ->whereNotNull('REFERENCED_COLUMN_NAME')
                ->get(['CONSTRAINT_NAME', 'COLUMN_NAME', 'REFERENCED_COLUMN_NAME'])
                ->groupBy('CONSTRAINT_NAME');
            $correct = $constraints->contains(static function ($entries): bool {
                $map = $entries->mapWithKeys(static fn ($r): array =>
                    [$r->COLUMN_NAME => $r->REFERENCED_COLUMN_NAME])->all();
                return count($map) === 2
                    && ($map['posting_batch_id'] ?? null) === 'posting_batch_id'
                    && ($map['source_kind'] ?? null) === 'source_kind';
            });
            if (!$correct) {
                $this->error('V2_PLOT_PURCHASE_INCOMPLETE_FK: Posting claim integrity is missing.');
                return self::FAILURE;
            }
        }
        if (!DB::table('projects')->where('id', $project)->exists()) {
            $this->error('Selected project is missing.');
            return self::FAILURE;
        }
        $accounts = \App\Models\ProjectHeadSubhead::availableForPosting()
            ->where('project_id', $project)->count();
        $roads = DB::table('roads')->where('is_active', 1)->count();
        $facings = DB::table('facings')->where('is_active', 1)->count();
        $purchases = DB::table('finance_plot_purchases')->where('project_id', $project)->count();
        $this->table(['Readiness check', 'Value'], [
            ['Plot Purchase storage and posting claim FK', 'PASS'],
            ['Available project accounts', $accounts],
            ['Active roads', $roads],
            ['Active facings', $facings],
            ['Existing pilot purchases in project', $purchases],
        ]);
        if ($accounts < 2 || $roads === 0 || $facings === 0) {
            $this->warn('Storage PASS; cannot post until seller/inventory accounts and active road/facing definitions are available.');
            return self::FAILURE;
        }
        $this->info('Phase 08 Plot Purchase pilot readiness PASS (read-only).');
        return self::SUCCESS;
    }
}
