<?php

namespace App\Services\Finance;

use App\Models\CustomerLedger;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;

/** Append-only correction for posted CR/CP/PPR sources and ledger legs. */
final class LegacyCashVoucherCorrection
{
    /** @return array{source:CustomerLedger,ledger:Ledger,reversal_source:CustomerLedger,reversal_ledger:Ledger} */
    public function correct(int $projectId, int $sourceId, int $accountId, array $changes, int $userId): array
    {
        return DB::transaction(function () use ($projectId, $sourceId, $accountId, $changes, $userId): array {
            $source = CustomerLedger::query()->whereKey($sourceId)->where('project_id', $projectId)
                ->whereIn('transaction_type', ['CR', 'CP', 'PPR'])->where('is_active', 1)
                ->lockForUpdate()->firstOrFail();
            $old = Ledger::query()->withoutGlobalScopes()->where('customer_ledger_id', $source->id)
                ->where('type', $source->transaction_type)->where('is_active', 1)
                ->whereIn('project_head_subheads_id', ProjectHeadSubhead::where('project_id', $projectId)->select('id'))
                ->lockForUpdate()->get();
            if ($old->count() !== 1) {
                throw new \DomainException('CASH_VOUCHER_SINGLE_SOURCE_LEG_REQUIRED');
            }
            $oldLedger = $old->first();
            $account = ProjectHeadSubhead::availableForPosting()->whereKey($accountId)->where('project_id', $projectId)
                ->lockForUpdate()->firstOrFail();
            $type = (string) $source->transaction_type;
            $allocator = app(ProjectVoucherAllocator::class);
            $next = $allocator->nextSourceId($projectId, $type);
            $correctedSourceId = $allocator->nextSourceId($projectId, $type);
            $reversalVoucherNumber = $allocator->next($projectId, $type);
            $correctedVoucherNumber = $allocator->next($projectId, $type);
            $date = (string) $changes['date'];
            $reversalReference = 'REV-'.$type.'-'.$source->id.'-'.$next;

            $base = $source->getAttributes();
            unset($base['id'], $base['created_at'], $base['updated_at']);
            $reversalSource = CustomerLedger::create(array_merge($base, [
                'type_id' => $next, 'reference' => $reversalReference, 'date' => $date,
                'amount_in' => $source->amount_out, 'amount_out' => $source->amount_in,
                'description' => 'Correction reversal: '.($source->description ?: $source->reference),
                'is_active' => 1,
            ]));
            $correctedSource = CustomerLedger::create(array_merge($base, $changes, [
                'type_id' => $correctedSourceId, 'project_id' => $projectId,
                'customer_id' => $changes['customer_id'] ?? $account->customer_id ?? $source->customer_id,
                'plot_id' => $changes['plot_id'] ?? $account->plot_id ?? $source->plot_id,
                'transaction_type' => $type, 'is_active' => 1,
            ]));
            $displayIdentity = app(ProjectVoucherDisplayIdentity::class);
            $displayIdentity->assign($projectId, 'customer_ledger', (int) $reversalSource->id, $type, $date);
            $displayIdentity->assign($projectId, 'customer_ledger', (int) $correctedSource->id, $type, $date);

            $common = [
                'type' => $type, 'project_head_subheads_id' => $oldLedger->project_head_subheads_id,
                'create_by' => $userId, 'is_active' => 1, 'status' => '0', 'date' => $date,
            ];
            $reversalLedger = Ledger::create(array_merge($common, [
                'type_id' => $reversalSource->type_id, 'voucher_number' => $reversalVoucherNumber,
                'customer_ledger_id' => $reversalSource->id, 'reference' => $reversalReference,
                'amount_in' => $oldLedger->amount_out, 'amount_out' => $oldLedger->amount_in,
                'detail' => $reversalSource->description,
            ]));
            $correctedLedger = Ledger::create([
                'type' => $type, 'type_id' => $correctedSource->type_id,
                'voucher_number' => $correctedVoucherNumber, 'project_head_subheads_id' => $account->id,
                'customer_ledger_id' => $correctedSource->id, 'reference' => $correctedSource->reference,
                'amount_in' => $correctedSource->amount_in, 'amount_out' => $correctedSource->amount_out,
                'detail' => $this->correctedDetail($correctedSource), 'create_by' => $userId,
                'is_active' => 1, 'status' => '0', 'date' => $date,
            ]);

            return ['source' => $correctedSource, 'ledger' => $correctedLedger,
                'reversal_source' => $reversalSource, 'reversal_ledger' => $reversalLedger];
        });
    }

    private function correctedDetail(CustomerLedger $source): string
    {
        if ($source->transaction_type !== 'PPR') return (string) ($source->description ?? '');

        $source->loadMissing(['customer_list', 'plot_list']);
        $plot = $source->plot_list;
        $plotLabel = $plot
            ? 'Plot '.(match ((int) $plot->type) { 1 => 'R-', 2 => 'C-', default => '' }).trim((string) $plot->name)
            : 'Unknown Plot';
        $customer = $source->customer_list;
        $customerName = trim((string) ($customer?->first_name ?? '').' '.(string) ($customer?->last_name ?? ''));
        $method = match ((int) $source->payment_type) { 2 => 'Online', 3 => 'Cheque', default => 'Cash' };
        $parts = ['PPR', 'Plot: '.$plotLabel, 'Cust: '.($customerName ?: 'Unknown Customer'), 'Method: '.$method];
        if (in_array((int) $source->payment_type, [2, 3], true)) {
            $bankName = function_exists('getBankNameById') ? getBankNameById($source->bank_id) : null;
            if ($bankName) $parts[] = 'Bank: '.$bankName;
            $instrument = (int) $source->payment_type === 2 ? 'Txn' : 'Chq';
            if (trim((string) $source->t_number) !== '') $parts[] = $instrument.': '.$source->t_number;
            if ($source->passing_date) $parts[] = 'Pass: '.substr((string) $source->passing_date, 0, 10);
        }
        if (trim((string) $source->reference) !== '') $parts[] = 'Ref: '.$source->reference;
        if ($source->date) $parts[] = 'Date: '.substr((string) $source->date, 0, 10);
        if (trim((string) $source->description) !== '') $parts[] = 'Note: '.$source->description;
        return implode(' | ', $parts);
    }
}
