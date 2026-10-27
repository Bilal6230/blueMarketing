<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialReadinessInspector;
use Illuminate\Console\Command;

final class AuditFinanceReadiness extends Command
{
    protected $signature = 'finance:audit-readiness {project : Existing project ID}';
    protected $description = 'Read-only financial metadata and unattributed JV-ledger readiness inventory';

    public function handle(FinancialReadinessInspector $inspector): int
    {
        $id = filter_var($this->argument('project'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->error('Valid numeric project ID required.');
            return self::INVALID;
        }
        try {
            $report = $inspector->inspect((int) $id);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        return $report['status'] === 'preliminary_ready' ? self::SUCCESS : self::FAILURE;
    }
}
