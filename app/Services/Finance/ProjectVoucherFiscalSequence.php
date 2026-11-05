<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherFiscalSequence
{
    /** Allocate a new display number inside the caller's posting transaction. */
    public function next(int $projectId, string $series, string $date): int
    {
        if (DB::transactionLevel() < 1) throw new \DomainException('FISCAL_SEQUENCE_REQUIRES_TRANSACTION');
        if ($projectId < 1 || !in_array($series, ProjectVoucherDisplay::SERIES, true)
            || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new \DomainException('INVALID_FISCAL_SEQUENCE_SCOPE');
        }
        if (!Schema::hasTable('project_voucher_fiscal_sequences')) throw new \DomainException('FISCAL_SEQUENCE_MIGRATION_REQUIRED');
        if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->first()) {
            throw new \DomainException('FISCAL_SEQUENCE_PROJECT_NOT_FOUND');
        }
        $year = (int) $parts[1];
        $query = DB::table('project_voucher_fiscal_sequences')->where('project_id', $projectId)
            ->where('series', $series)->where('financial_year', $year);
        $previous = $query->lockForUpdate()->first();
        $next = (int) ($previous->last_number ?? 0) + 1;
        if ($previous) $query->update(['last_number' => $next, 'updated_at' => now()]);
        else $query->insert(['project_id' => $projectId, 'series' => $series, 'financial_year' => $year,
            'last_number' => $next, 'created_at' => now(), 'updated_at' => now()]);
        return $next;
    }
}
