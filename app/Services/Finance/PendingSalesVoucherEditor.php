<?php

namespace App\Services\Finance;

use App\Models\DailyVoucherApproval;
use Illuminate\Support\Facades\DB;

/**
 * Change or withdraw an unposted, approval-pending Sales Voucher.
 * Neither operation creates a posting or modifies already approved finance legs.
 */
final class PendingSalesVoucherEditor
{
    public function update(int $projectId,int $voucherId,int $actorId,array $input,array $lines): void
    {
        FinanceConnectionGuard::assertAllowed();
        DB::transaction(function ()use($projectId,$voucherId,$actorId,$input,$lines):void{
            [$voucher,$approval]=$this->lockPending($projectId,$voucherId,$actorId);
            $existing=DB::table('journal_voucher_details')
                ->where('journal_voucher_id',$voucherId)->whereNull('deleted_at')
                ->lockForUpdate()->get()->keyBy('id');
            if($existing->count()<2)throw new \DomainException('SV_PENDING_DETAILS_MISSING');
            if(count($lines)<2||count($lines)>100)throw new \DomainException('SV_INVALID_LINES');
            $seen=[];$debit=0;$credit=0;$desired=[];
            foreach($lines as $line){
                $id=(int)($line['detail_id']??0);
                if($id&&(!$existing->has($id)||isset($seen[$id]))){
                    throw new \DomainException('SV_DETAIL_ID_MISMATCH');
                }
                if($id)$seen[$id]=true;
                $account=(int)($line['account_id']??0);
                if(!\App\Models\ProjectHeadSubhead::availableForPosting()
                    ->where('project_id',$projectId)->whereKey($account)->exists()){
                    throw new \DomainException('SV_ACCOUNT_INVALID');
                }
                $d=$this->cents($line['debit']??'0');
                $c=$this->cents($line['credit']??'0');
                if(($d>0)===($c>0))throw new \DomainException('SV_INVALID_DEBIT_CREDIT');
                $debit+=$d;$credit+=$c;
                $desired[]=['id'=>$id,'account_id'=>$account,'debit'=>$this->money($d),
                    'credit'=>$this->money($c),'description'=>(string)($line['description']??'')];
            }
            if($debit<1||$debit!==$credit)throw new \DomainException('SV_AMOUNT_IMBALANCE');
            $previous=$existing->values()->map(fn($d)=>[
                'id'=>(int)$d->id,'account_id'=>(int)$d->account_id,
                'debit'=>$this->money($this->cents($d->debit)),
                'credit'=>$this->money($this->cents($d->credit)),
                'description'=>(string)($d->description??''),
            ])->all();
            $same=(string)$voucher->date===(string)$input['date']
                &&(string)($voucher->reference??'')===(string)($input['reference']??'')
                &&(string)($voucher->description??'')===(string)$input['description']
                &&$previous===$desired;
            if($same)return;
            foreach($existing as $id=>$old){
                if(!isset($seen[$id])){
                    DB::table('journal_voucher_details')->where('id',$id)
                        ->where('journal_voucher_id',$voucherId)->whereNull('deleted_at')
                        ->update(['deleted_at'=>now(),'updated_at'=>now()]);
                }
            }
            foreach($desired as $line){
                $fields=[
                    'account_id'=>$line['account_id'],'debit'=>$line['debit'],
                    'credit'=>$line['credit'],'description'=>$line['description'],
                    'updated_at'=>now(),
                ];
                if($line['id']){
                    DB::table('journal_voucher_details')->where('id',$line['id'])
                        ->where('journal_voucher_id',$voucherId)->whereNull('deleted_at')
                        ->update($fields);
                }else{
                    DB::table('journal_voucher_details')->insert($fields+[
                        'journal_voucher_id'=>$voucherId,'created_at'=>now(),
                    ]);
                }
            }
            DB::table('journal_vouchers')->where('id',$voucherId)
                ->where('project_id',$projectId)->where('type','SV')
                ->update([
                    'date'=>$input['date'],'reference'=>$input['reference']??null,
                    'description'=>$input['description'],'total_debit'=>$this->money($debit),
                    'total_credit'=>$this->money($credit),'updated_by'=>$actorId,'updated_at'=>now(),
                ]);
            DB::table('daily_voucher_approval_events')->insert([
                'approval_id'=>$approval->id,'event'=>'pending_sv_edited',
                'actor_id'=>$actorId,'remarks'=>'Pending SV amended before decision',
                'created_at'=>now(),
            ]);
        },3);
    }

    public function withdraw(int $projectId,int $voucherId,int $actorId): void
    {
        FinanceConnectionGuard::assertAllowed();
        DB::transaction(function()use($projectId,$voucherId,$actorId):void{
            [$voucher,$approval]=$this->lockPending($projectId,$voucherId,$actorId);
            DB::table('journal_voucher_details')->where('journal_voucher_id',$voucherId)
                ->whereNull('deleted_at')->update(['deleted_at'=>now(),'updated_at'=>now()]);
            DB::table('journal_vouchers')->where('id',$voucherId)
                ->where('project_id',$projectId)->where('type','SV')
                ->update(['status'=>'rejected','deleted_at'=>now(),'updated_by'=>$actorId,
                    'updated_at'=>now()]);
            DB::table('daily_voucher_approvals')->where('id',$approval->id)
                ->where('project_id',$projectId)->where('status','pending')
                ->update(['status'=>'rejected','decided_by'=>$actorId,'decided_at'=>now(),
                    'rejection_reason'=>'Withdrawn before approval','updated_at'=>now()]);
            DB::table('daily_voucher_approval_events')->insert([
                'approval_id'=>$approval->id,'event'=>'withdrawn',
                'actor_id'=>$actorId,'remarks'=>'Withdrawn by submitter before approval',
                'created_at'=>now(),
            ]);
        },3);
    }

    private function lockPending(int $projectId,int $voucherId,int $actorId): array
    {
        DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
        $voucher=DB::table('journal_vouchers')->where('id',$voucherId)
            ->where('project_id',$projectId)->where('type','SV')
            ->whereNull('deleted_at')->lockForUpdate()->first();
        $approval=DailyVoucherApproval::query()->where('project_id',$projectId)
            ->where('voucher_type','SV')->where('source_table','journal_vouchers')
            ->where('source_id',$voucherId)->where('status','pending')
            ->lockForUpdate()->first();
        if(!$voucher||!$approval||$voucher->status!=='pending'){
            throw new \DomainException('SV_PENDING_SOURCE_NOT_FOUND');
        }
        if((int)$voucher->created_by!==$actorId
            &&!VoucherApproverAccess::isSuperAdmin(\App\Models\User::find($actorId))){
            throw new \DomainException('SV_PENDING_AUTHOR_NOT_AUTHORIZED');
        }
        if(DB::table('finance_posting_journal_sources')
            ->where('journal_voucher_id',$voucherId)->exists()
            ||DB::table('ledgers')->where('type','SV')->where('type_id',$voucherId)->exists()){
            throw new \DomainException('SV_ALREADY_POSTED');
        }
        return [$voucher,$approval];
    }

    private function cents(mixed $raw): int
    {
        if(!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D',(string)$raw,$m)){
            throw new \DomainException('SV_AMOUNT_INVALID');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }
    private function money(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }
}
