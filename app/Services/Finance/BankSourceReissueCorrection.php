<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 04: source correction preview/confirm with immutable voucher history.
 * Zero voucher allocations: edit ORIGINAL source ID in place.
 * One or more historical allocations: archive old source as Withdrawn, safely
 * cancel pending and void posted vouchers, then create a NEW unverified source
 * ID requiring normal bank clearance/confirmation before fresh vouchers.
 * All changes share one project-locked transaction; evidence is retained.
 */
final class BankSourceReissueCorrection
{
    private function table(string $method): string
    {
        return match ($method) {
            'online' => 'finance_online_intakes',
            'cheque' => 'finance_cheque_intakes',
            default => throw new \DomainException('Unsupported correction method.'),
        };
    }

    private function allowed(string $method): array
    {
        return $method === 'online'
            ? ['reference','type','transaction_date','amount','sending_bank','sending_account',
                'receiving_bank','receiving_account','sender_name','receiver_name',
                'purpose','account_mapping_id']
            : ['cheque_number','bank_name','direction','cheque_title','cheque_date','received_date','amount','branch',
                'received_from','purpose','account_mapping_id'];
    }

    private function normalized(string $method, array $changes, object $source, int $projectId): array
    {
        $fields=$this->allowed($method);
        $updates=array_intersect_key($changes,array_flip($fields));
        if (count($updates)!==count($fields)) {
            throw new \DomainException('Provide every editable source field for reviewed correction.');
        }
        foreach ($fields as $field) {
            if ($field==='branch' && $method==='cheque') {
                $updates[$field]=trim((string)($updates[$field]??''));
                continue;
            }
            if (!is_scalar($updates[$field]) || trim((string)$updates[$field])==='') {
                throw new \DomainException('A required correction field is missing: '.$field);
            }
            $updates[$field]=trim((string)$updates[$field]);
        }
        if (!preg_match('/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D',$updates['amount'])
            || (float)$updates['amount']<=0) {
            throw new \DomainException('Invalid corrected amount.');
        }
        [$whole,$fraction]=array_pad(explode('.',$updates['amount'],2),2,'');
        $fraction=str_pad($fraction,2,'0');
        if($whole==='0' && $fraction==='00') throw new \DomainException('Amount must be positive.');
        $updates['amount']=$whole.'.'.$fraction;
        foreach ($method==='online'?['transaction_date']:['cheque_date','received_date'] as $field) {
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$updates[$field]);
            if (!$date || $date->format('Y-m-d')!==$updates[$field]) {
                throw new \DomainException('Invalid source date: '.$field);
            }
        }
        $mapping=(int)$updates['account_mapping_id'];
        if ($mapping<1 || !DB::table('project_head_subheads')
            ->where('id',$mapping)->where('project_id',$projectId)->exists()) {
            throw new \DomainException('Account mapping does not belong to selected project.');
        }
        $updates['account_mapping_id']=$mapping;
        if ($method==='cheque' && Schema::hasColumn('finance_cheque_intakes','customer_id')
            && Schema::hasColumn('project_head_subheads','customer_id')) {
            $updates['customer_id']=DB::table('project_head_subheads')
                ->where('project_id',$projectId)->where('id',$mapping)->value('customer_id');
        }
        return $updates;
    }

    /** Bank evidence is required before a Withdrawn source can be reactivated. */
    private function hasOriginalVerification(int $projectId,string $method,int $sourceId,object $source): bool
    {
        if($method==='online') {
            return Schema::hasTable('finance_online_confirmation_events')
                && DB::table('finance_online_confirmation_events')
                    ->where('project_id',$projectId)->where('online_intake_id',$sourceId)
                    ->where('to_status','confirmed')->where('transaction_type',$source->type)
                    ->exists();
        }
        return Schema::hasTable('finance_cheque_intake_actions')
            && DB::table('finance_cheque_intake_actions')
                ->where('project_id',$projectId)->where('cheque_intake_id',$sourceId)
                ->where('action','cleared')->whereNotNull('verification_confirmed_at')
                ->exists();
    }

    public function preview(int $projectId,string $method,int $sourceId,array $changes): array
    {
        return DB::transaction(fn()=> $this->inspectLocked($projectId,$method,$sourceId,$changes),3);
    }

    private function inspectLocked(int $projectId,string $method,int $sourceId,array $changes): array
    {
        if ($projectId<1 || $sourceId<1) throw new \DomainException('Invalid source/project.');
        $table=$this->table($method);
        foreach (['projects',$table,'project_head_subheads','customer_ledger','ledgers',
            'finance_bank_voucher_allocations','finance_bank_voucher_allocation_releases',
            'daily_voucher_approvals','daily_voucher_edit_snapshots'] as $required) {
            if(!Schema::hasTable($required)) throw new \DomainException('Correction schema missing: '.$required);
        }
        DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
            ?: throw new \DomainException('Project not found.');
        $source=DB::table($table)->where('project_id',$projectId)
            ->where('id',$sourceId)->lockForUpdate()->first();
        if (!$source) throw new \DomainException('Bank source not found in the selected project.');
        if ($method==='cheque' && (int)($source->customer_ledger_id??0)>0) {
            throw new \DomainException('Legacy cheque ledger link requires manual reconciliation.');
        }
        $updated=$this->normalized($method,$changes,$source,$projectId);
        $before=[];
        foreach ($updated as $key=>$value) $before[$key]=$source->{$key}??null;
        $changed=false;
        foreach ($updated as $key=>$value) {
            if ((string)($before[$key]??'')!==(string)$value
                && !($key==='amount' && round((float)$before[$key],2)===round((float)$value,2))) {
                $changed=true;
            }
        }
        $allocations=DB::table('finance_bank_voucher_allocations')
            ->where('project_id',$projectId)->where('source_method',$method)
            ->where('source_id',$sourceId)->orderBy('id')->lockForUpdate()->get();
        $active=[]; $historical=0;
        $ids=[];
        foreach($allocations as $allocation) {
            if (DB::table('finance_bank_voucher_allocation_releases')
                ->where('allocation_id',$allocation->id)->exists()) {
                $historical++;
                continue;
            }
            $id=(int)$allocation->voucher_id;
            $type=(string)$allocation->voucher_type;
            $allocationKey=$type.':'.$id;
            if (in_array($allocationKey,$ids,true)) {
                throw new \DomainException('Duplicate live bank allocation identity.');
            }
            $ids[]=$allocationKey;
            if(in_array($type,['BD','BW','BD_DRAFT','BW_DRAFT',
                TypedPlotReceiptBankCustody::POSTED,TypedPlotReceiptBankCustody::DRAFT],true)){
                $draft=str_ends_with($type,'_DRAFT');
                if(in_array($type,['BD','BW','BD_DRAFT','BW_DRAFT'],true)){
                    $series=substr($type,0,2);
                    $record=$draft
                        ? DB::table('finance_bank_transfer_approval_drafts')
                            ->where('project_id',$projectId)->where('id',$id)
                            ->where('transfer_kind',$series==='BD'?'deposit':'withdrawal')
                            ->lockForUpdate()->first()
                        : DB::table('finance_bank_transfer_sources')
                            ->where('project_id',$projectId)->where('posting_batch_id',$id)
                            ->where('transfer_kind',$series==='BD'?'deposit':'withdrawal')
                            ->lockForUpdate()->first();
                    if(!$record || round((float)$record->amount,2)!==
                        round((float)$allocation->amount,2)
                        || ($draft && (!in_array((string)$record->status,['pending','rejected'],true)
                            || (string)$record->passed_source_method!==$method
                            || (int)$record->passed_source_id!==$sourceId))
                        || (!$draft && ((int)($record->is_active??1)!==1
                            || ($record->deleted_at??null)!==null
                            || ($record->linked_customer_ledger_id??null)!==null
                            || !DB::table('finance_posting_batches')
                                ->where('project_id',$projectId)->where('id',$id)
                                ->where('voucher_series',$series)
                                ->whereNotNull('posted_at')->whereNull('reversal_of_batch_id')->exists()))) {
                        throw new \DomainException('Typed bank transfer allocation changed before reissue.');
                    }
                    $status=$draft?(string)$record->status:'posted';
                    $updatedAt=(string)($record->updated_at??'');
                }else{
                    $record=$draft
                        ? DB::table('finance_plot_approval_drafts')
                            ->where('project_id',$projectId)->where('id',$id)
                            ->where('voucher_type','PPR')->lockForUpdate()->first()
                        : DB::table('finance_plot_receipts')
                            ->where('project_id',$projectId)->where('id',$id)
                            ->lockForUpdate()->first();
                    $payload=$draft&&$record
                        ?json_decode((string)$record->payload_json,true):null;
                    $value=$draft?($payload['amount']??0):($record->amount??0);
                    if(!$record || round((float)$value,2)!==
                        round((float)$allocation->amount,2)
                        || ($draft && (!in_array((string)$record->status,['pending','rejected'],true)
                            || (string)($payload['passed_source_method']??'')!==$method
                            || (int)($payload['passed_source_id']??0)!==$sourceId
                            || ($record->posted_source_id??null)!==null
                            || ($record->posting_batch_id??null)!==null))
                        || (!$draft && ((int)($record->is_active??1)!==1
                            || ($record->deleted_at??null)!==null))) {
                        throw new \DomainException('Typed multi-plot receipt allocation changed before reissue.');
                    }
                    $status=$draft?(string)$record->status:'posted';
                    $updatedAt=(string)($record->updated_at??'');
                }
                $active[]=[
                    'allocation_id'=>(int)$allocation->id,
                    'voucher_id'=>$id,'voucher_type'=>$type,
                    'amount'=>(string)$allocation->amount,
                    'typed_source_status'=>$status,'typed_source_updated'=>$updatedAt,
                    'approval_status'=>$draft?$status:null,
                ];
                continue;
            }
            if(!in_array($type,['CR','DR','BOR','BOP','PPR'],true)) {
                throw new \DomainException('Unknown linked bank allocation family.');
            }
            $voucher=DB::table('customer_ledger')->where('project_id',$projectId)
                ->where('id',$id)->lockForUpdate()->first();
            $legs=DB::table('ledgers as l')->join('project_head_subheads as m',
                'm.id','=','l.project_head_subheads_id')
                ->where('m.project_id',$projectId)->where('l.customer_ledger_id',$id)
                ->lockForUpdate()->get(['l.id','l.type','l.project_head_subheads_id',
                    'l.amount_in','l.amount_out','l.is_active','l.updated_at']);
            $approval=DB::table('daily_voucher_approvals')
                ->where('project_id',$projectId)->where('source_table','customer_ledger')
                ->where('source_id',$id)->lockForUpdate()->first();
            $series=(string)$allocation->voucher_type;
            $bankBalanced=in_array($series,['BOR','BOP'],true)
                && (int)($allocation->bank_account_id??0)>0;
            $standardBalanced=in_array($series,['CR','DR'],true) && $legs->count()===2;
            $expected=($bankBalanced||$standardBalanced)?2:1;
            if (!$voucher || $legs->count()!==$expected
                || DB::table('ledgers')->where('customer_ledger_id',$id)->count()!==$expected
                || (int)$voucher->payment_type!==($method==='online'?2:3)
                || (Schema::hasColumn('customer_ledger','deleted_at') && $voucher->deleted_at!==null)
                || ($approval && ((string)$approval->voucher_type!==$series
                    || !in_array($approval->status,['approved','posted','pending','rejected'],true)))
                || (!$approval && ((int)$voucher->is_active!==1
                    || $legs->contains(fn($leg)=>(int)$leg->is_active!==1)))) {
                throw new \DomainException('Linked voucher posting identity or ledger count invalid.');
            }
            $pendingOrRejected=$approval && in_array((string)$approval->status,['pending','rejected'],true);
            if($pendingOrRejected && ((int)$voucher->is_active!==0
                || $legs->contains(fn($leg)=>(int)$leg->is_active!==0))) {
                throw new \DomainException('Unposted voucher has active ledger rows; manual reconciliation required.');
            }
            if(!$pendingOrRejected && ((int)$voucher->is_active!==1
                || $legs->contains(fn($leg)=>(int)$leg->is_active!==1))) {
                throw new \DomainException('Posted voucher is not active; manual reconciliation required.');
            }
            if($bankBalanced){
                BalancedBankVoucherIntegrity::assert($projectId,$series,$voucher,$allocation,$legs);
            }elseif($standardBalanced){
                BalancedStandardVoucherIntegrity::assert($series,$voucher,$legs);
            }else{
                if((string)$legs->first()->type!==(string)$voucher->transaction_type){
                    throw new \DomainException('Legacy bank voucher ledger type invalid.');
                }
                BankVoucherLegIntegrity::assert($series,$voucher,$legs->first(),
                    (float)$allocation->amount);
            }
            if(round((float)$allocation->amount,2)!==
                round(max((float)$voucher->amount_in,(float)$voucher->amount_out),2)){
                throw new \DomainException('Verified allocation face amount changed.');
            }
            app(VoucherApprovalMutationGuard::class)->assertNoPendingCorrection($projectId,$id);
            if(DB::table('daily_voucher_edit_snapshots')->where('project_id',$projectId)
                ->where('source_id',$id)->where('status','pending')->exists()) {
                throw new \DomainException('Resolve pending linked voucher revision first.');
            }
            $active[]=['allocation_id'=>(int)$allocation->id,'voucher_id'=>$id,
                'voucher_type'=>(string)$allocation->voucher_type,
                'amount'=>(string)$allocation->amount,'leg_id'=>(int)$legs->first()->id,
                'ledger_legs'=>$legs->sortBy('id')->map(fn($leg)=>[
                    'id'=>(int)$leg->id,'account_id'=>(int)$leg->project_head_subheads_id,
                    'amount_in'=>(string)$leg->amount_in,
                    'amount_out'=>(string)$leg->amount_out,
                    'is_active'=>(int)$leg->is_active,
                    'updated_at'=>(string)($leg->updated_at??''),
                ])->values()->all(),
                'approval_status'=>$approval?->status,
                'voucher_updated'=>(string)($voucher->updated_at??''),
                'leg_updated'=>(string)($legs->first()->updated_at??'')];
        }
        // A legacy single-use claim may coexist with partial allocation history.
        // Never advertise that source as reusable until the claim is reconciled.
        $claimTable=$method==='online'?'finance_online_voucher_claims':'finance_cheque_voucher_claims';
        $claimColumn=$method==='online'?'online_intake_id':'cheque_intake_id';
        if(!Schema::hasTable($claimTable) || DB::table($claimTable)
            ->where('project_id',$projectId)->where($claimColumn,$sourceId)->exists()) {
            throw new \DomainException('Unreconciled historical bank claim prevents correction.');
        }
        $linked=$allocations->isNotEmpty();
        if($linked && (string)$source->status==='withdrawn') {
            throw new \DomainException('Original source is already withdrawn. Edit its active replacement instead.');
        }
        if (!$changed && !$linked) {
            throw new \DomainException('No source changes or active linked vouchers to correct.');
        }
        // Every historical allocation makes this a replacement, including
        // already-released and pending/rejected vouchers. Database uniqueness
        // prohibits reusing the archived source's bank reference.
        if($linked){
            if($method==='online'){
                $reference=mb_strtoupper((string)$updated['reference'],'UTF-8');
                if(DB::table($table)->where('project_id',$projectId)
                    ->where('reference_key',$reference)->exists()) {
                    throw new \DomainException('Linked source requires a NEW unique online reference for re-verification.');
                }
            }else{
                $number=strtoupper((string)$updated['cheque_number']);
                if(DB::table($table)->where('project_id',$projectId)
                    ->where('cheque_number',$number)->where('bank_name',$updated['bank_name'])->exists()) {
                    throw new \DomainException('Linked source requires a NEW unique cheque number/bank for re-verification.');
                }
            }
        }
        $state=['project_id'=>$projectId,'method'=>$method,'source_id'=>$sourceId,
            'status'=>(string)$source->status,
            'source_updated'=>(string)($source->updated_at??''),
            'reference'=>$method==='online'?(string)$source->reference:(string)$source->cheque_number,
            'verified_bank'=>$method==='online'?(string)$source->receiving_bank:(string)$source->bank_name,
            'direction'=>$method==='online'?(string)$source->type:(string)$source->direction,
            'before'=>$before,'after'=>$updated,
            'requires_replacement'=>$linked,
            'linked_voucher_history_count'=>$allocations->count(),
            'ready_after_correction'=>!$linked && ($method==='online'
                ? (string)$source->status==='confirmed' &&
                    $updated['reference']===(string)$source->reference && $updated['type']===(string)$source->type
                : (string)$source->status==='cleared' &&
                    $updated['cheque_number']===(string)$source->cheque_number &&
                    $updated['bank_name']===(string)$source->bank_name &&
                    $updated['direction']===(string)$source->direction),
            'result_status'=>$linked ? 'withdrawn' : (string)$source->status,
            'new_status'=>$linked ? ($method==='online'?'pending':($updated['direction']==='out'?'issued':'received')) : (string)$source->status,
            'active_vouchers'=>$active,'historical_released_count'=>$historical];
        $state['fingerprint']=hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
        return $state;
    }

    public function confirm(int $projectId,string $method,int $sourceId,int $actorId,
        string $reason,array $changes,string $fingerprint,?int $submittedBy=null): array
    {
        $reason=trim($reason);
        if($actorId<1 || mb_strlen($reason)<3 || mb_strlen($reason)>500
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            throw new \DomainException('Valid correction actor, reason and reviewed fingerprint required.');
        }
        return DB::transaction(function()use($projectId,$method,$sourceId,$actorId,$reason,
            $changes,$fingerprint,$submittedBy): array {
            $plan=$this->inspectLocked($projectId,$method,$sourceId,$changes);
            if(!hash_equals($plan['fingerprint'],$fingerprint)) {
                throw new \DomainException('Source or linked vouchers changed; review again.');
            }
            $table=$this->table($method);
            if($plan['requires_replacement']){
                $result=app(BankManagementSourceMutation::class)->replace(
                    $projectId,$method,$sourceId,$actorId,$reason,$plan['after']);
            }else{
                $result=app(BankManagementSourceMutation::class)->update(
                    $projectId,$method,$sourceId,$plan['after']);
            }
            DB::table('daily_voucher_edit_snapshots')->insert([
                'project_id'=>$projectId,'source_id'=>$sourceId,'approval_id'=>null,
                'status'=>'bank_source_fix',
                'before_snapshot'=>json_encode(['source_kind'=>$table,'method'=>$method,
                    'source_id'=>$sourceId,'status'=>$plan['status'],
                    'fields'=>$plan['before']],JSON_THROW_ON_ERROR),
                'proposed_snapshot'=>json_encode(['source_kind'=>$table,'method'=>$method,
                    'source_id'=>$sourceId,'status'=>$plan['result_status'],
                    'replacement_source_id'=>$result['replacement_source_id']??null,
                    'fields'=>$plan['after'],'released_vouchers'=>$plan['active_vouchers']],
                    JSON_THROW_ON_ERROR),
                'reason'=>$reason,'submitted_by'=>$submittedBy??$actorId,'decided_by'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return ['source_id'=>$sourceId,'source_method'=>$method,
                'replacement_source_id'=>$result['replacement_source_id']??null,
                'released_vouchers'=>$result['released_count']??0,
                'amount'=>(string)$plan['after']['amount'],
                'status'=>$result['new_status']??$result['status'],
                'ready_for_new_vouchers'=>($method==='online'
                    ? ($result['status']??$result['new_status'])==='confirmed'
                    : ($result['status']??$result['new_status'])==='cleared'),
                'requires_reverification'=>(bool)$plan['requires_replacement']];
        },3);
    }
}
