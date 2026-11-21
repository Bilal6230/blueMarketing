<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class SchemaCleanupCandidateAuditContractTest extends TestCase
{
    public function test_schema_cleanup_is_read_only_and_requires_exact_database(): void
    {
        $path=base_path('app/Console/Commands/AuditSchemaCleanupCandidates.php');
        $source=file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('schema:audit-cleanup-candidates',$source);
        self::assertStringContainsString("getDatabaseName()",$source);
        self::assertStringContainsString("information_schema.KEY_COLUMN_USAGE",$source);
        self::assertStringContainsString("information_schema.TABLES",$source);
        self::assertStringContainsString('where(\'TABLE_SCHEMA\',$database)',$source);
        self::assertStringContainsString("pluck('TABLE_NAME')",$source);
        self::assertStringNotContainsString('Schema::getTableListing()',$source);
        self::assertStringContainsString("RETAIN_DATA",$source);
        self::assertStringContainsString("RETAIN_REFERENCED",$source);
        self::assertStringContainsString("RETAIN_FOREIGN_KEY",$source);
        self::assertStringContainsString("REVIEW_ONLY",$source);
        self::assertStringContainsString('No database change attempted.',$source);
        self::assertStringNotContainsString('dropIfExists(',$source);
        self::assertStringNotContainsString('Schema::drop(',$source);
        self::assertStringNotContainsString('DROP TABLE',$source);
    }
}
