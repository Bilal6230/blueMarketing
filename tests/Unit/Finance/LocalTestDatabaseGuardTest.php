<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinanceConnectionGuard;
use Tests\TestCase;

final class LocalTestDatabaseGuardTest extends TestCase
{
    private function configure(string $database = 'bluenew', string $host = '127.0.0.1',
        string $environment = 'local', string $optIn = '', string $url = ''): void
    {
        config([
            'app.env' => $environment,
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql', 'database' => $database, 'host' => $host,
                'url' => $url, 'read' => null, 'write' => null,
            ],
            'finance.v2_local_test_database' => $optIn,
        ]);
    }

    public function test_existing_local_test_database_is_available_without_mode_flag(): void
    {
        $this->configure();
        config(['finance.normal_erp_mode' => false]);
        $this->assertTrue(FinanceConnectionGuard::isAllowed());
        $this->assertTrue(\App\Services\Finance\FinanceWorkflowAccess::normalErpMode());
        $this->assertFalse(\App\Services\Finance\FinanceWorkflowAccess::selected());
        config(['finance.v2_pilot_api_enabled' => true, 'finance.v2_pilot_write_enabled' => true]);
        $this->assertFalse(\App\Services\Finance\FinanceWorkflowAccess::selected());
        // Stale local toggles must not change the recognized DB identity.
        config(['finance.normal_erp_mode' => true]);
        $this->assertTrue(FinanceConnectionGuard::isAllowed());
    }

    public function test_remote_host_is_rejected_even_with_opt_in(): void
    {
        $this->configure(host: 'db.example.com', optIn: 'bluenew');
        $this->assertFalse(FinanceConnectionGuard::isAllowed());
    }

    public function test_database_url_override_is_rejected(): void
    {
        $this->configure(optIn: 'bluenew', url: 'mysql://other-host/database');
        $this->assertFalse(FinanceConnectionGuard::isAllowed());
    }

    public function test_production_environment_is_rejected(): void
    {
        $this->configure(environment: 'production', optIn: 'bluenew');
        $this->assertFalse(FinanceConnectionGuard::isAllowed());
    }

    public function test_different_database_name_is_rejected(): void
    {
        $this->configure(database: 'another_test_db', optIn: 'bluenew');
        $this->assertFalse(FinanceConnectionGuard::isAllowed());
    }

    public function test_read_write_replicas_are_rejected(): void
    {
        $this->configure(optIn: 'bluenew');
        config(['database.connections.mysql.read' => ['host' => 'localhost']]);
        $this->assertFalse(FinanceConnectionGuard::isAllowed());
    }

    public function test_standard_isolated_v2_database_remains_allowed(): void
    {
        $this->configure(database: 'bluemarketing_v2_local');
        $this->assertTrue(FinanceConnectionGuard::isAllowed());
        $this->assertFalse(\App\Services\Finance\FinanceWorkflowAccess::normalErpMode());
        $this->assertTrue(\App\Services\Finance\FinanceWorkflowAccess::selected());
    }
}
