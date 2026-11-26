<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherDisplay
{
    // Cheque is a payment method under the bank voucher series, not a separate
    // numbered voucher series. Keep CHR/CHP decoding at legacy read sites only.
    public const SERIES = ['CR', 'DR', 'CP', 'CCR', 'CDR', 'BOR', 'BOP', 'JV', 'CV', 'BO', 'BR', 'PPR', 'SV', 'EC', 'ECP'];

    /** Human-facing names; canonical series keys remain stable. */
    public const LABELS = [
        'CR'=>'Cash Receipt Voucher',
        'DR'=>'Debit Voucher',
        'CP'=>'Cash Payment Voucher',
        'CCR'=>'Cash Credit Voucher',
        'CDR'=>'Cash Debit Voucher',
        'BOR'=>'Bank Receipt Voucher',
        'BOP'=>'Bank Payment Voucher',
        'JV'=>'Journal Voucher',
        'CV'=>'Credit / Commission Voucher',
        'PPR'=>'Plot Receive Payment Voucher',
        'SV'=>'Sales Voucher',
        'BO'=>'BO Voucher',
        'BR'=>'BR Voucher',
        'EC'=>'Extra Charge Voucher',
        'ECP'=>'Extra Charge Payment Voucher',
    ];

    public static function label(string $series): string
    {
        return self::LABELS[$series] ?? ($series.' Voucher');
    }

    public function format(int $projectId, string $series, int $number, string $date): string
    {
        if ($projectId < 1 || !in_array($series, self::SERIES, true) || $number < 1
            || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/D', $date, $parts)
            || !checkdate((int) $parts[1], (int) $parts[2], (int) substr($date, 0, 4))) {
            throw new \DomainException('INVALID_VOUCHER_DISPLAY_INPUT');
        }
        // CP remains the immutable legacy source series, while its user-facing
        // voucher label is DR. Existing stored identities are not rewritten.
        $prefix = $series === 'CP' ? 'DR' : $series;
        if (Schema::hasTable('project_voucher_display_settings')) {
            $prefix = DB::table('project_voucher_display_settings')
                ->where('project_id', $projectId)->where('series', $series)->value('prefix') ?: $prefix;
            if ($series === 'CP' && $prefix === 'CP') $prefix = 'DR';
        }
        return $series.'-'.$number;
    }
}
