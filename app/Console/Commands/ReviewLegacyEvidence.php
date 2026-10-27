<?php

namespace App\Console\Commands;

use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use App\Services\Finance\LegacyVoucherEvidencePacket;
use Illuminate\Console\Command;

final class ReviewLegacyEvidence extends Command
{
    protected $signature = 'finance:review-legacy-evidence
        {project : Numeric project ID}
        {ledgers : Comma-separated ledger IDs (1-100)}
        {--review-file= : Existing reviewer JSON worksheet to validate without writing}';
    protected $description = 'Read-only fingerprinted evidence worksheet and submission checks; never attributes ledger sources';

    public function handle(LegacyVoucherEvidencePacket $packets, LegacyEvidenceReviewWorksheet $workflow): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $parts = explode(',', (string) $this->argument('ledgers'));
        if ($project === false || !$parts || count($parts) > 100) {
            $this->error('Project and 1-100 distinct ledger IDs required.');
            return self::INVALID;
        }
        $ids = [];
        foreach ($parts as $part) {
            $id = filter_var(trim($part), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $this->error('Positive numeric ledger IDs required.');
                return self::INVALID;
            }
            $ids[] = (int) $id;
        }
        try {
            $batch = $packets->inspectBatch((int) $project, $ids);
        } catch (\DomainException | \InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $file = $this->option('review-file');
        if ($file === null) {
            $this->line(json_encode($workflow->template($batch), JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }
        if (!is_string($file) || !is_file($file) || !is_readable($file)) {
            $this->error('Review file must be an existing readable JSON file.');
            return self::INVALID;
        }
        try {
            $review = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error('Review file must contain valid JSON.');
            return self::INVALID;
        }
        $result = $workflow->assess($batch, is_array($review) ? $review : []);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        return $result['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
