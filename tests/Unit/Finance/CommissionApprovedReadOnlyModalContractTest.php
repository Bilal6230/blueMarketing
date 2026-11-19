<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class CommissionApprovedReadOnlyModalContractTest extends TestCase
{
    public function test_approved_commission_opens_editable_but_reversed_posting_remains_view_only(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/CommissionVoucherController.php');
        $view=file_get_contents($root.'/resources/views/admin/reports/vouchers/edit_commision_voucher.blade.php');
        self::assertIsString($controller);
        self::assertIsString($view);

        $from=strpos($controller,'public function edit($id)');
        $to=strpos($controller,'public function update(Request $request',$from);
        self::assertNotFalse($from);
        self::assertNotFalse($to);
        $edit=substr($controller,$from,$to-$from);
        self::assertStringNotContainsString('abort(409',$edit);
        self::assertStringContainsString("'readOnly'",$edit);
        self::assertStringContainsString("['draft','approved']",$edit);
        self::assertStringContainsString("whereIn('reversal_of_batch_id'",$edit);
        self::assertStringContainsString("View Approved Commission Voucher",$edit);


        self::assertStringContainsString('@if($readOnly ?? false)',$view);
        self::assertStringContainsString('<fieldset @if($readOnly ?? false) disabled @endif>',$view);
        self::assertStringContainsString('@unless($readOnly ?? false)',$view);
        self::assertStringContainsString('this.tomselect.disable()',$view);
        self::assertStringContainsString('Approved Commission Voucher — View only',$view);
        self::assertStringContainsString('CommissionApprovedVoucherEditor::class',$controller);
        self::assertStringContainsString('status === \'draft\'',$view);
    }
}
