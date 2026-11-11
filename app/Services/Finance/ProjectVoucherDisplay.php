<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherDisplay
{
    // Cheque is a payment method under the bank voucher series, not a separate
    // numbered voucher series. Keep CHR/CHP decoding at legacy read sites only.
    public const SERIES = ['CR', 'DR', 'CP', 'CCR', 'CDR', 'BOR', 'BOP', 'JV', 'CV', 'BO', 'BR', 'PPR', 'SV', 'EC', 'ECP'];

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
        return $prefix.'-'.substr($date, 0, 4).'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
