<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingVoucher;
use App\Models\CustomerLedger;
use DomainException;
use Carbon\Carbon;

final class BookingPaidAmountResolver
{
    public function resolve(Booking $booking, bool $lock = false): array
    {
        $links = BookingVoucher::query()
            ->where('booking_id', $booking->id)
            ->where('voucher_series', 'PPR')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $linkedIds = $links->pluck('customer_ledger_id')->filter()->unique()->values();
        $receipts = CustomerLedger::query()
            ->whereIn('id', $linkedIds)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get()->keyBy('id');

        // Legacy rows are not assigned by plot or customer. A possibly relevant
        // unlinked receipt requires manual attribution before a price amendment.
        $unlinked = CustomerLedger::query()
            ->where('project_id', $booking->project_id)
            ->where('plot_id', $booking->plot_id)
            ->where('transaction_type', 'PPR')
            ->where('is_active', 1)
            ->whereDate('date', '>=', Carbon::parse($booking->booking_date)->toDateString())
            ->whereDoesntHave('bookingVoucher', fn ($query) => $query->whereNotNull('booking_id'))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
        if ($unlinked) {
            throw new DomainException('PAYMENT_ATTRIBUTION_REQUIRED');
        }

        $result = [
            'paid_to_date' => '0.00',
            'counted_receipt_ids' => [],
            'excluded_pending_ids' => [],
            'excluded_returned_ids' => [],
            'excluded_bounced_ids' => [],
        ];

        foreach ($links->unique('customer_ledger_id') as $link) {
            $receipt = $receipts->get($link->customer_ledger_id);
            if (!$receipt || (string) $receipt->transaction_type !== 'PPR'
                || (int) $receipt->project_id !== (int) $booking->project_id
                || (int) $receipt->plot_id !== (int) $booking->plot_id) {
                throw new DomainException('PAYMENT_ATTRIBUTION_REQUIRED');
            }
            if ((int) $receipt->is_active !== 1) {
                continue;
            }

            $status = $receipt->passing_status;
            if ((int) $receipt->payment_type === 1 && $status === null) {
                $result['paid_to_date'] = bcadd($result['paid_to_date'], (string) $receipt->amount_out, 2);
                $result['counted_receipt_ids'][] = $receipt->id;
            } elseif (in_array((int) $receipt->payment_type, [2, 3], true) && (int) $status === 1) {
                $result['paid_to_date'] = bcadd($result['paid_to_date'], (string) $receipt->amount_out, 2);
                $result['counted_receipt_ids'][] = $receipt->id;
            } elseif ($status === null || (int) $status === 0) {
                $result['excluded_pending_ids'][] = $receipt->id;
            } elseif ((int) $status === 2) {
                $result['excluded_returned_ids'][] = $receipt->id;
            } elseif ((int) $status === 3) {
                $result['excluded_bounced_ids'][] = $receipt->id;
            } else {
                throw new DomainException('PAYMENT_ATTRIBUTION_REQUIRED');
            }
        }

        return $result;
    }
}
