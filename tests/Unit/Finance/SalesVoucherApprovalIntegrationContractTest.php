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
        // The journal voucher header has created_by, but its details table does not.
        $schema=$this->source('database/migrations/2025_01_27_143458_create_journal_voucher_details_table.php');
        self::assertStringNotContainsString('`created_by`',$schema);
        $detailInsert=substr($pending,strpos($pending,"DB::table('journal_voucher_details')->insert("),
            strpos($pending,"app(ProjectVoucherDisplayIdentity::class)"));
        self::assertStringContainsString("'journal_voucher_id'=>\$id",$detailInsert);
        self::assertStringNotContainsString("'created_by'=>",$detailInsert);

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
    public function test_sv_decision_permission_resolves_bound_model_id(): void
    {
        $router=$this->source('app/Support/Finance/FinanceActionPermission.php');
        self::assertStringContainsString('$approvalParam instanceof \\App\\Models\\DailyVoucherApproval',$router);
        self::assertStringContainsString('(int)$approvalParam->getKey()',$router);
        self::assertStringNotContainsString('$approvalId = (int)$route->parameter(\'approval\')',$router);
    }

    public function test_sv_direct_posting_respects_journal_details_schema(): void
    {
        $writer=$this->source('app/Services/Finance/OriginalJournalWorkflow.php');
        $direct=substr($writer,strpos($writer,'public function createAndPost('),
            strpos($writer,'public function createPendingSales(')
                -strpos($writer,'public function createAndPost('));
        $schema=$this->source('database/migrations/2025_01_27_143458_create_journal_voucher_details_table.php');
        self::assertStringNotContainsString('`created_by`',$schema);
        $detail=substr($direct,strpos($direct,"DB::table('journal_voucher_details')->insert("));
        self::assertStringNotContainsString("'created_by' =>",$detail);
        self::assertStringContainsString('postSeries($series',$direct);
    }

    public function test_sv_actions_and_pending_editor_are_guarded(): void
    {
        $actions=$this->source('resources/views/admin/reports/vouchers/actions.blade.php');
        self::assertStringContainsString('$salesEditable',$actions);
        self::assertStringContainsString('journal.voucher',$actions);
        self::assertStringContainsString('delete-btn',$actions);
        $editor=$this->source('app/Services/Finance/PendingSalesVoucherEditor.php');
        self::assertStringContainsString("where('voucher_type','SV')",$editor);
        self::assertStringContainsString("where('status','pending')",$editor);
        self::assertStringContainsString('SV_ALREADY_POSTED',$editor);
        self::assertStringContainsString('pending_sv_edited',$editor);
        self::assertStringContainsString("'event'=>'withdrawn'",$editor);
        self::assertStringNotContainsString('postSalesVoucher(',$editor);
        self::assertStringNotContainsString('createAndPost(',$editor);
        $controller=$this->source('app/Http/Controllers/JournalVoucherController.php');
        self::assertSame(2,substr_count($controller,'if($pendingSales)'));
        self::assertStringContainsString('Pending Sales Voucher withdrawn',$controller);
        self::assertStringContainsString('Pending Sales Voucher updated',$controller);
    }
}
