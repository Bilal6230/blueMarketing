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
            ->lockForUpdate()->get()
            ->each(function ($row) use ($chargeId) {
                if (preg_match('/^EC_PAYMENT#' . $chargeId . '#[^#]+$/D',
                    (string) $row->reference) !== 1) {
                    throw new \DomainException('ECP_MALFORMED_SOURCE_REFERENCE');
                }
            });
    }

    private function assertSourceLegs(CustomerLedger $payment): void
    {
        // Read *all* linked legs. A rogue different-type leg must not hide
        // behind an otherwise balanced ECP pair.
        $legs = Ledger::query()->where('customer_ledger_id', $payment->id)
            ->lockForUpdate()->get();
        $amount = $this->cents($payment->amount_in);
        if ($amount <= 0 || $legs->count() !== 2 ||
            $this->cents($legs->sum('amount_in')) !== $amount ||
            $this->cents($legs->sum('amount_out')) !== $amount ||
            $legs->contains(fn ($leg) =>
                (string) $leg->type !== 'ECP' ||
                (int) $leg->type_id !== (int) $payment->type_id ||
                (int) $leg->voucher_number !== (int) $payment->type_id ||
                (string) $leg->reference !== (string) $payment->reference ||
                (int) $leg->is_active !== 1 ||
                !ProjectHeadSubhead::where('id', $leg->project_head_subheads_id)
                    ->where('project_id', $payment->project_id)->exists())) {
            throw new \DomainException('ECP_SETTLED_LEDGER_MISMATCH');
        }
    }

    /**
     * One authoritative balance contract for cash, pending instruments,
     * clearance and reporting. Project lock must be held on writes.
     * Rejected receipts release reservation, but keep their source identity.
     */
    public function balances(CustomerLedger $charge): array
    {
        if (V2OriginalWorkflowAccess::selected()) { return (new V2ExtraChargeBalance())->read($charge); }
        $due = $this->cents($charge->amount_in);
        $paid = 0;
        $pending = 0;
        foreach ($this->linked((int) $charge->project_id, (int) $charge->id) as $payment) {
            if ((int) $payment->is_active !== 1 ||
                (int) $payment->customer_id !== (int) $charge->customer_id ||
                (int) $payment->plot_id !== (int) $charge->plot_id ||
                $this->cents($payment->amount_in) <= 0) {
                throw new \DomainException('ECP_BALANCE_SOURCE_CONFLICT');
            }
            $status = $payment->passing_status;
            $kind = (int) $payment->payment_type;
            if ($kind === 1 && ($status === null || (int) $status === 0)) {
                $this->assertSourceLegs($payment);
                $paid += $this->cents($payment->amount_in);
            } elseif (in_array($kind, [2, 3], true) && (int) $status === 1) {
                $this->assertSourceLegs($payment);
                $paid += $this->cents($payment->amount_in);
            } elseif (in_array($kind, [2, 3], true) && (int) $status === 0) {
                if (Ledger::where('customer_ledger_id', $payment->id)->exists()) {
                    throw new \DomainException('ECP_PENDING_HAS_LEDGER');
                }
                $pending += $this->cents($payment->amount_in);
            } elseif (in_array($kind, [2, 3], true) && (int) $status === 2) {
                if (Ledger::where('customer_ledger_id', $payment->id)
                    ->where('type', 'ECP')->exists()) {
                    throw new \DomainException('ECP_REJECTED_HAS_LEDGER');
                }
            } else {
                throw new \DomainException('ECP_BALANCE_STATUS_CONFLICT');
            }
        }
        if ($paid + $pending > $due) {
            throw new \DomainException('ECP_BALANCE_OVERBOOKED');
        }
        return [
            'charged_cents' => $due,
            'paid_cents' => $paid,
            'pending_cents' => $pending,
            'outstanding_cents' => $due - $paid,
            'available_cents' => $due - $paid - $pending,
        ];
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
        $identity = 'EC_PAYMENT#' . $charge->id . '#' . $reference;
        if (CustomerLedger::query()->where('project_id', $projectId)
            ->where('transaction_type', 'ECP')->where('reference', $identity)
            ->lockForUpdate()->exists()) {
            throw new \DomainException('ECP_DUPLICATE_REFERENCE');
        }
        $balance = $this->balances($charge);
        if ($this->cents($amount) > $balance['available_cents']) {
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
            ->lockForUpdate()->exists()) {
            throw new \DomainException('ECP_CLEARANCE_EARLY_OR_DUPLICATE_LEDGER');
        }
        if ($status === 2) {
            $payment->update(['passing_status' => 2,
                'note' => 'Bank payment rejected: ' . trim((string) $request->input('note'))]);
            return;
        }
        $mapping = app(ExtraChargeAccounts::class)->forProject(
            $projectId, (int) $charge->customer_id, (int) $charge->plot_id);
        $approvedAccount = app(ExtraChargeAccounts::class)
            ->settlementAccount($projectId, 'Bank');
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
