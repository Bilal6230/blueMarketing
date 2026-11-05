<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherDisplay
{
    public const SERIES = ['CR', 'CP', 'JV', 'CV', 'BO', 'BR', 'PPR', 'SV', 'EC', 'ECP'];

    public function format(int $projectId, string $series, int $number, string $date): string
    {
        if ($projectId < 1 || !in_array($series, self::SERIES, true) || $number < 1
            || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/D', $date, $parts)
            || !checkdate((int) $parts[1], (int) $parts[2], (int) substr($date, 0, 4))) {
            throw new \DomainException('INVALID_VOUCHER_DISPLAY_INPUT');
        }
        $prefix = $series;
        if (Schema::hasTable('project_voucher_display_settings')) {
            $prefix = DB::table('project_voucher_display_settings')
                ->where('project_id', $projectId)->where('series', $series)->value('prefix') ?: $series;
        }
        return $prefix.'-'.substr($date, 0, 4).'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
