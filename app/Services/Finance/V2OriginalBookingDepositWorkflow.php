<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalBookingDepositWorkflow
{
    public function create(int $project, string $series, int $customer, int $plot, array $data, ?int $actor): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        return DB::transaction(function () use ($project, $series, $customer, $plot, $data, $actor): array {
            DB::table('projects')->where('id', $project)->lockForUpdate()->firstOrFail();
            $bookings = \App\Models\Booking::query()->where('project_id', $project)->where('customer_id', $customer)
                ->where('plot_id', $plot)->where('status', 'active')->where('cancel_status', '0')->lockForUpdate()->get();
            if ($bookings->count() !== 1 || !\App\Models\Plot::query()->whereKey($plot)->where('project_id', $project)->lockForUpdate()->first(['id'])
                || !\App\Models\Lead::query()->whereKey($customer)->where('project_id', $project)->exists()) {
                throw new \DomainException('V2_BOOKING_DEPOSIT_CONTEXT_INVALID');
            }
            $booking = $bookings->first(); $charge = null;
            if ($series === 'EC' && (!\App\Models\ChargeType::whereKey($data['charge_type_id'])->exists() || !in_array((int) $data['payment_type'], [0, 1], true))) {
                throw new \DomainException('V2_CHARGE_TYPE_OR_PAYMENT_INVALID');
            }
            if ($series === 'ECP') {
                $charge = \App\Models\CustomerLedger::query()->whereKey($data['charge_id'])->where('project_id', $project)
                    ->where('customer_id', $customer)->where('plot_id', $plot)->where('transaction_type', 'EC')->where('is_active', 1)->lockForUpdate()->first();
                if (!$charge || !DB::table('finance_extra_charge_sources')->where('customer_ledger_id', $charge->id)->where('booking_id', $booking->id)->exists()) {
                    throw new \DomainException('V2_PAYMENT_CHARGE_NOT_VERIFIED');
                }
                $retry = DB::table('finance_original_customer_requests')->where('project_id', $project)->where('request_key', $data['request_key'])->exists();
                $amount = (int) str_replace('.', '', V2OriginalCustomerWorkflow::money($data['amount']));
                if (!$retry && $amount > (new V2ExtraChargeBalance())->read($charge)['available_cents']) {
                    throw new \DomainException('V2_PAYMENT_EXCEEDS_CHARGE');
                }
            }
            $source = V2OriginalVoucherInput::source($data) + ['customer_id' => $customer, 'plot_id' => $plot,
                'request_context' => ['booking_id' => (int) $booking->id, 'charge_type_id' => $data['charge_type_id'] ?? null, 'charge_id' => $data['charge_id'] ?? null]];
            if ($charge) { $source['reference'] = 'EC_PAYMENT#'.$charge->id.'#'.$data['reference']; }
            $result = (new V2OriginalCustomerWorkflow())->create($project, $series, $data['request_key'], $source, $data['posting_lines'] ?? [], $actor);
            $id = $result['source_id'];
            if ($series === 'PPR') {
                $row = DB::table('booking_vouchers')->where('customer_ledger_id', $id)->lockForUpdate()->first();
                if ($row && ((int) $row->booking_id !== (int) $booking->id || (int) $row->project_id !== $project
                    || (int) $row->customer_id !== $customer || (int) $row->plot_id !== $plot || $row->voucher_series !== 'PPR'
                    || V2OriginalCustomerWorkflow::money($row->amount) !== V2OriginalCustomerWorkflow::money($data['amount'])
                    || (string) $row->receipt_date !== $data['date'] || (string) $row->slip_reference !== $data['reference'])) {
                    throw new \DomainException('V2_RECEIPT_CUSTODY_CHANGED');
                }
                if (!$row) {
                    if ($result['replayed']) { throw new \DomainException('V2_RECEIPT_CUSTODY_MISSING'); }
                    DB::table('booking_vouchers')->insert([
                        'booking_id' => $booking->id, 'project_id' => $project, 'customer_id' => $customer, 'plot_id' => $plot,
                        'customer_ledger_id' => $id, 'ledger_id' => null, 'voucher_series' => 'PPR',
                        'voucher_number' => DB::table('customer_ledger')->where('id', $id)->value('type_id'),
                        'amount' => V2OriginalCustomerWorkflow::money($data['amount']), 'receipt_date' => $data['date'],
                        'description' => $data['detail'], 'slip_reference' => $data['reference'], 'payment_type' => $data['payment_type'],
                        'is_active' => 1, 'is_approve' => $result['pending_bank'] ? 0 : 1,
                        'create_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
                    ] + array_intersect_key($data, array_flip(['bank_id', 't_number', 'passing_date'])));
                }
            } else {
                $table = $series === 'EC' ? 'finance_extra_charge_sources' : 'finance_extra_charge_payment_sources';
                $link = $series === 'EC' ? ['booking_id' => (int) $booking->id, 'charge_type_id' => (int) $data['charge_type_id']]
                    : ['charge_customer_ledger_id' => (int) $charge->id];
                $existing = DB::table($table)->where('customer_ledger_id', $id)->first();
                if ($existing) {
                    foreach ($link as $field => $value) { if ((int) $existing->$field !== $value) { throw new \DomainException('V2_CHARGE_CUSTODY_CHANGED'); } }
                } else {
                    if ($result['replayed']) { throw new \DomainException('V2_CHARGE_CUSTODY_MISSING'); }
                    DB::table($table)->insert($link + ['customer_ledger_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            return $result;
        }, 3);
    }
}
