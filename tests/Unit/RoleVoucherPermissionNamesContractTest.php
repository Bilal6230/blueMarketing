<?php

namespace Tests\Unit;

use App\Support\Finance\RolePermissionCatalog;
use PHPUnit\Framework\TestCase;

final class RoleVoucherPermissionNamesContractTest extends TestCase
{
    public function test_voucher_permission_sections_use_full_distinctive_labels(): void
    {
        $expected = [
            'cr'=>'CR — Credit Voucher',
            'dr'=>'DR — Debit Voucher',
            'cp'=>'CP — Cash Payment Voucher',
            'ccr'=>'CCR — Cash Credit Voucher',
            'cdr'=>'CDR — Cash Debit Voucher',
            'bor'=>'BOR — Bank Receipt Voucher',
            'bop'=>'BOP — Bank Payment Voucher',
            'jv'=>'JV — Journal Voucher',
            'sv'=>'SV — Sales Voucher',
            'cv'=>'CV — Credit / Commission Voucher',
            'bank-deposit'=>'BD — Bank Deposit Voucher',
            'bank-withdrawal'=>'BW — Bank Withdrawal Voucher',
            'plot-receipt'=>'PPR — Plot Receive Payment Voucher',
            'plot-purchase'=>'PP — Plot Purchase Voucher',
            'extra-charge'=>'EC — Extra Charge Voucher',
            'ecp'=>'ECP — Extra Charge Payment Voucher',
        ];
        foreach ($expected as $module=>$label) {
            self::assertSame($label, RolePermissionCatalog::moduleLabel($module));
        }
    }

    public function test_plot_receipt_and_ppr_use_one_choice_with_two_real_permission_names(): void
    {
        $view=file_get_contents(dirname(__DIR__,2).'/resources/views/admin/role.blade.php');
        self::assertIsString($view);

        self::assertStringContainsString("if(m.module==='ppr'||m.module==='cp')return;", $view);
        self::assertStringContainsString("m.module==='dr'?['dr','cp']", $view);
        self::assertStringContainsString("['plot-receipt','ppr']", $view);
        self::assertStringContainsString("byAction[action].names.push(permissionName)", $view);
        self::assertStringContainsString("p.names.every(function(n){return selected.has(n);})", $view);
        self::assertStringContainsString("p.names.some(function(n){return selected.has(n);})", $view);
        self::assertStringContainsString("$(this).data('permission-names')", $view);
        self::assertStringContainsString("$.each(p.names,function(_,name)", $view);
        self::assertStringContainsString('names:[p.name]', $view);
    }

    public function test_role_modal_has_wider_finance_navigation_and_14px_editor_text(): void
    {
        $view=file_get_contents(dirname(__DIR__,2).'/resources/views/admin/role.blade.php');
        self::assertStringContainsString('max-width:1080px', $view);
        self::assertStringContainsString('grid-template-columns:310px minmax(0,1fr)', $view);
        self::assertStringContainsString('margin-bottom:3px;font-size:14px', $view);
        self::assertStringContainsString('.role-permission-item{font-size:14px', $view);
        self::assertStringContainsString('.role-permission-content,.role-editor .role-selected,.role-editor .modal-footer{font-size:14px}', $view);
    }

    public function test_ec_and_ecp_keep_distinct_access_rights_and_existing_grants(): void
    {
        $catalog = file_get_contents(dirname(__DIR__,2).'/app/Support/Finance/RolePermissionCatalog.php');
        $role = file_get_contents(dirname(__DIR__,2).'/app/Http/Controllers/RoleController.php');
        $finance = file_get_contents(dirname(__DIR__,2).'/app/Support/Finance/FinanceActionPermission.php');

        self::assertStringContainsString("'ec'=>'extra-charge'", $finance);
        self::assertStringContainsString("'extra-charge' => ['view','create'", $catalog);
        self::assertStringContainsString("'ppr','ecp' => ['view','create'", $catalog);
        self::assertStringContainsString('array_merge($available,$hidden)', $role);
        self::assertStringContainsString('->syncPermissions(', $role);
    }
}
