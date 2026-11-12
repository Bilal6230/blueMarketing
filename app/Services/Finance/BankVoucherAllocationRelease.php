<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit, one-time, append-only release of a verified bank allocation.
 * No allocation, management intake, voucher number or approval history is deleted.
 * Caller must enforce permissions. Project lock ordering matches voucher writer.
 */
final class BankVoucherAllocationRelease
{
    public function release(int $projectId, int $voucherId, int $actorId, string $reason): array
    {
        $reason=trim($reason);
        if($projectId<1 || $voucherId<1 || $actorId<1 ||
            mb_strlen($reason)<3 || mb_strlen($reason)>500) {
            throw new \DomainException('A valid voucher and release reason (3–500 characters) are required.');
        }
        foreach (['finance_bank_voucher_allocations','finance_bank_voucher_allocation_releases',
            'project_voucher_display_numbers','customer_ledger','ledgers','daily_voucher_approvals'] as $table) {
            if(!Schema::hasTable($table)) throw new \DomainException('Bank allocation release migration/dependency missing.');
        }
        return DB::transaction(function()use($projectId,$voucherId,$actorId,$reason): array {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                ?: throw new \DomainException('Project not found.');
            $identity=DB::table('project_voucher_display_numbers')
                ->where('project_id',$projectId)->where('source_kind','customer_ledger')
                ->where('source_id',$voucherId)->whereIn('series',['CR','DR','BOR','BOP'])
                ->lockForUpdate()->first();
            if(!$identity) throw new \DomainException('Not a verified bank-funded CR/DR/BOR/BOP voucher.');
            $series=(string)$identity->series;
            $source=DB::table('customer_ledger')->where('project_id',$projectId)
                ->where('id',$voucherId)->lockForUpdate()->first();
            if(!$source || (string)$source->transaction_type!==(in_array($series,['CR','BOR'],true)?'CR':'CP')
                || !in_array((int)$source->payment_type,[2,3],true)
                || (\Illuminate\Support\Facades\Schema::hasColumn('customer_ledger','deleted_at')
                    && $source->deleted_at!==null)) {
                throw new \DomainException('Voucher source identity changed.');
            }
            $allocation=DB::table('finance_bank_voucher_allocations')
                ->where('project_id',$projectId)->where('voucher_type',$series)
                ->where('voucher_id',$voucherId)->lockForUpdate()->first();
            if(!$allocation || (float)($allocation->amount??0)<=0
                || !in_array($allocation->source_method,['online','cheque'],true)
                || (($allocation->source_method==='online'?2:3)!==(int)$source->payment_type)
                || round((float)$allocation->amount,2)!==
                    round(max((float)$source->amount_in,(float)$source->amount_out),2)) {
                throw new \DomainException('Voucher allocation or amount no longer matches.');
            }
            // Lock the original management source after locking project.
            // The voucher creator locks these in the same order.
            $intakeTable=$allocation->source_method==='online'
                ? 'finance_online_intakes':'finance_cheque_intakes';
            $intake=DB::table($intakeTable)->where('project_id',$projectId)
                ->where('id',$allocation->source_id)->lockForUpdate()->first();
            if(!$intake) throw new \DomainException('Verified management source missing.');
            if(DB::table('finance_bank_voucher_allocation_releases')
                ->where('allocation_id',$allocation->id)->lockForUpdate()->exists()){
                throw new \DomainException('Allocation already released. Repeated release is blocked.');
            }
            $approval=DB::table('daily_voucher_approvals')
                ->where('project_id',$projectId)->where('source_table','customer_ledger')
                ->where('source_id',$voucherId)->lockForUpdate()->first();
            if($approval && (string)$approval->voucher_type!==$series) {
                throw new \DomainException('Legacy/mismatched approval requires manual reconciliation.');
            }
            app(VoucherApprovalMutationGuard::class)
                ->assertNoPendingCorrection($projectId,$voucherId);
            if(Schema::hasTable('daily_voucher_edit_snapshots') &&
                DB::table('daily_voucher_edit_snapshots')
                  ->where('project_id',$projectId)->where('source_id',$voucherId)
                  ->where('status','pending')->exists()){
                throw new \DomainException('Resolve pending voucher edits before releasing allocation.');
            }
            if($approval && $approval->status==='pending'){
                throw new \DomainException('Pending approval must be decided before release.');
            }
            $legs=DB::table('ledgers as ledger')
                ->join('project_head_subheads as account','account.id','=','ledger.project_head_subheads_id')
                ->where('account.project_id',$projectId)
                ->where('ledger.customer_ledger_id',$voucherId)
                ->lockForUpdate()->get(['ledger.id','ledger.type','ledger.is_active',
                    'ledger.amount_in','ledger.amount_out']);
            if($legs->count()!==1
                || DB::table('ledgers')->where('customer_ledger_id',$voucherId)->count()!==1
                || (string)$legs->first()->type!==(string)$source->transaction_type
                || round(max((float)$legs->first()->amount_in,(float)$legs->first()->amount_out),2)
                    !==round((float)$allocation->amount,2)) {
                throw new \DomainException('Single-leg project financial integrity check failed.');
            }
            $rejected=$approval && $approval->status==='rejected';
            $posted=(int)$source->is_active===1 && (int)$legs->first()->is_active===1
                && (!$approval || $approval->status==='approved' || $approval->status==='posted');
            if(!$rejected && !$posted) {
                throw new \DomainException('Only rejected or active posted bank vouchers can be released.');
            }
            if($rejected && ((int)$source->is_active!==0 || (int)$legs->first()->is_active!==0)){
                throw new \DomainException('Rejected voucher unexpectedly has an active posting.');
            }
            $action=$rejected?'rejected_release':'posted_void_release';
            if($posted){
                // Administrative void, not a new offsetting double-entry voucher.
                // Never erase the original ledger or bank management record.
                DB::table('customer_ledger')->where('project_id',$projectId)
                    ->where('id',$voucherId)->update([
                        'is_active'=>0,'delete_reason'=>$reason,'updated_at'=>now(),
                    ]);
                DB::table('ledgers')->where('id',$legs->first()->id)
                    ->update(['is_active'=>0,'delete_reason'=>$reason,'update_by'=>$actorId,
                        'updated_at'=>now()]);
            }
            $releaseId=DB::table('finance_bank_voucher_allocation_releases')->insertGetId([
                'project_id'=>$projectId,'allocation_id'=>$allocation->id,
                'source_method'=>$allocation->source_method,'source_id'=>$allocation->source_id,
                'voucher_type'=>$series,'voucher_id'=>$voucherId,'amount'=>$allocation->amount,
                'action'=>$action,'reason'=>$reason,'released_by'=>$actorId,
                'released_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            if($approval && Schema::hasTable('daily_voucher_approval_events')) {
                DB::table('daily_voucher_approval_events')->insert([
                    'approval_id'=>$approval->id,'event'=>$action,'actor_id'=>$actorId,
                    'remarks'=>json_encode(['release_id'=>$releaseId,'reason'=>$reason,
                        'allocation_id'=>$allocation->id,'amount'=>$allocation->amount],
                        JSON_THROW_ON_ERROR),'created_at'=>now(),
                ]);
            }
            return ['voucher_number'=>(string)$identity->display_number,
                'action'=>$action,'amount'=>(string)$allocation->amount,
                'release_id'=>$releaseId,'method'=>(string)$allocation->source_method,
                'source_id'=>(int)$allocation->source_id];
        },3);
    }
}
