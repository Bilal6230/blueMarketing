<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class NormalFinanceMergeAuditGateTest extends TestCase
{
    public function test_normal_merge_audit_refuses_nonlocal_fixture_without_db_write(): void
    {
        config(['app.env' => 'testing', 'finance.v2_pilot_api_enabled' => false]);
        $this->assertSame(1, Artisan::call('finance:audit-normal-merge', ['project' => 3]));
    }

    public function test_normal_mode_plot_reversals_use_exact_source_and_permission_gated_ajax(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Finance/VoucherController.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $receipt = file_get_contents(resource_path('views/admin/finance/voucher/plot_receipt_workspace.blade.php'));
        $purchase = file_get_contents(resource_path('views/admin/finance/voucher/plot_purchase_workspace.blade.php'));

        $this->assertStringContainsString('PlotEntryLifecycleService', $controller);
        $this->assertStringContainsString("reversePlotSource(\$request, 'plot_receipt'", $controller);
        $this->assertStringContainsString("reversePlotSource(\$request, 'plot_purchase'", $controller);
        $this->assertStringContainsString("void_batch.reversal_of_batch_id", $controller);
        $this->assertStringContainsString("'reversed' => \$row->reversal_batch_id !== null", $controller);
        $this->assertStringContainsString("name('finance.voucher.plot-receipt.reverse')", $routes);
        $this->assertStringContainsString("name('finance.voucher.plot-purchase.reverse')", $routes);
        $this->assertStringContainsString("permission:delete voucher", $routes);
        $this->assertStringContainsString("id=\"prVoidForm\"", $receipt);
        $this->assertStringContainsString("id=\"ppVoidForm\"", $purchase);
        $this->assertStringContainsString(".on('submit',function(event)", $purchase);
    }

    public function test_normal_mode_posted_vouchers_preserve_v2_source_before_mutation(): void
    {
        $journal = file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $commission = file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));

        $this->assertStringContainsString('private function isMergedPostedJournal(', $journal);
        $this->assertStringContainsString('VoucherLifecycleService())->reverse(', $journal);
        $this->assertStringContainsString('private function isMergedTypedCommission(', $commission);
        $this->assertStringContainsString('cancelDraft($projectId, (int) $id)', $commission);
        $this->assertStringContainsString('VoucherLifecycleService())->reverse(', $commission);
    }
}
