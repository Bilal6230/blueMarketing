<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Milestone06ActivityAuditContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_admin_web_routes_record_mutations_and_audit_view_requires_specific_permission(): void
    {
        $routes = $this->source('routes/web.php');
        $this->assertStringContainsString('RecordActivityAudit::class', $routes);
        $this->assertStringContainsString("Route::get('activity-audit'", $routes);
        $this->assertStringContainsString('permission:read activity audit', $routes);
    }

    public function test_recorder_never_persists_sensitive_request_bodies_or_query_parameters(): void
    {
        $source = $this->source('app/Http/Middleware/RecordActivityAudit.php');
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            $this->assertStringContainsString("'".$verb."'", $source);
        }
        $this->assertStringContainsString("DB::table('activity_audits')->insert", $source);
        $this->assertStringContainsString('$request->path()', $source);
        $this->assertStringNotContainsString('$request->all()', $source);
        $this->assertStringNotContainsString('$request->input()', $source);
        $this->assertStringNotContainsString('$request->fullUrl()', $source);
        $this->assertStringContainsString('Log::error', $source);
    }

    public function test_audit_storage_has_actor_route_and_time_indexes_and_is_not_dropped_on_rollback(): void
    {
        $migration = $this->source('database/migrations/2026_11_25_130000_create_activity_audits.php');
        $this->assertStringContainsString("Schema::create('activity_audits'", $migration);
        $this->assertStringContainsString("['actor_id', 'created_at']", $migration);
        $this->assertStringContainsString("['route_name', 'created_at']", $migration);
        $this->assertStringContainsString("Permission::findOrCreate('read activity audit', 'web')", $migration);
        $this->assertStringNotContainsString("Schema::dropIfExists('activity_audits')", $migration);
    }

    public function test_read_only_history_page_has_filters_and_pagination_without_delete_actions(): void
    {
        $source = $this->source('resources/views/admin/activity-audit/index.blade.php');
        $this->assertStringContainsString('$events->links()', $source);
        $this->assertStringContainsString('name="actor_id"', $source);
        $this->assertStringContainsString('name="method"', $source);
        $this->assertStringNotContainsString('method="POST"', $source);
        $this->assertStringNotContainsString('method="DELETE"', $source);
    }
}
