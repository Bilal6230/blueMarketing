<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2BankTransferLifecycleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class NormalBankTransferReversalGateTest extends TestCase
{
    public function test_bank_transfer_reverse_is_blocked_outside_approved_database(): void
    {
        config(['app.env' => 'testing']);
        $this->expectException(\DomainException::class);
        (new V2BankTransferLifecycleService())->reverse(
            3, 'deposit', 12, str_repeat('x', 24), '2026-11-17', 'Test reversal', null
        );
    }

    public function test_bank_transfer_reversal_requires_exact_source_and_never_releases_cleared_cheque_implicitly(): void
    {
        $service = file_get_contents(app_path('Services/Finance/V2BankTransferLifecycleService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Finance/VoucherController.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $view = file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));

        $this->assertStringContainsString("where('transfer.posting_batch_id', \$batchId)", $service);
        $this->assertStringContainsString("where('claim.source_kind', 'bank_transfer')", $service);
        $this->assertStringContainsString('BANK_TRANSFER_LINKED_CHEQUE_REQUIRES_CUSTODY_RELEASE', $service);
        $this->assertStringContainsString('reversePostedBatch(', $service);
        $this->assertStringContainsString('function reverseTransferVoucher(', $controller);
        $this->assertStringContainsString("'can_reverse' =>", $controller);
        $this->assertStringContainsString("name('finance.voucher.transfer.reverse')", $routes);
        $this->assertStringContainsString('permission:delete voucher', $routes);
        $this->assertStringContainsString('bankTransferReverseForm', $view);
        $this->assertStringContainsString('r.can_reverse', $view);
        $this->assertStringContainsString('r.reversed', $view);
    }
}
