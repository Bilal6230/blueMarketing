<?php
namespace App\Services\Finance;

use App\Models\DailyVoucherApproval;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision of rejected, unposted JV only. Reuse the same voucher/approval/leg IDs.
 * Original rejected decision, reason and complete before-image are retained as events.
 * No existing active posting can be changed by this workflow.
 */
final class RejectedJournalVoucherResubmission
{
    public function resubmit(int $projectId, int $voucherId, int $actorId, array $data, array $lines, string $reason): void
    {
        DB::transaction(function() use($projectId,$voucherId,$actorId,$data,$lines,$reason): void {
            $voucher=JournalVoucher::query()->whereKey($voucherId)->where('project_id',$projectId)
                ->where(fn($q)=>$q->where('type','JV')->orWhereNull('type'))
                ->lockForUpdate()->firstOrFail();
            $actor=User::query()->findOrFail($actorId);
            if(!VoucherApproverAccess::isSuperAdmin($actor) && (int)$voucher->created_by!==$actorId)
                throw new \DomainException('Only the voucher creator or Super Admin may resubmit a rejected JV.');
            if(!VoucherApproverAccess::isSuperAdmin($actor) && (int)$actor->project_id!==$projectId)
                throw new \DomainException('Voucher project access denied.');
            if(!Schema::hasTable('daily_voucher_approvals') || !Schema::hasColumn('ledgers','journal_voucher_detail_id'))
                throw new \DomainException('JV revision requires approval records and detail provenance.');
            $approval=DailyVoucherApproval::query()->where('project_id',$projectId)
                ->where('voucher_type','JV')->where('source_table','journal_vouchers')
                ->where('source_id',$voucherId)->lockForUpdate()->firstOrFail();
            if($approval->status!=='rejected')
                throw new \DomainException('Only a rejected JV can be revised and resubmitted.');
            $policy=app(DailyVoucherApprovalWorkflow::class)->policy($projectId,'JV');
            $approver=$policy?->approver_user_id ? User::query()->find($policy->approver_user_id) : null;
            if(!$policy || !VoucherApproverAccess::eligibleForAssignment($approver,$projectId))
                throw new \DomainException('Configure an active authorized JV approver before resubmitting.');
            if((int)$approver->id===$actorId && !VoucherApproverAccess::isSuperAdmin($actor))
                throw new \DomainException('The submitter cannot be their own assigned approver.');
            $details=JournalVoucherDetail::query()->where('journal_voucher_id',$voucherId)
                ->orderBy('id')->lockForUpdate()->get();
            $ledgers=Ledger::query()->withoutGlobalScopes()
                ->where('type','JV')->where('type_id',$voucherId)
                ->whereIn('project_head_subheads_id',ProjectHeadSubhead::query()->where('project_id',$projectId)->select('id'))
                ->lockForUpdate()->get();
            if($details->count()!==count($lines) || $details->count()!==$ledgers->count() || $details->count()<2)
                throw new \DomainException('JV detail/ledger counts do not match. Revision stopped.');
            if($ledgers->contains(fn($ledger)=>(int)$ledger->is_active!==0) ||
                $ledgers->contains(fn($ledger)=>!$ledger->journal_voucher_detail_id))
                throw new \DomainException('JV has active/unattributed ledger rows; revision stopped.');
            $byDetail=$ledgers->keyBy('journal_voucher_detail_id');
            if($byDetail->count()!==$details->count() ||
               $details->contains(fn($detail)=>!$byDetail->has($detail->id)))
                throw new \DomainException('JV detail provenance is inconsistent; revision stopped.');
            $before=[
                'reference'=>$voucher->reference,'date'=>$voucher->date,
                'description'=>$voucher->description,'total_debit'=>$voucher->total_debit,
                'total_credit'=>$voucher->total_credit,
                'previous_rejection_reason'=>$approval->rejection_reason,
                'details'=>$details->map(fn($x)=>[
                    'id'=>$x->id,'account_id'=>$x->account_id,'debit'=>$x->debit,
                    'credit'=>$x->credit,'description'=>$x->description
                ])->all(),
                'ledgers'=>$ledgers->map(fn($x)=>[
                    'id'=>$x->id,'account_id'=>$x->project_head_subheads_id,
                    'amount_in'=>$x->amount_in,'amount_out'=>$x->amount_out,
                    'detail'=>$x->detail,'reference'=>$x->reference,'date'=>$x->date
                ])->all()
            ];
            $audit=json_encode(['reason'=>$reason,'before'=>$before],JSON_THROW_ON_ERROR);
            if(strlen($audit)>60000)
                throw new \DomainException('JV revision history exceeds safe audit storage size.');
            $debit=0;$credit=0;
            foreach($details as $index=>$detail){
                $line=$lines[$index];
                $account=ProjectHeadSubhead::availableForPosting()->where('project_id',$projectId)
                    ->whereKey((int)$line['account_id'])->firstOrFail();
                $debit+=(int)$line['debit_cents'];$credit+=(int)$line['credit_cents'];
                $amountIn=number_format($line['debit_cents']/100,2,'.','');
                $amountOut=number_format($line['credit_cents']/100,2,'.','');
                $detail->update(['account_id'=>$account->id,'debit'=>$amountIn,
                    'credit'=>$amountOut,'description'=>$line['description']]);
                $leg=$byDetail->get($detail->id);
                $leg->update([
                    'project_head_subheads_id'=>$account->id,'reference'=>$data['reference']??null,
                    'amount_in'=>$amountIn,'amount_out'=>$amountOut,
                    'detail'=>$line['description'],'date'=>$data['date'],
                    'update_by'=>$actorId,'is_active'=>0,
                ]);
            }
            if($debit<=0 || $debit!==$credit)
                throw new \DomainException('Unbalanced JV revision is not allowed.');
            $voucher->update([
                'reference'=>$data['reference']??null,'date'=>$data['date'],
                'description'=>$data['description'],
                'total_debit'=>number_format($debit/100,2,'.',''),
                'total_credit'=>number_format($credit/100,2,'.',''),
                'updated_by'=>$actorId,'status'=>'pending',
            ]);
            // The table has a UNIQUE(source_table,source_id). Reuse the original
            // approval row, never insert a second row for this voucher.
            DB::table('daily_voucher_approval_events')->insert([
                'approval_id'=>$approval->id,'event'=>'resubmitted',
                'actor_id'=>$actorId,'remarks'=>$audit,'created_at'=>now(),
            ]);
            $approval->forceFill([
                'status'=>'pending','submitted_by'=>$actorId,
                'assigned_approver_id'=>$approver->id,'decided_by'=>null,
                'decided_at'=>null,'rejection_reason'=>null,
            ])->save();
        },3);
    }
}
