<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class BankSourceDashboardDrilldownContractTest extends TestCase
{
    public function test_phase09_source_link_filters_are_scoped_and_history_actions_remain_read_only(): void
    {
        foreach ([
            [
                app_path('Http/Controllers/Finance/ChequeIntakeController.php'),
                resource_path('views/admin/finance/cheque/intake.blade.php'),
                'cheque', 'c.id', 'cheque-link-filter', 'cheque-history',
            ],
            [
                app_path('Http/Controllers/Finance/OnlineIntakeController.php'),
                resource_path('views/admin/finance/online/intake.blade.php'),
                'online', 'finance_online_intakes.id', 'online-link-filter', 'online-history-button',
            ],
        ] as [$controllerPath, $viewPath, $method, $idColumn, $filterId, $historyAction]) {
            $controller = file_get_contents($controllerPath);
            $view = file_get_contents($viewPath);
            $this->assertStringContainsString('finance_bank_voucher_allocations', $controller);
            $this->assertStringContainsString('link_status', $controller);
            $this->assertStringContainsString("'linked','unlinked'", str_replace(' ', '', $controller));
            $this->assertStringContainsString("where('a.source_method', '".$method."')", str_replace(' ', '', $controller));
            $this->assertStringContainsString("'source_method','".$method."'", str_replace(' ', '', $controller));
            $this->assertStringContainsString('whereColumn(', $controller);
            $this->assertStringContainsString($idColumn, $controller);
            $this->assertStringContainsString('whereNotExists', $controller);
            $this->assertStringContainsString('linked_voucher_count', $controller);
            $this->assertStringContainsString('id="'.$filterId.'"', $view);
            $this->assertStringContainsString('name="link_status"', $view);
            $this->assertStringContainsString('Vouchers (', $view);
            $this->assertStringContainsString($historyAction, $view);
            $this->assertStringContainsString('linked_vouchers', $view);
        }
    }
}
