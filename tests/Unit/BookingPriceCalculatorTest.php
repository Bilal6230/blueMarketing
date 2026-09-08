<?php

namespace Tests\Unit;

use App\Services\Booking\BookingPriceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BookingPriceCalculatorTest extends TestCase
{
    private BookingPriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BookingPriceCalculator();
    }

    /** @dataProvider validCalculations */
    public function test_exact_calculations(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->calculator->calculate(...$input)->toArray());
    }

    public function validCalculations(): array
    {
        return [
            'rate only and two decimals' => [
                ['10', '500000', 0, '99', 0, '88', '0'],
                ['plot_size' => '10.00', 'plot_rate' => '500000.00', 'base_amount' => '5000000.00', 'park_charge' => '0.00', 'corner_charge' => '0.00', 'discount' => '0.00', 'gross_amount' => '5000000.00', 'total_price' => '5000000.00'],
            ],
            'discount' => [
                ['10', '500000', 0, '0', 0, '0', '25,000.50'],
                ['plot_size' => '10.00', 'plot_rate' => '500000.00', 'base_amount' => '5000000.00', 'park_charge' => '0.00', 'corner_charge' => '0.00', 'discount' => '25000.50', 'gross_amount' => '5000000.00', 'total_price' => '4974999.50'],
            ],
            'park and corner combined' => [
                ['5.50', '1,000,000.25', 1, '100,000.10', 1, '75,000.20', '50,000.05'],
                ['plot_size' => '5.50', 'plot_rate' => '1000000.25', 'base_amount' => '5500001.37', 'park_charge' => '100000.10', 'corner_charge' => '75000.20', 'discount' => '50000.05', 'gross_amount' => '5675001.67', 'total_price' => '5625001.62'],
            ],
        ];
    }

    /** @dataProvider invalidInputs */
    public function test_invalid_inputs_are_rejected(array $input, string $code): void
    {
        try {
            $this->calculator->calculate(...$input);
            $this->fail('Expected calculator to reject invalid input.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    public function invalidInputs(): array
    {
        $base = ['10', '500000', 0, '0', 0, '0', '0'];

        return [
            'invalid precision' => [[...$base, 1 => '1.001'], 'PLOT_RATE_INVALID'],
            'negative' => [[...$base, 3 => '-1'], 'PARK_FACING_INVALID'],
            'scientific notation' => [[...$base, 1 => '1e5'], 'PLOT_RATE_INVALID'],
            'currency symbol' => [[...$base, 1 => 'Rs. 500'], 'PLOT_RATE_INVALID'],
            'multiple decimal points' => [[...$base, 6 => '1.2.3'], 'DICOUNT_VALUE_INVALID'],
            'invalid commas' => [[...$base, 1 => '10,00'], 'PLOT_RATE_INVALID'],
            'zero rate' => [[...$base, 1 => '0'], 'PLOT_RATE_MUST_BE_POSITIVE'],
            'discount above gross' => [[...$base, 6 => '5000001'], 'DISCOUNT_EXCEEDS_GROSS'],
            'database overflow' => [['10', '99999999.99', 0, '0', 0, '0', '0'], 'BASE_AMOUNT_DB_OVERFLOW'],
            'nonnumeric plot size' => [['10 Marla', '500000', 0, '0', 0, '0', '0'], 'PLOT_SIZE_INVALID'],
        ];
    }

    public function test_client_total_is_not_an_input(): void
    {
        $method = new \ReflectionMethod(BookingPriceCalculator::class, 'calculate');
        $names = array_map(fn (\ReflectionParameter $parameter) => $parameter->getName(), $method->getParameters());

        $this->assertNotContains('totalPrice', $names);
        $this->assertNotContains('total_price', $names);
    }
}
