<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class PlotLegacyMigrationCompatibilityTest extends TestCase
{
    public function test_all_plot_storage_migrations_use_unified_connection_guard(): void
    {
        $root=dirname(__DIR__,3);
        foreach ([
            'database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php',
            'database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php',
            'database/local_finance_migrations/2026_11_14_000101_repair_finance_plot_receipt_claim_fk.php',
            'database/local_finance_migrations/2026_11_14_000200_create_finance_plot_purchase_storage.php',
        ] as $path) {
            $source=file_get_contents($root.'/'.$path);
            self::assertIsString($source,$path);
            self::assertStringContainsString('FinanceConnectionGuard::assertAllowed();',$source,$path);
            self::assertStringNotContainsString('V2IsolatedConnectionGuard',$source,$path);
            self::assertStringNotContainsString('V2_ISOLATED_CONNECTION_REQUIRED',$source,$path);
            self::assertStringContainsString('Schema::hasTable',$source,$path);
        }
    }

    public function test_feature_tests_do_not_require_retired_isolated_database_mode(): void
    {
        $source=file_get_contents(dirname(__DIR__,3).'/tests/Feature/Finance/PlotEntryWriterTest.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('V2_ISOLATED_CONNECTION_REQUIRED',$source);
        self::assertStringContainsString('test_phase08_existing_storage_is_unchanged_with_production_environment',$source);
        self::assertStringContainsString('test_phase07_complete_storage_is_preserved_on_configured_connection',$source);
    }
}
