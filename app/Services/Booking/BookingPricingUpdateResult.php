<?php

namespace App\Services\Booking;

final class BookingPricingUpdateResult
{
    public function __construct(
        public bool $changed,
        public string $oldBookingTotal,
        public string $oldAccountingPrincipal,
        public string $newTotal,
        public string $deltaFromBooking,
        public string $deltaFromAccounting,
        public ?string $operation
    ) {
    }

    public function toArray(): array
    {
        return [
            'changed' => $this->changed,
            'old_booking_total' => $this->oldBookingTotal,
            'old_accounting_principal' => $this->oldAccountingPrincipal,
            'new_total' => $this->newTotal,
            'delta_from_booking' => $this->deltaFromBooking,
            'delta_from_accounting' => $this->deltaFromAccounting,
            'operation' => $this->operation,
        ];
    }
}
