<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only, project-scoped integrity check of posted batches across ALL
 * finance voucher series (including REV). Never mutates source or ledger.
 */
final class PostingBatchIntegrityAudit
{
    private function cents(mixed $value): int
    {
        if (!preg_match('/^([0-9]{1,14})(?:\.([0-9]{1,2}))?$/D', (string)$value, $m)) {
            throw new \DomainException('Invalid posting-line amount.');
        }
        return (int)$m[1]*100 + (int)str_pad($m[2]??'',2,'0');
    }

    /** @return array<int,array<string,mixed>> */
    public function audit(int $projectId): array
    {
        if ($projectId < 1) throw new \InvalidArgumentException('Project ID must be positive.');
        FinanceConnectionGuard::assertAllowed();
        $batches=DB::table('finance_posting_batches')
            ->where('project_id',$projectId)->whereNotNull('posted_at')
            ->orderBy('id')->get(['id','voucher_series','voucher_number','reversal_of_batch_id']);
        if ($batches->isEmpty()) return [];
        $lines=DB::table('finance_posting_lines as line')
            ->leftJoin('project_head_subheads as account','account.id','=','line.project_head_subhead_id')
            ->whereIn('line.posting_batch_id',$batches->pluck('id')->all())
            ->when(Schema::hasColumn('finance_posting_lines','is_active'),
                fn($q)=>$q->where('line.is_active',1))
            ->orderBy('line.id')
            ->get(['line.posting_batch_id','line.project_head_subhead_id','line.debit','line.credit',
                'account.project_id as account_project_id'])
            ->groupBy('posting_batch_id');
        $findings=[];
        foreach ($batches as $batch) {
            $legs=$lines->get($batch->id,collect());
            $debit=0;$credit=0;$issues=[];
            if ($legs->count()<2) $issues[]='ACTIVE_LINES_MISSING';
            foreach ($legs as $leg) {
                if ((int)$leg->account_project_id!==$projectId)
                    $issues[]='ACCOUNT_PROJECT_MISMATCH';
                try {
                    $dr=$this->cents($leg->debit);
                    $cr=$this->cents($leg->credit);
                } catch (\DomainException) {
                    $issues[]='INVALID_AMOUNT';
                    continue;
                }
                if (($dr>0)==($cr>0)) $issues[]='INVALID_DEBIT_CREDIT_LEG';
                $debit+=$dr;$credit+=$cr;
            }
            if ($debit<1 || $debit!==$credit) $issues[]='POSTING_UNBALANCED';
            if ($issues!==[]) $findings[]=[
                'batch_id'=>(int)$batch->id,
                'series'=>(string)$batch->voucher_series,
                'voucher_number'=>(string)$batch->voucher_number,
                'reversal_of_batch_id'=>$batch->reversal_of_batch_id===null
                    ? null : (int)$batch->reversal_of_batch_id,
                'active_line_count'=>$legs->count(),
                'issues'=>array_values(array_unique($issues)),
            ];
        }
        return $findings;
    }
}
