<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 04 / Batch 03: same-ID review and atomic correction of
 * verified bank-source amounts and their exact allocated voucher legs.
 *
 * The dedicated permission-guarded controller exposes explicit preview/confirm.
 * This service deliberately never changes bank references or silently
 * reallocates voucher amounts, account identities or posted approvals.
 */
final class BankLinkedCorrection
{
    private function sourceTable(string $method): string
    {
        return match ($method) {
            'online' => 'finance_online_intakes',
            'cheque' => 'finance_cheque_intakes',
            default => throw new \DomainException('Unsupported bank method.'),
        };
    }

    private function money(mixed $value): string
    {
        $value=trim((string)$value);
        if (!preg_match('/^(?:0|[1-9][0-9]{0,12})(?:\\.[0-9]{1,2})?$/D',$value)) {
            throw new \DomainException('Enter a positive amount with no more than two decimals.');
        }
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        $cents=((int)$whole)*100+(int)str_pad($fraction,2,'0');
        if($cents<1 || $cents>999999999999999) {
            throw new \DomainException('Amount is outside the permitted range.');
        }
        return $whole.'.'.str_pad($fraction,2,'0');
    }

    private function cents(mixed $value): int
    {
        [$whole,$fraction]=explode('.',$this->money($value));
        return ((int)$whole)*100+(int)$fraction;
    }

    /**
     * @param array<int|string, string|int|float> $voucherAmounts keyed by immutable voucher ID
     */
    public function preview(int $projectId, string $method, int $sourceId,
        mixed $newAmount, array $voucherAmounts): array
    {
        return DB::transaction(fn () => $this->inspectLocked(
            $projectId, $method, $sourceId, $this->money($newAmount), $voucherAmounts
        ), 3);
    }

    /**
     * A stale/modified preview cannot be confirmed. All source, allocation,
     * voucher, ledger and evidence writes share one database transaction.
     */
    public function confirm(int $projectId, string $method, int $sourceId, int $actorId,
        string $reason, mixed $newAmount, array $voucherAmounts, string $expectedFingerprint): array
    {
        $reason = trim($reason);
        if ($actorId < 1 || mb_strlen($reason) < 3 || mb_strlen($reason) > 500
            || !preg_match('/^[a-f0-9]{64}$/D', $expectedFingerprint)) {
            throw new \DomainException('A valid actor, reason and reviewed correction fingerprint are required.');
        }
        return DB::transaction(function () use ($projectId,$method,$sourceId,$actorId,$reason,
            $newAmount,$voucherAmounts,$expectedFingerprint): array {
            $plan = $this->inspectLocked($projectId,$method,$sourceId,
                $this->money($newAmount),$voucherAmounts);
            if (!hash_equals($plan['fingerprint'],$expectedFingerprint)) {
                throw new \DomainException('Linked records changed. Review the correction again.');
            }
            $table = $this->sourceTable($method);
            $now = now();
            DB::table($table)->where('project_id',$projectId)->where('id',$sourceId)
                ->update(['amount'=>$plan['amount_after'],'updated_at'=>$now]);

            foreach ($plan['vouchers'] as $item) {
                if ($item['amount_before'] === $item['amount_after']) continue;
                $incoming = in_array($item['series'],['CR','BOR'],true);
                $new = $item['amount_after'];
                $amountFields = $incoming
                    ? ['amount_in'=>$new,'amount_out'=>0]
                    : ['amount_out'=>$new,'amount_in'=>0];
                $allocationChanges = ['amount'=>$new];
                if (Schema::hasColumn('finance_bank_voucher_allocations','updated_at')) {
                    $allocationChanges['updated_at']=$now;
                }
                DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$projectId)->where('id',$item['allocation_id'])
                    ->update($allocationChanges);
                DB::table('customer_ledger')->where('project_id',$projectId)
                    ->where('id',$item['voucher_id'])
                    ->update($amountFields + ['updated_at'=>$now]);
                DB::table('ledgers')->where('id',$item['ledger_id'])
                    ->update($amountFields + ['update_by'=>$actorId,'updated_at'=>$now]);
                DB::table('daily_voucher_edit_snapshots')->insert([
                    'project_id'=>$projectId,'source_id'=>$item['voucher_id'],
                    'approval_id'=>$item['approval_id'],
                    'status'=>'bank_corrected',
                    'before_snapshot'=>json_encode([
                        'bank_method'=>$method,'bank_source_id'=>$sourceId,
                        'allocation_id'=>$item['allocation_id'],'ledger_id'=>$item['ledger_id'],
                        'voucher_type'=>$item['series'],'amount'=>$item['amount_before']
                    ],JSON_THROW_ON_ERROR),
                    'proposed_snapshot'=>json_encode([
                        'bank_method'=>$method,'bank_source_id'=>$sourceId,
                        'allocation_id'=>$item['allocation_id'],'ledger_id'=>$item['ledger_id'],
                        'voucher_type'=>$item['series'],'amount'=>$new
                    ],JSON_THROW_ON_ERROR),
                    'reason'=>$reason,'submitted_by'=>$actorId,'decided_by'=>$actorId,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            if ($plan['amount_before'] !== $plan['amount_after']) {
                DB::table('daily_voucher_edit_snapshots')->insert([
                    'project_id'=>$projectId,'source_id'=>$sourceId,
                    'approval_id'=>null,'status'=>'bank_source_fix',
                    'before_snapshot'=>json_encode([
                        'source_kind'=>$table,'method'=>$method,'source_id'=>$sourceId,
                        'amount'=>$plan['amount_before']
                    ],JSON_THROW_ON_ERROR),
                    'proposed_snapshot'=>json_encode([
                        'source_kind'=>$table,'method'=>$method,'source_id'=>$sourceId,
                        'amount'=>$plan['amount_after']
                    ],JSON_THROW_ON_ERROR),
                    'reason'=>$reason,'submitted_by'=>$actorId,'decided_by'=>$actorId,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            return ['method'=>$method,'source_id'=>$sourceId,
                'amount_before'=>$plan['amount_before'],'amount_after'=>$plan['amount_after'],
                'updated_vouchers'=>count(array_filter($plan['vouchers'],
                    fn (array $v) => $v['amount_before'] !== $v['amount_after'])),
                'fingerprint'=>$plan['fingerprint']];
        }, 3);
    }

    private function inspectLocked(int $projectId, string $method, int $sourceId,
        string $amount, array $voucherAmounts): array
    {
        if ($projectId < 1 || $sourceId < 1) throw new \DomainException('Invalid project/source.');
        $table = $this->sourceTable($method);
        foreach (['projects',$table,'finance_bank_voucher_allocations',
            'finance_bank_voucher_allocation_releases','project_voucher_display_numbers',
            'customer_ledger','ledgers','project_head_subheads','daily_voucher_approvals',
            'daily_voucher_edit_snapshots'] as $required) {
            if (!Schema::hasTable($required)) throw new \DomainException('Linked correction schema is incomplete.');
        }
        DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
            ?: throw new \DomainException('Project not found.');
        $source = DB::table($table)->where('project_id',$projectId)
            ->where('id',$sourceId)->lockForUpdate()->first();
        if (!$source || ($method==='online' && (string)$source->status!=='confirmed')
            || ($method==='cheque' && (string)$source->status!=='cleared')) {
            throw new \DomainException('Only active confirmed/cleared sources can be corrected.');
        }
        if ($method==='cheque' && (int)($source->customer_ledger_id ?? 0)>0) {
            throw new \DomainException('Legacy cheque ledger link requires manual reconciliation.');
        }
        $allocations = DB::table('finance_bank_voucher_allocations')
            ->where('project_id',$projectId)->where('source_method',$method)
            ->where('source_id',$sourceId)->orderBy('id')->lockForUpdate()->get();
        // A verified source can still have a wholly unallocated balance.
        // Historical released links remain immutable and are not active allocations.
        $activeAllocations=$allocations->filter(fn ($allocation) =>
            !DB::table('finance_bank_voucher_allocation_releases')
                ->where('allocation_id',$allocation->id)->exists())->values();
        $ids = $activeAllocations->pluck('voucher_id')->map(fn ($v)=>(int)$v)->all();
        if (count($ids)!==count(array_unique($ids))) {
            throw new \DomainException('Ambiguous duplicate voucher allocation.');
        }
        foreach (array_keys($voucherAmounts) as $key) {
            if (!ctype_digit((string)$key) || (int)$key<1) {
                throw new \DomainException('Linked voucher IDs must be positive integers.');
            }
        }
        $provided = array_map('intval',array_keys($voucherAmounts));
        sort($provided);
        $expected = $ids;
        sort($expected);
        if ($provided !== $expected) {
            throw new \DomainException('Specify exactly one proposed amount per linked voucher.');
        }

        $rows = [];
        $sumCents = 0;
        $originalCents = 0;
        foreach ($activeAllocations as $allocation) {
            $voucherId=(int)$allocation->voucher_id;
            $series=(string)$allocation->voucher_type;
            if (!in_array($series,['CR','DR','BOR','BOP'],true)) {
                throw new \DomainException('Unsupported linked voucher series.');
            }
            $identity=DB::table('project_voucher_display_numbers')
                ->where('project_id',$projectId)->where('source_kind','customer_ledger')
                ->where('source_id',$voucherId)->where('series',$series)->lockForUpdate()->first();
            $voucher=DB::table('customer_ledger')->where('project_id',$projectId)
                ->where('id',$voucherId)->lockForUpdate()->first();
            $legs=DB::table('ledgers as l')
                ->join('project_head_subheads as p','p.id','=','l.project_head_subheads_id')
                ->where('p.project_id',$projectId)->where('l.customer_ledger_id',$voucherId)
                ->lockForUpdate()->get(['l.id','l.type','l.is_active','l.amount_in','l.amount_out','l.project_head_subheads_id']);
            $approval=DB::table('daily_voucher_approvals')
                ->where('project_id',$projectId)->where('source_table','customer_ledger')
                ->where('source_id',$voucherId)->lockForUpdate()->first();
            app(VoucherApprovalMutationGuard::class)->assertNoPendingCorrection($projectId,$voucherId);
            if (DB::table('daily_voucher_edit_snapshots')->where('project_id',$projectId)
                ->where('source_id',$voucherId)->where('status','pending')->exists()) {
                throw new \DomainException('Pending voucher revision must be resolved first.');
            }
            $allowed = match ($series) {
                'CR'=>['CR'],'DR'=>['DR','CP'],'BOR'=>['BOR','CR'],'BOP'=>['BOP','CP'],
            };
            if (!$identity || !$voucher || !in_array((string)$voucher->transaction_type,$allowed,true)
                || (int)$voucher->is_active!==1 || $legs->count()!==1
                || DB::table('ledgers')->where('customer_ledger_id',$voucherId)->count()!==1
                || (int)$legs->first()->is_active!==1
                || (string)$legs->first()->type!==(string)$voucher->transaction_type
                || (int)$voucher->payment_type!==($method==='online'?2:3)
                || ($approval && (!in_array((string)$approval->status,['approved','posted'],true)
                    || (string)$approval->voucher_type!==$series))) {
                throw new \DomainException('Linked voucher identity, status or ledger ownership is invalid.');
            }
            BankVoucherLegIntegrity::assert($series,$voucher,$legs->first(),(float)$allocation->amount);
            $originalCents += $this->cents($allocation->amount);
            $after = $this->money($voucherAmounts[$voucherId]);
            $sumCents += $this->cents($after);
            $rows[]=[
                'allocation_id'=>(int)$allocation->id,'voucher_id'=>$voucherId,
                'voucher_number'=>(string)$identity->display_number,
                'series'=>$series,'ledger_id'=>(int)$legs->first()->id,
                'ledger_account_id'=>(int)$legs->first()->project_head_subheads_id,
                'approval_id'=>$approval?->id,
                'amount_before'=>$this->money($allocation->amount),
                'amount_after'=>$after,
            ];
        }
        if ($originalCents > $this->cents($source->amount)) {
            throw new \DomainException('Existing allocations exceed the original verified source amount.');
        }
        if ($sumCents > $this->cents($amount)) {
            throw new \DomainException('Proposed allocations exceed the corrected bank source amount.');
        }
        $before=$this->money($source->amount);
        $unallocatedBefore=sprintf('%d.%02d',intdiv($this->cents($before)-$originalCents,100),($this->cents($before)-$originalCents)%100);
        $unallocatedAfter=sprintf('%d.%02d',intdiv($this->cents($amount)-$sumCents,100),($this->cents($amount)-$sumCents)%100);
        if ($before===$amount && !collect($rows)->contains(
            fn (array $row) => $row['amount_before'] !== $row['amount_after'])) {
            throw new \DomainException('No financial correction was proposed.');
        }
        $state=['project_id'=>$projectId,'method'=>$method,'source_id'=>$sourceId,
            'status'=>(string)$source->status,'account_mapping_id'=>(int)$source->account_mapping_id,
            'bank_identity'=>$method==='online'
                ? [(string)$source->reference,(string)$source->transaction_date]
                : [(string)$source->cheque_number,(string)$source->bank_name,(string)$source->cheque_date],
            'amount_before'=>$before,'amount_after'=>$amount,
            'allocated_before'=>sprintf('%d.%02d',intdiv($originalCents,100),$originalCents%100),
            'allocated_after'=>sprintf('%d.%02d',intdiv($sumCents,100),$sumCents%100),
            'unallocated_before'=>$unallocatedBefore,'unallocated_after'=>$unallocatedAfter,
            'historical_released_count'=>$allocations->count()-$activeAllocations->count(),
            'vouchers'=>$rows];
        $state['fingerprint']=hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
        return $state;
    }
}
