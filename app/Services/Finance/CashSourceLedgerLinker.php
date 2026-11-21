<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Source-owned cash/charge legs in the existing General Ledger.
 * Uses customer_ledger_id, never a guess from legacy polymorphic type_id.
 */
final class CashSourceLedgerLinker
{
    private function cents(mixed $raw): int
    {
        $s=trim((string)$raw);
        if(!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',$s,$m))
            throw new \DomainException('CASH_LEDGER_INVALID_MONEY');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    public function sync(int $projectId,int $batchId): int
    {
        return DB::transaction(function()use($projectId,$batchId):int{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $batch=DB::table('finance_posting_batches')
                ->where('project_id',$projectId)->where('id',$batchId)
                ->whereNull('reversal_of_batch_id')->lockForUpdate()->first();
            if(!$batch||!in_array($batch->voucher_series,['CR','CP','DR','PPR','EC','ECP'],true))
                throw new \DomainException('CASH_LEDGER_BATCH_NOT_SUPPORTED');
            $link=DB::table('finance_posting_cash_sources')
                ->where('posting_batch_id',$batchId)->first();
            $claim=DB::table('finance_posting_source_claims')
                ->where('posting_batch_id',$batchId)
                ->where('source_kind','cash')->exists();
            if(!$link||!$claim)throw new \DomainException('CASH_LEDGER_SOURCE_CLAIM_INVALID');
            $source=DB::table('customer_ledger')
                ->where('id',$link->customer_ledger_id)->where('project_id',$projectId)
                ->lockForUpdate()->first();
            if(!$source||(string)$source->transaction_type!==(string)$batch->voucher_series)
                throw new \DomainException('CASH_LEDGER_SOURCE_TYPE_MISMATCH');
            $lines=DB::table('finance_posting_lines')
                ->where('posting_batch_id',$batchId)->orderBy('id')->lockForUpdate()->get();
            if($lines->count()<2)throw new \DomainException('CASH_LEDGER_MISSING_LINES');
            $activeDebit=$activeCredit=0;
            foreach($lines as $line){
                if(!DB::table('project_head_subheads')
                    ->where('id',$line->project_head_subhead_id)
                    ->where('project_id',$projectId)->exists()){
                    throw new \DomainException('CASH_LEDGER_FOREIGN_ACCOUNT');
                }
                $debit=$this->cents($line->debit);
                $credit=$this->cents($line->credit);
                if(($debit>0)===($credit>0)||$debit>9999999999||$credit>9999999999)
                    throw new \DomainException('CASH_LEDGER_INVALID_POSTING_LINE');
                if((int)($line->is_active??1)===1){$activeDebit+=$debit;$activeCredit+=$credit;}
            }
            if($activeDebit!==$activeCredit)
                throw new \DomainException('CASH_LEDGER_UNBALANCED');
            $legacy=DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('a.project_id',$projectId)->where('l.customer_ledger_id',$source->id)
                ->orderBy('l.id')->lockForUpdate()->get(['l.*']);
            // If historical legacy rows exist, every row must be accounted for,
            // matched uniquely by physical account, direction and amount.
            $claimed=[];$created=0;
            foreach($lines as $line){
                $match=null;
                if($line->legacy_ledger_id!==null){
                    $match=$legacy->first(fn($l)=>(int)$l->id===(int)$line->legacy_ledger_id);
                    if(!$match)throw new \DomainException('CASH_LEDGER_LINK_MISSING');
                }else{
                    $candidates=$legacy->filter(function($l)use($line,$claimed){
                        return !isset($claimed[(int)$l->id])
                            &&(int)$l->project_head_subheads_id===(int)$line->project_head_subhead_id
                            &&$this->cents($l->amount_in)===$this->cents($line->debit)
                            &&$this->cents($l->amount_out)===$this->cents($line->credit)
                            &&(int)$l->is_active===(int)($line->is_active??1);
                    })->values();
                    if($candidates->count()>1)
                        throw new \DomainException('CASH_LEDGER_AMBIGUOUS_SOURCE_LINES');
                    $match=$candidates->first();
                }
                if($match){
                    if(isset($claimed[(int)$match->id])
                        ||(string)$match->type!==(string)$source->transaction_type
                        ||(int)$match->is_active!==(int)($line->is_active??1)
                        ||(int)$match->project_head_subheads_id!==(int)$line->project_head_subhead_id
                        ||$this->cents($match->amount_in)!==$this->cents($line->debit)
                        ||$this->cents($match->amount_out)!==$this->cents($line->credit)){
                        throw new \DomainException('CASH_LEDGER_LEGACY_MISMATCH');
                    }
                    $ledgerId=(int)$match->id;
                }else{
                    if($legacy->count()>0)
                        throw new \DomainException('CASH_LEDGER_PARTIAL_LEGACY_SOURCE');
                    $ledgerId=DB::table('ledgers')->insertGetId([
                        'customer_ledger_id'=>$source->id,
                        'type'=>(string)$source->transaction_type,
                        'type_id'=>$source->type_id,
                        'voucher_number'=>$source->type_id,
                        'project_head_subheads_id'=>(int)$line->project_head_subhead_id,
                        'reference'=>$source->reference??null,
                        'amount_in'=>$line->debit,'amount_out'=>$line->credit,
                        'detail'=>$line->description??$source->description??'',
                        'date'=>$batch->effective_date,'status'=>'0',
                        'is_active'=>(int)($line->is_active??1),
                        'create_by'=>$batch->posted_by,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $created++;
                }
                $claimed[$ledgerId]=true;
                if($line->legacy_ledger_id===null){
                    $updated=DB::table('finance_posting_lines')->where('id',$line->id)
                        ->whereNull('legacy_ledger_id')->update([
                            'legacy_ledger_id'=>$ledgerId,'updated_at'=>now(),
                        ]);
                    if($updated!==1)throw new \DomainException('CASH_LEDGER_LINK_CONCURRENT');
                }
            }
            if($legacy->count()>0 && count($claimed)!==$legacy->count()){
                throw new \DomainException('CASH_LEDGER_ORPHAN_LEGACY_SOURCE');
            }
            return $created;
        },3);
    }
}
