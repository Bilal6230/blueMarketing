<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class BookingCrossFlowLockingTest extends TestCase
{
    public function test_competing_flows_acquire_booking_lock_before_dependent_mutation(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/BookingController.php');
        $pricing = file_get_contents(__DIR__ . '/../../app/Services/Booking/BookingPricingUpdateService.php');

        $this->assertOrdered($this->method($controller, 'storePaymentSchedule'), [
            'DB::transaction(', 'Booking::query()', '->lockForUpdate()', 'bccomp($submittedTotal, $lockedTotal, 2)', "BookingDetail::where('booking_id'", 'BookingDetail::create(',
        ]);
        $this->assertOrdered($this->method($controller, 'deposit'), [
            'DB::transaction(', '$bookings = Booking::where(', '->lockForUpdate()', 'CustomerLedger::create(',
        ]);
        $this->assertOrdered($this->method($controller, 'update'), [
            'DB::transaction(', '$existingBooking = Booking::query()', '->lockForUpdate()', '$this->validateFileTransferSnapshot(', 'Booking::updateOrCreate(', '$this->postFileTransferEntries(', '$this->attechCustomerToOldProjectSale(',
        ]);
        $this->assertOrdered($this->method($controller, 'cancel'), [
            'DB::transaction(', '$booking = Booking::where(', '->lockForUpdate()', '$plot = Plot::where(',
        ]);
        $this->assertOrdered($this->method($pricing, 'updatePristineBooking'), [
            'DB::transaction(', '$booking = Booking::query()', '->lockForUpdate()', '$this->assertLifecycleIsPristine($booking)',
        ]);
    }

    public function test_file_transfer_form_submits_the_complete_old_booking_snapshot(): void
    {
        $view = file_get_contents(__DIR__ . '/../../resources/views/admin/booking/file_transfer.blade.php');
        foreach ([
            'updated_at', 'project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size',
            'plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price',
            'dicount_value', 'total_price', 'booking_date', 'status', 'broker_id',
        ] as $field) {
            $this->assertStringContainsString('name="expected_' . $field . '"', $view);
        }
    }

    private function method(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertNotFalse($start, $name . ' was not found');
        $next = strpos($source, "\n    public function ", $start + 10);
        return substr($source, $start, $next === false ? null : $next - $start);
    }

    private function assertOrdered(string $source, array $needles): void
    {
        $position = -1;
        foreach ($needles as $needle) {
            $next = strpos($source, $needle, $position + 1);
            $this->assertNotFalse($next, $needle . ' was not found in the expected flow');
            $this->assertGreaterThan($position, $next, $needle . ' is out of lock order');
            $position = $next;
        }
    }
}
