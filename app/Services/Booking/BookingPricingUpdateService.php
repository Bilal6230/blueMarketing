<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingVoucher;
use App\Models\CustomerLedger;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BookingPricingUpdateService
{
    public function __construct(private BookingPriceCalculator $calculator)
    {
    }

    public function updatePristineBooking(
        int $bookingId,
        int $projectId,
        array $expectedSnapshot,
        array $requestedPricing,
        ?int $userId = null
    ): BookingPricingUpdateResult {
        return DB::transaction(function () use ($bookingId, $projectId, $expectedSnapshot, $requestedPricing) {
            $booking = Booking::query()
                ->where('project_id', $projectId)
                ->where('cancel_status', '0')
                ->lockForUpdate()
                ->findOrFail($bookingId);

            $this->assertExpectedPricing($booking, $expectedSnapshot);
            $this->assertLifecycleIsPristine($booking);

            $calculation = $this->calculator->calculate(
                (string) $booking->plot_size,
                $requestedPricing['plot_rate'] ?? null,
                $requestedPricing['is_park'] ?? null,
                $requestedPricing['park_facing'] ?? null,
                $requestedPricing['is_corner'] ?? null,
                $requestedPricing['carner_price'] ?? null,
                $requestedPricing['dicount_value'] ?? null
            );

            $records = $this->lockAndValidateAccounting($booking);
            $oldBookingTotal = $this->decimal($booking->total_price);
            $oldPrincipal = $records['principal'];
            $newTotal = $calculation->totalPrice;
            $bookingPricingMatches = $this->bookingPricingMatches($booking, $requestedPricing, $newTotal);
            $accountingMatches = bccomp($oldPrincipal, $newTotal, 2) === 0;

            if (!$bookingPricingMatches || !$accountingMatches) {
                $booking->plot_rate = $calculation->plotRate;
                $booking->is_park = (int) ($requestedPricing['is_park'] ?? 0);
                $booking->park_facing = $calculation->parkCharge;
                $booking->is_corner = (int) ($requestedPricing['is_corner'] ?? 0);
                $booking->carner_price = $calculation->cornerCharge;
                $booking->dicount_value = $calculation->discount;
                $booking->total_price = $newTotal;
                $booking->save();

                $records['customerLedger']->amount_in = $newTotal;
                $records['customerLedger']->save();
                $records['customerDetail']->credit = $newTotal;
                $records['customerDetail']->save();
                $records['totalSaleDetail']->debit = $newTotal;
                $records['totalSaleDetail']->save();
                $records['customerLedgerLine']->amount_out = $newTotal;
                $records['customerLedgerLine']->save();
                $records['totalSaleLedgerLine']->amount_in = $newTotal;
                $records['totalSaleLedgerLine']->save();

                $detailSums = $this->detailSums($records['details']->map(fn ($detail) => $detail->fresh()));
                if (bccomp($detailSums['debit'], $detailSums['credit'], 2) !== 0) {
                    throw new DomainException('POST_UPDATE_VOUCHER_UNBALANCED');
                }
                $records['voucher']->total_debit = $detailSums['debit'];
                $records['voucher']->total_credit = $detailSums['credit'];
                $records['voucher']->save();

                $this->assertPostUpdate($booking, $records, $newTotal);
            }

            return new BookingPricingUpdateResult(
                !$bookingPricingMatches || !$accountingMatches,
                $oldBookingTotal,
                $oldPrincipal,
                $newTotal,
                bcsub($newTotal, $oldBookingTotal, 2),
                bcsub($newTotal, $oldPrincipal, 2)
            );
        });
    }

    private function assertExpectedPricing(Booking $booking, array $expected): void
    {
        $required = ['expected_plot_rate', 'expected_is_park', 'expected_park_facing', 'expected_is_corner', 'expected_carner_price', 'expected_dicount_value', 'expected_total_price'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $expected)) {
                throw new DomainException('EXPECTED_PRICING_SNAPSHOT_INCOMPLETE');
            }
        }

        foreach (['plot_rate', 'park_facing', 'carner_price', 'dicount_value', 'total_price'] as $field) {
            if (bccomp($this->decimal($expected['expected_' . $field]), $this->decimal($booking->{$field}), 2) !== 0) {
                throw new DomainException('STALE_BOOKING_PRICING');
            }
        }
        foreach (['is_park', 'is_corner'] as $field) {
            if ((int) $expected['expected_' . $field] !== (int) $booking->{$field}) {
                throw new DomainException('STALE_BOOKING_PRICING');
            }
        }
    }

    private function assertLifecycleIsPristine(Booking $booking): void
    {
        if ($booking->trashed() || (string) $booking->cancel_status !== '0' || (string) $booking->status !== 'active') {
            throw new DomainException('BOOKING_NOT_ACTIVE');
        }
        if (BookingDetail::where('booking_id', $booking->id)->lockForUpdate()->exists()) {
            throw new DomainException('SCHEDULE_EXISTS');
        }
        if (BookingVoucher::where('booking_id', $booking->id)->where('voucher_series', 'PPR')->lockForUpdate()->exists()) {
            throw new DomainException('PAYMENT_ACTIVITY_EXISTS');
        }
        if (CustomerLedger::where('project_id', $booking->project_id)->where('customer_id', $booking->customer_id)
            ->where('plot_id', $booking->plot_id)->where('transaction_type', 'PPR')->lockForUpdate()->exists()) {
            throw new DomainException('PAYMENT_ACTIVITY_EXISTS');
        }
        if (JournalVoucher::where('project_id', $booking->project_id)->where('type', 'SV')
            ->where('reference', 'TRANSFER-' . $booking->id)->lockForUpdate()->exists()) {
            throw new DomainException('TRANSFER_HISTORY_EXISTS');
        }
        if (Ledger::where('reference', $booking->id)->where('detail', 'like', 'RESALE_PROFIT#' . $booking->id . '%')->lockForUpdate()->exists()) {
            throw new DomainException('RESALE_HISTORY_EXISTS');
        }
    }

    private function lockAndValidateAccounting(Booking $booking): array
    {
        $vouchers = JournalVoucher::where('project_id', $booking->project_id)->where('type', 'SV')
            ->where('reference', 'BOOKING-' . $booking->id)->lockForUpdate()->get();
        $this->expectOne($vouchers, 'ORIGINAL_VOUCHER');
        $voucher = $vouchers->first();
        if ((string) $voucher->status !== 'pending') {
            throw new DomainException('VOUCHER_NOT_PENDING');
        }

        $customerPivots = ProjectHeadSubhead::where('project_id', $booking->project_id)->where('head_accounting_id', 16)
            ->where('plot_id', $booking->plot_id)->where('customer_id', $booking->customer_id)->lockForUpdate()->get();
        $totalSalePivots = ProjectHeadSubhead::where('project_id', $booking->project_id)->where('head_accounting_id', 16)
            ->where('subhead_accounting_id', 123)->lockForUpdate()->get();
        $this->expectOne($customerPivots, 'CUSTOMER_PIVOT');
        $this->expectOne($totalSalePivots, 'TOTAL_SALE_PIVOT');
        $customerPivot = $customerPivots->first();
        $totalSalePivot = $totalSalePivots->first();

        $details = JournalVoucherDetail::where('journal_voucher_id', $voucher->id)->lockForUpdate()->get();
        $customerDetails = $details->where('account_id', $customerPivot->id)->values();
        $totalSaleDetails = $details->where('account_id', $totalSalePivot->id)->values();
        $this->expectOne($customerDetails, 'CUSTOMER_DETAIL');
        $this->expectOne($totalSaleDetails, 'TOTAL_SALE_DETAIL');
        if ($details->count() !== 2) {
            throw new DomainException('UNEXPECTED_VOUCHER_LINES');
        }

        $ledgers = Ledger::where('type', 'SV')->where('type_id', $voucher->id)
            ->where('voucher_number', $voucher->voucher_number)->where('reference', $booking->id)->lockForUpdate()->get();
        $customerLedgerLines = $ledgers->where('project_head_subheads_id', $customerPivot->id)->values();
        $totalSaleLedgerLines = $ledgers->where('project_head_subheads_id', $totalSalePivot->id)->values();
        $this->expectOne($customerLedgerLines, 'CUSTOMER_LEDGER_LINE');
        $this->expectOne($totalSaleLedgerLines, 'TOTAL_SALE_LEDGER_LINE');
        if ($ledgers->count() !== 2) {
            throw new DomainException('UNEXPECTED_VOUCHER_LINES');
        }

        $customerDetail = $customerDetails->first();
        $totalSaleDetail = $totalSaleDetails->first();
        $customerLedgerLine = $customerLedgerLines->first();
        $totalSaleLedgerLine = $totalSaleLedgerLines->first();
        if (!$customerLedgerLine->customer_ledger_id) {
            throw new DomainException('INITIAL_CUSTOMER_LEDGER_INVALID');
        }
        $customerLedger = CustomerLedger::whereKey($customerLedgerLine->customer_ledger_id)->lockForUpdate()->first();
        $shared = Ledger::where('customer_ledger_id', $customerLedgerLine->customer_ledger_id)
            ->whereNotIn('id', [$customerLedgerLine->id, $totalSaleLedgerLine->id])->lockForUpdate()->exists();

        $zeroes = [$customerDetail->debit, $totalSaleDetail->credit, $customerLedgerLine->amount_in, $totalSaleLedgerLine->amount_out, $customerLedger?->amount_out];
        foreach ($zeroes as $zero) {
            if ($zero === null || bccomp($this->decimal($zero), '0.00', 2) !== 0) {
                throw new DomainException('ACCOUNTING_DIRECTION_INVALID');
            }
        }
        if (!$customerLedger || $shared || (string) $customerLedger->transaction_type !== 'Bo'
            || (int) $customerLedger->project_id !== (int) $booking->project_id
            || (int) $customerLedger->customer_id !== (int) $booking->customer_id
            || (int) $customerLedger->plot_id !== (int) $booking->plot_id) {
            throw new DomainException('INITIAL_CUSTOMER_LEDGER_INVALID');
        }

        $amounts = [$customerLedger->amount_in, $customerDetail->credit, $totalSaleDetail->debit, $customerLedgerLine->amount_out, $totalSaleLedgerLine->amount_in];
        $principal = $this->decimal($amounts[0]);
        foreach (array_slice($amounts, 1) as $amount) {
            if (bccomp($principal, $this->decimal($amount), 2) !== 0) {
                throw new DomainException('ACCOUNTING_PRINCIPAL_INCONSISTENT');
            }
        }

        $sums = $this->detailSums($details);
        if (bccomp($sums['debit'], $sums['credit'], 2) !== 0
            || bccomp($this->decimal($voucher->total_debit), $sums['debit'], 2) !== 0
            || bccomp($this->decimal($voucher->total_credit), $sums['credit'], 2) !== 0) {
            throw new DomainException('VOUCHER_UNBALANCED');
        }

        return compact('voucher', 'details', 'customerDetail', 'totalSaleDetail', 'customerLedgerLine', 'totalSaleLedgerLine', 'customerLedger', 'principal');
    }

    private function assertPostUpdate(Booking $booking, array $records, string $total): void
    {
        $values = [
            $booking->fresh()->total_price,
            $records['customerLedger']->fresh()->amount_in,
            $records['customerDetail']->fresh()->credit,
            $records['totalSaleDetail']->fresh()->debit,
            $records['customerLedgerLine']->fresh()->amount_out,
            $records['totalSaleLedgerLine']->fresh()->amount_in,
        ];
        foreach ($values as $value) {
            if (bccomp($this->decimal($value), $total, 2) !== 0) {
                throw new DomainException('POST_UPDATE_PRINCIPAL_MISMATCH');
            }
        }
        $voucher = $records['voucher']->fresh();
        $details = JournalVoucherDetail::where('journal_voucher_id', $voucher->id)->lockForUpdate()->get();
        $sums = $this->detailSums($details);
        if (bccomp($this->decimal($voucher->total_debit), $sums['debit'], 2) !== 0
            || bccomp($this->decimal($voucher->total_credit), $sums['credit'], 2) !== 0
            || bccomp($sums['debit'], $sums['credit'], 2) !== 0) {
            throw new DomainException('POST_UPDATE_VOUCHER_UNBALANCED');
        }
    }

    private function bookingPricingMatches(Booking $booking, array $requested, string $total): bool
    {
        foreach (['plot_rate', 'park_facing', 'carner_price', 'dicount_value'] as $field) {
            $expected = $field === 'park_facing' && (int) $requested['is_park'] === 0 ? '0' : ($field === 'carner_price' && (int) $requested['is_corner'] === 0 ? '0' : $requested[$field]);
            if (bccomp($this->decimal($booking->{$field}), $this->decimal($expected), 2) !== 0) {
                return false;
            }
        }
        return (int) $booking->is_park === (int) $requested['is_park']
            && (int) $booking->is_corner === (int) $requested['is_corner']
            && bccomp($this->decimal($booking->total_price), $total, 2) === 0;
    }

    private function expectOne(Collection $rows, string $name): void
    {
        if ($rows->count() !== 1) {
            throw new DomainException($rows->isEmpty() ? $name . '_MISSING' : $name . '_DUPLICATE');
        }
    }

    private function detailSums(Collection $details): array
    {
        $debit = '0.00';
        $credit = '0.00';
        foreach ($details as $detail) {
            $debit = bcadd($debit, (string) $detail->debit, 2);
            $credit = bcadd($credit, (string) $detail->credit, 2);
        }
        return compact('debit', 'credit');
    }

    private function decimal($value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }
}
