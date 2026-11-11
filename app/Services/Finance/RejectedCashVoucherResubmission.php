<?php
namespace App\Services\Finance;

use App\Models\CustomerLedger;
use App\Models\DailyVoucherApproval;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RejectedCashVoucherResubmission
{
    public function resubmit(int $projectId, int $sourceId, int $actorId, array $data): array
    {
        return DB::transaction(function () use ($projectId, $sourceId, $actorId, $data): array {
            $old = CustomerLedger::query()->where('project_id', $projectId)
                ->whereKey($sourceId)->whereIn('transaction_type', ['CR','CP'])
                ->lockForUpdate()->firstOrFail();
            if (!in_array((int)$old->payment_type,[1,2,3],true))
                throw new \DomainException('Unsupported cash voucher method.');
            $method=(int)$old->payment_type;
            if ($method !== 1) {
                if (!in_array($old->passing_status === null ? 0 : (int)$old->passing_status,[0],true)
                    || $old->bank_post_at !== null)
                    throw new \DomainException('Cleared, returned, bounced or bank-posted voucher cannot be revised.');
                $historyTable=$method===2?'finance_online_lifecycle_events':'finance_cheque_lifecycle_events';
                if (\Illuminate\Support\Facades\Schema::hasTable($historyTable)) {
                    $events=DB::table($historyTable)->where('project_id',$projectId)
                        ->where('customer_ledger_id',$sourceId)->pluck('event_type');
                    $allowed=$method===2?['captured']:['received'];
                    if ($events->contains(fn($event)=>!in_array((string)$event,$allowed,true)))
                        throw new \DomainException('Voucher already has bank/cheque lifecycle decisions; revision blocked.');
                }
                if (DB::table('customer_ledger')->where('project_id',$projectId)
                    ->where('id','<>',$sourceId)->where('reference',$old->reference)
                    ->whereIn('transaction_type',['CR','CP','PPR'])->exists()) {
                    throw new \DomainException('Ambiguous bank source reference. Revision blocked.');
                }
            }
            $actor=User::query()->findOrFail($actorId);
            $approval=DailyVoucherApproval::query()->where('project_id',$projectId)
                ->where('source_table','customer_ledger')->where('source_id',$sourceId)
                ->where('voucher_type',$old->transaction_type)
                ->lockForUpdate()->firstOrFail();
            $approvedCorrection = $approval->status === 'approved';
            if (!in_array($approval->status, ['rejected','approved'], true))
                throw new \DomainException('Voucher must be approved or rejected before revision.');
            if ($approvedCorrection && ($method !== 1 || (int)$old->is_active !== 1))
                throw new \DomainException('Approved bank/cheque vouchers need a cleared-source reversal before correction.');
            if (!$approvedCorrection && (int)$old->is_active !== 0)
                throw new \DomainException('Rejected voucher must be inactive before resubmission.');
            if (!VoucherApproverAccess::isSuperAdmin($actor) &&
                ((int)$approval->submitted_by !== $actorId ||
                 (int)$actor->project_id !== $projectId))
                throw new \DomainException('Revision is restricted to original submitter or Super Admin.');
            if (DB::table('daily_voucher_approval_events')
                ->where('approval_id',$approval->id)->where('event','revised_to_new_source')
                ->exists())
                throw new \DomainException('Voucher has already been revised.');
            $legs=Ledger::query()->withoutGlobalScopes()
                ->where('customer_ledger_id',$old->id)->where('type',$old->transaction_type)
                ->lockForUpdate()->get();
            if ($legs->count() !== 1 || (int)$legs->first()->is_active !== ($approvedCorrection ? 1 : 0))
                throw new \DomainException('Unsafe source ledger provenance. Revision blocked.');
            $account=ProjectHeadSubhead::availableForPosting()
                ->where('project_id',$projectId)->whereKey((int)$data['account_id'])
                ->firstOrFail();
            $workflow=app(DailyVoucherApprovalWorkflow::class);
            $policy=$workflow->policy($projectId,$old->transaction_type);
            $approver=$policy?->approver_user_id ? User::query()->find($policy->approver_user_id) : null;
            if (!$policy || !VoucherApproverAccess::eligibleForAssignment($approver,$projectId))
                throw new \DomainException('Active authorized approver is required.');
            if ((int)$approver->id === $actorId && !VoucherApproverAccess::isSuperAdmin($actor))
                throw new \DomainException('Submitter cannot be their own approver.');
            if ($method !== 1) {
                foreach(['t_number','bank_id','passing_date'] as $field) {
                    if (empty($data[$field]))
                        throw new \DomainException('Bank/cheque fields are required.');
                }
                if ($method===3 && trim((string)($data['cheque_purpose']??''))==='')
                    throw new \DomainException('Cheque purpose is required.');
                if (DB::table('customer_ledger')->where('project_id',$projectId)
                    ->where('id','<>',$sourceId)->where('payment_type',$method)
                    ->where('t_number',$data['t_number'])->where('bank_id',$data['bank_id'])
                    ->whereIn('transaction_type',['CR','CP'])->where('is_active',1)->exists())
                    throw new \DomainException('This bank instrument is already used in an active voucher.');
            }
            $cents=(int)round((float)$data['amount']*100);
            if ($cents <= 0)throw new \DomainException('Amount must be positive.');
            $amount=number_format($cents/100,2,'.','');
            $type=$old->transaction_type;
            $number=app(ProjectVoucherAllocator::class)->next($projectId,$type);
            $reference='REV-'.$sourceId.'-'.$number;
            $new=CustomerLedger::create([
                'transaction_type'=>$type,'type_id'=>$number,'reference'=>$reference,
                'project_id'=>$projectId,'customer_id'=>$account->customer_id,
                'plot_id'=>$account->plot_id,'amount_in'=>$type==='CR'?$amount:0,
                'amount_out'=>$type==='CP'?$amount:0,'description'=>$data['description'],
                'date'=>$data['date'],'payment_type'=>$method,
                't_number'=>$method===1?null:$data['t_number'],
                'bank_id'=>$method===1?null:(int)$data['bank_id'],
                'passing_date'=>$method===1?null:$data['passing_date'],
                'passing_status'=>$method===1?null:0,
                'cheque_purpose'=>$method===3?$data['cheque_purpose']:null,
                'is_active'=>0,'is_approve'=>0,
            ]);
            $series=app(ProjectVoucherDisplayIdentity::class)
                ->series($projectId,'customer_ledger',$sourceId) ?: ($type==='CR'?'CCR':'CDR');
            $allowedSeries=$method===1?['CR','DR','CP','CCR','CDR']:($type==='CR'?['CR','BOR','CHR']:['DR','CP','BOP','CHP']);
            if (!in_array($series,$allowedSeries,true))
                throw new \DomainException('Voucher series does not match bank/payment method.');
            $display=app(ProjectVoucherDisplayIdentity::class)
                ->assign($projectId,'customer_ledger',(int)$new->id,$series,$data['date']);
            $ledger=Ledger::create([
                'customer_ledger_id'=>$new->id,'voucher_number'=>$number,
                'type'=>$type,'type_id'=>$number,
                'project_head_subheads_id'=>$account->id,'reference'=>$reference,
                'amount_in'=>$type==='CR'?$amount:0,'amount_out'=>$type==='CP'?$amount:0,
                'date'=>$data['date'],'detail'=>$data['description'],
                'create_by'=>$actorId,'is_active'=>0,'status'=>0,
            ]);
            if (!$workflow->submitIfRequired($projectId,$type,'customer_ledger',(int)$new->id,$actorId))
                throw new \DomainException('Voucher approval policy disabled.');
            $newApproval=DailyVoucherApproval::query()->where('project_id',$projectId)
                ->where('source_table','customer_ledger')->where('source_id',$new->id)
                ->firstOrFail();
            foreach ([
                [$approval->id,'revised_to_new_source',[
                    'new_source_id'=>$new->id,'new_approval_id'=>$newApproval->id,
                    'previous_rejection_reason'=>$approval->rejection_reason,
                    'original_status'=>$approval->status,
                    'revision_reason'=>$data['revision_reason'],
                    'original_account_id'=>$legs->first()->project_head_subheads_id,
                    'original_amount_in'=>$old->amount_in,'original_amount_out'=>$old->amount_out,
                    'method'=>$method,'original_instrument'=>$old->t_number,
                    'new_instrument'=>$method===1?null:$data['t_number'],
                    'old_bank_id'=>$old->bank_id,'new_bank_id'=>$method===1?null:$data['bank_id'],
                ]],
                [$newApproval->id,$approvedCorrection ? 'revision_from_approved' : 'revision_from_rejected',[
                    'old_source_id'=>$sourceId,'old_approval_id'=>$approval->id,
                    'revision_reason'=>$data['revision_reason'],
                ]],
            ] as [$approvalId,$event,$context]) {
                DB::table('daily_voucher_approval_events')->insert([
                    'approval_id'=>$approvalId,'event'=>$event,'actor_id'=>$actorId,
                    'remarks'=>json_encode($context,JSON_THROW_ON_ERROR),'created_at'=>now(),
                ]);
            }
            return ['source_id'=>(int)$new->id,'display'=>$display??$type.'-'.$number,
                'original_status'=>$approval->status];
        },3);
    }
}
