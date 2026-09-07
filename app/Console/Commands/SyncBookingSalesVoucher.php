<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingSalesVoucherSyncService;
use Illuminate\Console\Command;

class SyncBookingSalesVoucher extends Command
{
    protected $signature = 'accounting:sync-booking-sales-voucher {--booking= : Booking ID} {--dry-run : Report without writing}';

    protected $description = 'Synchronize one BOOKING Sales Voucher with bookings.total_price';

    public function handle(BookingSalesVoucherSyncService $service): int
    {
        $bookingId = filter_var($this->option('booking'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$bookingId) {
            $this->error('A positive --booking ID is required.');
            return self::INVALID;
        }

        $booking = Booking::find($bookingId);
        if (!$booking) {
            $this->error("Booking {$bookingId} was not found.");
            return self::FAILURE;
        }

        $before = $service->inspect($booking);
        $this->table(['Booking', 'Voucher', 'Current debit', 'Current credit', 'Expected'], [[
            $booking->id,
            $before['voucher_number'] ?? 'missing',
            $before['total_debit'] ?? 'missing',
            $before['total_credit'] ?? 'missing',
            $before['expected'],
        ]]);

        if ($this->option('dry-run')) {
            $this->info('Dry run: no records were changed.');
            return self::SUCCESS;
        }

        try {
            $after = $service->sync($booking);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Synchronized BOOKING-{$booking->id} to {$after['expected']}; voucher {$after['voucher_number']} was preserved.");
        return self::SUCCESS;
    }
}
