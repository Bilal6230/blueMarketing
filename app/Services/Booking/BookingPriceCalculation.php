<?php

namespace App\Services\Booking;

final class BookingPriceCalculation
{
    public function __construct(
        public string $plotSize,
        public string $plotRate,
        public string $baseAmount,
        public string $parkCharge,
        public string $cornerCharge,
        public string $discount,
        public string $grossAmount,
        public string $totalPrice
    ) {
    }

    public function toArray(): array
    {
        return [
            'plot_size' => $this->plotSize,
            'plot_rate' => $this->plotRate,
            'base_amount' => $this->baseAmount,
            'park_charge' => $this->parkCharge,
            'corner_charge' => $this->cornerCharge,
            'discount' => $this->discount,
            'gross_amount' => $this->grossAmount,
            'total_price' => $this->totalPrice,
        ];
    }
}
