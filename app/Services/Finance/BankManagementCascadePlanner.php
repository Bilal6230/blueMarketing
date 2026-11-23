<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 04: read-only, fail-closed dependency graph before changing a
 * confirmed Online or cleared Cheque source. Does not edit source/postings.
 * All links are from immutable allocation IDs; never infer by voucher number.
 */
final class BankManagementCascadePlanner
{
    public function inspect(int $projectId, string $method, int $sourceId): array
    {
        if ($projectId <= 0 || $sourceId <= 0 || !in_array($method, ['online', 'cheque'], true)) {
            throw new \InvalidArgumentException('Invalid project or bank management source.');
        }
        $table = $method === 'online' ? 'finance_online_intakes' : 'finance_cheque_intakes';
        $source = DB::table($table)->where('project_id', $projectId)->where('id', $sourceId)->first();
        if (!$source) throw new \DomainException('BANK_MANAGEMENT_SOURCE_NOT_FOUND');
        foreach (['finance_bank_voucher_allocations', 'finance_bank_voucher_allocation_releases',
            'customer_ledger', 'ledgers', 'project_head_subheads'] as $required) {
            if (!Schema::hasTable($required)) {
                return [
                    'linked' => false, 'can_edit_directly' => false, 'can_delete_directly' => false,
                    'requires_cascade' => false, 'blocked' => true,
                    'reason' => 'Required allocation/ledger schema is missing.',
                    'allocations' => [],
                ];
            }
        }

        $allocations = DB::table('finance_bank_voucher_allocations as a')
            ->leftJoin('finance_bank_voucher_allocation_releases as r', function ($join) use ($projectId) {
                $join->on('r.allocation_id', '=', 'a.id')->where('r.project_id', '=', $projectId);
            })
            ->where('a.project_id', $projectId)
            ->where('a.source_method', $method)->where('a.source_id', $sourceId)
            ->orderBy('a.id')
            ->get(['a.id', 'a.voucher_id', 'a.voucher_type', 'a.amount',
                'r.id as release_id', 'r.action as release_action',
                Schema::hasColumn('finance_bank_voucher_allocations','bank_account_id')
                    ? 'a.bank_account_id' : DB::raw('NULL AS bank_account_id')]);

        $blocked = false;
        $details = [];
        foreach ($allocations as $allocation) {
            $type=(string)$allocation->voucher_type;
            if(in_array($type,['BD','BW','BD_DRAFT','BW_DRAFT'],true)){
                $draft=str_ends_with($type,'_DRAFT');
                $series=$draft?substr($type,0,2):$type;
                $record=$draft
                    ? DB::table('finance_bank_transfer_approval_drafts')
                        ->where('project_id',$projectId)->where('id',$allocation->voucher_id)
                        ->where('transfer_kind',$series==='BD'?'deposit':'withdrawal')->first()
                    : DB::table('finance_bank_transfer_sources')
                        ->where('project_id',$projectId)->where('posting_batch_id',$allocation->voucher_id)
                        ->where('transfer_kind',$series==='BD'?'deposit':'withdrawal')->first();
                $unreleased=$allocation->release_id===null;
                $matches=(!$unreleased)||($record!==null
                    && round((float)$record->amount,2)===round((float)$allocation->amount,2)
                    && ($draft
                        ? in_array((string)$record->status,['pending','rejected'],true)
                            && (string)$record->passed_source_method===$method
                            && (int)$record->passed_source_id===$sourceId
                        : (int)($record->is_active??1)===1
                            && ($record->deleted_at??null)===null));
                $pendingApproval=$draft&&$unreleased&&$record!==null
                    && (string)$record->status==='pending';
                $unsafe=!$matches||$unreleased;
                $blocked=$blocked||$unsafe;
                $details[]=[
                    'allocation_id'=>(int)$allocation->id,
                    'voucher_type'=>$type,'voucher_id'=>(int)$allocation->voucher_id,
                    'amount'=>(string)$allocation->amount,
                    'released'=>!$unreleased,'release_action'=>$allocation->release_action,
                    'verified'=>$matches,'pending_approval'=>$pendingApproval,
                    'active_ledger'=>!$draft&&$unreleased,
                ];
                continue;
            }
            $voucher = DB::table('customer_ledger')
                ->where('project_id', $projectId)->where('id', $allocation->voucher_id)
                ->first(['id', 'transaction_type', 'type_id', 'is_active', 'is_approve',
                    'payment_type','amount_in','amount_out']);
            $legs = DB::table('ledgers as l')
                ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
                ->where('a.project_id', $projectId)
                ->where('l.customer_ledger_id', $allocation->voucher_id)
                ->get(['l.id', 'l.type', 'l.project_head_subheads_id',
                    'l.is_active', 'l.amount_in', 'l.amount_out']);

            $allowedSourceTypes = match ((string)$allocation->voucher_type) {
                'CR' => ['CR'], 'DR' => ['DR','CP'],
                'BOR' => ['BOR','CR'], 'BOP' => ['BOP','CP'],
                'PPR' => ['PPR'],
                default => [],
            };
            $balanced=in_array((string)$allocation->voucher_type,['BOR','BOP'],true)
                && (int)($allocation->bank_account_id??0)>0;
            $matches = $voucher !== null
                && in_array((string)$voucher->transaction_type, $allowedSourceTypes, true)
                && $legs->count() === ($balanced?2:1)
                && (int)$voucher->payment_type === ($method === 'online' ? 2 : 3);
            if ($matches) {
                try {
                    if($balanced){
                        BalancedBankVoucherIntegrity::assert($projectId,
                            (string)$allocation->voucher_type,$voucher,$allocation,$legs);
                    }else{
                        if((string)$legs->first()->type!==(string)$voucher->transaction_type){
                            throw new \DomainException('Legacy bank source type mismatch.');
                        }
                        BankVoucherLegIntegrity::assert((string)$allocation->voucher_type,
                            $voucher,$legs->first(),(float)$allocation->amount);
                    }
                } catch (\DomainException $e) {
                    $matches = false;
                }
            }
            $pendingApproval = Schema::hasTable('daily_voucher_approvals')
                && DB::table('daily_voucher_approvals')
                    ->where('project_id', $projectId)
                    ->where('source_table', 'customer_ledger')
                    ->where('source_id', $allocation->voucher_id)
                    ->where('status', 'pending')->exists();
            $unreleased = $allocation->release_id === null;
            $unsafe = !$matches || $pendingApproval
                || ($unreleased && ((int) $voucher->is_active === 1 || (int) $legs->contains(fn($leg)=>(int)$leg->is_active===1)));
            $blocked = $blocked || $unsafe;
            $details[] = [
                'allocation_id' => (int) $allocation->id,
                'voucher_type' => (string) $allocation->voucher_type,
                'voucher_id' => (int) $allocation->voucher_id,
                'amount' => (string) $allocation->amount,
                'released' => !$unreleased,
                'release_action' => $allocation->release_action,
                'verified' => $matches,
                'pending_approval' => $pendingApproval,
                'active_ledger' => $legs->contains(fn ($leg) => (int) $leg->is_active === 1),
            ];
        }
        $linked = $allocations->isNotEmpty();
        $terminal = $method === 'online'
            ? (string) $source->status === 'confirmed'
            : in_array((string) $source->status, ['cleared', 'returned', 'rejected', 'withdrawn'], true);
        return [
            'linked' => $linked,
            'can_edit_directly' => !$linked && !$terminal,
            'can_delete_directly' => !$linked && !$terminal,
            'requires_cascade' => $linked,
            'blocked' => $blocked || $terminal,
            'reason' => $linked
                ? 'Linked allocations must be reviewed and corrected transactionally before changing their management source.'
                : ($terminal
                    ? 'Verified or terminal bank management records require a controlled correction.'
                    : 'No linked voucher allocations.'),
            'allocations' => $details,
        ];
    }
}
