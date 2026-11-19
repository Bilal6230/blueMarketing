<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class FinanceCoreMigrationUnifiedConnectionContractTest extends TestCase
{
    public function test_finance_posting_schema_migration_uses_configured_database_not_name_allowlist(): void
    {
        $root=dirname(__DIR__,3);
        $migration=file_get_contents($root.
            '/database/finance_legacy_migrations/2026_10_30_000100_create_finance_finance_posting_core.php');
        self::assertIsString($migration);
        self::assertStringContainsString('FinanceConnectionGuard::assertAllowed()',$migration);
        self::assertStringContainsString('assertFinanceBaseline()',$migration);
        self::assertStringNotContainsString('bluemarketing_v2_',$migration);
        self::assertStringNotContainsString('assertIsolatedV2',$migration);
        // Project and PK FK compatibility checks are still mandatory.
        self::assertStringContainsString('self::REQUIRED',$migration);
        self::assertStringContainsString('information_schema.COLUMNS',$migration);
    }
}
