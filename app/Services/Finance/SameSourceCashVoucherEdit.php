<?php

namespace App\Services\Finance;

use App\Models\DailyVoucherApproval;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same source_id/type_id and ONE accounting leg. Pending edits are proposals:
 * no new voucher, no reversal, and no modification of posted amounts until
 * an authorized approver accepts. All transitions run inside transactions.
 */
final class SameSourceCashVoucherEdit
{
    public function submit(int $projectId, int $sourceId, int $userId, array $data): array
    {
        if (!Schema::hasTable('daily_voucher_edit_snapshots')) {
            throw new \DomainException('Run the voucher edit snapshot migration before editing.');
        }
        return DB::transaction(function () use ($projectId,$sourceId,$userId,$data): array {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first() ?: throw new \DomainException('Project not found.');
            $source=DB::table('customer_ledger')->where('project_id',$projectId)
                ->where('id',$sourceId)->whereIn('transaction_type',['CR','CP'])
                ->lockForUpdate()->first();
            if (!$source) throw new \DomainException('Voucher not found.');
            if ((int)$source->payment_type!==1) {
                throw new \DomainException('Bank/cheque voucher edit requires separate instrument lifecycle verification.');
            }
            $approvals=DailyVoucherApproval::query()->where('project_id',$projectId)
                ->where('source_table','customer_ledger')->where('source_id',$sourceId)
                ->lockForUpdate()->first();
            if ($approvals && !in_array($approvals->status,['approved','rejected','posted'],true)) {
                throw new \DomainException('This voucher already has a pending decision.');
            }
            if (!$approvals && (int)$source->is_active!==1) {
                throw new \DomainException('Inactive untracked voucher cannot be edited.');
            }
            if ($approvals && $approvals->status==='rejected' && (int)$source->is_active!==0) {
                throw new \DomainException('Rejected voucher posting state invalid.');
            }
            if ($approvals && $approvals->status==='approved' && (int)$source->is_active!==1) {
                throw new \DomainException('Approved voucher posting state invalid.');
            }
            if (DB::table('daily_voucher_edit_snapshots')->where('project_id',$projectId)
                ->where('source_id',$sourceId)->where('status','pending')->lockForUpdate()->exists()) {
                throw new \DomainException('This voucher already has a pending edit.');
            }
            app(VoucherApprovalMutationGuard::class)->assertNoPendingCorrection($projectId,$sourceId);
            // Earlier append-only corrections may have already offset this
            // source with active reversal rows. Those require a one-time
            // ledger reconciliation before they can be edited in place.
            $previousCorrections=DB::table('daily_voucher_approval_events')
                ->whereIn('event',['legacy_cash_correction','approved_cash_direct'])
                ->where('remarks','like','%"original_source_id":'.$sourceId.',%')
                ->get(['remarks']);
            foreach ($previousCorrections as $prior) {
                $history=json_decode((string)$prior->remarks,true);
                if (is_array($history) &&
                    (int)($history['original_source_id']??0)===$sourceId) {
                    throw new \DomainException('This voucher has a previous reversal/correction chain. Audit and reconcile that chain before same-ID editing.');
                }
            }
            $leg=DB::table('ledgers as l')
                ->join('project_head_subheads as p','p.id','=','l.project_head_subheads_id')
                ->where('l.customer_ledger_id',$sourceId)->where('l.type',$source->transaction_type)
                ->where('p.project_id',$projectId)->lockForUpdate()
                ->get(['l.id','l.project_head_subheads_id','l.type','l.voucher_number',
                    'l.amount_in','l.amount_out','l.date','l.detail','l.is_active']);
            if ($leg->count()!==1 || (int)$leg->first()->is_active!==(int)$source->is_active ||
                DB::table('ledgers')->where('customer_ledger_id',$sourceId)->count()!==1 ||
                round((float)$leg->first()->amount_in,2)!==round((float)$source->amount_in,2) ||
                round((float)$leg->first()->amount_out,2)!==round((float)$source->amount_out,2)) {
                throw new \DomainException('Single-leg voucher ownership/posting integrity required.');
            }
            $account=ProjectHeadSubhead::availableForPosting()
                ->where('project_id',$projectId)->whereKey((int)$data['account_id'])->firstOrFail();
            $amount=number_format(round((float)$data['amount'],2),2,'.','');
            if ((float)$amount<=0) throw new \DomainException('Invalid amount.');
            $before=[
                'date'=>(string)$source->date,'amount_in'=>(string)$source->amount_in,
                'amount_out'=>(string)$source->amount_out,'description'=>(string)$source->description,
                'account_id'=>(int)$leg->first()->project_head_subheads_id,
                'customer_id'=>$source->customer_id,'plot_id'=>$source->plot_id,
                'active'=>(int)$source->is_active,'type'=>(string)$source->transaction_type,
                'type_id'=>(int)$source->type_id,'leg_id'=>(int)$leg->first()->id,
            ];
            $proposed=[
                'date'=>$data['date'],
                'amount_in'=>$source->transaction_type==='CR'?$amount:'0.00',
                'amount_out'=>$source->transaction_type==='CP'?$amount:'0.00',
                'description'=>(string)$data['description'],
                'account_id'=>(int)$account->id,
                'customer_id'=>$account->customer_id ?? $source->customer_id,
                'plot_id'=>$account->plot_id ?? $source->plot_id,
            ];
            $reason=trim((string)$data['revision_reason']);
            if (mb_strlen($reason)<3) throw new \DomainException('Reason for Edit/Revision is required.');
            $workflow=app(DailyVoucherApprovalWorkflow::class);
            $policy=$workflow->policy($projectId,(string)$source->transaction_type);
            if ($policy) {
                $approver=\App\Models\User::find($policy->approver_user_id);
                $user=\App\Models\User::find($userId);
                if (!VoucherApproverAccess::eligibleForAssignment($approver,$projectId) ||
                    ($policy->approver_user_id===$userId && !VoucherApproverAccess::isSuperAdmin($user))) {
                    throw new \DomainException('Valid independent approver required.');
                }
                if ($approvals) {
                    $previous=(string)$approvals->status;
                    $previousDecidedBy=$approvals->decided_by;
                    $approvals->forceFill([
                        'status'=>'pending','submitted_by'=>$userId,
                        'assigned_approver_id'=>$policy->approver_user_id,
                        'decided_by'=>null,'decided_at'=>null,'rejection_reason'=>null,
                    ])->save();
                    $approval=$approvals;
                } else {
                    $previous='posted';
                    $previousDecidedBy=null;
                    if (!$workflow->submitIfRequired($projectId,(string)$source->transaction_type,
                        'customer_ledger',$sourceId,$userId)) {
                        throw new \DomainException('Approval submission failed.');
                    }
                    $approval=DailyVoucherApproval::where('project_id',$projectId)
                        ->where('source_table','customer_ledger')->where('source_id',$sourceId)->firstOrFail();
                }
                $snapshotId=DB::table('daily_voucher_edit_snapshots')->insertGetId([
                    'project_id'=>$projectId,'source_id'=>$sourceId,'approval_id'=>$approval->id,
                    'status'=>'pending','before_snapshot'=>json_encode($before,JSON_THROW_ON_ERROR),
                    'proposed_snapshot'=>json_encode($proposed,JSON_THROW_ON_ERROR),
                    'reason'=>$reason,'submitted_by'=>$userId,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('daily_voucher_approval_events')->insert([
                    'approval_id'=>$approval->id,'event'=>'same_source_edit',
                    'actor_id'=>$userId,
                    'remarks'=>json_encode([
                        'edit_snapshot_id'=>$snapshotId,'previous_status'=>$previous,
                        'previous_decided_by'=>$previousDecidedBy,
                        'revision_reason'=>$reason,
                    ],JSON_THROW_ON_ERROR),'created_at'=>now(),
                ]);
                return ['pending'=>true,'source_id'=>$sourceId,
                    'voucher_number'=>$source->transaction_type.'-'.$source->type_id];
            }
            $this->applyLocked($projectId,$source,$leg->first(),$before,$proposed);
            $snapshotId=DB::table('daily_voucher_edit_snapshots')->insertGetId([
                'project_id'=>$projectId,'source_id'=>$sourceId,'approval_id'=>$approvals?->id,
                'status'=>'applied','before_snapshot'=>json_encode($before,JSON_THROW_ON_ERROR),
                'proposed_snapshot'=>json_encode($proposed,JSON_THROW_ON_ERROR),
                'reason'=>$reason,'submitted_by'=>$userId,'decided_by'=>$userId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            if ($approvals) {
                if ($approvals->status==='rejected') {
                    $approvals->forceFill([
                        'status'=>'posted','rejection_reason'=>null,
                        'decided_by'=>$userId,'decided_at'=>now(),
                    ])->save();
                }
                DB::table('daily_voucher_approval_events')->insert([
                    'approval_id'=>$approvals->id,'event'=>'same_source_direct',
                    'actor_id'=>$userId,
                    'remarks'=>json_encode(['edit_snapshot_id'=>$snapshotId,'revision_reason'=>$reason],
                        JSON_THROW_ON_ERROR),'created_at'=>now(),
                ]);
            }
            return ['pending'=>false,'source_id'=>$sourceId,
                'voucher_number'=>$source->transaction_type.'-'.$source->type_id];
        },3);
    }

    public function decide(DailyVoucherApproval $approval,int $actorId,string $decision): bool
    {
        $snapshot=DB::table('daily_voucher_edit_snapshots')
            ->where('project_id',$approval->project_id)->where('source_id',$approval->source_id)
            ->where('approval_id',$approval->id)->where('status','pending')
            ->lockForUpdate()->first();
        if (!$snapshot) return false;
        $before=json_decode((string)$snapshot->before_snapshot,true,512,JSON_THROW_ON_ERROR);
        $proposed=json_decode((string)$snapshot->proposed_snapshot,true,512,JSON_THROW_ON_ERROR);
        $source=DB::table('customer_ledger')->where('project_id',$approval->project_id)
            ->where('id',$approval->source_id)->lockForUpdate()->first();
        $leg=DB::table('ledgers as l')->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
            ->where('l.id',$before['leg_id'])->where('l.customer_ledger_id',$approval->source_id)
            ->where('a.project_id',$approval->project_id)->lockForUpdate()
            ->first(['l.id','l.project_head_subheads_id','l.amount_in','l.amount_out','l.is_active']);
        if (!$source || !$leg || (int)$source->type_id!==(int)$before['type_id'] ||
            $source->transaction_type!==$before['type'] ||
            (int)$source->is_active!==(int)$before['active'] ||
            (int)$leg->is_active!==(int)$before['active'] ||
            (int)$leg->project_head_subheads_id!==(int)$before['account_id'] ||
            DB::table('ledgers')->where('customer_ledger_id',$approval->source_id)->count()!==1 ||
            round((float)$leg->amount_in,2)!==round((float)$before['amount_in'],2) ||
            round((float)$leg->amount_out,2)!==round((float)$before['amount_out'],2) ||
            substr((string)$source->date,0,10)!==substr((string)$before['date'],0,10) ||
            (string)$source->description!==(string)$before['description'] ||
            round((float)$source->amount_in,2)!==round((float)$before['amount_in'],2) ||
            round((float)$source->amount_out,2)!==round((float)$before['amount_out'],2)) {
            throw new \DomainException('Voucher changed while approval pending; decision blocked.');
        }
        if ($decision==='approved') {
            $this->applyLocked((int)$approval->project_id,$source,$leg,$before,$proposed);
        }
        DB::table('daily_voucher_edit_snapshots')->where('id',$snapshot->id)->update([
            'status'=>$decision==='approved'?'applied':'rejected',
            'decided_by'=>$actorId,'updated_at'=>now(),
        ]);
        return true;
    }

    private function applyLocked(int $projectId,object $source,object $leg,array $before,array $next): void
    {
        if ((int)$source->payment_type!==1 || !in_array($source->transaction_type,['CR','CP'],true)) {
            throw new \DomainException('Only same-method cash correction is supported.');
        }
        $account=ProjectHeadSubhead::availableForPosting()
            ->where('project_id',$projectId)->whereKey((int)$next['account_id'])->firstOrFail();
        // Controlled direct SQL is intentionally restricted to this locked,
        // auditable two-row transaction; normal model immutability still guards
        // every other writer. No new customer_ledger/ledger rows or numbers.
        $fields=[
            'date'=>$next['date'],'amount_in'=>$next['amount_in'],
            'amount_out'=>$next['amount_out'],'description'=>$next['description'],
            'customer_id'=>$next['customer_id'],'plot_id'=>$next['plot_id'],
            'is_active'=>1,'is_approve'=>1,'updated_at'=>now(),
        ];
        DB::table('customer_ledger')->where('id',$source->id)
            ->where('project_id',$projectId)->update($fields);
        DB::table('ledgers')->where('id',$leg->id)
            ->where('customer_ledger_id',$source->id)
            ->update([
                'project_head_subheads_id'=>$account->id,'date'=>$next['date'],
                'amount_in'=>$next['amount_in'],'amount_out'=>$next['amount_out'],
                'detail'=>$next['description'],'is_active'=>1,'updated_at'=>now(),
            ]);
    }
}
