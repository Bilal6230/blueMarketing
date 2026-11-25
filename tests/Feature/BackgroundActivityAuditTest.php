<?php

namespace Tests\Feature;

use App\Support\Audit\BackgroundActivityAudit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/** In-memory tests: no new tables or changes in the user's local MySQL DB. */
final class BackgroundActivityAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('activity_audits', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('actor_id');
            $table->string('action');
            $table->string('route_name')->nullable();
            $table->string('request_path');
            $table->integer('response_status');
            $table->string('ip_address')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('activity_audit_impacts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('activity_audit_id')->nullable();
            $table->string('request_token')->nullable();
            $table->string('table_name');
            $table->string('operation');
            $table->string('record_id')->nullable();
            $table->string('capture_type');
            $table->text('before_values')->nullable();
            $table->text('after_values')->nullable();
            $table->integer('statement_count')->default(1);
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('audit_background_fixture', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('state');
        });
        DB::table('users')->insert(['id' => 17, 'name' => 'Test actor']);
    }

    public function test_committed_background_write_is_attributed_to_explicit_user(): void
    {
        $result = (new BackgroundActivityAudit())->run(17, 'employee.sync', function () {
            DB::table('audit_background_fixture')->insert(['state' => 'committed']);
            return 'processed';
        });

        $this->assertSame('processed', $result);
        $this->assertSame(1, DB::table('audit_background_fixture')->count());
        $event = DB::table('activity_audits')->first();
        $this->assertSame(17, (int) $event->actor_id);
        $this->assertSame('JOB', $event->action);
        $this->assertSame('background:employee.sync', $event->route_name);
        $this->assertSame(1, DB::table('activity_audit_impacts')
            ->where('activity_audit_id', $event->id)
            ->where('table_name', 'audit_background_fixture')->count());
    }

    public function test_failed_background_transaction_does_not_create_audit_or_business_row(): void
    {
        try {
            (new BackgroundActivityAudit())->run(17, 'employee.sync', function (): void {
                DB::table('audit_background_fixture')->insert(['state' => 'rolled-back']);
                throw new RuntimeException('Job failed');
            });
            $this->fail('Job exception should be preserved.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Job failed', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('audit_background_fixture')->count());
        $this->assertSame(0, DB::table('activity_audits')->count());
        $this->assertSame(0, DB::table('activity_audit_impacts')->count());
    }

    public function test_read_only_background_job_does_not_pollute_audit_history(): void
    {
        $result = (new BackgroundActivityAudit())->run(17, 'employee.lookup', function () {
            return DB::table('users')->count();
        });
        $this->assertSame(1, $result);
        $this->assertSame(0, DB::table('activity_audits')->count());
    }

    public function test_unknown_actor_is_rejected_without_running_work(): void
    {
        $didRun = false;
        try {
            (new BackgroundActivityAudit())->run(999, 'employee.sync', function () use (&$didRun): void {
                $didRun = true;
            });
            $this->fail('Unknown actor must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertFalse($didRun);
            $this->assertSame(0, DB::table('activity_audits')->count());
        }
    }

    public function test_nested_outer_transaction_is_rejected_without_running_work(): void
    {
        DB::beginTransaction();
        try {
            $this->expectException(RuntimeException::class);
            (new BackgroundActivityAudit())->run(17, 'employee.sync', function (): void {
                DB::table('audit_background_fixture')->insert(['state' => 'unsafe']);
            });
        } finally {
            DB::rollBack();
        }
    }
}
