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
        $query=DB::table('finance_posting_batches')
            ->where('project_id',$projectId)->whereNotNull('posted_at');
        if (Schema::hasTable('finance_bank_transfer_sources')
            && Schema::hasColumn('finance_bank_transfer_sources','is_active')) {
            $query->whereNotExists(fn($q)=>$q->selectRaw('1')
                ->from('finance_bank_transfer_sources as archived_bank')
                ->whereColumn('archived_bank.posting_batch_id','finance_posting_batches.id')
                ->where('archived_bank.is_active',0));
        }
        $batches=$query->orderBy('id')
            ->get(['id','voucher_series','voucher_number','reversal_of_batch_id']);
        $lines=DB::table('finance_posting_lines as line')
            ->leftJoin('project_head_subheads as account','account.id','=','line.project_head_subhead_id')
            ->whereIn('line.posting_batch_id',$batches->pluck('id')->all())
            ->when(Schema::hasColumn('finance_posting_lines','is_active'),
                fn($q)=>$q->where('line.is_active',1))
            ->orderBy('line.id')
            ->get(['line.posting_batch_id','line.project_head_subhead_id','line.debit','line.credit',
                'account.project_id as account_project_id'])
            ->groupBy('posting_batch_id');
        // Read original reversal targets independently, including foreign
        // projects, so a cross-project reversal cannot masquerade as valid.
        $reversalIds=$batches->pluck('reversal_of_batch_id')->filter()->unique()->values()->all();
        $reversedOriginals=$reversalIds === [] ? collect() :
            DB::table('finance_posting_batches')->whereIn('id',$reversalIds)
                ->get(['id','project_id','posted_at','reversal_of_batch_id'])->keyBy('id');
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
            if ($batch->reversal_of_batch_id !== null) {
                $original=$reversedOriginals->get((int)$batch->reversal_of_batch_id);
                if (!$original || (int)$original->project_id!==$projectId
                    || $original->posted_at===null
                    || $original->reversal_of_batch_id!==null) {
                    $issues[]='REVERSAL_ORIGINAL_PROJECT_OR_STATE_MISMATCH';
                }
                if ((string)$batch->voucher_series!=='REV') {
                    $issues[]='REVERSAL_SERIES_MISMATCH';
                }
            } elseif ((string)$batch->voucher_series==='REV') {
                $issues[]='REVERSAL_ORIGINAL_MISSING';
            }
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
        // Archived bank vouchers are omitted from live posting totals but
        // MUST be flagged if even one posting leg was left active by tampering.
        if (Schema::hasTable('finance_bank_transfer_sources')
            && Schema::hasColumn('finance_bank_transfer_sources','is_active')
            && Schema::hasColumn('finance_posting_lines','is_active')) {
            $leaks=DB::table('finance_bank_transfer_sources as source')
                ->join('finance_posting_batches as batch','batch.id','=','source.posting_batch_id')
                ->join('finance_posting_lines as leg',function($join){
                    $join->on('leg.posting_batch_id','=','batch.id')
                        ->where('leg.is_active',1);
                })
                ->where('source.project_id',$projectId)
                ->where('batch.project_id',$projectId)->where('source.is_active',0)
                ->groupBy('batch.id','batch.voucher_series','batch.voucher_number')
                ->get(['batch.id as id','batch.voucher_series','batch.voucher_number',
                    DB::raw('COUNT(leg.id) as active_leg_count')]);
            foreach($leaks as $leak) {
                $findings[]=[
                    'batch_id'=>(int)$leak->id,'series'=>(string)$leak->voucher_series,
                    'voucher_number'=>(string)$leak->voucher_number,
                    'reversal_of_batch_id'=>null,
                    'active_line_count'=>(int)$leak->active_leg_count,
                    'issues'=>['ARCHIVED_BANK_WITH_ACTIVE_POSTINGS'],
                ];
            }
        }
        return $findings;
    }
}
