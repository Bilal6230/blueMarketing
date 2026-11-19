<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class ExtraChargeCashLifecycleContractTest extends TestCase
{
    public function test_ec_ecp_cash_edit_delete_route_to_exact_source_and_balanced_ledger_editor(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/BookingController.php');
        $service=file_get_contents($root.'/app/Services/Finance/ExtraChargeCashEditor.php');
        $settlement=file_get_contents($root.'/app/Services/Finance/ExtraChargeBankSettlement.php');
        foreach([$routes,$controller,$service,$settlement] as $value)
            self::assertIsString($value);
        self::assertStringContainsString("->name('finance.extra-charge.cash.details')",$routes);
        self::assertStringContainsString("->name('finance.extra-charge.cash.update')",$routes);
        self::assertStringContainsString("->name('finance.extra-charge.cash.delete')",$routes);
        self::assertStringContainsString('function updateExtraChargeCash(',$controller);
        self::assertStringContainsString('function deleteExtraChargeCash(',$controller);
        self::assertStringContainsString('ExtraChargeCashEditor::class',$controller);
        self::assertStringContainsString("'finance extra-charge edit'",$controller);
        self::assertStringContainsString("'finance extra-charge delete'",$controller);
        self::assertStringContainsString("where('customer_ledger_id',\$sourceId)",$service);
        self::assertStringContainsString("whereIn('transaction_type',['EC','ECP'])",$service);
        self::assertStringContainsString("'is_active'=>0",$service);
        self::assertStringContainsString('ExtraChargeBankSettlement::class',$service);
        self::assertStringNotContainsString('PostingReversalWriter',$service);
        self::assertStringContainsString("where('is_active',1)",$settlement);
        // Typed vs legacy EC ownership depends on actual FK evidence, never
        // on the currently selected global finance workflow flag.
        self::assertStringNotContainsString('FinanceWorkflowAccess::selected()',$settlement);
        self::assertStringContainsString("Schema::hasTable('finance_extra_charge_sources')",$settlement);
    }

    public function test_ec_modal_uses_ajax_feedback_and_partial_table_refresh_without_full_page_reload(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/booking/extra_charge.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('fvcExtraCashEditModal',$view);
        self::assertStringContainsString('fvcExtraCashDeleteModal',$view);
        self::assertStringContainsString('refreshExtraCashTable',$view);
        self::assertStringContainsString("type:'PUT'",$view);
        self::assertStringContainsString("type:'DELETE'",$view);
        self::assertStringContainsString('stopImmediatePropagation()',$view);
        self::assertStringContainsString('$.parseHTML(html,document,false)',$view);
        self::assertStringNotContainsString('window.location.reload()',$view);
    }
}
