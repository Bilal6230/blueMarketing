<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordActivityAudit;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Real middleware + SQLite transaction integration gate (no production data).
 * MySQL trigger atomicity is checked separately by owner strict health gate;
 * this test verifies the request-level collector does not claim rolled-back
 * Query Builder operations were committed.
 */
final class ActivityAuditTransactionBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('activity_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('actor_id');
            $table->string('action');
            $table->string('route_name')->nullable();
            $table->string('request_path');
            $table->unsignedInteger('response_status');
            $table->string('ip_address')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('activity_audit_impacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('activity_audit_id')->nullable();
            $table->string('request_token')->nullable();
            $table->string('table_name');
            $table->string('operation');
            $table->string('record_id')->nullable();
            $table->string('capture_type');
            $table->text('before_values')->nullable();
            $table->text('after_values')->nullable();
            $table->unsignedInteger('statement_count')->default(1);
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('audit_fixture_records', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status');
        });
    }

    private function perform(callable $write): void
    {
        $request = Request::create('/admin/audit-fixture', 'POST');
        $request->setUserResolver(fn () => new GenericUser(['id' => 17]));
        $request->setRouteResolver(fn () => new Route('POST', 'admin/audit-fixture', function () {
        }));
        $request->route()->name('audit.fixture.write');

        (new RecordActivityAudit())->handle($request, function () use ($write): Response {
            $write();
            return new Response('OK', 200);
        });
    }

    public function test_committed_query_builder_write_is_recorded(): void
    {
        $this->perform(function (): void {
            DB::transaction(function (): void {
                DB::table('audit_fixture_records')->insert(['status' => 'committed']);
            });
        });

        $this->assertSame(1, DB::table('audit_fixture_records')->count());
        $this->assertSame(1, DB::table('activity_audits')->count());
        $this->assertSame(1, DB::table('activity_audit_impacts')
            ->where('table_name', 'audit_fixture_records')
            ->where('capture_type', 'sql_statement')->count());
    }

    public function test_caught_transaction_rollback_does_not_create_false_audit(): void
    {
        $this->perform(function (): void {
            try {
                DB::transaction(function (): void {
                    DB::table('audit_fixture_records')->insert(['status' => 'rolled-back']);
                    throw new \RuntimeException('Intentional test rollback');
                });
            } catch (\RuntimeException $exception) {
                // Typical legacy AJAX controller returns 200 after a
                // handled failure; the collector must still discard writes.
            }
        });

        $this->assertSame(0, DB::table('audit_fixture_records')->count());
        $this->assertSame(0, DB::table('activity_audits')->count());
        $this->assertSame(0, DB::table('activity_audit_impacts')->count());
    }

    public function test_committed_write_before_a_later_rollback_is_preserved(): void
    {
        $this->perform(function (): void {
            DB::transaction(function (): void {
                DB::table('audit_fixture_records')->insert(['status' => 'committed']);
            });
            try {
                DB::transaction(function (): void {
                    DB::table('audit_fixture_records')->insert(['status' => 'rolled-back']);
                    throw new \RuntimeException('Intentional second rollback');
                });
            } catch (\RuntimeException $exception) {
                // The earlier committed write remains a real change.
            }
        });

        $this->assertSame(1, DB::table('audit_fixture_records')->count());
        $this->assertSame(1, DB::table('activity_audits')->count());
        $this->assertSame(1, DB::table('activity_audit_impacts')
            ->where('table_name', 'audit_fixture_records')
            ->where('capture_type', 'sql_statement')->count());
    }
}
