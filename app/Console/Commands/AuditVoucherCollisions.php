<?php

namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherCollisionInspector;
use Illuminate\Console\Command;

final class AuditVoucherCollisions extends Command
{
    protected $signature = 'finance:audit-voucher-collisions {project : Numeric project ID} {--limit=100 : Maximum overlapping IDs reported (1-1000)}';
    protected $description = 'Read-only JV/CV header-ID overlap inventory; does not establish ledger source';

    public function handle(LegacyVoucherCollisionInspector $inspector): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($project === false || $limit === false) {
            $this->error('Provide positive project ID and --limit between 1 and 1000.');
            return self::INVALID;
        }
        $report = $inspector->inspect($project, $limit);
        foreach ($report['rows'] as $row) {
            $this->line(json_encode($row, JSON_THROW_ON_ERROR));
        }
        $this->info("Potential JV/CV header ID overlaps: {$report['overlap_total']}; reported: {$report['reported_count']}. Ledger source remains UNKNOWN.");
        if ($report['incomplete']) {
            $this->warn('INCOMPLETE collision inventory: increase --limit for more results.');
        }
        return $report['overlap_total'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
