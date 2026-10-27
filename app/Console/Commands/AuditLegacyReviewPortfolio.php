<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherEvidencePacket;
use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use App\Services\Finance\LegacyEvidenceApprovalCheckpoint;
use App\Services\Finance\LegacyEvidencePortfolioReconciliation;
use Illuminate\Console\Command;

final class AuditLegacyReviewPortfolio extends Command
{
    protected $signature='finance:audit-legacy-review-portfolio {manifest : Existing JSON file with project groups}';
    protected $description='Read-only multi-project independent review reconciliation; no ledger updates';

    public function handle(LegacyVoucherEvidencePacket $packet, LegacyEvidenceReviewWorksheet $worksheet,
        LegacyEvidenceApprovalCheckpoint $checkpoint, LegacyEvidencePortfolioReconciliation $portfolio): int
    {
        $path=$this->argument('manifest');
        if (!is_string($path) || !is_file($path) || !is_readable($path)) {
            $this->error('Readable portfolio manifest JSON required.'); return self::INVALID;
        }
        try { $input=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { $this->error('Invalid portfolio JSON.'); return self::INVALID; }
        if (!is_array($input) || !isset($input['projects']) || !is_array($input['projects']) ||
            !$input['projects'] || count($input['projects'])>100) {
            $this->error('Manifest must contain 1-100 project groups.'); return self::INVALID;
        }
        $groups=[]; $seen=[];
        try {
            foreach ($input['projects'] as $group) {
                if (!is_array($group) || !isset($group['project_id'],$group['ledger_ids'],$group['review'],$group['approval']) ||
                    !is_int($group['project_id']) || $group['project_id'] < 1 ||
                    !is_array($group['ledger_ids']) || !is_array($group['review']) || !is_array($group['approval']) ||
                    isset($seen[$group['project_id']])) {
                    throw new \InvalidArgumentException('Invalid/duplicate project review group.');
                }
                $seen[$group['project_id']]=true;
                $groups[]=[
                    'batch'=>$packet->inspectBatch($group['project_id'],$group['ledger_ids']),
                    'review'=>$group['review'],'approval'=>$group['approval']
                ];
            }
            $result=$portfolio->compile($groups,$checkpoint,$worksheet);
        } catch (\DomainException | \InvalidArgumentException $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return $result['status']==='blocked' ? self::FAILURE : self::SUCCESS;
    }
}
