<?php

namespace App\Services\Finance;

use App\Models\CustomerLedger;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ExtraChargeBankSettlement
{
    private function cents($value): int
    {
        return (int) round((float) $value * 100);
    }

    private function linked(int $projectId, int $chargeId)
    {
        return CustomerLedger::query()
            ->where('project_id', $projectId)
            ->where('transaction_type', 'ECP')
            ->where('reference', 'like', 'EC_PAYMENT#' . $chargeId . '#%')
            ->lockForUpdate()->get();
    }

    private function assertSourceLegs(CustomerLedger $payment): void
    {
        $legs = Ledger::query()->where('customer_ledger_id', $payment->id)
            ->where('type', 'ECP')->lockForUpdate()->get();
        $amount = $this->cents($payment->amount_in);
        if ($amount <= 0 || $legs->count() !== 2 ||
            $this->cents($legs->sum('amount_in')) !== $amount ||
            $this->cents($legs->sum('amount_out')) !== $amount ||
            $legs->contains(fn ($leg) =>
                (string) $leg->reference !== (string) $payment->reference ||
                (int) $leg->is_active !== 1)) {
            throw new \DomainException('ECP_SETTLED_LEDGER_MISMATCH');
        }
    }

    public function recordPending(
        Request $request, CustomerLedger $charge, int $projectId,
        array $mapping, string $amount, string $reference, string $date
    ): void {
        $kind = (int) $request->input('payment_type');
        $bankId = (int) $request->input('bank_id');
        $transaction = trim((string) $request->input('t_number'));
        $passingDate = (string) $request->input('passing_date');
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $passingDate);
        if (!in_array($kind, [2, 3], true) || $bankId <= 0 ||
            $transaction === '' || strlen($transaction) > 100 ||
            !$parsedDate || $parsedDate->format('Y-m-d') !== $passingDate) {
            throw new \DomainException('ECP_BANK_DETAILS_REQUIRED');
        }
        // The caller holds the selected project row lock; do not allow a
        // different customer's receipt to reserve this charge's balance.
        $payments = $this->linked($projectId, $charge->id);
        $identity = 'EC_PAYMENT#' . $charge->id . '#' . $reference;
        $reserved = 0;
        foreach ($payments as $payment) {
            if ($payment->reference === $identity) {
                throw new \DomainException('ECP_DUPLICATE_REFERENCE');
            }
            if ((int) $payment->is_active !== 1) {
                throw new \DomainException('ECP_INACTIVE_SOURCE_CONFLICT');
            }
            if ((int) $payment->customer_id !== (int) $charge->customer_id ||
                (int) $payment->plot_id !== (int) $charge->plot_id) {
                throw new \DomainException('ECP_SOURCE_OWNER_CONFLICT');
            }
            if ((int) $payment->payment_type === 1 ||
                (int) $payment->passing_status === 1) {
                $this->assertSourceLegs($payment);
                $reserved += $this->cents($payment->amount_in);
            } elseif (in_array((int) $payment->payment_type, [2, 3], true) &&
                (int) $payment->passing_status === 0) {
                if (Ledger::where('customer_ledger_id', $payment->id)
                    ->where('type', 'ECP')->exists()) {
                    throw new \DomainException('ECP_PENDING_HAS_LEDGER');
                }
                $reserved += $this->cents($payment->amount_in);
            } elseif ((int) $payment->passing_status !== 2) {
                throw new \DomainException('ECP_INVALID_PREVIOUS_STATUS');
            }
        }
        if ($this->cents($amount) + $reserved > $this->cents($charge->amount_in)) {
            throw new \DomainException('ECP_RESERVED_EXCEEDS_CHARGE');
        }
        $number = app(ProjectVoucherAllocator::class)->next($projectId, 'ECP');
        CustomerLedger::create([
            'project_id' => $projectId, 'customer_id' => $charge->customer_id,
            'plot_id' => $charge->plot_id, 'transaction_type' => 'ECP',
            'type_id' => $number, 'reference' => $identity,
            'date' => $date, 'payment_type' => $kind,
            'bank_id' => $bankId, 't_number' => $transaction,
            'passing_date' => $passingDate, 'passing_status' => 0,
            'amount_in' => $amount, 'amount_out' => 0,
            'description' => 'Pending bank Extra Charge payment for EC #' . $charge->id,
            'is_active' => 1, 'is_approve' => 0,
        ]);
    }

    public function resolve(Request $request, int $projectId): void
    {
        $paymentId = (int) $request->input('extra_charge_payment_id');
        $status = (int) $request->input('passing_status');
        $date = (string) $request->input('bank_post_at');
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($paymentId <= 0 || !in_array($status, [1, 2], true) ||
            ($status === 1 && (!$parsedDate || $parsedDate->format('Y-m-d') !== $date))) {
            throw new \DomainException('ECP_CLEARANCE_INPUT_INVALID');
        }
        if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
            throw new \DomainException('ECP_CLEARANCE_PROJECT_NOT_FOUND');
        }
        $payment = CustomerLedger::query()->where('project_id', $projectId)
            ->where('transaction_type', 'ECP')->whereKey($paymentId)
            ->lockForUpdate()->first();
        if (!$payment || (int) $payment->is_active !== 1 ||
            !in_array((int) $payment->payment_type, [2, 3], true) ||
            (int) $payment->passing_status !== 0 ||
            !preg_match('/^EC_PAYMENT#([1-9][0-9]*)#/', (string) $payment->reference, $match)) {
            throw new \DomainException('ECP_CLEARANCE_SOURCE_NOT_PENDING');
        }
        $chargeId = (int) $match[1];
        $charge = CustomerLedger::query()->where('project_id', $projectId)
            ->where('transaction_type', 'EC')->whereKey($chargeId)
            ->where('is_active', 1)->lockForUpdate()->first();
        if (!$charge || (int) $charge->customer_id !== (int) $payment->customer_id ||
            (int) $charge->plot_id !== (int) $payment->plot_id) {
            throw new \DomainException('ECP_CLEARANCE_CHARGE_MISMATCH');
        }
        if (Ledger::where('customer_ledger_id', $payment->id)
            ->where('type', 'ECP')->lockForUpdate()->exists()) {
            throw new \DomainException('ECP_CLEARANCE_EARLY_OR_DUPLICATE_LEDGER');
        }
        if ($status === 2) {
            $payment->update(['passing_status' => 2,
                'note' => 'Bank payment rejected: ' . trim((string) $request->input('note'))]);
            return;
        }
        $approvedAccount = (int) config("finance.extra_charge_bank_accounts.{$projectId}", 0);
        $bank = $approvedAccount ? ProjectHeadSubhead::query()
            ->where('project_id', $projectId)->whereKey($approvedAccount)
            ->lockForUpdate()->first() : null;
        if (!$bank) {
            throw new \DomainException('ECP_BANK_ACCOUNT_NOT_APPROVED');
        }
        $mapping = app(ExtraChargeAccounts::class)->forProject(
            $projectId, (int) $charge->customer_id, (int) $charge->plot_id);
        if (in_array($approvedAccount, array_values($mapping), true)) {
            throw new \DomainException('ECP_BANK_ACCOUNT_CONFLICT');
        }
        // Re-evaluate paid and pending reservations under the project lock.
        $sum = 0;
        foreach ($this->linked($projectId, $chargeId) as $previous) {
            if ((int) $previous->is_active !== 1) {
                throw new \DomainException('ECP_INACTIVE_SOURCE_CONFLICT');
            }
            if ((int) $previous->passing_status === 2) {
                continue;
            }
            if ((int) $previous->payment_type === 1 ||
                (int) $previous->passing_status === 1) {
                $this->assertSourceLegs($previous);
            } elseif ($previous->id !== $payment->id &&
                Ledger::where('customer_ledger_id', $previous->id)->where('type', 'ECP')->exists()) {
                throw new \DomainException('ECP_PENDING_HAS_LEDGER');
            }
            $sum += $this->cents($previous->amount_in);
        }
        if ($sum > $this->cents($charge->amount_in)) {
            throw new \DomainException('ECP_CLEARANCE_EXCEEDS_CHARGE');
        }
        foreach ([
            [$approvedAccount, $payment->amount_in, '0.00'],
            [(int) $mapping['debit_account_id'], '0.00', $payment->amount_in],
        ] as [$accountId, $in, $out]) {
            Ledger::create([
                'customer_ledger_id' => $payment->id, 'type' => 'ECP',
                'type_id' => $payment->type_id,
                'voucher_number' => $payment->type_id,
                'project_head_subheads_id' => $accountId,
                'reference' => $payment->reference,
                'amount_in' => $in, 'amount_out' => $out,
                'date' => $date, 'detail' => 'Cleared EC bank payment #' . $chargeId,
                'is_active' => 1, 'status' => 0,
                'create_by' => Auth::id(), 'update_by' => Auth::id(),
            ]);
        }
        $payment->update(['passing_status' => 1, 'bank_post_at' => $date,
            'note' => 'EC bank payment cleared']);
    }
}
