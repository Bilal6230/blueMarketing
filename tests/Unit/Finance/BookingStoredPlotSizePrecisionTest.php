<?php

namespace Tests\Unit\Finance;

use App\Services\Booking\BookingPriceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BookingStoredPlotSizePrecisionTest extends TestCase
{
    public function test_booking_241_four_decimal_stored_plot_size_calculates_original_price(): void
    {
        $result=(new BookingPriceCalculator())->calculate(
            '2.0000', '2500000.00', '1', '0.00',
            '1', '0.00', '20000.00'
        );
        self::assertSame('2.0000',$result->plotSize);
        self::assertSame('5000000.00',$result->baseAmount);
        self::assertSame('4980000.00',$result->totalPrice);
    }

    public function test_fractional_plot_size_retains_four_decimal_precision_in_total(): void
    {
        $result=(new BookingPriceCalculator())->calculate(
            '2.1250','2500000.00',0,'0',0,'0','0'
        );
        self::assertSame('2.1250',$result->plotSize);
        self::assertSame('5312500.00',$result->totalPrice);
    }

    public function test_money_still_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PLOT_RATE_INVALID');
        (new BookingPriceCalculator())->calculate(
            '2.0000','2500000.001',0,'0',0,'0','0'
        );
    }

    public function test_plot_size_rejects_more_than_four_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PLOT_SIZE_INVALID');
        (new BookingPriceCalculator())->calculate(
            '2.00001','2500000.00',0,'0',0,'0','0'
        );
    }
}
