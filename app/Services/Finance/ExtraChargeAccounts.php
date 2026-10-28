<?php

namespace App\Services\Finance;

use App\Models\HeadAccounting;
use App\Models\ProjectHeadSubhead;
use App\Models\SubheadAccounting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ExtraChargeAccounts
{
    /**
     * Resolve a charge receivable (debit) and income (credit) in an existing
     * project transaction. Mirrors Stock's project_head_subheads creation
     * pattern, but never guesses a customer bank/cash account.
     */
    public function forProject(int $projectId, int $customerId, int $plotId): array
    {
        if (DB::transactionLevel() < 1 ||
            !DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
            throw new \DomainException('EC_PROJECT_ACCOUNT_TRANSACTION_REQUIRED');
        }

        $head = HeadAccounting::where('name', 'Extra Charge')->orderBy('id')->first();
        if (!$head) {
            $head = HeadAccounting::create([
                'name' => 'Extra Charge',
                'is_active' => 1,
                'acct_type' => 0,
                'create_by' => Auth::id(),
            ]);
        }

        $accounts = [];
        foreach (['Extra Charge Receivable', 'Extra Charge Income'] as $name) {
            $subhead = SubheadAccounting::where('name', $name)->orderBy('id')->first();
            if (!$subhead) {
                $subhead = SubheadAccounting::create([
                    'name' => $name,
                    'is_active' => 1,
                    'create_by' => Auth::id(),
                ]);
            }
            $scope = [
                'project_id' => $projectId,
                'head_accounting_id' => $head->id,
                'subhead_accounting_id' => $subhead->id,
                'plot_id' => $name === 'Extra Charge Receivable' ? $plotId : null,
                'customer_id' => $name === 'Extra Charge Receivable' ? $customerId : null,
            ];
            $accounts[$name] = (int) ProjectHeadSubhead::firstOrCreate($scope)->id;
        }

        if ($accounts['Extra Charge Receivable'] === $accounts['Extra Charge Income']) {
            throw new \DomainException('EC_ACCOUNT_SIDES_NOT_DISTINCT');
        }

        return [
            'debit_account_id' => $accounts['Extra Charge Receivable'],
            'credit_account_id' => $accounts['Extra Charge Income'],
        ];
    }
}
