<?php

namespace App\Services\Booking;

final class BookingEditLifecycleResult
{
    public function __construct(public array $data)
    {
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
