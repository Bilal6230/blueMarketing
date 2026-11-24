<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardModuleMetricsGlobalLabourContractTest extends TestCase
{
    public function test_labour_dashboard_count_handles_global_master_without_project_column(): void
    {
        $root=dirname(__DIR__,2);
        $metrics=file_get_contents($root.'/app/Services/Dashboard/ModuleMetrics.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_24_120000_make_labours_global_master.php');
        self::assertIsString($metrics);
        self::assertIsString($migration);
        self::assertStringContainsString("dropColumn('project_id')", $migration);
        self::assertStringContainsString("Schema::hasColumn('labours', 'project_id')", $metrics);
        self::assertStringContainsString("Schema::hasColumn('labours', 'status')", $metrics);
        self::assertStringContainsString("Schema::hasColumn('labours', 'deleted_at')", $metrics);
        self::assertStringContainsString("->from('labour_attendances as la')", $metrics);
        self::assertStringContainsString("->whereColumn('la.labour_id', 'labours.id')", $metrics);
        self::assertStringContainsString("->where('la.project_id', \$projectId)", $metrics);
        self::assertStringContainsString("'active' => \$labours->count()", $metrics);
        self::assertStringNotContainsString("DB::table('labours')->where('project_id', \$projectId)", $metrics);
    }

    public function test_labour_attendance_kpis_remain_project_scoped(): void
    {
        $metrics=file_get_contents(dirname(__DIR__,2).'/app/Services/Dashboard/ModuleMetrics.php');
        self::assertStringContainsString(
            "DB::table('labour_attendances')->where('project_id', \$projectId)",$metrics
        );
        foreach (['present','pending_bills','payable','cost'] as $key) {
            self::assertStringContainsString("'".$key."' =>",$metrics);
        }
    }
}
