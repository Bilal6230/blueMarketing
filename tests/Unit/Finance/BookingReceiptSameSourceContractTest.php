<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class BookingReceiptSameSourceContractTest extends TestCase
{
    public function test_cash_ppr_edit_and_delete_preserve_source_booking_ledger_identity_without_reversal(): void
    {
        $root=dirname(__DIR__,3);
        $editor=file_get_contents($root.'/app/Services/Finance/BookingReceiptCashEditor.php');
        $booking=file_get_contents($root.'/app/Http/Controllers/BookingController.php');
        self::assertIsString($editor);
        self::assertIsString($booking);
        self::assertStringContainsString('public function edit(',$editor);
        self::assertStringContainsString('public function delete(',$editor);
        self::assertStringContainsString("where('transaction_type','PPR')",$editor);
        self::assertStringContainsString("where('customer_ledger_id',\$sourceId)",$editor);
        self::assertStringContainsString("where('ledger_id',\$leg[0]->id)",$editor);
        self::assertStringContainsString("where('voucher_series','PPR')",$editor);
        self::assertStringNotContainsString('PostingReversalWriter',$editor);
        self::assertStringNotContainsString('LegacyCashVoucherCorrection',$editor);
        self::assertStringContainsString("'amount_out'=>\$amount",$editor);
        self::assertStringContainsString("'amount_in'=>\$amount",$editor);
        self::assertStringContainsString("'is_active'=>0",$editor);
        self::assertStringContainsString('BookingReceiptCashEditor::class',$booking);
        self::assertStringContainsString('Receipt removed from active balances',$booking);
        self::assertStringContainsString('Same voucher and ledger IDs retained.',$booking);
    }
}
