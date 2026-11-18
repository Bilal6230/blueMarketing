<?php

namespace Tests\Unit\Finance;

use App\Support\Finance\FinanceActionPermission;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;

final class FinanceVoucherEditPermissionParityTest extends TestCase
{
    public function test_edit_routes_use_edit_grant_instead_of_resubmit_or_legacy_update(): void
    {
        $mapping = file_get_contents(dirname(__DIR__, 3).'/app/Support/Finance/FinanceActionPermission.php');
        self::assertIsString($mapping);
        self::assertStringContainsString("'finance.daily-vouchers.revise.edit','finance.daily-vouchers.revise.store'", $mapping);
        self::assertStringContainsString("str_ends_with(\$name,'.edit') ? 'edit' : 'edit'", $mapping);
        self::assertSame('finance cr edit', FinanceActionPermission::name('cr','edit'));
        self::assertNotSame(FinanceActionPermission::name('cr','edit'),FinanceActionPermission::name('cr','resubmit'));
    }

    public function test_daily_directory_edit_link_checks_same_granular_grant(): void
    {
        $directory=file_get_contents(dirname(__DIR__,3).'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        self::assertIsString($directory);
        self::assertStringContainsString("\$canEdit = \$module !== null && auth()->user()?->can('finance '.\$module.' edit')",$directory);
        self::assertStringContainsString("['edit_url'=>'edit', 'revise_url'=>'edit'",$directory);
        self::assertStringContainsString("['posted','approved']",$directory);
        self::assertStringNotContainsString("can('update voucher')",$directory);
        self::assertStringNotContainsString("can('edit jv')",$directory);
        self::assertStringNotContainsString("can('read jv')",$directory);
        self::assertStringNotContainsString("can('delete voucher')",$directory);
    }

    public function test_edit_form_authorizes_by_granular_source_module_not_original_submitter(): void
    {
        $edit=file_get_contents(dirname(__DIR__,3).'/app/Http/Controllers/Finance/RejectedCashVoucherController.php');
        self::assertIsString($edit);
        self::assertSame(2,substr_count($edit,"can('finance '.\$module.' edit')"));
        self::assertStringContainsString('FinanceActionPermission::sourceModule($customerLedger)',$edit);
        self::assertStringNotContainsString("can('update voucher')",$edit);
        self::assertStringNotContainsString("approval->submitted_by===(int)\$actor->id",$edit);
    }

    public function test_reverse_listener_is_not_gated_by_obsolete_delete_permission(): void
    {
        $view=file_get_contents(dirname(__DIR__,3).'/resources/views/admin/finance/voucher/daily_directory.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString("on('click','.dv-typed-reverse'",$view);
        self::assertStringNotContainsString("@can('delete voucher')",$view);
    }
}
