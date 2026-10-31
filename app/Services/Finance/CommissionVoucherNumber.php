<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class CommissionVoucherNumber
{
    public static function display(mixed $number): string
    {
        $number = (string) $number;
        if (preg_match('/^(?:CV-)?([0-9]+)$/D', $number, $match)) {
            return 'CV-'.str_pad($match[1], 7, '0', STR_PAD_LEFT);
        }
        return str_starts_with($number, 'CV-') ? $number : 'CV-'.$number;
    }

    /** A form preview only. Creation still checks uniqueness under project lock. */
    public static function next(int $projectId): int
    {
        $max = 0;
        foreach (DB::table('commision_vouchers')->where('project_id', $projectId)->pluck('voucher_number') as $number) {
            if (preg_match('/^(?:CV-)?([0-9]{1,15})$/D', (string) $number, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }
        return $max + 1;
    }
}
