<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class BankManagementCascadePlannerContractTest extends TestCase
{
    public function test_online_and_cheque_history_expose_exact_project_scoped_cascade_impact(): void
    {
        $planner = file_get_contents(base_path('app/Services/Finance/BankManagementCascadePlanner.php'));
        $online = file_get_contents(base_path('app/Http/Controllers/Finance/OnlineIntakeController.php'));
        $cheque = file_get_contents(base_path('app/Http/Controllers/Finance/ChequeIntakeController.php'));

        self::assertStringContainsString('where(\'a.project_id\', $projectId)', $planner);
        self::assertStringContainsString('where(\'a.source_method\', $method)', $planner);
        self::assertStringContainsString('where(\'a.source_id\', $sourceId)', $planner);
        self::assertStringContainsString("on('r.allocation_id', '=', 'a.id')", $planner);
        self::assertStringContainsString('where(\'l.customer_ledger_id\', $allocation->voucher_id)', $planner);
        self::assertStringContainsString('\'requires_cascade\' => $linked', $planner);
        self::assertStringContainsString("'cascade_impact'=>app(", $online);
        self::assertStringContainsString("'cascade_impact' => app(", $cheque);
    }

    public function test_history_displays_informational_impact_without_performing_financial_writes(): void
    {
        $planner = file_get_contents(base_path('app/Services/Finance/BankManagementCascadePlanner.php'));
        self::assertStringNotContainsString("->insert(", $planner);
        self::assertStringNotContainsString("->update(", $planner);
        self::assertStringNotContainsString("->delete(", $planner);
        foreach ([
            'resources/views/admin/finance/online/intake.blade.php'=>'online-cascade-impact',
            'resources/views/admin/finance/cheque/intake.blade.php'=>'cheque-cascade-impact',
        ] as $file => $id) {
            $view=file_get_contents(base_path($file));
            self::assertStringContainsString($id, $view);
            self::assertStringContainsString('result.cascade_impact', $view);
        }
    }
}
