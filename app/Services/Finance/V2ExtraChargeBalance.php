<?php

namespace App\Services\Finance;

use App\Models\CustomerLedger;
use Illuminate\Support\Facades\DB;

final class V2ExtraChargeBalance
{
    private function cents(mixed $amount): int
    {
        return (int) str_replace('.', '', V2OriginalCustomerWorkflow::money($amount));
    }

    public function read(CustomerLedger $charge): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if ($charge->transaction_type !== 'EC' || (int) $charge->is_active !== 1
            || !DB::table('finance_extra_charge_sources')->where('customer_ledger_id', $charge->id)->exists()) {
            throw new \DomainException('V2_EXTRA_CHARGE_NOT_VERIFIED');
        }
        // The checked reader also verifies exact account-wise reversals and
        // physical source links. Do not infer settled money from status alone.
        $totals = [];
        foreach ((new V2PostingGateway())->postingLines((int) $charge->project_id) as $line) {
            if ($line['source_kind'] !== 'cash') { continue; }
            $id = (int) $line['source_id']; $batch = (int) $line['posting_batch_id'];
            $totals[$id][$batch] ??= ['amount' => 0, 'reverse' => $line['is_reversal']];
            $totals[$id][$batch]['amount'] += $this->cents($line['debit']);
        }
        $net = function (int $id, int $expected) use ($totals): int {
            if (empty($totals[$id])) { throw new \DomainException('V2_EXTRA_CHARGE_POSTING_MISSING'); }
            $net = 0;
            foreach ($totals[$id] as $batch) {
                if ($batch['amount'] !== $expected) { throw new \DomainException('V2_EXTRA_CHARGE_POSTING_CHANGED'); }
                $net += $batch['reverse'] ? -$expected : $expected;
            }
            return $net;
        };
        $due = $net((int) $charge->id, $this->cents($charge->amount_in)); $paid = $pending = 0;
        $payments = DB::table('finance_extra_charge_payment_sources as link')
            ->join('customer_ledger as payment', 'payment.id', '=', 'link.customer_ledger_id')
            ->where('link.charge_customer_ledger_id', $charge->id)->get(['payment.*']);
        foreach ($payments as $payment) {
            if ((int) $payment->project_id !== (int) $charge->project_id || $payment->transaction_type !== 'ECP'
                || (int) $payment->customer_id !== (int) $charge->customer_id || (int) $payment->plot_id !== (int) $charge->plot_id) {
                throw new \DomainException('V2_EXTRA_CHARGE_PAYMENT_CONTEXT_INVALID');
            }
            $amount = $this->cents($payment->amount_in);
            if ((int) $payment->is_active !== 1) {
                if (!empty($totals[$payment->id])) { throw new \DomainException('V2_EXTRA_CHARGE_INACTIVE_POSTING'); }
                continue;
            }
            if ((int) $payment->is_approve === 1) { $paid += $net((int) $payment->id, $amount); }
            elseif (in_array((int) $payment->payment_type, [2, 3], true) && (int) $payment->passing_status === 0 && empty($totals[$payment->id])) { $pending += $amount; }
            elseif (in_array((int) $payment->payment_type, [2, 3], true) && (int) $payment->passing_status === 2 && empty($totals[$payment->id])) { continue; }
            else { throw new \DomainException('V2_EXTRA_CHARGE_PAYMENT_STATE_INVALID'); }
        }
        if ($paid < 0 || $due < 0 || $paid + $pending > $due) { throw new \DomainException('V2_EXTRA_CHARGE_OVERBOOKED'); }
        return ['charged_cents' => $due, 'paid_cents' => $paid, 'pending_cents' => $pending,
            'outstanding_cents' => $due - $paid, 'available_cents' => $due - $paid - $pending];
    }
}
