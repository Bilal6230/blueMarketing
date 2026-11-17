<?php

namespace Tests\Unit;

use App\Services\Finance\V2IsolatedConnectionGuard;
use App\Services\Finance\V2OriginalWorkflowAccess;
use Tests\TestCase;

final class UnifiedLocalFinanceConnectionTest extends TestCase
{
    private function localConnection(string $database, string $host = '127.0.0.1'): void
    {
        config()->set('app.env', 'local');
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => $host,
            'database' => $database,
            'url' => null,
            'read' => null,
            'write' => null,
        ]);
    }

    public function test_clean_final_uses_unified_finance_workflow(): void
    {
        $this->localConnection('bluenew_clean_final');
        self::assertTrue(V2IsolatedConnectionGuard::isAllowed());
        self::assertTrue(V2OriginalWorkflowAccess::normalErpMode());
        self::assertTrue(V2OriginalWorkflowAccess::mergedWriterAllowed());
        self::assertFalse(V2OriginalWorkflowAccess::selected());
    }

    public function test_unapproved_or_remote_database_is_rejected(): void
    {
        $this->localConnection('unapproved_database');
        self::assertFalse(V2IsolatedConnectionGuard::isAllowed());

        $this->localConnection('bluenew_clean_final', 'remote.example.org');
        self::assertFalse(V2IsolatedConnectionGuard::isAllowed());

        $this->localConnection('bluenew_clean_final');
        config()->set('app.env', 'production');
        self::assertFalse(V2IsolatedConnectionGuard::isAllowed());
    }

    public function test_replica_and_connection_url_cannot_bypass_guard(): void
    {
        $this->localConnection('bluenew_clean_final');
        config()->set('database.connections.mysql.read', ['host' => '127.0.0.1']);
        self::assertFalse(V2IsolatedConnectionGuard::isAllowed());

        $this->localConnection('bluenew_clean_final');
        config()->set('database.connections.mysql.url', 'mysql://localhost/example');
        self::assertFalse(V2IsolatedConnectionGuard::isAllowed());
    }
}
