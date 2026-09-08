<?php

namespace App\Services\Booking;

use InvalidArgumentException;

final class BookingPriceCalculator
{
    private const MAX_DECIMAL_10_2 = '99999999.99';

    public function calculate(
        $storedPlotSize,
        $plotRate,
        $isPark,
        $parkFacing,
        $isCorner,
        $cornerPrice,
        $discountValue
    ): BookingPriceCalculation {
        if (!function_exists('bcmul')) {
            throw new \RuntimeException('BCMath is required for booking price calculations.');
        }

        $size = $this->normalizeDecimal($storedPlotSize, 'plot_size');
        $rate = $this->normalizeDecimal($plotRate, 'plot_rate');
        $parkEnabled = $this->normalizeFlag($isPark, 'is_park');
        $cornerEnabled = $this->normalizeFlag($isCorner, 'is_corner');
        $parkInput = $this->normalizeDecimal($parkFacing ?? '0', 'park_facing');
        $cornerInput = $this->normalizeDecimal($cornerPrice ?? '0', 'carner_price');
        $discount = $this->normalizeDecimal($discountValue ?? '0', 'dicount_value');

        if (bccomp($rate, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('PLOT_RATE_MUST_BE_POSITIVE');
        }

        $park = $parkEnabled ? $parkInput : '0.00';
        $corner = $cornerEnabled ? $cornerInput : '0.00';
        $base = $this->applyUnconfirmedMoneyScalePolicy(bcmul($size, $rate, 4));
        $gross = bcadd(bcadd($base, $park, 2), $corner, 2);

        $this->assertDatabaseRange($base, 'base_amount');
        $this->assertDatabaseRange($gross, 'gross_amount');

        if (bccomp($discount, $gross, 2) === 1) {
            throw new InvalidArgumentException('DISCOUNT_EXCEEDS_GROSS');
        }

        $total = bcsub($gross, $discount, 2);
        if (bccomp($total, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('TOTAL_MUST_BE_POSITIVE');
        }
        $this->assertDatabaseRange($total, 'total_price');

        return new BookingPriceCalculation($size, $rate, $base, $park, $corner, $discount, $gross, $total);
    }

    private function normalizeFlag($value, string $field): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw new InvalidArgumentException(strtoupper($field) . '_INVALID');
    }

    private function normalizeDecimal($value, string $field): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException(strtoupper($field) . '_INVALID');
        }

        $raw = trim((string) $value);
        $plain = '/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/';
        $grouped = '/^(?:[1-9]\d{0,2})(?:,\d{3})+(?:\.\d{1,2})?$/';

        if (!preg_match($plain, $raw) && !preg_match($grouped, $raw)) {
            throw new InvalidArgumentException(strtoupper($field) . '_INVALID');
        }

        $normalized = str_replace(',', '', $raw);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        if (strlen($whole) > 8) {
            throw new InvalidArgumentException(strtoupper($field) . '_DB_OVERFLOW');
        }

        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    private function assertDatabaseRange(string $value, string $field): void
    {
        if (bccomp($value, self::MAX_DECIMAL_10_2, 2) === 1) {
            throw new InvalidArgumentException(strtoupper($field) . '_DB_OVERFLOW');
        }
    }

    /**
     * Temporary Phase 4A behavior: truncate to two decimal places.
     *
     * The business rounding policy is not yet confirmed. Keeping this operation
     * isolated prevents future write behavior from depending on an implicit
     * BCMath scale choice and allows replacement with the approved rule.
     */
    private function applyUnconfirmedMoneyScalePolicy(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
