<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class BankManagementMutationContractTest extends TestCase
{
    public function test_bank_edit_delete_routes_are_mapped_to_action_specific_permissions(): void
    {
        $routes=file_get_contents(base_path('routes/web.php'));
        $access=file_get_contents(base_path('app/Support/Finance/FinanceActionPermission.php'));
        $controller=file_get_contents(base_path('app/Http/Controllers/Finance/BankManagementMutationController.php'));

        foreach (['finance.bank-management.edit','finance.bank-management.update',
                  'finance.bank-management.delete'] as $name) {
            self::assertStringContainsString("name('$name')",$routes);
            self::assertStringContainsString("'$name'",$access);
        }
        self::assertStringContainsString('return [$method, $name === \'finance.bank-management.delete\' ? \'delete\' : \'edit\']', $access);
        self::assertStringContainsString('VoucherApproverAccess::isSuperAdmin($user)', $controller);
        self::assertStringContainsString("->lockForUpdate()->first()", $controller);
    }

    public function test_verified_bank_edit_requires_authorized_replacement_not_in_place_posting(): void
    {
        $controller=file_get_contents(base_path('app/Http/Controllers/Finance/BankManagementMutationController.php'));
        $mutation=file_get_contents(base_path('app/Services/Finance/BankManagementSourceMutation.php'));
        self::assertStringContainsString("->replace(", $controller);
        self::assertStringContainsString("function replace(", $mutation);
        self::assertStringContainsString('->withdraw($projectId,$method,$sourceId,$actorId,$reason)', $mutation);
        self::assertStringContainsString('\'status\']=\'pending\'', $mutation);
        self::assertStringContainsString('\'status\']=$changes[\'direction\']===\'out\'?\'issued\':\'received\'', $mutation);
        self::assertStringContainsString('finance_bank_voucher_allocations', $mutation);
    }

    public function test_online_and_cheque_management_forms_use_jquery_and_distinct_edit_delete_controls(): void
    {
        $online=file_get_contents(base_path('resources/views/admin/finance/online/intake.blade.php'));
        $cheque=file_get_contents(base_path('resources/views/admin/finance/cheque/intake.blade.php'));
        foreach ([[$online,'online-edit-button','online-delete-button'],
                   [$cheque,'cheque-edit-source','cheque-delete-source']] as $pair) {
            self::assertStringContainsString($pair[1],$pair[0]);
            self::assertStringContainsString($pair[2],$pair[0]);
            self::assertStringContainsString("new FormData", $pair[0]);
            self::assertStringContainsString("payload.append('_method','PUT')", $pair[0]);
            self::assertStringContainsString("dataType:'json'", $pair[0]);
        }
    }

    public function test_allocation_release_recognizes_actual_native_bor_bop_dr_types(): void
    {
        $release=file_get_contents(base_path('app/Services/Finance/BankVoucherAllocationRelease.php'));
        self::assertStringContainsString("'BOR' => ['BOR','CR']", $release);
        self::assertStringContainsString("'BOP' => ['BOP','CP']", $release);
        self::assertStringContainsString("'DR' => ['DR','CP']", $release);
    }
}
