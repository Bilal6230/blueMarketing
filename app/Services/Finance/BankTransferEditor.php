<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Correct an unlinked manual bank deposit/withdrawal under its original batch ID. */
final class BankTransferEditor
{
    private function cents(mixed $value): int
    {
        $s=(string)$value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',$s,$m)) {
            throw new \DomainException('Bank voucher amount is invalid.');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function money(int $value): string
    {
        return intdiv($value,100).'.'.str_pad((string)($value%100),2,'0',STR_PAD_LEFT);
    }

    public function update(int $projectId,string $kind,int $batchId,int $bankAccountId,
        int $cashAccountId,string $date,string $amount,string $narration,?int $actorId=null): void
    {
        FinanceConnectionGuard::assertAllowed();
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if ($projectId<1||$batchId<1||$bankAccountId<1||$cashAccountId<1
            ||!in_array($kind,['deposit','withdrawal'],true)
            ||!$day||$day->format('Y-m-d')!==$date||trim($narration)==='') {
            throw new \DomainException('Bank voucher correction input is invalid.');
        }
        $cents=$this->cents($amount);
        if ($cents<1) throw new \DomainException('Bank voucher requires a positive amount.');
        if (!Schema::hasColumn('finance_posting_lines','is_active')) {
            throw new \DomainException('Bank voucher correction needs the existing posting lifecycle migration.');
        }
        DB::transaction(function()use($projectId,$kind,$batchId,$bankAccountId,$cashAccountId,
            $date,$cents,$narration,$actorId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $batch=DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)->lockForUpdate()->first();
            $source=DB::table('finance_bank_transfer_sources')
                ->where('posting_batch_id',$batchId)->where('project_id',$projectId)
                ->where('transfer_kind',$kind)->lockForUpdate()->first();
            if (!$batch||!$source||$batch->posted_at===null
                ||$batch->reversal_of_batch_id!==null
                ||(string)$batch->voucher_series!==($kind==='deposit'?'BD':'BW')
                ||DB::table('finance_posting_batches')
                    ->where('reversal_of_batch_id',$batchId)->exists()
                ||!DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id',$batchId)->where('source_kind','bank_transfer')->exists()) {
                throw new \DomainException('Bank voucher source or active posting is invalid.');
            }
            if ($source->linked_customer_ledger_id!==null) {
                // Cheque custody must be resolved by the separate linked-source
                // workflow. Never silently change a passed cheque amount.
                throw new \DomainException('Linked cheque bank voucher requires custody release before editing.');
            }
            $oldBank=DB::table('project_bank_accounts')->where('id',$source->bank_account_id)
                ->where('project_id',$projectId)->first();
            $newBank=DB::table('project_bank_accounts')->where('id',$bankAccountId)
                ->where('project_id',$projectId)->where('is_active',1)->first();
            $cash=\App\Models\ProjectHeadSubhead::availableForPosting()
                ->where('project_id',$projectId)->where('id',$cashAccountId)
                ->where(function($query) {
                    $query->whereHas('headAccounting',fn($head)=>$head->where('name','LIKE','%cash%'))
                        ->orWhereHas('subheadAccounting',fn($sub)=>$sub->where('name','LIKE','%cash%'));
                })->exists();
            if (!$oldBank||!$newBank||!$cash
                ||(int)$newBank->account_mapping_id===$cashAccountId) {
                throw new \DomainException('Bank or cash account does not belong to this project.');
            }
            $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
                ->where('is_active',1)->orderBy('id')->lockForUpdate()->get();
            // Multi-leg historical transfers remain untouched until they have
            // a stable per-allocation FK. No index guessing or partial writes.
            if ($lines->count()!==2) {
                throw new \DomainException('Bank voucher with multiple cash allocations requires detail-linked correction.');
            }
            $bankLeg=$lines->first(fn($line)=>
                (int)$line->project_head_subhead_id===(int)$oldBank->account_mapping_id);
            $cashLeg=$lines->first(fn($line)=>(int)$line->id!==(int)($bankLeg->id??0));
            $priorCents=$this->cents($source->amount);
            if (!$bankLeg||!$cashLeg
                ||$this->cents($bankLeg->{$kind==='deposit'?'debit':'credit'})!==$priorCents
                ||$this->cents($cashLeg->{$kind==='deposit'?'credit':'debit'})!==$priorCents
                ||$this->cents($bankLeg->{$kind==='deposit'?'credit':'debit'})!==0
                ||$this->cents($cashLeg->{$kind==='deposit'?'debit':'credit'})!==0) {
                throw new \DomainException('Bank voucher original posting amounts are inconsistent.');
            }
            $priorLines=[['account_id'=>(int)$cashLeg->project_head_subhead_id,
                'amount'=>$this->money($priorCents)]];
            $expected=hash('sha256',json_encode([
                $projectId,(int)$source->bank_account_id,$kind,
                (string)$batch->effective_date,$this->money($priorCents),
                (string)$source->narration,$priorLines,null,
            ],JSON_THROW_ON_ERROR));
            if (!hash_equals((string)$batch->payload_sha256,$expected)) {
                throw new \DomainException('Bank voucher posting fingerprint mismatch.');
            }
            $narration=trim($narration);
            $newAmount=$this->money($cents);
            if ((int)$source->bank_account_id===$bankAccountId
                &&(int)$cashLeg->project_head_subhead_id===$cashAccountId
                &&$priorCents===$cents
                &&(string)$batch->effective_date===$date
                &&(string)$source->narration===$narration) return;
            $hash=hash('sha256',json_encode([
                $projectId,$bankAccountId,$kind,$date,$newAmount,
                $narration,[['account_id'=>$cashAccountId,'amount'=>$newAmount]],null,
            ],JSON_THROW_ON_ERROR));
            DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
                ->whereIn('id',$lines->pluck('id')->all())->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            $isDeposit=$kind==='deposit';
            foreach([
                ['project_head_subhead_id'=>(int)$newBank->account_mapping_id,
                    'debit'=>$isDeposit?$newAmount:'0.00',
                    'credit'=>$isDeposit?'0.00':$newAmount],
                ['project_head_subhead_id'=>$cashAccountId,
                    'debit'=>$isDeposit?'0.00':$newAmount,
                    'credit'=>$isDeposit?$newAmount:'0.00'],
            ] as $line) {
                DB::table('finance_posting_lines')->insert($line+[
                    'posting_batch_id'=>$batchId,'description'=>$narration,
                    'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            DB::table('finance_bank_transfer_sources')
                ->where('posting_batch_id',$batchId)->where('project_id',$projectId)
                ->update(['bank_account_id'=>$bankAccountId,'amount'=>$newAmount,
                    'narration'=>$narration,'updated_at'=>now()]);
            DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)
                ->update(['effective_date'=>$date,'payload_sha256'=>$hash,'updated_at'=>now()]);
        },3);
    }
}
