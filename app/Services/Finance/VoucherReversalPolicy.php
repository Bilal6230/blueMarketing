<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 05: project-scoped reversal switch for verified posted vouchers.
 * Existing installations retain their current behaviour until explicitly
 * disabled; this is not an ordinary Edit/Delete or a source-custody bypass.
 */
final class VoucherReversalPolicy
{
    public function enabled(int $projectId, string $series): bool
    {
        if ($projectId < 1 || $series === '' || strtoupper($series) === 'REV') {
            return false;
        }
        if (!Schema::hasTable('daily_voucher_approval_policies')
            || !Schema::hasColumn('daily_voucher_approval_policies', 'reversal_enabled')) {
            return true; // Migration rollout must not silently break old postings.
        }
        $configured = DB::table('daily_voucher_approval_policies')
            ->where('project_id', $projectId)->where('voucher_type', strtoupper($series))
            ->value('reversal_enabled');

        return $configured === null || (bool) $configured;
    }

    public function assertEnabled(int $projectId, string $series): void
    {
        if (!$this->enabled($projectId, $series)) {
            throw new \DomainException('VOUCHER_REVERSAL_DISABLED');
        }
    }
}
