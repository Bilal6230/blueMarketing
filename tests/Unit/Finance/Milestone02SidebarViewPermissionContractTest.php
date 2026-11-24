<?php

namespace Tests\Unit\Finance;

use App\Support\Finance\FinanceActionPermission;
use PHPUnit\Framework\TestCase;

final class Milestone02SidebarViewPermissionContractTest extends TestCase
{
    private function sidebar(): string
    {
        $view=file_get_contents(dirname(__DIR__,3).'/resources/views/admin/layouts/sidebar-menu.blade.php');
        self::assertIsString($view);
        return $view;
    }

    public function test_daily_voucher_links_require_the_same_exact_view_ability_as_routes(): void
    {
        $view=$this->sidebar();
        self::assertStringContainsString(
            "FinanceActionPermission::name(\$module,'view')",$view
        );
        self::assertStringContainsString(
            "@if(\$financeView(\$voucherInfo[1]))",$view
        );
        $expected=[
            'CR'=>'cr','DR'=>'dr','CCR'=>'ccr','CDR'=>'cdr',
            'BOR'=>'bor','BOP'=>'bop',
        ];
        foreach($expected as $series=>$module){
            self::assertSame('finance '.$module.' view',
                FinanceActionPermission::name($module,'view'));
            self::assertStringContainsString(
                "'".$series."' => [",$view
            );
        }
        self::assertStringContainsString("request()->query('type')===\$voucherType",$view);
        self::assertStringNotContainsString("@can('read voucher')<li", $view);
    }

    public function test_every_finance_sidebar_link_is_scoped_to_its_module_view(): void
    {
        $view=$this->sidebar();
        $pairs=[
            'online'=>'finance.online.index',
            'cheque'=>'finance.cheques.index',
            'directory'=>'finance.daily-vouchers.index',
            'bank-deposit'=>'finance.voucher.transfer.workspace',
            'bank-withdrawal'=>'finance.voucher.transfer.workspace',
            'plot-receipt'=>'finance.voucher.plot-receipt.workspace',
            'plot-purchase'=>'finance.voucher.plot-purchase.workspace',
            'draft'=>'finance.voucher.draft',
            'jv'=>'journal.voucher.index',
            'sv'=>'sales.voucher.index',
            'cv'=>'commision.voucher.index',
            'extra-charge'=>'payment_schedule.extra_charge',
        ];
        foreach ($pairs as $module=>$route) {
            self::assertStringContainsString("@if(\$financeView('".$module."'))",$view);
            self::assertStringContainsString("route('".$route."'",$view);
        }
        self::assertStringContainsString('@if($hasDailyMenu)', $view);
        self::assertStringNotContainsString(
            "@canany(['read voucher','read jv','read slip','add slip'])",$view
        );
    }

    public function test_non_finance_sidebar_matches_existing_view_guards(): void
    {
        $view=$this->sidebar();
        self::assertStringContainsString(
            "StockAbility::allows(auth()->user(),'workspace','view')",$view
        );
        self::assertStringContainsString("@can('read labour')",$view);
        self::assertStringNotContainsString("@can('labour module')",$view);
        self::assertStringNotContainsString("@can('stock module')",$view);
        self::assertStringContainsString("@can('read accounting')",$view);
        self::assertStringContainsString("'read sector','read area','filemanager'",$view);
    }
}
