<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class Milestone03OwnerBrowserRepairBatchContractTest extends TestCase
{
    private function source(string $file): string
    {
        $s=file_get_contents(dirname(__DIR__,3).'/'.$file);
        self::assertIsString($s, $file);
        return $s;
    }

    public function test_booking_amendment_atomically_syncs_original_bo_and_sv_rows(): void
    {
        $service=$this->source('app/Services/Booking/BookingAmendmentService.php');
        $controller=$this->source('app/Http/Controllers/BookingController.php');
        $create=$this->source('resources/views/admin/booking/sale.blade.php');
        self::assertStringContainsString('function syncOriginalSale(',$service);
        self::assertStringContainsString('BOOKING_SV_SOURCE_AMBIGUOUS',$service);
        self::assertStringContainsString('BOOKING_SV_UNBALANCED',$service);
        self::assertStringContainsString("DB::table('journal_voucher_details')",$service);
        self::assertStringContainsString("DB::table('customer_ledger')",$service);
        self::assertStringContainsString('$this->syncOriginalSale(', $service);
        self::assertStringContainsString("'discount_value'=>'0'",$controller);
        self::assertStringContainsString('archiveUnpaidBookingSale(',$controller);
        self::assertStringContainsString('Booking has linked receipts/vouchers',$controller);
        self::assertStringContainsString("old('discount_value','0')",$create);
        self::assertStringContainsString("$('#discount_value').val('0')",$create);
        self::assertStringContainsString('$errors->all()', $create);
    }

    public function test_ec_main_directory_hides_ecp_and_owns_nested_payment_history(): void
    {
        $controller=$this->source('app/Http/Controllers/BookingController.php');
        $view=$this->source('resources/views/admin/booking/extra_charge.blade.php');
        $editor=$this->source('app/Services/Finance/ExtraChargeCashEditor.php');
        self::assertStringContainsString("['null', 'EC']",$controller);
        self::assertStringContainsString("ecPaymentsByCharge",$controller);
        self::assertStringContainsString('id="fvcPaymentHistoryModal"',$view);
        self::assertStringContainsString('id="fvcPaymentRows"',$view);
        self::assertStringContainsString('fvc-payment-history',$view);
        self::assertStringContainsString("#fvcPaymentHistoryModal .btn-edit",$view);
        self::assertStringContainsString("#fvcPaymentHistoryModal .btn-delete",$view);
        self::assertStringContainsString('fvcExtraCashIdentityFields',$view);
        self::assertStringContainsString('Customer and Plot need one active booking',$editor);
        self::assertStringContainsString("Charge has linked payment history",$editor);
    }

    public function test_stock_minor_units_and_legacy_posting_are_handled_without_silent_amount_drift(): void
    {
        $controller=$this->source('app/Http/Controllers/StockController.php');
        $editor=$this->source('app/Services/Finance/StockDocumentEditor.php');
        $view=$this->source('resources/views/admin/stock/index.blade.php');
        self::assertStringContainsString('intdiv($amountMinor,100)',$controller);
        self::assertStringContainsString('$legacyHundredfold',$editor);
        self::assertStringContainsString('$postedTotal===$oldTotal*100',$editor);
        self::assertStringContainsString('if($same&&!$legacyHundredfold)return;',$editor);
        self::assertStringContainsString('stockDocumentNumber',$view);
        self::assertStringContainsString('stockDocumentParty',$view);
        self::assertStringContainsString('stockDocumentVehicle',$view);
        self::assertStringContainsString('stockDocumentDriver',$view);
        self::assertStringContainsString('stock-compact-line stock-doc-line',$view);
        self::assertStringContainsString("'vehicle'=>['nullable','string','max:80']",$controller);
        self::assertStringContainsString("'driver'=>['nullable','string','max:120']",$controller);
    }

    public function test_labour_master_is_global_and_site_account_mapping_stays_project_specific(): void
    {
        $controller=$this->source('app/Http/Controllers/LabourController.php');
        $view=$this->source('resources/views/admin/labours/index.blade.php');
        $migration=$this->source('database/migrations/2026_11_24_120000_make_labours_global_master.php');
        self::assertStringNotContainsString("Labour::where('project_id'",$controller);
        self::assertStringNotContainsString("Labour::where('labours.project_id'",$controller);
        self::assertStringContainsString("Labour::query()->get()",$controller);
        self::assertStringContainsString("Site::where('project_id'",$controller);
        self::assertStringContainsString("subhead_accounting_id +", $view);
        self::assertStringContainsString("dropColumn('project_id')",$migration);
        self::assertStringContainsString("Schema::getForeignKeys('labours')",$migration);
    }
}
