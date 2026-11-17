<?php

namespace Tests\Unit;

use App\Services\Finance\FinanceConnectionGuard;
use App\Services\Finance\FinanceWorkflowAccess;
use Tests\TestCase;

final class UnifiedLocalFinanceConnectionTest extends TestCase
{
    private function configuredConnection(string $database, string $environment, string $host): void
    {
        config()->set('app.env', $environment);
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => $host,
            'database' => $database,
            'url' => null,
        ]);
    }

    public function test_unified_finance_works_without_a_database_allowlist(): void
    {
        foreach ([
            ['bluenew_clean_final', 'local', '127.0.0.1'],
            ['customer_production', 'production', 'mysql.internal'],
            ['new_database', 'testing', 'remote.example.org'],
        ] as [$database, $environment, $host]) {
            $this->configuredConnection($database, $environment, $host);
            self::assertTrue(FinanceConnectionGuard::isAllowed());
            self::assertTrue(FinanceWorkflowAccess::normalErpMode());
            self::assertTrue(FinanceWorkflowAccess::mergedWriterAllowed());
            self::assertTrue(FinanceWorkflowAccess::enabled());
            self::assertFalse(FinanceWorkflowAccess::selected());
        }
    }

    public function test_finance_uses_laravel_configured_connection_including_url_and_replicas(): void
    {
        $this->configuredConnection('client_database', 'production', 'mysql.internal');
        config()->set('database.connections.mysql.url', 'mysql://example.invalid/client_database');
        config()->set('database.connections.mysql.read', ['host' => 'mysql-read.internal']);
        config()->set('database.connections.mysql.write', ['host' => 'mysql-write.internal']);
        self::assertTrue(FinanceConnectionGuard::isAllowed());
    }

    public function test_only_missing_connection_configuration_is_rejected(): void
    {
        config()->set('database.default', 'missing');
        config()->set('database.connections.missing', null);
        self::assertFalse(FinanceConnectionGuard::isAllowed());
        self::assertFalse(FinanceWorkflowAccess::mergedWriterAllowed());
    }
}
