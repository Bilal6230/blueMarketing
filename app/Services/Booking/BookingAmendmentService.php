<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingEditAudit;
use App\Models\ProjectHeadSubhead;
use DomainException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class BookingAmendmentService
{
    public function __construct(
        private BookingPriceCalculator $calculator,
        private BookingPaidAmountResolver $payments
    ) {
    }

    public function update(int $bookingId, int $projectId, array $input, bool $mayBroker, bool $mayPrice, ?int $userId): array
    {
        return DB::transaction(function () use ($bookingId, $projectId, $input, $mayBroker, $mayPrice, $userId) {
            $booking = Booking::where('project_id', $projectId)->where('cancel_status', '0')
                ->lockForUpdate()->findOrFail($bookingId);
            if ($booking->trashed() || (string) $booking->status !== 'active') {
                throw new DomainException('BOOKING_NOT_ACTIVE');
            }

            $actualVersion = $booking->updated_at?->format('Y-m-d H:i:s.u');
            $expectedVersion = ($input['expected_updated_at'] ?? null) ?: null;
            if ($actualVersion !== $expectedVersion) {
                throw new DomainException('STALE_BOOKING');
            }

            $oldBroker = $booking->broker_id === null ? null : (int) $booking->broker_id;
            $newBroker = empty($input['broker_id']) ? null : (int) $input['broker_id'];
            if ($oldBroker !== (empty($input['expected_broker_id']) ? null : (int) $input['expected_broker_id'])) {
                throw new DomainException('STALE_BOOKING');
            }
            foreach (['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size', 'booking_date', 'status'] as $field) {
                if (!array_key_exists($field, $input)) {
                    throw new DomainException('STALE_BOOKING');
                }
                $actual = $field === 'booking_date'
                    ? Carbon::parse($booking->{$field})->format('Y-m-d H:i:s')
                    : (string) $booking->{$field};
                if ((string) $input[$field] !== $actual) {
                    throw new DomainException('STALE_BOOKING');
                }
            }

            $fields = ['plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'];
            foreach ($fields as $field) {
                if (!array_key_exists('expected_' . $field, $input)) {
                    throw new DomainException('STALE_BOOKING');
                }
                $actual = (string) $booking->{$field};
                $expected = (string) $input['expected_' . $field];
                if (in_array($field, ['is_park', 'is_corner'], true)
                    ? (int) $actual !== (int) $expected
                    : bccomp($actual, $expected, 2) !== 0) {
                    throw new DomainException('STALE_BOOKING');
                }
            }

            $brokerChanged = $oldBroker !== $newBroker;
            if ($brokerChanged && !$mayBroker) {
                throw new DomainException('BROKER_PERMISSION_REQUIRED');
            }
            if ($brokerChanged && $newBroker !== null && !ProjectHeadSubhead::where('project_id', $projectId)
                ->where('head_accounting_id', 6)->where('subhead_accounting_id', $newBroker)->exists()) {
                throw new DomainException('BROKER_NOT_IN_PROJECT');
            }

            $oldPricing = [];
            foreach ($fields as $field) {
                $oldPricing[$field] = $booking->{$field};
            }
            $requested = [];
            foreach (array_slice($fields, 0, 6) as $field) {
                $requested[$field] = $input[$field] ?? $booking->{$field};
            }
            $pricingChanged = false;
            foreach ($requested as $field => $value) {
                $normalized = in_array($field, ['is_park', 'is_corner'], true)
                    ? ((int) $value !== (int) $booking->{$field})
                    : bccomp(str_replace(',', '', (string) $value), (string) $booking->{$field}, 2) !== 0;
                $pricingChanged = $pricingChanged || $normalized;
            }
            if ($pricingChanged && !$mayPrice) {
                throw new DomainException('PRICING_PERMISSION_REQUIRED');
            }
            if (!$pricingChanged && !$brokerChanged) {
                return ['changed' => false, 'price_changed' => false, 'schedule_reset' => false, 'refund_due' => '0.00'];
            }

            $newTotal = (string) $booking->total_price;
            $paid = null;
            $outstanding = null;
            $refund = '0.00';
            $scheduleBefore = [];
            $scheduleReset = false;
            if ($pricingChanged) {
                if (trim((string) ($input['reason'] ?? '')) === '') {
                    throw new DomainException('AMENDMENT_REASON_REQUIRED');
                }
                $calculation = $this->calculator->calculate(
                    (string) $booking->plot_size, $requested['plot_rate'], $requested['is_park'],
                    $requested['park_facing'], $requested['is_corner'], $requested['carner_price'], $requested['dicount_value']
                );
                $newTotal = $calculation->totalPrice;
                $requested['plot_rate'] = $calculation->plotRate;
                $requested['park_facing'] = $calculation->parkCharge;
                $requested['carner_price'] = $calculation->cornerCharge;
                $requested['dicount_value'] = $calculation->discount;
                $receiptResult = $this->payments->resolve($booking, true);
                $paid = $receiptResult['paid_to_date'];
                if (!array_key_exists('expected_paid_to_date', $input)
                    || bccomp((string) $input['expected_paid_to_date'], $paid, 2) !== 0) {
                    throw new DomainException('STALE_BOOKING');
                }
                $outstanding = bccomp($newTotal, $paid, 2) === 1 ? bcsub($newTotal, $paid, 2) : '0.00';
                $refund = bccomp($paid, $newTotal, 2) === 1 ? bcsub($paid, $newTotal, 2) : '0.00';
                if (bccomp($newTotal, (string) $booking->total_price, 2) !== 0) {
                    $rows = BookingDetail::where('booking_id', $booking->id)->lockForUpdate()->get();
                    $scheduleBefore = $rows->map(fn ($row) => $row->only(['id', 'installment_details', 'amount', 'due_date']))->all();
                    BookingDetail::where('booking_id', $booking->id)->delete();
                    $scheduleReset = count($scheduleBefore) > 0;
                }
            }

            $oldTotal = (string) $booking->total_price;
            $booking->broker_id = $newBroker;
            if ($pricingChanged) {
                foreach ($requested as $field => $value) {
                    $booking->{$field} = $value;
                }
                $booking->total_price = $newTotal;
            }
            $booking->save();

            BookingEditAudit::create([
                'booking_id' => $booking->id,
                'project_id' => $projectId,
                'user_id' => $userId,
                'operation' => $pricingChanged ? 'booking_price_amendment' : 'broker_update',
                'reason' => $pricingChanged ? trim((string) $input['reason']) : null,
                'old_values' => ['broker_id' => $oldBroker, 'pricing' => $oldPricing, 'paid_to_date' => $paid,
                    'old_outstanding' => $paid === null ? null : (bccomp($oldTotal, $paid, 2) === 1 ? bcsub($oldTotal, $paid, 2) : '0.00'),
                    'schedule_before' => $scheduleBefore],
                'new_values' => ['broker_id' => $newBroker, 'pricing' => $pricingChanged ? array_merge($requested, ['total_price' => $newTotal]) : $oldPricing,
                    'paid_to_date' => $paid, 'new_outstanding' => $outstanding, 'refund_due' => $refund,
                    'schedule_reset' => $scheduleReset, 'schedule_after' => []],
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'delta' => bcsub($newTotal, $oldTotal, 2),
            ]);

            return ['changed' => true, 'price_changed' => $pricingChanged, 'schedule_reset' => $scheduleReset, 'refund_due' => $refund,
                'paid_to_date' => $paid, 'new_outstanding' => $outstanding];
        });
    }
}
