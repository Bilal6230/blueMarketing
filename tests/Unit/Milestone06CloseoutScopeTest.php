<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * M06 release scope: authenticated HTTP mutation auditing is mandatory,
 * whereas scheduled/background auditing is not deployed or required.
 * Audit evidence is retained until a separate retention policy is approved.
 */
final class Milestone06CloseoutScopeTest extends TestCase
{
    private function source(string $path): string
    {
        $content = file_get_contents(base_path($path));
        $this->assertNotFalse($content, $path.' must exist.');

        return $content;
    }

    public function test_admin_and_authenticated_mobile_and_finance_routes_keep_audit_middleware(): void
    {
        $web = $this->source('routes/web.php');
        $api = $this->source('routes/api.php');

        $this->assertStringContainsString(
            "'finance.action', \\App\\Http\\Middleware\\RecordActivityAudit::class",
            $web
        );
        $this->assertStringContainsString(
            "'auth:sanctum', \\App\\Http\\Middleware\\RecordActivityAudit::class",
            $api
        );
        $this->assertStringContainsString(
            "'auth:sanctum', 'throttle:30,1', \\App\\Http\\Middleware\\RecordActivityAudit::class",
            $api
        );
    }

    public function test_cron_and_background_auditing_are_not_required_or_automatically_enabled(): void
    {
        $kernel = $this->source('app/Console/Kernel.php');
        $routes = $this->source('routes/console.php');
        $runner = $this->source('app/Support/Audit/BackgroundActivityAudit.php');

        $this->assertStringNotContainsString('BackgroundActivityAudit', $kernel);
        $this->assertStringNotContainsString('BackgroundActivityAudit', $routes);
        $this->assertStringNotContainsString('activity-audit:prune', $kernel);
        $this->assertStringNotContainsString('activity-audit:prune', $routes);
        $this->assertStringContainsString('public function run(int $actorId, string $source, Closure $work)', $runner);
        $this->assertStringContainsString('DB::table(\'users\')->where(\'id\', $actorId)->exists()', $runner);
        $this->assertStringNotContainsString('schedule(', $runner);
    }

    public function test_audit_retention_is_observation_only_and_not_automatic_deletion(): void
    {
        $health = $this->source('app/Console/Commands/ActivityAuditHealth.php');
        $kernel = $this->source('app/Console/Kernel.php');

        $this->assertStringContainsString('retention planning only', $health);
        $this->assertStringContainsString('Missing audit query indexes:', $health);
        $this->assertStringNotContainsString('->delete(', $health);
        $this->assertStringNotContainsString('->truncate(', $health);
        $this->assertStringNotContainsString('DB::unprepared(', $health);
        $this->assertStringNotContainsString('activity-audit:prune', $kernel);
    }

    public function test_real_crm_controller_regression_and_commit_safety_tests_remain_present(): void
    {
        $api = $this->source('tests/Feature/ActivityAuditAuthenticatedApiTest.php');
        $transaction = $this->source('tests/Feature/ActivityAuditTransactionBoundaryTest.php');
        $mysql = $this->source('tests/Feature/ActivityAuditMysqlTriggerRollbackTest.php');

        $this->assertStringContainsString(
            'test_real_mobile_crm_store_controller_audits_new_lead_and_actor',
            $api
        );
        $this->assertStringContainsString(
            'test_thrown_exception_after_rolled_back_write_is_not_audited',
            $transaction
        );
        $this->assertStringContainsString(
            'test_mysql_existing_trigger_discards_audit_snapshot_after_rollback',
            $mysql
        );
    }
}
