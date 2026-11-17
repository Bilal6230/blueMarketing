<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\FinanceConnectionGuard;
use App\Services\Finance\FinanceWorkflowAccess;
use Tests\TestCase;

final class FinanceConnectionGuardTest extends TestCase
{
    public function test_configured_database_is_accepted_regardless_of_name_host_or_environment(): void
    {
        foreach ([
            ['local', 'bluenew', '127.0.0.1'],
            ['production', 'client_live_database', 'mysql.internal'],
            ['testing', 'another_database', '10.10.1.3'],
        ] as [$env, $database, $host]) {
            config()->set('app.env', $env);
            config()->set('database.default', 'mysql');
            config()->set('database.connections.mysql', [
                'driver' => 'mysql', 'database' => $database, 'host' => $host,
            ]);
            self::assertTrue(FinanceConnectionGuard::isAllowed());
            self::assertTrue(FinanceWorkflowAccess::normalErpMode());
            self::assertFalse(FinanceWorkflowAccess::selected());
        }
    }

    public function test_missing_connection_configuration_is_refused(): void
    {
        config()->set('database.default', 'missing');
        config()->set('database.connections.missing', null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FINANCE_CONNECTION_NOT_CONFIGURED');
        FinanceConnectionGuard::assertAllowed();
    }
}
