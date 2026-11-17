<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinanceConnectionGuard;
use App\Services\Finance\FinanceWorkflowAccess;
use Tests\TestCase;

final class FinanceConnectionCompatibilityTest extends TestCase
{
    public function test_database_selection_does_not_change_the_finance_workflow(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql', 'database' => 'any_database',
            'host' => 'remote.example.org', 'read' => ['host' => 'replica.example.org'],
        ]);
        config()->set('app.env', 'production');

        self::assertTrue(FinanceConnectionGuard::isAllowed());
        self::assertTrue(FinanceWorkflowAccess::mergedWriterAllowed());
        self::assertFalse(FinanceWorkflowAccess::selected());
        self::assertTrue(FinanceWorkflowAccess::enabled());
    }
}
