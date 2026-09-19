<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\CustomerLedger;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;

final class BookingSalesAccountingResolver
{
    public function resolve(Booking $booking): BookingSalesAccountingResult
    {
        $reasons = [];
        $data = [
            'booking_total' => $this->decimal($booking->total_price),
            'voucher_found' => false,
            'voucher_count' => 0,
            'voucher_id' => null,
            'voucher_number' => null,
            'voucher_status' => null,
            'customer_pivot_count' => 0,
            'customer_pivot_id' => null,
            'total_sale_pivot_count' => 0,
            'total_sale_pivot_id' => null,
            'customer_detail_count' => 0,
            'customer_detail_id' => null,
            'total_sale_detail_count' => 0,
            'total_sale_detail_id' => null,
            'customer_ledger_line_count' => 0,
            'customer_ledger_line_id' => null,
            'total_sale_ledger_line_count' => 0,
            'total_sale_ledger_line_id' => null,
            'initial_customer_ledger_id' => null,
            'customer_ledger_total' => null,
            'customer_jv_total' => null,
            'customer_sv_ledger_total' => null,
            'total_sale_jv_total' => null,
            'total_sale_ledger_total' => null,
            'voucher_total_debit' => null,
            'voucher_total_credit' => null,
            'voucher_detail_debit_sum' => null,
            'voucher_detail_credit_sum' => null,
            'unexpected_voucher_line_count' => 0,
        ];

        $vouchers = JournalVoucher::query()
            ->where('project_id', $booking->project_id)
            ->where('type', 'SV')
            ->where('reference', 'BOOKING-' . $booking->id)
            ->get();
        $data['voucher_count'] = $vouchers->count();
        $data['voucher_found'] = $vouchers->count() === 1;
        $this->expectOne($vouchers->count(), 'ORIGINAL_VOUCHER_MISSING', 'ORIGINAL_VOUCHER_DUPLICATE', $reasons);

        $customerPivots = ProjectHeadSubhead::query()
            ->where('project_id', $booking->project_id)
            ->where('head_accounting_id', 16)
            ->where('plot_id', $booking->plot_id)
            ->where('customer_id', $booking->customer_id)
            ->get();
        $data['customer_pivot_count'] = $customerPivots->count();
        $data['customer_pivot_id'] = $customerPivots->count() === 1 ? $customerPivots->first()->id : null;
        $this->expectOne($customerPivots->count(), 'CUSTOMER_PIVOT_MISSING', 'CUSTOMER_PIVOT_AMBIGUOUS', $reasons);

        $totalSalePivots = ProjectHeadSubhead::query()
            ->where('project_id', $booking->project_id)
            ->where('head_accounting_id', 16)
            ->where('subhead_accounting_id', 123)
            ->get();
        $data['total_sale_pivot_count'] = $totalSalePivots->count();
        $data['total_sale_pivot_id'] = $totalSalePivots->count() === 1 ? $totalSalePivots->first()->id : null;
        $this->expectOne($totalSalePivots->count(), 'TOTAL_SALE_PIVOT_MISSING', 'TOTAL_SALE_PIVOT_AMBIGUOUS', $reasons);

        if ($vouchers->count() !== 1) {
            return $this->result($data, $reasons);
        }

        $voucher = $vouchers->first();
        $data['voucher_id'] = $voucher->id;
        $data['voucher_number'] = $voucher->voucher_number;
        $data['voucher_status'] = $voucher->status;
        $data['voucher_total_debit'] = $this->decimal($voucher->total_debit);
        $data['voucher_total_credit'] = $this->decimal($voucher->total_credit);
        if ($voucher->status !== 'pending') {
            $reasons[] = 'VOUCHER_NOT_PENDING';
        }

        $details = JournalVoucherDetail::where('journal_voucher_id', $voucher->id)->get();
        $ledgers = Ledger::query()
            ->where('type', 'SV')
            ->where('type_id', $voucher->id)
            ->where('voucher_number', $voucher->voucher_number)
            ->where('reference', $booking->id)
            ->get();

        $data['voucher_detail_debit_sum'] = $this->sum($details->pluck('debit')->all());
        $data['voucher_detail_credit_sum'] = $this->sum($details->pluck('credit')->all());

        $customerDetail = $this->resolveDetail($details, $data['customer_pivot_id'], 'customer', $data, $reasons);
        $totalSaleDetail = $this->resolveDetail($details, $data['total_sale_pivot_id'], 'total_sale', $data, $reasons);
        $customerLedgerLine = $this->resolveLedger($ledgers, $data['customer_pivot_id'], 'customer', $data, $reasons);
        $totalSaleLedgerLine = $this->resolveLedger($ledgers, $data['total_sale_pivot_id'], 'total_sale', $data, $reasons);

        if ($customerDetail) {
            $data['customer_jv_total'] = $this->decimal($customerDetail->credit);
            if (bccomp($this->decimal($customerDetail->debit), '0.00', 2) !== 0) {
                $reasons[] = 'CUSTOMER_DETAIL_DIRECTION_INVALID';
            }
        }
        if ($totalSaleDetail) {
            $data['total_sale_jv_total'] = $this->decimal($totalSaleDetail->debit);
            if (bccomp($this->decimal($totalSaleDetail->credit), '0.00', 2) !== 0) {
                $reasons[] = 'TOTAL_SALE_DETAIL_DIRECTION_INVALID';
            }
        }
        if ($customerLedgerLine) {
            $data['customer_sv_ledger_total'] = $this->decimal($customerLedgerLine->amount_out);
            if (bccomp($this->decimal($customerLedgerLine->amount_in), '0.00', 2) !== 0) {
                $reasons[] = 'CUSTOMER_LEDGER_LINE_DIRECTION_INVALID';
            }
        }
        if ($totalSaleLedgerLine) {
            $data['total_sale_ledger_total'] = $this->decimal($totalSaleLedgerLine->amount_in);
            if (bccomp($this->decimal($totalSaleLedgerLine->amount_out), '0.00', 2) !== 0) {
                $reasons[] = 'TOTAL_SALE_LEDGER_LINE_DIRECTION_INVALID';
            }
        }

        $expectedDetailIds = array_filter([$customerDetail?->id, $totalSaleDetail?->id]);
        $expectedLedgerIds = array_filter([$customerLedgerLine?->id, $totalSaleLedgerLine?->id]);
        $unexpected = $details->whereNotIn('id', $expectedDetailIds)->count()
            + $ledgers->whereNotIn('id', $expectedLedgerIds)->count();
        $data['unexpected_voucher_line_count'] = $unexpected;
        if ($unexpected > 0) {
            $reasons[] = 'UNEXPECTED_VOUCHER_LINES';
        }

        if (
            bccomp($data['voucher_total_debit'], $data['voucher_total_credit'], 2) !== 0
            || bccomp($data['voucher_detail_debit_sum'], $data['voucher_detail_credit_sum'], 2) !== 0
            || bccomp($data['voucher_total_debit'], $data['voucher_detail_debit_sum'], 2) !== 0
            || bccomp($data['voucher_total_credit'], $data['voucher_detail_credit_sum'], 2) !== 0
        ) {
            $reasons[] = 'VOUCHER_UNBALANCED';
        }

        if (!$customerLedgerLine || !$customerLedgerLine->customer_ledger_id) {
            $reasons[] = 'INITIAL_CUSTOMER_LEDGER_INVALID';
        } else {
            $customerLedger = CustomerLedger::find($customerLedgerLine->customer_ledger_id);
            $data['initial_customer_ledger_id'] = $customerLedger?->id;
            $data['customer_ledger_total'] = $customerLedger ? $this->decimal($customerLedger->amount_in) : null;

            $allowedSharingIds = array_filter([$customerLedgerLine->id, $totalSaleLedgerLine?->id]);
            $sharedOutsidePrincipal = Ledger::where('customer_ledger_id', $customerLedgerLine->customer_ledger_id)
                ->whereNotIn('id', $allowedSharingIds)
                ->exists();
            $valid = $customerLedger
                && (string) $customerLedger->transaction_type === 'Bo'
                && (int) $customerLedger->project_id === (int) $booking->project_id
                && (int) $customerLedger->customer_id === (int) $booking->customer_id
                && (int) $customerLedger->plot_id === (int) $booking->plot_id
                && bccomp($this->decimal($customerLedger->amount_out), '0.00', 2) === 0
                && !$sharedOutsidePrincipal;
            if (!$valid) {
                $reasons[] = 'INITIAL_CUSTOMER_LEDGER_INVALID';
            }
        }

        return $this->result($data, $reasons);
    }

    private function resolveDetail($details, ?int $accountId, string $prefix, array &$data, array &$reasons)
    {
        $matches = $accountId ? $details->where('account_id', $accountId)->values() : collect();
        $data[$prefix . '_detail_count'] = $matches->count();
        $data[$prefix . '_detail_id'] = $matches->count() === 1 ? $matches->first()->id : null;
        $this->expectOne(
            $matches->count(),
            strtoupper($prefix) . '_DETAIL_MISSING',
            strtoupper($prefix) . '_DETAIL_AMBIGUOUS',
            $reasons
        );
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function resolveLedger($ledgers, ?int $accountId, string $prefix, array &$data, array &$reasons)
    {
        $matches = $accountId ? $ledgers->where('project_head_subheads_id', $accountId)->values() : collect();
        $data[$prefix . '_ledger_line_count'] = $matches->count();
        $data[$prefix . '_ledger_line_id'] = $matches->count() === 1 ? $matches->first()->id : null;
        $this->expectOne(
            $matches->count(),
            strtoupper($prefix) . '_LEDGER_LINE_MISSING',
            strtoupper($prefix) . '_LEDGER_LINE_AMBIGUOUS',
            $reasons
        );
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function expectOne(int $count, string $missing, string $duplicate, array &$reasons): void
    {
        if ($count === 0) {
            $reasons[] = $missing;
        } elseif ($count > 1) {
            $reasons[] = $duplicate;
        }
    }

    private function result(array $data, array $reasons): BookingSalesAccountingResult
    {
        $reasons = array_values(array_unique($reasons));
        $structuralCodes = array_filter($reasons, fn (string $reason) => $reason !== 'VOUCHER_NOT_PENDING');
        $amounts = [
            $data['booking_total'],
            $data['customer_ledger_total'],
            $data['customer_jv_total'],
            $data['customer_sv_ledger_total'],
            $data['total_sale_jv_total'],
            $data['total_sale_ledger_total'],
            $data['voucher_total_debit'],
            $data['voucher_total_credit'],
        ];
        $consistent = !in_array(null, $amounts, true);
        if ($consistent) {
            foreach (array_slice($amounts, 1) as $amount) {
                if (bccomp($amounts[0], $amount, 2) !== 0) {
                    $consistent = false;
                    break;
                }
            }
        }

        return new BookingSalesAccountingResult($data, $reasons, count($structuralCodes) > 0, $consistent);
    }

    private function decimal($value): string
    {
        $value = (string) ($value ?? '0');
        return strpos($value, '.') === false ? $value . '.00' : bcadd($value, '0', 2);
    }

    private function sum(array $values): string
    {
        $sum = '0.00';
        foreach ($values as $value) {
            $sum = bcadd($sum, (string) $value, 2);
        }
        return $sum;
    }
}
