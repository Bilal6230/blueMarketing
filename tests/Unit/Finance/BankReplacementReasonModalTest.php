<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class BankReplacementReasonModalTest extends TestCase
{
    public function test_linked_bank_replacement_reason_is_editable_inside_bootstrap_modal(): void
    {
        foreach (['cheque' => 'Cheque', 'online' => 'Online'] as $kind => $suffix) {
            $view = file_get_contents(base_path('resources/views/admin/finance/'.$kind.'/intake.blade.php'));
            self::assertStringContainsString('id="'.$kind.'-replacement-reason"', $view);
            self::assertStringContainsString('name="reason" class="form-control"', $view);
            self::assertStringContainsString("$('#".$kind."-replacement-reason').prop('required',editing".$suffix."Replacement)", $view);
            self::assertStringContainsString("payload.set('reason',\$.trim(\$('#".$kind."-replacement-reason').val()))", $view);
            self::assertStringNotContainsString("input:editing".$suffix."Replacement?'textarea'", $view);
            self::assertStringContainsString('Replacement reason must be 3–500 characters.', $view);
        }
    }
}
