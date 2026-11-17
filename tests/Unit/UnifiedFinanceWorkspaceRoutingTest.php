<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UnifiedFinanceWorkspaceRoutingTest extends TestCase
{
    public function test_bank_and_plot_workspaces_do_not_fall_back_to_pilot_setup_page(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Finance/VoucherController.php');
        self::assertIsString($source);
        foreach ([
            'function bankVoucherWorkspace(',
            'function transferVoucherWorkspace(',
            'function plotReceiptWorkspace(',
            'function plotPurchaseWorkspace(',
        ] as $method) {
            self::assertStringContainsString($method, $source);
        }
        self::assertStringNotContainsString('unavailableV2Workspace', $source);
        self::assertStringNotContainsString('V2IsolatedConnectionGuard::isAllowed()', $source);
        self::assertStringContainsString('V2OriginalWorkflowAccess::authorizeRead($projectId)', $source);
    }

    public function test_db_name_and_environment_are_not_finance_feature_flags(): void
    {
        $root = dirname(__DIR__, 2).'/app/Services/Finance/';
        $guard = file_get_contents($root.'V2IsolatedConnectionGuard.php');
        $workflow = file_get_contents($root.'V2OriginalWorkflowAccess.php');
        self::assertIsString($guard);
        self::assertIsString($workflow);
        self::assertStringNotContainsString('bluenew_clean_final', $guard);
        self::assertStringNotContainsString('bluemarketing_v2_', $guard);
        self::assertStringNotContainsString("in_array(\$environment, ['local', 'testing']", $guard);
        self::assertStringNotContainsString("config('app.env') === 'local'", $workflow);
        self::assertStringContainsString('public static function normalErpMode(): bool', $workflow);
    }
}
