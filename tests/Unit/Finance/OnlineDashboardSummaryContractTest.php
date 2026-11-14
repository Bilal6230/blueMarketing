<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class OnlineDashboardSummaryContractTest extends TestCase
{
    public function test_phase09_online_summary_is_filtered_project_scoped_and_read_only(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/Finance/OnlineIntakeController.php'));
        $view=file_get_contents(resource_path('views/admin/finance/online/intake.blade.php'));

        $this->assertStringContainsString('where(\'project_id\',$project)', $controller);
        $this->assertStringContainsString('(clone $query)', $controller);
        $this->assertStringContainsString("groupBy('status','type')", $controller);
        $this->assertStringContainsString("'summary'=>\$summary", $controller);
        $this->assertLessThan(strpos($controller, "paginate((int)"), strpos($controller, '(clone $query)'));
        foreach (['confirmed_amount','pending_amount','receipt_amount','payment_amount'] as $field) {
            $this->assertStringContainsString($field, $controller);
        }
        foreach (['online-kpi-total','online-kpi-confirmed','online-kpi-pending','online-kpi-direction'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $view);
        }
        $this->assertStringContainsString('showOnlineSummary(data.summary)', $view);
        $this->assertStringContainsString('showOnlineSummary(null)', $view);
        $this->assertStringContainsString('$(function()', $view);
    }
}
