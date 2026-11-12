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
                ->where('id',$sourceId)->whereIn('transaction_type',['CR','CP','CCR','CDR'])
                ->lockForUpdate()->first();
            if (!$source) throw new \DomainException('Voucher not found.');
            $isBank=(int)$source->payment_type!==1;
            if ($isBank && !in_array((int)$source->payment_type,[2,3],true)) {
                throw new \DomainException('Unsupported instrument payment method.');
            }
            $approvals=DailyVoucherApproval::query()->where('project_id',$projectId)
                ->where('source_table','customer_ledger')->where('source_id',$sourceId)
                ->lockForUpdate()->first();
            if ($approvals && !in_array($approvals->status,['approved','rejected','posted'],true)) {
                throw new \DomainException('This voucher already has a pending decision.');
            }
            $bankSeries=null;
            if ($isBank) {
                // Only rejected verified BOR/BOP entries are eligible. Posted or
                // approved bank vouchers require a separately audited reversal.
                if (!$approvals || $approvals->status!=='rejected'
                    || !in_array((string)$approvals->voucher_type,['BOR','BOP'],true)
                    || !Schema::hasTable('finance_bank_voucher_allocations')
                    || !Schema::hasTable('project_voucher_display_numbers')) {
                    throw new \DomainException('Only rejected verified BOR/BOP vouchers can be revised in place.');
                }
                $bankSeries=(string)$approvals->voucher_type;
                $expectedSourceType=$bankSeries==='BOR'?'CR':'CP';
                if ((string)$source->transaction_type!==$expectedSourceType
                    || (int)$source->is_active!==0) {
                    throw new \DomainException('Rejected bank voucher identity/posting state changed.');
                }
                $identity=DB::table('project_voucher_display_numbers')
                    ->where('project_id',$projectId)->where('source_kind','customer_ledger')
                    ->where('source_id',$sourceId)->where('series',$bankSeries)
                    ->lockForUpdate()->first();
                $allocation=DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$projectId)->where('voucher_type',$bankSeries)
                    ->where('voucher_id',$sourceId)->lockForUpdate()->first();
                if (!$identity || !$allocation
                    || (Schema::hasTable('finance_bank_voucher_allocation_releases')
                        && DB::table('finance_bank_voucher_allocation_releases')
                            ->where('allocation_id',$allocation?->id)->exists())
                    || (($allocation->source_method==='online'?2:($allocation->source_method==='cheque'?3:0))
                        !==(int)$source->payment_type)
                    || round((float)$allocation->amount,2)!==
                        round(max((float)$source->amount_in,(float)$source->amount_out),2)) {
                    throw new \DomainException('Verified bank allocation/display identity mismatch.');
                }
                if ((string)($data['t_number']??'')!==(string)($source->t_number??'')
                    || (int)($data['bank_id']??0)!==(int)$source->bank_id
                    || substr((string)($data['passing_date']??''),0,10)
                        !==substr((string)($source->passing_date??''),0,10)
                    || (int)$source->payment_type===3 &&
                        trim((string)($data['cheque_purpose']??''))!==trim((string)($source->cheque_purpose??''))) {
                    throw new \DomainException('Cheque/Online reference, bank, passing date and purpose cannot change in this revision.');
                }
                if (round((float)$data['amount'],2)!==round((float)$allocation->amount,2)) {
                    throw new \DomainException('Allocated amount cannot change in this revision. A separately audited allocation adjustment is required.');
                }
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
                ->get(['l.id','l.project_head_subheads_id','l.type','l.voucher_number','l.reference',
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
                'payment_type'=>(int)$source->payment_type,
                'source_reference'=>(string)$source->reference,
                'ledger_reference'=>(string)$leg->first()->reference,
                'ledger_voucher_number'=>(int)$leg->first()->voucher_number,
            ];
            if ($bankSeries) {
                $before=array_merge($before,BankVoucherRevisionEvidence::capture(
                    $source,$bankSeries,$allocation));
                BankVoucherLegIntegrity::assert(
                    $bankSeries,$source,$leg->first(),(float)$allocation->amount);
            }
            $proposed=[
                'date'=>$data['date'],
                'amount_in'=>in_array($source->transaction_type,['CR','CCR'],true)?$amount:'0.00',
                'amount_out'=>in_array($source->transaction_type,['CP','CDR'],true)?$amount:'0.00',
                'description'=>(string)$data['description'],
                'account_id'=>(int)$account->id,
                'customer_id'=>$account->customer_id ?? $source->customer_id,
                'plot_id'=>$account->plot_id ?? $source->plot_id,
            ];
            $attachment=$data['attachment_metadata']??null;
            if ($attachment) {
                if(!VoucherAttachmentRules::supportsSourceType((string)$source->transaction_type) ||
                    !Schema::hasTable('cash_voucher_attachments') ||
                    !Schema::hasColumn('cash_voucher_attachments','status') ||
                    !Schema::hasColumn('cash_voucher_attachments','edit_snapshot_id')) {
                    throw new \DomainException('Cash voucher attachment staging is not ready.');
                }
            }
            $reason=trim((string)$data['revision_reason']);
            if (mb_strlen($reason)<3) throw new \DomainException('Reason for Edit/Revision is required.');
            $workflow=app(DailyVoucherApprovalWorkflow::class);
            $policy=$workflow->policy($projectId,$bankSeries ?? (string)$source->transaction_type);
            if ($isBank && !$policy) {
                // A rejected financial instrument must never be silently
                // reactivated by switching its approval policy OFF.
                throw new \DomainException('Enable the independent BOR/BOP approval policy and assign an authorized approver before resubmitting a rejected bank voucher.');
            }
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
                    if (!$workflow->submitIfRequired($projectId,$bankSeries ?? (string)$source->transaction_type,
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
                if($attachment) $this->recordAttachment($projectId,$sourceId,$userId,$snapshotId,$attachment,'pending');
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
                    'voucher_number'=>$bankSeries
                        ? (string)$identity->display_number
                        : $source->transaction_type.'-'.$source->type_id];
            }
            $this->applyLocked($projectId,$source,$leg->first(),$before,$proposed);
            $snapshotId=DB::table('daily_voucher_edit_snapshots')->insertGetId([
                'project_id'=>$projectId,'source_id'=>$sourceId,'approval_id'=>$approvals?->id,
                'status'=>'applied','before_snapshot'=>json_encode($before,JSON_THROW_ON_ERROR),
                'proposed_snapshot'=>json_encode($proposed,JSON_THROW_ON_ERROR),
                'reason'=>$reason,'submitted_by'=>$userId,'decided_by'=>$userId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            if($attachment) $this->recordAttachment($projectId,$sourceId,$userId,$snapshotId,$attachment,'applied');
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
                'voucher_number'=>$bankSeries
                    ? (string)$identity->display_number
                    : $source->transaction_type.'-'.$source->type_id];
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
            ->first(['l.id','l.project_head_subheads_id','l.voucher_number','l.reference',
                'l.amount_in','l.amount_out','l.is_active']);
        if (!$source || !$leg || (int)$source->type_id!==(int)$before['type_id'] ||
            $source->transaction_type!==$before['type'] ||
            (int)$source->is_active!==(int)$before['active'] ||
            (int)$leg->is_active!==(int)$before['active'] ||
            (int)$leg->project_head_subheads_id!==(int)$before['account_id'] ||
            (int)$source->payment_type!==(int)$before['payment_type'] ||
            (string)$source->reference!==(string)$before['source_reference'] ||
            (string)$leg->reference!==(string)$before['ledger_reference'] ||
            (int)$leg->voucher_number!==(int)$before['ledger_voucher_number'] ||
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
            if (in_array((string)$approval->voucher_type,['BOR','BOP'],true)) {
                $allocation=DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$approval->project_id)
                    ->where('voucher_type',$approval->voucher_type)
                    ->where('voucher_id',$approval->source_id)
                    ->lockForUpdate()->first();
                if (!$allocation) {
                    throw new \DomainException('Bank revision allocation missing at approval.');
                }
                BankVoucherRevisionEvidence::assertUnchanged(
                    $before,$source,$allocation,(string)$approval->voucher_type);
                BankVoucherLegIntegrity::assert(
                    (string)$approval->voucher_type,$source,$leg,(float)$allocation->amount);
            }
            $this->applyLocked((int)$approval->project_id,$source,$leg,$before,$proposed);
        }
        if(Schema::hasTable('cash_voucher_attachments') &&
           Schema::hasColumn('cash_voucher_attachments','edit_snapshot_id') &&
           Schema::hasColumn('cash_voucher_attachments','status')) {
            DB::table('cash_voucher_attachments')
                ->where('project_id',$approval->project_id)
                ->where('customer_ledger_id',$approval->source_id)
                ->where('edit_snapshot_id',$snapshot->id)->where('status','pending')
                ->update(['status'=>$decision==='approved'?'applied':'rejected',
                    'updated_at'=>now()]);
        }
        DB::table('daily_voucher_edit_snapshots')->where('id',$snapshot->id)->update([
            'status'=>$decision==='approved'?'applied':'rejected',
            'decided_by'=>$actorId,'updated_at'=>now(),
        ]);
        return true;
    }

    private function recordAttachment(int $projectId,int $sourceId,int $actorId,
        int $snapshotId,array $file,string $status): void
    {
        DB::table('cash_voucher_attachments')->insert([
            'project_id'=>$projectId,'customer_ledger_id'=>$sourceId,
            'edit_snapshot_id'=>$snapshotId,'status'=>$status,
            'path'=>$file['path'],'disk'=>'local',
            'original_name'=>$file['original_name'],'mime_type'=>$file['mime_type'],
            'size'=>$file['size'],'uploaded_by'=>$actorId,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function applyLocked(int $projectId,object $source,object $leg,array $before,array $next): void
    {
        if (!in_array($source->transaction_type,['CR','CP','CCR','CDR'],true)) {
            throw new \DomainException('Unsupported original voucher accounting type.');
        }
        if ((int)$source->payment_type!==1) {
            // Recheck immutable source allocation and display family under the
            // original transaction lock before activating the edited posting.
            $id=(int)$source->id;
            $identity=Schema::hasTable('project_voucher_display_numbers')
                ? DB::table('project_voucher_display_numbers')
                    ->where('project_id',$projectId)->where('source_kind','customer_ledger')
                    ->where('source_id',$id)->lockForUpdate()->first() : null;
            $series=(string)($identity->series??'');
            $allocation=Schema::hasTable('finance_bank_voucher_allocations')
                ? DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$projectId)->where('voucher_type',$series)
                    ->where('voucher_id',$id)->lockForUpdate()->first() : null;
            if (!in_array($series,['BOR','BOP'],true) || !$allocation
                || (Schema::hasTable('finance_bank_voucher_allocation_releases')
                    && DB::table('finance_bank_voucher_allocation_releases')
                        ->where('allocation_id',$allocation?->id)->exists())
                || ($series==='BOR'?'CR':'CP')!==(string)$source->transaction_type
                || !in_array((int)$source->payment_type,[2,3],true)
                || (int)$source->is_active!==0 || (int)$leg->is_active!==0
                || round((float)$allocation->amount,2)!==
                    round(max((float)$source->amount_in,(float)$source->amount_out),2)
                || round((float)$allocation->amount,2)!==
                    round(max((float)$next['amount_in'],(float)$next['amount_out']),2)) {
                throw new \DomainException('Verified bank revision source/allocation no longer matches.');
            }
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
