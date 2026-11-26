<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project-specific visibility of voucher ENTRY routes. Historical records,
 * reports, print, audits and approvals are deliberately not revoked.
 */
final class VoucherAvailability
{
    public const FAMILIES = [
        'CR'=>'Credit Voucher', 'DR'=>'Debit Voucher',
        'CP'=>'Cash Payment Voucher', 'CCR'=>'Cash Credit Voucher',
        'CDR'=>'Cash Debit Voucher', 'BOR'=>'Bank Receipt Voucher',
        'BOP'=>'Bank Payment Voucher', 'BD'=>'Bank Deposit Voucher',
        'BW'=>'Bank Withdrawal Voucher', 'JV'=>'Journal Voucher',
        'CV'=>'Commission Voucher', 'SV'=>'Sales Voucher',
        'PPR'=>'Plot Receive Payment', 'PP'=>'Plot Purchase Voucher',
        'BO'=>'BO Voucher', 'BR'=>'BR Voucher',
        'EC'=>'Extra Charge Voucher','ECP'=>'Extra Charge Payment Voucher',
        'CHR'=>'Cheque Receipt Voucher','CHP'=>'Cheque Payment Voucher',
    ];

    public static function enabled(int $projectId, string $series): bool
    {
        if ($projectId < 1 || !array_key_exists($series,self::FAMILIES)) return false;
        // Older databases remain operational until the additive S4 migration.
        if (!Schema::hasTable('project_voucher_availability')) return true;
        return (int)(DB::table('project_voucher_availability')
            ->where('project_id',$projectId)->where('series',$series)
            ->value('is_active') ?? 1) === 1;
    }

    public static function enforce(int $projectId, string $series): void
    {
        abort_unless(self::enabled($projectId,$series),403,'This voucher type is inactive for the selected project.');
    }
}
