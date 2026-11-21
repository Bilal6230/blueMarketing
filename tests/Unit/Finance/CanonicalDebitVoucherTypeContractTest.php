<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class CanonicalDebitVoucherTypeContractTest extends TestCase
{
    private function code(string $file): string
    {
        $contents=file_get_contents(base_path($file));
        self::assertIsString($contents);
        return str_replace(["\r\n","\r"],"\n",$contents);
    }

    public function test_new_daily_cash_vouchers_keep_their_actual_series_in_both_source_and_general_ledger(): void
    {
        $controller=$this->code('app/Http/Controllers/LedgerController.php');
        self::assertStringContainsString("if ((string)\$customerLedger->transaction_type!==\$displaySeries)",$controller);
        self::assertStringContainsString("'type' => \$displaySeries",$controller);
        self::assertStringContainsString('$approvalPolicyType = $displaySeries',$controller);
        $workflow=$this->code('app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        self::assertStringContainsString("'CP','DR'",$workflow);
        self::assertStringContainsString('[$sourceType,$series]',$workflow);
    }

    public function test_original_cp_alias_is_persisted_as_dr_for_new_typed_sources(): void
    {
        $source=$this->code('app/Services/Finance/OriginalCustomerWorkflow.php');
        self::assertStringContainsString("if (\$series==='CP') \$series='DR';",$source);
        self::assertStringContainsString("'CR', 'DR', 'PPR', 'EC', 'ECP'",$source);
        $cash=$this->code('app/Services/Finance/CashPostingWriter.php');
        self::assertStringContainsString("'CR', 'CP', 'DR'",$cash);
        self::assertStringContainsString('CashSourceLedgerLinker::class',$cash);
    }

    public function test_legacy_type_normalization_preserves_distinct_cdr_and_bop_source_types(): void
    {
        $source=$this->code('app/Console/Commands/NormalizeLegacyVoucherTypes.php');
        self::assertStringContainsString('finance:normalize-legacy-voucher-types',$source);
        self::assertStringContainsString("'CR','DR','CCR','CDR','BOR','BOP'",$source);
        self::assertStringContainsString("'project_voucher_display_numbers'",$source);
        self::assertStringContainsString("'customer_ledger'",$source);
        self::assertStringContainsString("'type' => 'DR'",$this->code('app/Http/Controllers/LedgerController.php'));
        self::assertStringNotContainsString('Schema::create(',$source);
        self::assertStringNotContainsString('Schema::dropIfExists(',$source);
    }
}
