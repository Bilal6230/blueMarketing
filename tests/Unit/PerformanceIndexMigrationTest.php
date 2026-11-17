<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PerformanceIndexMigrationTest extends TestCase
{
    public function test_reversible_index_migration_uses_only_three_approved_read_path_indexes(): void
    {
        $root=dirname(__DIR__,2);
        $migration=file_get_contents($root.'/database/migrations/2026_11_17_999999_optimize_read_paths_indexes.php');
        $audit=file_get_contents($root.'/tools/schema/audit_clean_reconciliation.php');
        self::assertIsString($migration);
        self::assertIsString($audit);
        foreach ([
            'idx_jv_project_type_recent',
            'idx_ledgers_source_active_recent',
            'idx_customer_project_type_recent',
        ] as $index) {
            self::assertSame(2,substr_count($migration, "'".$index."'"),'Index should be added and reversible: '.$index);
            self::assertStringContainsString($index, $audit);
        }
        self::assertSame(3,substr_count($migration,'$table->index('));
        self::assertSame(3,substr_count($migration,'$table->dropIndex('));
        self::assertStringNotContainsString('$table->dropForeign(', $migration);
        self::assertStringNotContainsString('truncate(', strtolower($migration));
        self::assertStringNotContainsString('delete(', strtolower($migration));
    }
}
