<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherCollisionInventory;
use Illuminate\Console\Command;

final class AuditLegacyVoucherCases extends Command
{
    protected $signature = 'finance:audit-legacy-cases {project : Numeric project ID} {--limit=100 : Max ledger rows (1-1000)} {--summary : Compact bounded project-wide classification} {--max-rows=5000 : Summary scan cap (1-10000)}';
    protected $description = 'Read-only ledger-level JV/CV source ambiguity inventory for manual review';

    public function handle(LegacyVoucherCollisionInventory $inspector): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>1000]]);
        if ($project === false || $limit === false) {
            $this->error('Positive project ID and --limit 1-1000 required.');
            return self::INVALID;
        }
        $summary = (bool) $this->option('summary');
        $maxRows = filter_var($this->option('max-rows'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>10000]]);
        if ($summary && $maxRows === false) {
            $this->error('Summary --max-rows must be between 1 and 10000.');
            return self::INVALID;
        }
        try {
            $result = $summary
                ? $inspector->summarize((int) $project, (int) $maxRows)
                : $inspector->inspect((int) $project, (int) $limit);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        // Legacy provenance cannot be auto-certified, even if one numeric
        // header exists. This is investigation output, not readiness approval.
        return $result['available'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
