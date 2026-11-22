<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 04 management record correction. All mutations are serialized
 * by the selected project lock; verified voucher allocations are never erased.
 */
final class BankManagementSourceMutation
{
    private function table(string $method): string
    {
        return match ($method) {
            'online' => 'finance_online_intakes',
            'cheque' => 'finance_cheque_intakes',
            default => throw new \InvalidArgumentException('Unsupported bank management method.'),
        };
    }

    public function update(int $projectId, string $method, int $sourceId, array $changes): array
    {
        $table = $this->table($method);
        return DB::transaction(function () use ($projectId, $method, $sourceId, $changes, $table): array {
            $this->lockProject($projectId);
            $source = DB::table($table)->where('project_id', $projectId)
                ->where('id', $sourceId)->lockForUpdate()->first();
            if (!$source) throw new \DomainException('BANK_MANAGEMENT_SOURCE_NOT_FOUND');
            $this->assertUnlinkedAndEditable($projectId, $method, $sourceId, $source);
            $updates = array_intersect_key($changes, array_flip($method === 'online'
                ? ['transaction_date','amount','type','sending_bank','sending_account',
                    'receiving_bank','receiving_account','sender_name','receiver_name',
                    'purpose','account_mapping_id','reference']
                : ['cheque_date','received_date','amount','direction','cheque_number',
                    'bank_name','branch','received_from','purpose','cheque_title',
                    'account_mapping_id']));
            if (!$updates) throw new \DomainException('No management fields provided.');
            if ($method === 'online' && isset($updates['reference'])) {
                $updates['reference'] = trim((string) $updates['reference']);
                $updates['reference_key'] = mb_strtoupper($updates['reference'], 'UTF-8');
                $exists = DB::table($table)->where('project_id', $projectId)
                    ->where('reference_key', $updates['reference_key'])
                    ->where('id', '<>', $sourceId)->exists();
                if ($exists) throw new \DomainException('Online reference already exists in this project.');
            }
            if ($method === 'cheque' && (isset($updates['cheque_number']) || isset($updates['bank_name']))) {
                $number = strtoupper(trim((string) ($updates['cheque_number'] ?? $source->cheque_number)));
                $bank = trim((string) ($updates['bank_name'] ?? $source->bank_name));
                if (DB::table($table)->where('project_id', $projectId)
                    ->where('bank_name', $bank)->where('cheque_number', $number)
                    ->where('id', '<>', $sourceId)->exists()) {
                    throw new \DomainException('Cheque and bank reference already exists in this project.');
                }
                $updates['cheque_number'] = $number;
                $updates['bank_name'] = $bank;
            }
            if (isset($updates['account_mapping_id'])) {
                $mapping = DB::table('project_head_subheads')->where('project_id', $projectId)
                    ->where('id', (int) $updates['account_mapping_id'])->exists();
                if (!$mapping) throw new \DomainException('Account mapping belongs to another project.');
            }
            $updates['updated_at'] = now();
            DB::table($table)->where('project_id', $projectId)->where('id', $sourceId)
                ->update($updates);
            return ['id'=>$sourceId,'method'=>$method,'status'=>(string)$source->status,
                'changed_fields'=>array_keys($changes)];
        }, 3);
    }

    public function withdraw(int $projectId, string $method, int $sourceId, int $actorId, string $reason): array
    {
        $table = $this->table($method);
        $reason = trim($reason);
        if ($actorId < 1 || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new \DomainException('A reason of 3–500 characters and authorized actor are required.');
        }
        return DB::transaction(function () use ($projectId, $method, $sourceId, $actorId, $reason, $table): array {
            $this->lockProject($projectId);
            $source = DB::table($table)->where('project_id', $projectId)
                ->where('id', $sourceId)->lockForUpdate()->first();
            if (!$source) throw new \DomainException('BANK_MANAGEMENT_SOURCE_NOT_FOUND');
            if ((string)$source->status === 'withdrawn') {
                throw new \DomainException('This bank management source was already withdrawn.');
            }
            foreach (['finance_bank_voucher_allocations','finance_bank_voucher_allocation_releases'] as $required) {
                if (!Schema::hasTable($required)) throw new \DomainException('Missing bank allocation evidence.');
            }
            $allocations = DB::table('finance_bank_voucher_allocations')
                ->where('project_id', $projectId)->where('source_method', $method)
                ->where('source_id', $sourceId)->orderBy('id')
                ->lockForUpdate()->get(['id','voucher_id','voucher_type']);
            $releases = [];
            if ($allocations->isEmpty()) {
                $this->assertUnlinkedAndEditable($projectId, $method, $sourceId, $source);
            } else {
                // Historical fully released allocations remain immutable.
                foreach ($allocations as $allocation) {
                    if (DB::table('finance_bank_voucher_allocation_releases')
                        ->where('project_id', $projectId)->where('allocation_id', $allocation->id)->exists()) {
                        continue;
                    }
                    $releases[] = app(BankVoucherAllocationRelease::class)->release(
                        $projectId, (int)$allocation->voucher_id, $actorId, $reason
                    );
                }
                // Refuse any residual live claim after cascade.
                $open = DB::table('finance_bank_voucher_allocations as a')
                    ->where('a.project_id',$projectId)->where('a.source_method',$method)
                    ->where('a.source_id',$sourceId)
                    ->whereNotExists(function($q){
                        $q->selectRaw('1')->from('finance_bank_voucher_allocation_releases as r')
                          ->whereColumn('r.allocation_id','a.id');
                    })->exists();
                if ($open) throw new \DomainException('Active linked allocation remains after cascade.');
            }
            if ($method === 'online') {
                DB::table($table)->where('project_id',$projectId)->where('id',$sourceId)
                    ->update(['status'=>'withdrawn',
                        'remarks'=>trim((string)($source->remarks ?? '') . "\nWithdrawn by #$actorId: $reason"),
                        'updated_at'=>now()]);
            } else {
                // Keep cheque verification and historic bank documents intact.
                DB::table($table)->where('project_id',$projectId)->where('id',$sourceId)
                    ->update(['status'=>'withdrawn','updated_at'=>now()]);
                if (Schema::hasTable('finance_cheque_intake_actions')) {
                    DB::table('finance_cheque_intake_actions')->insert([
                        'project_id'=>$projectId,'cheque_intake_id'=>$sourceId,
                        'action'=>'withdrawn','from_status'=>(string)$source->status,
                        'to_status'=>'withdrawn','action_date'=>now()->toDateString(),
                        'reason'=>$reason,'actor_id'=>$actorId,'created_at'=>now(),
                    ]);
                }
            }
            return ['id'=>$sourceId,'status'=>'withdrawn','released_vouchers'=>$releases,
                'released_count'=>count($releases)];
        }, 3);
    }

    private function lockProject(int $projectId): void
    {
        if ($projectId < 1 || !DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()) {
            throw new \DomainException('Selected project not found.');
        }
    }

    private function assertUnlinkedAndEditable(int $projectId, string $method, int $id, object $source): void
    {
        $editable = $method === 'online'
            ? (string)$source->status === 'pending'
            : in_array((string)$source->status, ['received','issued'],true);
        if (!$editable) throw new \DomainException('Verified or terminal source requires linked correction; direct edit/delete is blocked.');
        if (!Schema::hasTable('finance_bank_voucher_allocations')) {
            throw new \DomainException('Allocation evidence is unavailable.');
        }
        if (DB::table('finance_bank_voucher_allocations')->where('project_id',$projectId)
            ->where('source_method',$method)->where('source_id',$id)->exists()) {
            throw new \DomainException('Linked source cannot be edited directly.');
        }
        $claimTable = $method === 'online'?'finance_online_voucher_claims':'finance_cheque_voucher_claims';
        $claimColumn = $method === 'online'?'online_intake_id':'cheque_intake_id';
        if (!Schema::hasTable($claimTable) || DB::table($claimTable)
            ->where('project_id',$projectId)->where($claimColumn,$id)->exists()) {
            throw new \DomainException('Historical claim exists or claim evidence unavailable.');
        }
        $eventTable = $method === 'online'?'finance_online_confirmation_events':'finance_cheque_intake_actions';
        $eventColumn = $method === 'online'?'online_intake_id':'cheque_intake_id';
        if (!Schema::hasTable($eventTable) || DB::table($eventTable)->where('project_id',$projectId)
            ->where($eventColumn,$id)->exists()) {
            throw new \DomainException('Historical bank action exists or action evidence unavailable.');
        }
    }
}
