<?php
namespace App\Console\Commands;
use App\Services\Finance\FinancialSourceReconciliationInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditFinancialSourceAttributions extends Command
{
    protected $signature = 'finance:audit-source-attributions {project? : Numeric project ID for audit} {--limit=100 : Max rows, 1-1000} {--list-projects : List up to 1000 project IDs read-only}';
    protected $description = 'Read-only, bounded verification of explicit ledger source attributions';

    public function handle(FinancialSourceReconciliationInspector $inspector): int
    {
        if ($this->option('list-projects')) {
            if ($this->argument('project') !== null) {
                $this->error('Use --list-projects without a project ID.');
                return self::INVALID;
            }
            $ids = DB::table('projects')->orderBy('id')->limit(1001)->pluck('id');
            $incomplete = $ids->count() > 1000;
            $this->line(json_encode(['project_ids'=>$ids->take(1000)->all(),
                'incomplete'=>$incomplete], JSON_THROW_ON_ERROR));
            return $incomplete ? self::FAILURE : self::SUCCESS;
        }
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000]]);
        if ($project === false || $limit === false) {
            $this->error('Valid project and --limit 1-1000 required.');
            return self::INVALID;
        }
        try {
            $report = $inspector->inspect((int)$project,(int)$limit);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        if ($report['status'] !== 'scanned' || $report['incomplete'] || $report['issues']) {
            $this->warn('Source attribution scan is not clean or complete.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
