<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class AccountMappingHistoryGuard
{
    public function assertUnused(int $id): void
    {
        foreach (['ledgers' => 'project_head_subheads_id',
            'draft_ledgers' => 'project_head_subheads_id',
            'journal_voucher_details' => 'account_id',
            'commision_voucher_details' => 'account_id',
            'finance_posting_lines' => 'project_head_subhead_id'] as $table => $column) {
            // Include archived references: their accounting identity must survive.
            if (DB::getSchemaBuilder()->hasTable($table)
                && DB::table($table)->where($column, $id)->exists()) {
                throw new \DomainException('ACCOUNT_MAPPING_HAS_HISTORY');
            }
        }
    }
}
