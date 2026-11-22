<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\ProjectVoucherAllocator;
use Tests\TestCase;

final class VoucherPreviewProjectScopeRegressionTest extends TestCase
{
    public function test_unselected_project_preview_does_not_crash_extra_charge_or_commission_forms(): void
    {
        $allocator = new ProjectVoucherAllocator();
        $this->assertSame(1, $allocator->preview(0, 'EC'));
        $this->assertSame(1, $allocator->preview(0, 'CV'));
        $this->assertSame(1, $allocator->preview(0, 'PPR'));
        $this->assertSame(1, $allocator->preview(0, 'PP'));
    }

    public function test_unsupported_series_is_still_rejected(): void
    {
        $this->expectException(\DomainException::class);
        (new ProjectVoucherAllocator())->preview(0, 'INVALID');
    }

    public function test_plot_preview_routes_remain_per_series_authorized(): void
    {
        $permission = file_get_contents(base_path('app/Support/Finance/FinanceActionPermission.php'));
        $access = file_get_contents(base_path('app/Services/Finance/FinanceWorkflowAccess.php'));

        $this->assertStringContainsString("if (\$name === 'finance.voucher.plot-next-number')", $permission);
        $this->assertStringContainsString("\$series === 'PPR' ? 'plot-receipt' : 'plot-purchase'", $permission);
        $this->assertStringContainsString("FinanceActionPermission::forRequest(request())", $access);
        $this->assertStringContainsString("where('id', \$projectId)->where('is_active', 1)", $access);
    }
}
