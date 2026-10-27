<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherCollisionInventory;
use Illuminate\Console\Command;

final class AuditLegacyVoucherCases extends Command
{
    protected $signature = 'finance:audit-legacy-cases {project : Numeric project ID} {--limit=100 : Max ledger rows (1-1000)} {--summary : Compact bounded project-wide classification} {--evidence-summary : Aggregate packet/reference triage without raw references} {--review-category= : Export one review category (both_headers, jv_only, cv_only, neither_header)} {--max-rows=5000 : Summary scan cap (1-10000)}';
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
        $evidenceSummary = (bool) $this->option('evidence-summary');
        $reviewCategory = $this->option('review-category');
        if ($evidenceSummary && ($summary || $reviewCategory !== null)) {
            $this->error('Choose --evidence-summary without other report modes.');
            return self::INVALID;
        }
        if ($reviewCategory !== null &&
            (!in_array($reviewCategory, ['both_headers', 'jv_only', 'cv_only', 'neither_header'], true) || $summary)) {
            $this->error('Choose a valid --review-category without --summary.');
            return self::INVALID;
        }
        $maxRows = filter_var($this->option('max-rows'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>10000]]);
        if (($summary || $evidenceSummary) && $maxRows === false) {
            $this->error('Summary --max-rows must be between 1 and 10000.');
            return self::INVALID;
        }
        try {
            $result = $evidenceSummary
                ? $inspector->summarizeEvidence((int) $project, (int) $maxRows)
                : ($summary
                ? $inspector->summarize((int) $project, (int) $maxRows)
                : ($reviewCategory !== null
                    ? $inspector->reviewQueue((int) $project, (string) $reviewCategory, (int) $limit)
                    : $inspector->inspect((int) $project, (int) $limit)));
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
