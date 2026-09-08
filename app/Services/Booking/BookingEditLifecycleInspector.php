<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingVoucher;
use App\Models\CustomerLedger;
use App\Models\JournalVoucher;
use App\Models\Ledger;

final class BookingEditLifecycleInspector
{
    public function __construct(private BookingSalesAccountingResolver $accountingResolver)
    {
    }

    public function inspect(Booking $booking): BookingEditLifecycleResult
    {
        $scheduleCount = BookingDetail::where('booking_id', $booking->id)->count();
        $hasPrimaryPayment = BookingVoucher::query()
            ->where('booking_id', $booking->id)
            ->where('voucher_series', 'PPR')
            ->exists();
        $hasLegacyPayment = CustomerLedger::query()
            ->where('project_id', $booking->project_id)
            ->where('customer_id', $booking->customer_id)
            ->where('plot_id', $booking->plot_id)
            ->where('transaction_type', 'PPR')
            ->exists();
        $hasTransfer = JournalVoucher::query()
            ->where('project_id', $booking->project_id)
            ->where('type', 'SV')
            ->where('reference', 'TRANSFER-' . $booking->id)
            ->exists();
        $hasResale = Ledger::query()
            ->where('reference', $booking->id)
            ->where('detail', 'like', 'RESALE_PROFIT#' . $booking->id . '%')
            ->exists();
        $isCancelled = (string) $booking->cancel_status === '1';
        $isDeleted = $booking->trashed();
        $accounting = $this->accountingResolver->resolve($booking);

        $reasons = $accounting->blockReasons;
        if ($isCancelled) {
            $reasons[] = 'BOOKING_CANCELLED';
        }
        if ($isDeleted) {
            $reasons[] = 'BOOKING_DELETED';
        }
        if ($scheduleCount > 0) {
            $reasons[] = 'SCHEDULE_EXISTS';
        }
        if ($hasPrimaryPayment || $hasLegacyPayment) {
            $reasons[] = 'PAYMENT_ACTIVITY_EXISTS';
        }
        if ($hasTransfer) {
            $reasons[] = 'TRANSFER_HISTORY_EXISTS';
        }
        if ($hasResale || ($accounting->data['unexpected_voucher_line_count'] ?? 0) > 0) {
            $reasons[] = 'RESALE_HISTORY_EXISTS';
        }
        $reasons = array_values(array_unique($reasons));

        return new BookingEditLifecycleResult([
            'is_cancelled' => $isCancelled,
            'is_deleted' => $isDeleted,
            'has_schedule' => $scheduleCount > 0,
            'schedule_row_count' => $scheduleCount,
            'has_payment_activity' => $hasPrimaryPayment || $hasLegacyPayment,
            'has_transfer_history' => $hasTransfer,
            'has_resale_history' => $hasResale || ($accounting->data['unexpected_voucher_line_count'] ?? 0) > 0,
            'original_voucher_count' => $accounting->data['voucher_count'],
            'original_voucher_status' => $accounting->data['voucher_status'],
            'has_accounting_ambiguity' => $accounting->hasStructuralAmbiguity,
            'amounts_consistent' => $accounting->amountsConsistent,
            'accounting' => $accounting->toArray(),
            'pricing_edit_allowed' => count($reasons) === 0,
            'pricing_block_reasons' => $reasons,
            'metadata_edit_allowed' => !$isCancelled && !$isDeleted,
        ]);
    }
}
