<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class ChequeDashboardSummaryContractTest extends TestCase
{
    public function test_phase09_cheque_dashboard_summary_is_filtered_read_only_and_project_scoped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Finance/ChequeIntakeController.php'));
        $view = file_get_contents(resource_path('views/admin/finance/cheque/intake.blade.php'));

        $this->assertStringContainsString('where(\'c.project_id\', $project)', $controller);
        $this->assertStringContainsString('(clone $query)', $controller);
        $this->assertStringContainsString("groupBy('c.status', 'c.direction')", $controller);
        $this->assertStringContainsString("'summary' => \$summary", $controller);
        foreach (['cleared_amount', 'pending_amount', 'exception_amount'] as $key) {
            $this->assertStringContainsString($key, $controller);
        }
        foreach (['cheque-kpi-total', 'cheque-kpi-cleared', 'cheque-kpi-pending', 'cheque-kpi-exception'] as $id) {
            $this->assertStringContainsString($id, $view);
        }
        $this->assertStringContainsString('showChequeSummary(data.summary)', $view);
        $this->assertStringContainsString("$(function ()", $view);
    }
}
