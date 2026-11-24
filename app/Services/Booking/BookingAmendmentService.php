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

    /**
     * Keep the booking's original BO source and the two SV base legs in sync.
     * Any extra resale-profit split belongs to its own source and is untouched.
     * Fail closed if the historic source/leg identity cannot be proven.
     */
    private function syncOriginalSale(int $projectId, int $bookingId,
        string $oldTotal, string $newTotal, string $date): void
    {
        $source=DB::table('customer_ledger')->where('project_id',$projectId)
            ->where('transaction_type','Bo')->where('plot_id',
                DB::table('bookings')->where('id',$bookingId)->value('plot_id'))
            ->where('is_active',1)->lockForUpdate()->get();
        $voucher=DB::table('journal_vouchers')->where('project_id',$projectId)
            ->where('type','SV')->where('reference','BOOKING-'.$bookingId)
            ->whereNull('deleted_at')->lockForUpdate()->first();
        if(!$voucher)throw new DomainException('BOOKING_SV_SOURCE_MISSING');
        // BO rows can exist for a resold plot. Resolve only by the SV
        // detail-linked customer ledger, not by a bare project/plot match.
        $base=DB::table('ledgers')->where('type','SV')->where('type_id',$voucher->id)
            ->where('is_active',1)->whereNotNull('journal_voucher_detail_id')
            ->lockForUpdate()->get();
        $groups=$base->groupBy('customer_ledger_id')->filter(function($legs)use($oldTotal){
            return $legs->count()===2
                &&bccomp((string)$legs->sum('amount_in'),$oldTotal,2)===0
                &&bccomp((string)$legs->sum('amount_out'),$oldTotal,2)===0;
        });
        if($groups->count()!==1)throw new DomainException('BOOKING_SV_SOURCE_AMBIGUOUS');
        $legs=$groups->first();
        $customerId=(int)$legs->first()->customer_ledger_id;
        $bo=DB::table('customer_ledger')->where('id',$customerId)
            ->where('project_id',$projectId)->where('transaction_type','Bo')
            ->where('is_active',1)->lockForUpdate()->first();
        if(!$bo||bccomp((string)$bo->amount_in,$oldTotal,2)!==0
            ||bccomp((string)$bo->amount_out,'0',2)!==0)
            throw new DomainException('BOOKING_BO_SOURCE_AMBIGUOUS');
        if($legs->filter(fn($leg)=>(float)$leg->amount_in>0)->count()!==1
            ||$legs->filter(fn($leg)=>(float)$leg->amount_out>0)->count()!==1)
            throw new DomainException('BOOKING_SV_SOURCE_AMBIGUOUS');
        foreach($legs as $leg){
            $detail=DB::table('journal_voucher_details')
                ->where('journal_voucher_id',$voucher->id)
                ->where('id',$leg->journal_voucher_detail_id)
                ->whereNull('deleted_at')->lockForUpdate()->first();
            if(!$detail
                ||bccomp((string)$detail->debit,(string)$leg->amount_in,2)!==0
                ||bccomp((string)$detail->credit,(string)$leg->amount_out,2)!==0)
                throw new DomainException('BOOKING_SV_DETAIL_MISMATCH');
        }
        $now=now();
        DB::table('customer_ledger')->where('id',$customerId)
            ->update(['amount_in'=>$newTotal,'date'=>$date,'updated_at'=>$now]);
        foreach($legs as $leg){
            $debit=(float)$leg->amount_in>0;
            DB::table('ledgers')->where('id',$leg->id)
                ->update(['amount_in'=>$debit?$newTotal:'0.00',
                    'amount_out'=>$debit?'0.00':$newTotal,
                    'date'=>$date,'updated_at'=>$now]);
            DB::table('journal_voucher_details')->where('id',$leg->journal_voucher_detail_id)
                ->update(['debit'=>$debit?$newTotal:'0.00',
                    'credit'=>$debit?'0.00':$newTotal,'updated_at'=>$now]);
        }
        $totals=DB::table('journal_voucher_details')
            ->where('journal_voucher_id',$voucher->id)->whereNull('deleted_at')
            ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')->first();
        if(bccomp((string)$totals->debit,(string)$totals->credit,2)!==0)
            throw new DomainException('BOOKING_SV_UNBALANCED');
        DB::table('journal_vouchers')->where('id',$voucher->id)
            ->update(['date'=>$date,'total_debit'=>$totals->debit,
                'total_credit'=>$totals->credit,'updated_at'=>$now]);
    }

    public function update(int $bookingId, int $projectId, array $input, bool $mayBroker, bool $mayPrice, ?int $userId): array
    {
        return DB::transaction(function () use ($bookingId, $projectId, $input, $mayBroker, $mayPrice, $userId) {
            $booking = Booking::where('project_id', $projectId)->where('cancel_status', '0')
                ->lockForUpdate()->findOrFail($bookingId);
            if ($booking->trashed()) {
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
            foreach (['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size'] as $field) {
                if (!array_key_exists($field, $input)) {
                    throw new DomainException('STALE_BOOKING');
                }
                $actual = (string) $booking->{$field};
                if ((string) $input[$field] !== $actual) {
                    throw new DomainException('STALE_BOOKING');
                }
            }
            $oldBookingDate = Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s');
            $expectedBookingDate = Carbon::parse($input['expected_booking_date'])->format('Y-m-d H:i:s');
            $oldStatus = (string) $booking->status;
            if ($oldBookingDate !== $expectedBookingDate || $oldStatus !== (string) $input['expected_status']) {
                throw new DomainException('STALE_BOOKING');
            }
            $newBookingDate = Carbon::parse($input['booking_date'])->format('Y-m-d');
            $newStatus = (string) $input['status'];
            $dateChanged = Carbon::parse($booking->booking_date)->format('Y-m-d') !== $newBookingDate;
            $statusChanged = $oldStatus !== $newStatus;
            if (($dateChanged || $statusChanged) && !$mayBroker) {
                throw new DomainException('METADATA_PERMISSION_REQUIRED');
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
            if ($brokerChanged && $newBroker !== null && !ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
                ->where('head_accounting_id', 6)->where('subhead_accounting_id', $newBroker)->exists()) {
                throw new DomainException('BROKER_NOT_AVAILABLE_IN_PROJECT');
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
            if ($pricingChanged && $oldStatus !== 'active') {
                throw new DomainException('BOOKING_NOT_ACTIVE');
            }
            if (!$pricingChanged && !$brokerChanged && !$dateChanged && !$statusChanged) {
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
            if ($dateChanged) {
                $booking->booking_date = $newBookingDate;
            }
            if ($statusChanged) {
                $booking->status = $newStatus;
            }
            if ($pricingChanged) {
                foreach ($requested as $field => $value) {
                    $booking->{$field} = $value;
                }
                $booking->total_price = $newTotal;
            }
            $booking->save();
            if($pricingChanged && bccomp($oldTotal,$newTotal,2)!==0){
                $this->syncOriginalSale($projectId,(int)$booking->id,
                    $oldTotal,$newTotal,$newBookingDate);
            }

            BookingEditAudit::create([
                'booking_id' => $booking->id,
                'project_id' => $projectId,
                'user_id' => $userId,
                'operation' => $pricingChanged ? 'booking_price_amendment' : ($brokerChanged ? 'broker_update' : 'metadata_update'),
                'reason' => $pricingChanged ? trim((string) $input['reason']) : null,
                'old_values' => ['broker_id' => $oldBroker, 'booking_date' => $oldBookingDate, 'status' => $oldStatus,
                    'pricing' => $oldPricing, 'paid_to_date' => $paid,
                    'old_outstanding' => $paid === null ? null : (bccomp($oldTotal, $paid, 2) === 1 ? bcsub($oldTotal, $paid, 2) : '0.00'),
                    'schedule_before' => $scheduleBefore],
                'new_values' => ['broker_id' => $newBroker, 'booking_date' => $newBookingDate, 'status' => $newStatus,
                    'pricing' => $pricingChanged ? array_merge($requested, ['total_price' => $newTotal]) : $oldPricing,
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
