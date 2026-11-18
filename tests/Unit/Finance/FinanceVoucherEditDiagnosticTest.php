<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class FinanceVoucherEditDiagnosticTest extends TestCase
{
    public function test_diagnostic_is_read_only_and_inspects_actual_eligibility_separately_from_role_permission(): void
    {
        $root=dirname(__DIR__,3);
        $command=file_get_contents($root.'/app/Console/Commands/DiagnoseVoucherEditAccess.php');
        self::assertIsString($command);
        self::assertStringContainsString('finance:diagnose-edit',$command);
        self::assertStringContainsString('Role grant:', $command);
        self::assertStringContainsString('effective access:', $command);
        self::assertStringContainsString('LEDGER LINK COUNT', $command);
        self::assertStringContainsString('PENDING: existing edit service rejects pending decision',$command);
        self::assertStringNotContainsString('->update(', $command);
        self::assertStringNotContainsString('->delete(', $command);
        self::assertStringNotContainsString('->insert(', $command);
    }

    public function test_cash_voucher_edit_action_is_issued_only_from_granular_edit_permission(): void
    {
        $root=dirname(__DIR__,3);
        $directory=file_get_contents($root.'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        $edit=file_get_contents($root.'/app/Http/Controllers/Finance/RejectedCashVoucherController.php');
        self::assertStringContainsString("auth()->user()?->can('finance '.\$module.' edit')",$directory);
        self::assertStringContainsString("['edit_url'=>'edit', 'revise_url'=>'edit'",$directory);
        self::assertStringContainsString("can('finance '.\$module.' edit')",$edit);
        self::assertStringNotContainsString("can('update voucher')",$edit);
    }
}
