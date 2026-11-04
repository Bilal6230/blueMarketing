<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountMappingHistoryGuard
{
    public function assertNoFinancialHistory(int $id): void
    {
        foreach ([
            'ledgers' => 'project_head_subheads_id',
            'draft_ledgers' => 'project_head_subheads_id',
            'journal_voucher_details' => 'account_id',
            'commision_voucher_details' => 'account_id',
            'finance_posting_lines' => 'project_head_subhead_id',
        ] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $id)->exists()) {
                throw new \DomainException('ACCOUNT_MAPPING_HAS_HISTORY');
            }
        }
    }

    public function assertUnused(int $id): void
    {
        $this->assertNoFinancialHistory($id);

        foreach ([
            'sites' => 'project_head_subhead_id',
            'stock_party_project_accounts' => 'project_head_subhead_id',
            'project_bank_accounts' => 'account_mapping_id',
            'contractors' => 'project_head_subhead_id',
            'stock_party_project_account_audits' => 'previous_account_id',
        ] as $table => $column) {
            // Include archived and configuration references: account identity
            // is still needed by history, reports, and future audit review.
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $id)->exists()) {
                throw new \DomainException('ACCOUNT_MAPPING_HAS_HISTORY');
            }
        }

        if (Schema::hasTable('stock_party_project_account_audits')
            && Schema::hasColumn('stock_party_project_account_audits', 'new_account_id')
            && DB::table('stock_party_project_account_audits')->where('new_account_id', $id)->exists()) {
            throw new \DomainException('ACCOUNT_MAPPING_HAS_HISTORY');
        }
    }
}
