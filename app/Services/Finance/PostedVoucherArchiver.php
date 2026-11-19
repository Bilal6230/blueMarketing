<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ordinary logical Delete of a posted JV/SV/CV. Keep the original batch,
 * posting-detail attribution and legacy-ledger FK history. Disable only its
 * live financial legs; never generate an external reversal voucher.
 */
final class PostedVoucherArchiver
{
    private function cents(mixed $value): int
    {
        $value=(string)$value;
        if(!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',$value,$m)) {
            throw new \DomainException('Posted voucher amount is invalid.');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function money(mixed $v): string
    {
        $c=$this->cents($v);
        return intdiv($c,100).'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT);
    }

    public function archive(int $projectId,string $kind,int $voucherId): void
    {
        FinanceConnectionGuard::assertAllowed();
        if ($projectId<1||$voucherId<1||!in_array($kind,['journal','commission'],true)) {
            throw new \DomainException('Posted voucher source identity is invalid.');
        }
        if (!Schema::hasColumn('finance_posting_lines','is_active')) {
            throw new \DomainException('Posted voucher lifecycle migration is required.');
        }
        if ($kind==='commission') {
            app(CommissionLedgerSynchronizer::class)->assertEnforcedDetailForeignKey();
        }
        DB::transaction(function()use($projectId,$kind,$voucherId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $cv=$kind==='commission';
            $headerTable=$cv?'commision_vouchers':'journal_vouchers';
            $detailTable=$cv?'commision_voucher_details':'journal_voucher_details';
            $sourceTable=$cv?'finance_posting_commission_sources':'finance_posting_journal_sources';
            $sourceColumn=$cv?'commision_voucher_id':'journal_voucher_id';
            $linksTable=$cv?'finance_commission_line_sources':'finance_journal_line_sources';
            $detailFK=$cv?'commision_voucher_detail_id':'journal_voucher_detail_id';
            $voucher=DB::table($headerTable)->where('id',$voucherId)
                ->where('project_id',$projectId)->whereNull('deleted_at')
                ->lockForUpdate()->first();
            if(!$voucher||($cv&&(string)$voucher->status!=='approved')
                ||(!$cv&&!in_array((string)$voucher->type,['JV','SV'],true))) {
                throw new \DomainException('Only a currently posted source may be deleted.');
            }
            if (Schema::hasTable('daily_voucher_approvals')
                && DB::table('daily_voucher_approvals')->where('project_id',$projectId)
                    ->where('source_table',$headerTable)->where('source_id',$voucherId)
                    ->where('status','pending')->exists()) {
                throw new \DomainException('Posted voucher has a pending approval decision.');
            }
            $batchIds=DB::table($sourceTable)->where($sourceColumn,$voucherId)
                ->pluck('posting_batch_id');
            if($batchIds->count()!==1) {
                throw new \DomainException('Posting source must own exactly one batch.');
            }
            $batchId=(int)$batchIds[0];
            $batch=DB::table('finance_posting_batches')
                ->where('id',$batchId)->where('project_id',$projectId)
                ->lockForUpdate()->first();
            if(!$batch||!$batch->posted_at||$batch->reversal_of_batch_id!==null
                ||DB::table('finance_posting_batches')
                    ->where('reversal_of_batch_id',$batchId)->exists()
                ||!DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id',$batchId)->where('source_kind',$kind)->exists()
                ||(string)$batch->voucher_series!==($cv?'CV':(string)$voucher->type)
                ||(string)$batch->effective_date!==(string)$voucher->date) {
                throw new \DomainException('Posted voucher has invalid or reversed financial evidence.');
            }
            $details=DB::table($detailTable)->where($sourceColumn,$voucherId)
                ->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            if($details->count()<2)throw new \DomainException('Source has insufficient active details.');
            $lines=$details->map(fn($d)=>[
                'detail_id'=>(int)$d->id,'account_id'=>(int)$d->account_id,
                'debit'=>$this->money($d->debit),'credit'=>$this->money($d->credit),
                'description'=>$d->description,
            ])->all();
            $hash=$cv
                ? hash('sha256',json_encode([
                    'source_kind'=>'commission','project_id'=>$projectId,
                    'source_id'=>$voucherId,'source_number_text'=>(string)$voucher->voucher_number,
                    'source_date'=>(string)$voucher->date,'source_status'=>'approved',
                    'legs'=>$lines,
                ],JSON_THROW_ON_ERROR))
                : hash('sha256',json_encode([
                    'project_id'=>$projectId,'journal_id'=>$voucherId,
                    'voucher_number'=>(string)$voucher->voucher_number,
                    'effective_date'=>(string)$voucher->date,'lines'=>$lines,
                ],JSON_THROW_ON_ERROR));
            if(!hash_equals((string)$batch->payload_sha256,$hash)) {
                throw new \DomainException('Posted voucher original fingerprint mismatch.');
            }
            $post=DB::table($linksTable.' as src')
                ->join('finance_posting_lines as line','line.id','=','src.posting_line_id')
                ->where('line.posting_batch_id',$batchId)->where('line.is_active',1)
                ->lockForUpdate()->get(['line.id','line.project_head_subhead_id','line.debit',
                    'line.credit','src.'.$detailFK])
                ->groupBy($detailFK);
            if($post->flatten(1)->count()!==$details->count()
                ||DB::table('finance_posting_lines')
                    ->where('posting_batch_id',$batchId)->where('is_active',1)
                    ->count()!==$details->count()) {
                throw new \DomainException('Posted voucher source/detail/posting FK counts differ.');
            }
            $legacy=collect();
            if(Schema::hasTable('ledgers')) {
                if(!Schema::hasColumn('ledgers',$detailFK)){
                    if(DB::table('ledgers')->where('type',$cv?'CV':(string)$voucher->type)
                        ->where('type_id',$voucherId)->where('is_active',1)->exists()){
                        throw new \DomainException('Voucher legacy ledger FK column is missing.');
                    }
                }else{
                    $legacy=DB::table('ledgers')->whereIn($detailFK,$details->pluck('id')->all())
                        ->where('is_active',1)->lockForUpdate()->get()->groupBy($detailFK);
                    foreach($legacy->flatten(1) as $ledgerLeg){
                        if((string)$ledgerLeg->type!==($cv?'CV':(string)$voucher->type)
                            ||(int)$ledgerLeg->type_id!==$voucherId){
                            throw new \DomainException('Archived voucher detail FK is attributed to another source.');
                        }
                    }
                }
            }
            $legacyCount=$legacy->flatten(1)->count();
            if(($cv && $legacyCount!==$details->count())
                ||(!$cv && $legacyCount!==0 && $legacyCount!==$details->count())) {
                throw new \DomainException('Posted voucher legacy ledger links differ from details.');
            }
            if($legacyCount&&!Schema::hasColumn('ledgers',$detailFK)) {
                throw new \DomainException('Posted voucher legacy ledger detail FK is missing.');
            }
            foreach($details as $detail) {
                $id=(int)$detail->id;
                $attached=$post->get($id,collect());
                $ledger=$legacy->get($id,collect());
                if($attached->count()!==1
                    ||(int)$attached[0]->project_head_subhead_id!==(int)$detail->account_id
                    ||$this->cents($attached[0]->debit)!==$this->cents($detail->debit)
                    ||$this->cents($attached[0]->credit)!==$this->cents($detail->credit)) {
                    throw new \DomainException('Posted voucher posting line FK or amount mismatch.');
                }
                if($legacyCount && ($ledger->count()!==1
                    ||(int)$ledger[0]->project_head_subheads_id!==(int)$detail->account_id
                    ||$this->cents($ledger[0]->amount_in)!==$this->cents($detail->debit)
                    ||$this->cents($ledger[0]->amount_out)!==$this->cents($detail->credit))) {
                    throw new \DomainException('Posted voucher ledger FK or amount mismatch.');
                }
            }
            $postIds=$post->flatten(1)->pluck('id')->all();
            DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
                ->whereIn('id',$postIds)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            if($legacyCount){
                DB::table('ledgers')->whereIn('id',$legacy->flatten(1)->pluck('id')->all())
                    ->where('is_active',1)->update(['is_active'=>0,'updated_at'=>now()]);
            }
            DB::table($detailTable)->where($sourceColumn,$voucherId)
                ->whereIn('id',$details->pluck('id')->all())
                ->whereNull('deleted_at')->update(['deleted_at'=>now(),'updated_at'=>now()]);
            DB::table($headerTable)->where('id',$voucherId)
                ->where('project_id',$projectId)->whereNull('deleted_at')
                ->update(['deleted_at'=>now(),'updated_at'=>now()]);
        },3);
    }
}
