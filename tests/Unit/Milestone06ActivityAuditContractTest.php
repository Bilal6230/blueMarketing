<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Milestone06ActivityAuditContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_admin_routes_require_read_audit_permission_for_both_listing_and_details(): void
    {
        $routes = $this->source('routes/web.php');
        $this->assertStringContainsString('RecordActivityAudit::class', $routes);
        $this->assertStringContainsString("Route::get('activity-audit'", $routes);
        $this->assertStringContainsString("Route::get('activity-audit/{audit}'", $routes);
        $this->assertSame(2, substr_count($routes, "permission:read activity audit"));
    }

    public function test_recorder_never_captures_request_bodies_or_query_strings(): void
    {
        $middleware = $this->source('app/Http/Middleware/RecordActivityAudit.php');
        $capture = $this->source('app/Support/Audit/ActivityAuditCapture.php');
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            $this->assertStringContainsString("'".$verb."'", $middleware);
        }
        $this->assertStringContainsString("DB::table('activity_audits')->insertGetId", $middleware);
        $this->assertStringContainsString("DB::table('activity_audit_impacts')->insert", $middleware);
        $this->assertStringContainsString('$request->path()', $middleware);
        $this->assertStringNotContainsString('$request->all()', $middleware);
        $this->assertStringNotContainsString('$request->input()', $middleware);
        $this->assertStringNotContainsString('$request->fullUrl()', $middleware);
        $this->assertStringNotContainsString('$query->bindings', $capture);
        $this->assertStringNotContainsString('$query->sql,', $capture);
        $this->assertStringContainsString('Log::error', $middleware);
    }

    public function test_migrations_keep_audit_evidence_and_index_lookup_paths(): void
    {
        $migration = $this->source('database/migrations/2026_11_25_130000_create_activity_audits.php');
        $detail = $this->source('database/migrations/2026_11_25_131000_add_activity_audit_impacts.php');
        $this->assertStringContainsString("Schema::create('activity_audits'", $migration);
        $this->assertStringContainsString("Permission::findOrCreate('read activity audit', 'web')", $migration);
        $this->assertStringContainsString("Schema::create('activity_audit_impacts'", $detail);
        $this->assertStringContainsString("['activity_audit_id', 'table_name']", $detail);
        $this->assertStringContainsString("['table_name', 'record_id']", $detail);
        $this->assertStringNotContainsString('Schema::dropIfExists', $migration.$detail);
    }

    public function test_model_row_differences_are_separate_from_sql_table_observations(): void
    {
        $capture = $this->source('app/Support/Audit/ActivityAuditCapture.php');
        $this->assertStringContainsString("'eloquent_row'", $capture);
        $this->assertStringContainsString("'sql_statement'", $capture);
        $this->assertStringContainsString('getRawOriginal()', $capture);
        $this->assertStringContainsString('getChanges()', $capture);
        $this->assertStringContainsString('self::VISIBLE', $capture);
        $this->assertStringNotContainsString('getBindings()', $capture);
    }

    public function test_modern_activity_history_has_column_filters_ajax_skeleton_and_view_modal(): void
    {
        $blade = $this->source('resources/views/admin/activity-audit/index.blade.php');
        $controller = $this->source('app/Http/Controllers/ActivityAuditController.php');
        $this->assertStringContainsString('audit-column-filter', $blade);
        $this->assertStringContainsString('$.blueTableSkeleton.show', $blade);
        $this->assertStringContainsString('audit-detail-modal', $blade);
        $this->assertStringContainsString('audit-view', $blade);
        $this->assertStringContainsString('sql_statement', $blade);
        $this->assertStringContainsString("->paginate(25)", $controller);
        $this->assertStringContainsString("DB::table('activity_audit_impacts')", $controller);
        $this->assertStringNotContainsString('method="POST"', $blade);
        $this->assertStringNotContainsString('method="DELETE"', $blade);
    }
}
