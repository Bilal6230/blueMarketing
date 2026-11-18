<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class UnusedTableAuditSafetyTest extends TestCase
{
    public function test_schema_usage_audit_is_read_only_and_requires_multiple_independent_evidence_signals(): void
    {
        $source=file_get_contents(dirname(__DIR__,3).'/app/Console/Commands/AuditUnusedTables.php');
        self::assertIsString($source);
        foreach ([
            'schema:audit-unused-tables',
            'information_schema.TABLES',
            'information_schema.KEY_COLUMN_USAGE',
            'information_schema.VIEWS',
            'information_schema.TRIGGERS',
            'application_reference_examples',
            'incoming_fk_tables',
            'outgoing_fk_tables',
            'REVIEW_EMPTY_UNREFERENCED',
            'REVIEW_UNREFERENCED_WITH_DATA',
            'Never drop based only on this report.',
            'read_only',
        ] as $guard) self::assertStringContainsString($guard,$source);
        foreach ([
            'Schema::drop(', 'Schema::dropIfExists(', 'DB::unprepared(',
            'DB::statement(', '->delete(', '->truncate(',
            'DROP TABLE', 'TRUNCATE TABLE', 'DELETE FROM ',
        ] as $writeOperation) self::assertStringNotContainsString($writeOperation,$source);
        self::assertStringContainsString("['app','routes','resources/views','resources/js','public/js','config']",$source);
        self::assertStringNotContainsString('DB_DATABASE=',$source);
    }
}
