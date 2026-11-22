<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinancialSourceOwnership;
use Tests\TestCase;

final class IndependentVoucherSeriesContractTest extends TestCase
{
    public function test_independent_series_are_source_owned_and_never_remapped_by_payment_method(): void
    {
        foreach (['CR','DR','CCR','CDR','BOR','BOP'] as $series) {
            self::assertContains($series, FinancialSourceOwnership::FAMILIES);
        }
        $controller = file_get_contents(base_path('app/Http/Controllers/LedgerController.php'));
        self::assertStringContainsString("'transaction_type' => \$voucherPrefix", $controller);
        self::assertStringContainsString("'type' => \$displaySeries", $controller);
        self::assertStringContainsString("'type_id' => \$resolvedVoucherNumber", $controller);
        self::assertStringContainsString("\$displaySeries = \$voucherPrefix;", $controller);
        self::assertStringContainsString("'BOR','BOP' => [2,3]", $controller);
        // Prevent a missing local variable from turning a bank save into
        // the generic 500 error reported by the confirmation modal.
        self::assertStringContainsString(
            "\$requestedDisplaySeries = strtoupper(trim((string)\$request->input('display_series','')));",
            $controller
        );
        self::assertStringContainsString(
            "\$displaySeries !== \$requestedDisplaySeries || !\$linkedVerifiedSource",
            $controller
        );
        self::assertStringContainsString("'CCR','CDR','CP' => [1]", $controller);
        self::assertStringNotContainsString("\$displaySeries = match ([\$effectiveVoucherType, \$paymentType])", $controller);
        self::assertStringNotContainsString("->update(['transaction_type'=>\$displaySeries])", $controller);
    }

    public function test_bank_number_and_source_type_match_before_display_identity_is_created(): void
    {
        $identity = file_get_contents(base_path('app/Services/Finance/ProjectVoucherDisplayIdentity.php'));
        self::assertStringContainsString("BANK_VOUCHER_SOURCE_SERIES_MISMATCH", $identity);
        self::assertStringContainsString("(string)\$source->transaction_type !== \$series", $identity);
        self::assertStringContainsString("(int)\$source->type_id !== \$bankSequence", $identity);
        self::assertStringContainsString("VOUCHER_SOURCE_IDENTITY_MISMATCH", $identity);
    }

    public function test_no_existing_voucher_rows_are_relabelled_by_milestone(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/LedgerController.php'));
        self::assertStringContainsString("CustomerLedger::create([", $controller);
        self::assertStringNotContainsString("CustomerLedger::query()->update(['transaction_type'", $controller);
    }
}
