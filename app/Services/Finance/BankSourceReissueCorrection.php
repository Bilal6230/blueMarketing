<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 04: correct a verified bank management source IN PLACE while
 * retiring ALL active linked vouchers. New vouchers are subsequently created
 * using the existing partial allocation writer and fresh voucher identities.
 *
 * Original confirmation evidence, released allocation rows and voucher
 * history are never removed. All operations are project-locked and atomic.
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
            ? ['transaction_date','amount','sending_bank','sending_account',
                'receiving_bank','receiving_account','sender_name','receiver_name',
                'purpose','account_mapping_id']
            : ['cheque_title','cheque_date','received_date','amount','branch',
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
        // Bank-confirmed instrument identity and direction cannot silently
        // change as an ordinary correction; use genuine replacement instead.
        foreach ($method==='online'?['reference','type']:['cheque_number','bank_name','direction'] as $immutable) {
            if (array_key_exists($immutable,$changes)
                && (string)$changes[$immutable] !== (string)$source->{$immutable}) {
                throw new \DomainException($immutable.' is verified bank identity; use Replace & Reverify.');
            }
        }
        return $updates;
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
        if (!$source || (string)$source->status!==($method==='online'?'confirmed':'cleared')) {
            throw new \DomainException('Only confirmed/cleared sources can be corrected.');
        }
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
            if (in_array($id,$ids,true)) throw new \DomainException('Duplicate live bank allocation.');
            $ids[]=$id;
            $voucher=DB::table('customer_ledger')->where('project_id',$projectId)
                ->where('id',$id)->lockForUpdate()->first();
            $legs=DB::table('ledgers as l')->join('project_head_subheads as m',
                'm.id','=','l.project_head_subheads_id')
                ->where('m.project_id',$projectId)->where('l.customer_ledger_id',$id)
                ->lockForUpdate()->get(['l.id','l.type','l.amount_in','l.amount_out','l.is_active']);
            $approval=DB::table('daily_voucher_approvals')
                ->where('project_id',$projectId)->where('source_table','customer_ledger')
                ->where('source_id',$id)->lockForUpdate()->first();
            if (!$voucher || $legs->count()!==1
                || DB::table('ledgers')->where('customer_ledger_id',$id)->count()!==1
                || (int)$voucher->is_active!==1 || (int)$legs->first()->is_active!==1
                || (int)$voucher->payment_type!==($method==='online'?2:3)
                || (Schema::hasColumn('customer_ledger','deleted_at') && $voucher->deleted_at!==null)
                || ($approval && ((string)$approval->voucher_type!==(string)$allocation->voucher_type
                    || !in_array($approval->status,['approved','posted'],true)))) {
                throw new \DomainException('Linked voucher is not a safely posted single-leg entry.');
            }
            BankVoucherLegIntegrity::assert((string)$allocation->voucher_type,
                $voucher,$legs->first(),(float)$allocation->amount);
            app(VoucherApprovalMutationGuard::class)->assertNoPendingCorrection($projectId,$id);
            if(DB::table('daily_voucher_edit_snapshots')->where('project_id',$projectId)
                ->where('source_id',$id)->where('status','pending')->exists()) {
                throw new \DomainException('Resolve pending linked voucher revision first.');
            }
            $active[]=['allocation_id'=>(int)$allocation->id,'voucher_id'=>$id,
                'voucher_type'=>(string)$allocation->voucher_type,
                'amount'=>(string)$allocation->amount,'leg_id'=>(int)$legs->first()->id,
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
        if (!$changed && count($active)===0) {
            throw new \DomainException('No source changes or active linked vouchers to correct.');
        }
        $state=['project_id'=>$projectId,'method'=>$method,'source_id'=>$sourceId,
            'status'=>(string)$source->status,
            'source_updated'=>(string)($source->updated_at??''),
            'reference'=>$method==='online'?(string)$source->reference:(string)$source->cheque_number,
            'verified_bank'=>$method==='online'?(string)$source->receiving_bank:(string)$source->bank_name,
            'direction'=>$method==='online'?(string)$source->type:(string)$source->direction,
            'before'=>$before,'after'=>$updated,
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
            foreach($plan['active_vouchers'] as $item) {
                app(BankVoucherAllocationRelease::class)->release($projectId,
                    $item['voucher_id'],$actorId,'Source correction: '.$reason);
            }
            $table=$this->table($method);
            DB::table($table)->where('project_id',$projectId)->where('id',$sourceId)
                ->update($plan['after']+['updated_at'=>now()]);
            DB::table('daily_voucher_edit_snapshots')->insert([
                'project_id'=>$projectId,'source_id'=>$sourceId,'approval_id'=>null,
                'status'=>'bank_source_fix',
                'before_snapshot'=>json_encode(['source_kind'=>$table,'method'=>$method,
                    'source_id'=>$sourceId,'fields'=>$plan['before']],JSON_THROW_ON_ERROR),
                'proposed_snapshot'=>json_encode(['source_kind'=>$table,'method'=>$method,
                    'source_id'=>$sourceId,'fields'=>$plan['after'],'released_vouchers'=>$plan['active_vouchers']],
                    JSON_THROW_ON_ERROR),
                'reason'=>$reason,'submitted_by'=>$submittedBy??$actorId,'decided_by'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return ['source_id'=>$sourceId,'source_method'=>$method,
                'released_vouchers'=>count($plan['active_vouchers']),
                'amount'=>(string)$plan['after']['amount'],'status'=>$method==='online'?'confirmed':'cleared',
                'ready_for_new_vouchers'=>true];
        },3);
    }
}
