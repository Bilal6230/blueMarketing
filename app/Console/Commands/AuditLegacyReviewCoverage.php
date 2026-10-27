<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyEvidenceCoverageInspector;
use Illuminate\Console\Command;

final class AuditLegacyReviewCoverage extends Command
{
    protected $signature='finance:audit-legacy-review-coverage
        {project : Existing project ID}
        {review-file : JSON file containing project_id and reviewed ledger_ids}
        {--sample=50 : Maximum missing ledger IDs returned (1-100)}';
    protected $description='Read-only full project coverage reconciliation for historical review ID sets';

    public function handle(LegacyEvidenceCoverageInspector $inspector): int
    {
        $project=filter_var($this->argument('project'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $limit=filter_var($this->option('sample'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $path=$this->argument('review-file');
        if ($project === false || $limit === false || $limit>100 ||
            !is_string($path) || !is_file($path) || !is_readable($path)) {
            $this->error('Valid project, readable JSON and sample limit 1-100 required.');
            return self::INVALID;
        }
        try {
            $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error('Review coverage file must be valid JSON.'); return self::INVALID;
        }
        if (!is_array($data) || ($data['project_id'] ?? null) !== (int)$project ||
            !isset($data['ledger_ids']) || !is_array($data['ledger_ids'])) {
            $this->error('Review file must contain matching project_id and ledger_ids array.');
            return self::INVALID;
        }
        try {
            $result=$inspector->inspect((int)$project,$data['ledger_ids'],(int)$limit);
        } catch (\DomainException | \InvalidArgumentException $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return $result['status']==='blocked' ? self::FAILURE : self::SUCCESS;
    }
}
