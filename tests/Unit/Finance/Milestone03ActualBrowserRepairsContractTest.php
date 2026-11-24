<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class Milestone03ActualBrowserRepairsContractTest extends TestCase
{
    private function source(string $path): string
    {
        $content=file_get_contents(dirname(__DIR__,3).'/'.$path);
        self::assertIsString($content, $path);
        return $content;
    }

    public function test_labour_page_has_no_obsolete_v2_class_or_unreachable_mode_only_form(): void
    {
        $view=$this->source('resources/views/admin/labours/index.blade.php');
        self::assertStringNotContainsString('V2OriginalWorkflowAccess',$view);
        self::assertStringNotContainsString('typedPayrollFields',$view);
        self::assertStringContainsString('btnPersonRun',$view);
    }

    public function test_booking_delete_uses_submitted_id_and_preserves_financial_history(): void
    {
        $controller=$this->source('app/Http/Controllers/BookingController.php');
        $view=$this->source('resources/views/admin/booking/index.blade.php');
        self::assertStringContainsString("'booking_id' => ['required','integer','min:1']",$controller);
        self::assertStringContainsString("findOrFail((int) \$data['booking_id'])",$controller);
        self::assertStringContainsString('booking_has_financial_history',$controller);
        self::assertStringContainsString('Swal.fire({icon:\'error\',title:\'Booking Not Deleted\'', $view);
        self::assertStringNotContainsString('alert("Error deleting booking.', $view);
    }

    public function test_extra_charge_register_exposes_cash_payment_edit_and_actual_ledger_accounts(): void
    {
        $controller=$this->source('app/Http/Controllers/BookingController.php');
        $view=$this->source('resources/views/admin/booking/extra_charge.blade.php');
        self::assertStringContainsString("['null', 'EC', 'ECP']",$controller);
        self::assertStringContainsString("'posting_accounts'=>\$postingAccounts",$controller);
        self::assertStringContainsString("['EC','ECP']",$view);
        self::assertStringContainsString('id="fvcExtraCashPostingAccounts"',$view);
        self::assertStringContainsString('data-id="{{ $i->id }}"',$view);
        self::assertStringContainsString('extraCashBase+\'/\'+source',$view);
        self::assertStringContainsString('Linked EC payment',$view);
    }

    public function test_stock_edit_delete_modal_has_no_dependency_on_bootstrap_cdn(): void
    {
        $view=$this->source('resources/views/admin/stock/index.blade.php');
        self::assertStringContainsString('stock-doc-fallback-backdrop',$view);
        self::assertStringContainsString("stockDocModal('#stockDocumentEditModal','show')",$view);
        self::assertStringContainsString('Loading original voucher and item mappings...',$view);
        self::assertStringContainsString('stock-doc-delete',$view);
        self::assertStringContainsString('Stock Entry slip no.', $view);
    }
}
