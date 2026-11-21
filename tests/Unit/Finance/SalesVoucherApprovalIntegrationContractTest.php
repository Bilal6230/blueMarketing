<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class SalesVoucherApprovalIntegrationContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(base_path($path));
        self::assertIsString($source);
        return $source;
    }

    public function test_sales_create_form_and_controller_keep_sv_identity(): void
    {
        $controller=$this->source('app/Http/Controllers/JournalVoucherController.php');
        $create=substr($controller,strpos($controller,'public function create()'),
            strpos($controller,'public function store(Request $request)')-
                strpos($controller,'public function create()'));
        self::assertStringContainsString("routeIs('sales.voucher.create')",$create);
        self::assertStringContainsString("'SV' : 'JV'",$create);
        $form=$this->source('resources/views/admin/reports/vouchers/journal_voucher.blade.php');
        self::assertStringContainsString("route('sales.voucher.store')",$form);
        $store=substr($controller,strpos($controller,'public function store(Request $request)'));
        self::assertStringContainsString("routeIs('sales.voucher.store')",$store);
        self::assertStringContainsString('createPendingSales(',$store);
        self::assertStringContainsString('createAndPost(',$store);
        self::assertStringContainsString("'SV'",$store);
    }

    public function test_sales_approval_uses_unposted_source_until_approver_posts(): void
    {
        $pending=$this->source('app/Services/Finance/OriginalJournalWorkflow.php');
        $pending=substr($pending,strpos($pending,'public function createPendingSales('));
        self::assertStringContainsString("'type'=>'SV'",$pending);
        self::assertStringContainsString("'status'=>'pending'",$pending);
        self::assertStringContainsString("submitIfRequired(",$pending);
        self::assertStringNotContainsString('postSalesVoucher(',$pending);
        self::assertStringContainsString('SV_REQUEST_KEY_CONFLICT',$pending);

        $approval=$this->source('app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        self::assertStringContainsString("\$type !== 'SV' && FinanceWorkflowAccess::normalErpMode()",$approval);
        self::assertStringContainsString("if (\$approval->source_table === 'journal_vouchers' && \$approval->voucher_type === 'SV')",$approval);
        self::assertStringContainsString('postSalesVoucher(',$approval);
        self::assertStringContainsString("['status'=>\$active?'approved':'rejected'",$approval);
        self::assertStringContainsString('SV_APPROVAL_ALREADY_POSTED',$approval);
    }

    public function test_sales_policy_dashboard_and_status_are_available(): void
    {
        $settings=$this->source('app/Http/Controllers/Finance/VoucherSettingsController.php');
        self::assertStringContainsString("'SV' => ['label'=>'Sales Voucher', 'supported'=>true]",$settings);
        $dashboard=$this->source('app/Http/Controllers/Finance/DailyVoucherApprovalController.php');
        self::assertStringContainsString("'BOP', 'JV', 'SV'",$dashboard);
        $directory=$this->source('resources/views/admin/reports/vouchers/index.blade.php');
        self::assertStringContainsString("in_array(\$type,['JV','SV'],true)",$directory);
        $controller=$this->source('app/Http/Controllers/JournalVoucherController.php');
        self::assertStringContainsString('applySalesApprovalStatusFilter(',$controller);
        self::assertStringContainsString("'approval_status' => ucfirst(\$approval['status']??'posted')",$controller);
    }
}
