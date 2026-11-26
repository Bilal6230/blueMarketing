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
        'EC'=>'Extra Charge Voucher','ECP'=>'Extra Charge Payment Voucher',
    ];

    public static function enabled(int $projectId, string $series): bool
    {
        if ($projectId < 1 || !array_key_exists($series,self::FAMILIES)) return false;
        // One project-scoped lookup per HTTP request, even for large sidebars.
        // Request attributes avoid stale state across tests and subsequent requests.
        $key='s4.voucher.availability.'.$projectId;
        $flags=request()->attributes->get($key);
        if (!is_array($flags)) {
            $flags=Schema::hasTable('project_voucher_availability')
                ? DB::table('project_voucher_availability')->where('project_id',$projectId)
                    ->pluck('is_active','series')->all()
                : [];
            request()->attributes->set($key,$flags);
        }
        return (int)($flags[$series] ?? 1)===1;
    }

    public static function enforce(int $projectId, string $series): void
    {
        abort_unless(self::enabled($projectId,$series),403,'This voucher type is inactive for the selected project.');
    }
}
