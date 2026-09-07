<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CustomerLedger;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingSalesVoucherSyncService
{
    public function inspect(Booking $booking): array
    {
        $reference = 'BOOKING-' . $booking->id;
        $voucher = JournalVoucher::query()
            ->where('project_id', $booking->project_id)
            ->where('type', 'SV')
            ->where('reference', $reference)
            ->first();

        return [
            'booking_id' => $booking->id,
            'expected' => $this->money($booking->total_price),
            'voucher_id' => $voucher?->id,
            'voucher_number' => $voucher?->voucher_number,
            'total_debit' => $voucher?->total_debit,
            'total_credit' => $voucher?->total_credit,
            'ledger_count' => $voucher ? Ledger::where('type', 'SV')->where('type_id', $voucher->id)->where('reference', $booking->id)->count() : 0,
            'detail_count' => $voucher ? JournalVoucherDetail::where('journal_voucher_id', $voucher->id)->count() : 0,
        ];
    }

    public function sync(Booking $booking): array
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $amount = $this->money($booking->total_price);
            $reference = 'BOOKING-' . $booking->id;

            $vouchers = JournalVoucher::query()
                ->where('project_id', $booking->project_id)
                ->where('type', 'SV')
                ->where('reference', $reference)
                ->lockForUpdate()
                ->get();

            if ($vouchers->count() !== 1) {
                throw new RuntimeException("Expected exactly one Sales Voucher for {$reference}; found {$vouchers->count()}.");
            }

            $voucher = $vouchers->first();
            $ledgers = Ledger::query()
                ->where('type', 'SV')
                ->where('type_id', $voucher->id)
                ->where('reference', (string) $booking->id)
                ->lockForUpdate()
                ->get();

            $totalSaleLedgers = $ledgers->filter(function (Ledger $ledger) use ($booking) {
                return (int) optional($ledger->projectHeadSubhead)->project_id === (int) $booking->project_id
                    && (int) optional($ledger->projectHeadSubhead)->head_accounting_id === 16
                    && (int) optional($ledger->projectHeadSubhead)->subhead_accounting_id === 123;
            });
            $customerLedgers = $ledgers->filter(function (Ledger $ledger) use ($totalSaleLedgers) {
                return !$totalSaleLedgers->contains('id', $ledger->id)
                    && $ledger->customer_ledger_id !== null
                    && stripos((string) $ledger->detail, 'RESALE_PROFIT#') === false;
            });

            if ($totalSaleLedgers->isEmpty() || $customerLedgers->isEmpty()) {
                throw new RuntimeException("The customer and Total Sale ledger rows for {$reference} could not be identified safely.");
            }

            $totalSaleLedger = $this->retainOne($totalSaleLedgers);
            $customerLedger = $this->retainOne($customerLedgers);

            $this->updateLedger($totalSaleLedger, $amount, '0.00');
            $this->updateLedger($customerLedger, '0.00', $amount);

            $details = JournalVoucherDetail::query()
                ->where('journal_voucher_id', $voucher->id)
                ->whereIn('account_id', [$totalSaleLedger->project_head_subheads_id, $customerLedger->project_head_subheads_id])
                ->lockForUpdate()
                ->get()
                ->groupBy('account_id');

            $saleDetail = $this->retainOne($details->get($totalSaleLedger->project_head_subheads_id, collect()));
            $customerDetail = $this->retainOne($details->get($customerLedger->project_head_subheads_id, collect()));
            $saleDetail->forceFill(['debit' => $amount, 'credit' => '0.00'])->save();
            $customerDetail->forceFill(['debit' => '0.00', 'credit' => $amount])->save();

            $bookingCustomerLedger = CustomerLedger::query()
                ->whereKey($customerLedger->customer_ledger_id)
                ->where('project_id', $booking->project_id)
                ->where('transaction_type', 'Bo')
                ->lockForUpdate()
                ->first();

            if (!$bookingCustomerLedger) {
                throw new RuntimeException("The booking customer ledger for {$reference} could not be identified safely.");
            }

            $bookingCustomerLedger->forceFill(['amount_in' => $amount, 'amount_out' => '0.00'])->save();
            $voucher->forceFill(['total_debit' => $amount, 'total_credit' => $amount])->save();

            return $this->inspect($booking);
        });
    }

    private function retainOne($rows)
    {
        if ($rows->isEmpty()) {
            throw new RuntimeException('A required Sales Voucher accounting row is missing.');
        }

        $keep = $rows->sortBy('id')->first();
        $rows->where('id', '!=', $keep->id)->each->delete();

        return $keep;
    }

    private function updateLedger(Ledger $ledger, string $amountIn, string $amountOut): void
    {
        $ledger->forceFill(['amount_in' => $amountIn, 'amount_out' => $amountOut])->save();
    }

    private function money($value): string
    {
        $value = str_replace(',', '', trim((string) $value));
        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new RuntimeException('Booking total_price is not a valid non-negative decimal amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return $whole . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
