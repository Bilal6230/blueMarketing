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

    private function syncLinkedLedgerStatus(int $projectId,int $batchId): void
    {
        if(!Schema::hasTable('ledger_source_attributions')||!Schema::hasTable('ledgers')){
            if(app()->environment('testing'))return;
            throw new \DomainException('GENERAL_LEDGER_SCHEMA_REQUIRED');
        }
        $series=(string)DB::table('finance_posting_batches')
            ->where('id',$batchId)->where('project_id',$projectId)->value('voucher_series');
        $sourceId=DB::table('finance_bank_transfer_sources')
            ->where('posting_batch_id',$batchId)->where('project_id',$projectId)->value('posting_batch_id');
        if(!$sourceId||!in_array($series,['BD','BW'],true))
            throw new \DomainException('BANK_LEDGER_SOURCE_INVALID');
        $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
            ->whereNotNull('legacy_ledger_id')->lockForUpdate()->get();
        foreach($lines as $line){
            $ledger=DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('a.project_id',$projectId)
                ->where('l.id',$line->legacy_ledger_id)
                ->where('l.type',$series)->where('l.type_id',$sourceId)->first(['l.id']);
            if(!$ledger)throw new \DomainException('BANK_LEDGER_RELATION_INVALID');
            DB::table('ledgers')->where('id',$ledger->id)
                ->update(['is_active'=>(int)($line->is_active??1),'updated_at'=>now()]);
        }
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
                ->where('transfer_kind',$kind)
                ->when(Schema::hasColumn('finance_bank_transfer_sources','is_active'),
                    fn($q)=>$q->where('is_active',1))
                ->when(Schema::hasColumn('finance_bank_transfer_sources','deleted_at'),
                    fn($q)=>$q->whereNull('deleted_at'))
                ->lockForUpdate()->first();
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
            $this->syncLinkedLedgerStatus($projectId,$batchId);
            if(Schema::hasTable('ledger_source_attributions')){
                app(TypedLedgerBackfill::class)->inspect($projectId,$batchId,true);
            }
        },3);
    }
    /** Ordinary logical Delete; preserve original batch and every retired leg. */
    public function delete(int $projectId,string $kind,int $batchId): void
    {
        FinanceConnectionGuard::assertAllowed();
        if ($projectId<1||$batchId<1||!in_array($kind,['deposit','withdrawal'],true)) {
            throw new \DomainException('Bank voucher deletion identity is invalid.');
        }
        if (!Schema::hasColumn('finance_bank_transfer_sources','is_active')
            ||!Schema::hasColumn('finance_bank_transfer_sources','deleted_at')
            ||!Schema::hasColumn('finance_posting_lines','is_active')) {
            throw new \DomainException('Bank voucher lifecycle migration is required.');
        }
        DB::transaction(function()use($projectId,$kind,$batchId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $source=DB::table('finance_bank_transfer_sources')
                ->where('posting_batch_id',$batchId)->where('project_id',$projectId)
                ->where('transfer_kind',$kind)->where('is_active',1)
                ->whereNull('deleted_at')->lockForUpdate()->first();
            $batch=DB::table('finance_posting_batches')
                ->where('id',$batchId)->where('project_id',$projectId)
                ->lockForUpdate()->first();
            if (!$source||!$batch||!$batch->posted_at
                ||$batch->reversal_of_batch_id!==null
                ||(string)$batch->voucher_series!==($kind==='deposit'?'BD':'BW')
                ||DB::table('finance_posting_batches')
                    ->where('reversal_of_batch_id',$batchId)->exists()
                ||!DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id',$batchId)->where('source_kind','bank_transfer')->exists()) {
                throw new \DomainException('Bank voucher is not an active un-reversed source.');
            }
            if ($source->linked_customer_ledger_id!==null) {
                throw new \DomainException('Linked cheque bank voucher requires custody release before deleting.');
            }
            $bank=DB::table('project_bank_accounts')
                ->where('id',$source->bank_account_id)
                ->where('project_id',$projectId)->first();
            $legs=DB::table('finance_posting_lines')
                ->where('posting_batch_id',$batchId)->where('is_active',1)
                ->orderBy('id')->lockForUpdate()->get();
            $bankLeg=$bank?$legs->first(fn($l)=>
                (int)$l->project_head_subhead_id===(int)$bank->account_mapping_id):null;
            $cashLeg=$legs->first(fn($l)=>(int)$l->id!==(int)($bankLeg->id??0));
            $value=$this->cents($source->amount);
            if (!$bank||!$bankLeg||!$cashLeg||$legs->count()!==2||$value<1
                ||$this->cents($bankLeg->{$kind==='deposit'?'debit':'credit'})!==$value
                ||$this->cents($bankLeg->{$kind==='deposit'?'credit':'debit'})!==0
                ||$this->cents($cashLeg->{$kind==='deposit'?'credit':'debit'})!==$value
                ||$this->cents($cashLeg->{$kind==='deposit'?'debit':'credit'})!==0) {
                throw new \DomainException('Bank voucher cannot be deleted with inconsistent posting legs.');
            }
            $fingerprint=hash('sha256',json_encode([
                $projectId,(int)$source->bank_account_id,$kind,
                (string)$batch->effective_date,$this->money($value),
                (string)$source->narration,
                [['account_id'=>(int)$cashLeg->project_head_subhead_id,
                  'amount'=>$this->money($value)]],null,
            ],JSON_THROW_ON_ERROR));
            if (!hash_equals((string)$batch->payload_sha256,$fingerprint)) {
                throw new \DomainException('Bank voucher cannot be deleted with altered source fingerprint.');
            }
            DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
                ->whereIn('id',$legs->pluck('id')->all())->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            DB::table('finance_bank_transfer_sources')
                ->where('posting_batch_id',$batchId)->where('project_id',$projectId)
                ->where('is_active',1)->update([
                    'is_active'=>0,'deleted_at'=>now(),'updated_at'=>now(),
                ]);
            $this->syncLinkedLedgerStatus($projectId,$batchId);
        },3);
    }

}
