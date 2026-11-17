<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CleanDatabasePerformanceAuditTest extends TestCase
{
    public function test_explain_audit_is_local_only_and_does_not_change_data(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/tools/schema/audit_query_performance.php');
        self::assertIsString($source);
        self::assertStringContainsString("bluenew_clean_final", $source);
        self::assertStringContainsString("app()->environment('local')", $source);
        self::assertStringContainsString("information_schema.STATISTICS", $source);
        self::assertStringContainsString("information_schema.COLUMNS", $source);
        self::assertStringContainsString("'EXPLAIN '.\$sql", $source);
        self::assertStringNotContainsString("EXPLAIN ANALYZE '.\$sql", $source);
        self::assertStringContainsString("DO NOT DROP automatically", $source);
        self::assertStringNotContainsString("DB::statement('ALTER", $source);
        self::assertStringNotContainsString("Schema::table(", $source);
        self::assertStringNotContainsString("->insert(", $source);
        self::assertStringNotContainsString("->update(", $source);
        self::assertStringNotContainsString("->delete(", $source);
    }

    public function test_workload_covers_journals_ledger_customer_bookings_and_leads(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/tools/schema/audit_query_performance.php');
        self::assertIsString($source);
        foreach ([
            'journal_vouchers',
            'journal_voucher_details',
            'ledgers',
            'customer_ledger',
            'bookings',
            'booking_details',
            'leads',
            'works',
            'project_head_subheads',
            'finance_posting_batches',
            'finance_posting_lines',
        ] as $table) {
            self::assertStringContainsString("'".$table."'", $source);
        }
    }
}
