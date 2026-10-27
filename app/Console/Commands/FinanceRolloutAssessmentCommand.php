<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceRolloutAssessment;
use Illuminate\Console\Command;

final class FinanceRolloutAssessmentCommand extends Command
{
    protected $signature = 'finance:rollout-assessment {--limit=100 : Maximum projects (1-1000)}';
    protected $description = 'Read-only schema and all-project finance rollout blocker assessment';

    public function handle(FinanceRolloutAssessment $assessment): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Project limit must be between 1 and 1000.');
            return self::INVALID;
        }

        $report = $assessment->inspect((int) $limit);
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        if ($report['status'] !== 'preliminary_ready') {
            $this->warn('Rollout remains blocked; report is read-only and no migrations were run.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
