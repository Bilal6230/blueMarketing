<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherEvidencePacket;
use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use App\Services\Finance\LegacyEvidenceApprovalCheckpoint;
use App\Services\Finance\LegacyEvidenceConflictGate;
use Illuminate\Console\Command;

final class AuditLegacyEvidenceConflicts extends Command
{
    protected $signature='finance:audit-legacy-evidence-conflicts
        {project : Existing project ID}
        {ledgers : Comma-separated ledger IDs, 1-100}
        {review-file : Completed review worksheet JSON file}
        {approval-file : Completed approval checkpoint JSON file}';
    protected $description='Read-only reconciliation of reviewed ledger evidence against existing typed attribution';

    public function handle(LegacyVoucherEvidencePacket $packets, LegacyEvidenceReviewWorksheet $worksheet,
        LegacyEvidenceApprovalCheckpoint $approval, LegacyEvidenceConflictGate $gate): int
    {
        $project=filter_var($this->argument('project'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $parts=explode(',',(string)$this->argument('ledgers'));
        if ($project === false || count($parts)>100) return self::INVALID;
        $ids=[];
        foreach ($parts as $value) {
            $id=filter_var(trim($value),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id === false) return self::INVALID;
            $ids[]=(int)$id;
        }
        $review=$this->load($this->argument('review-file'));
        $signoff=$this->load($this->argument('approval-file'));
        if ($review === null || $signoff === null) {
            $this->error('Both existing readable JSON review and approval files required.');
            return self::INVALID;
        }
        try {
            $batch=$packets->inspectBatch((int)$project,$ids);
            $result=$gate->inspect($batch,$review,$signoff,$worksheet,$approval);
        } catch (\DomainException | \InvalidArgumentException $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return $result['status']==='blocked' ? self::FAILURE : self::SUCCESS;
    }
    private function load($path): ?array
    {
        if (!is_string($path) || !is_file($path) || !is_readable($path)) return null;
        try {
            $value=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : null;
        } catch (\JsonException $e) {
            return null;
        }
    }
}
