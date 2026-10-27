<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherEvidencePacket;
use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use App\Services\Finance\LegacyEvidenceApprovalCheckpoint;
use Illuminate\Console\Command;

final class AuditLegacyReviewCheckpoint extends Command
{
    protected $signature = 'finance:audit-legacy-review-checkpoint
        {project : Existing numeric project ID}
        {ledgers : Comma separated ledger IDs (1-100)}
        {review-file : Existing completed review worksheet JSON}
        {--approval-file= : Optional completed two-person approval JSON}';
    protected $description = 'Read-only two-person legacy evidence review checkpoint, no attribution';

    public function handle(LegacyVoucherEvidencePacket $evidence, LegacyEvidenceReviewWorksheet $worksheet, LegacyEvidenceApprovalCheckpoint $checkpoint): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $parts = explode(',',(string)$this->argument('ledgers'));
        if ($project === false || count($parts)>100 || !$parts) return self::INVALID;
        $ids=[];
        foreach ($parts as $part) {
            $id=filter_var(trim($part),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id === false) return self::INVALID;
            $ids[]=(int)$id;
        }
        try {
            $batch=$evidence->inspectBatch((int)$project,$ids);
        } catch (\DomainException | \InvalidArgumentException $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }
        $review=$this->readJson($this->argument('review-file'));
        if ($review === null) { $this->error('Invalid review JSON file.'); return self::INVALID; }
        $approvalPath=$this->option('approval-file');
        if ($approvalPath === null) {
            $result=$checkpoint->manifest($batch,$review,$worksheet);
        } else {
            $approval=$this->readJson($approvalPath);
            if ($approval === null) { $this->error('Invalid approval JSON file.'); return self::INVALID; }
            $result=$checkpoint->assess($batch,$review,$approval,$worksheet);
        }
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return $result['status']==='blocked' ? self::FAILURE : self::SUCCESS;
    }

    private function readJson($path): ?array
    {
        if (!is_string($path) || !is_file($path) || !is_readable($path)) return null;
        try {
            $value=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : null;
        } catch (\JsonException $e) { return null; }
    }
}
