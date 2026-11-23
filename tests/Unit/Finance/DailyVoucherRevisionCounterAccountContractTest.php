<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class DailyVoucherRevisionCounterAccountContractTest extends TestCase
{
    public function test_daily_directory_uses_real_revision_route_instead_of_cash_form_modal(): void
    {
        $root=dirname(__DIR__,3);
        $directory=file_get_contents($root.'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        $routes=file_get_contents($root.'/routes/web.php');
        self::assertIsString($directory);
        self::assertIsString($routes);
        self::assertStringContainsString("route('finance.daily-vouchers.revise.edit'",$directory);
        self::assertStringContainsString('RejectedCashVoucherController::class',$routes);
    }

    public function test_real_revision_modal_preselects_opposite_account_for_all_five_series(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/RejectedCashVoucherController.php');
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/rejected_cash_revision.blade.php');
        $editor=file_get_contents($root.'/app/Services/Finance/SameSourceCashVoucherEdit.php');
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertIsString($editor);

        self::assertStringContainsString("whereIn('transaction_type',['CR','CP','CCR','CDR'])",$controller);
        self::assertStringContainsString("->orderBy('id')->get()",$controller);
        self::assertStringContainsString('$counterLeg=$legs->count()===2 ? $legs->last() : null',$controller);
        self::assertStringContainsString('counterAccountId',$controller);
        self::assertStringContainsString('counterAccountId', $view);
        self::assertStringContainsString('name="counter_account_id"', $view);
        self::assertStringContainsString('@selected((int)$counterAccountId===(int)$counterOption->id)',$view);
        self::assertStringContainsString("'counter_account_id'=>['nullable','integer','min:1']",$controller);
        self::assertStringContainsString("'counter_account_id'=>$legCount===2",$editor);
        self::assertStringContainsString('Debit and Credit accounts must be different.', $view);
        self::assertStringContainsString('cr-revise-counter-account',$view);
    }

    public function test_verified_bank_counter_identity_is_read_only_and_history_is_not_rewritten(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/RejectedCashVoucherController.php');
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/rejected_cash_revision.blade.php');
        self::assertStringContainsString("where('voucher_type',$revisionPolicyType)",$controller);
        self::assertStringContainsString("where('account_mapping_id',$counterLeg->project_head_subheads_id)",$controller);
        self::assertStringContainsString('id="cr-revise-bank-counter"',$view);
        self::assertStringContainsString('account changes require audited bank correction',strtolower($view));
        self::assertStringContainsString('Legacy voucher — no linked counter-account',$view);
    }
}
