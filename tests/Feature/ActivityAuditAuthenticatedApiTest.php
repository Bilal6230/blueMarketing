<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordActivityAudit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises the same auth:sanctum + RecordActivityAudit middleware pipeline
 * used by mobile API mutations with an isolated test-only write handler.
 * This is not a live CRM controller test; no local MySQL tables are touched.
 */
final class ActivityAuditAuthenticatedApiTest extends TestCase
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
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
        Schema::create('activity_audits', function (Blueprint $table): void {
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
        Schema::create('audit_mobile_fixture', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('actor_id');
            $table->string('status');
        });

        Route::middleware(['api', 'auth:sanctum', RecordActivityAudit::class])
            ->post('/api/v1/mobile/audit-contract-probe', function (Request $request) {
                DB::transaction(function () use ($request): void {
                    DB::table('audit_mobile_fixture')->insert([
                        'actor_id' => (int) $request->user()->getAuthIdentifier(),
                        'status' => 'created',
                    ]);
                });
                return response()->json(['status' => true]);
            })->name('mobile.audit.contract.probe');

        Route::middleware(['api', 'auth:sanctum', RecordActivityAudit::class])
            ->post('/api/v1/mobile/audit-read-probe', function () {
                return response()->json(['status' => true]);
            })->name('mobile.audit.read.probe');
    }

    public function test_authenticated_mobile_style_api_write_records_real_actor_and_impact(): void
    {
        $user = User::query()->create([
            'name' => 'Employee Test',
            'email' => 'audit-mobile@example.test',
            'password' => 'unused-hash',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/mobile/audit-contract-probe')
            ->assertOk()->assertJsonPath('status', true);

        $this->assertSame(1, DB::table('audit_mobile_fixture')->count());
        $event = DB::table('activity_audits')->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $user->id, (int) $event->actor_id);
        $this->assertSame('POST', $event->action);
        $this->assertSame('mobile.audit.contract.probe', $event->route_name);
        $this->assertSame(1, DB::table('activity_audit_impacts')
            ->where('activity_audit_id', $event->id)
            ->where('table_name', 'audit_mobile_fixture')
            ->where('capture_type', 'sql_statement')->count());
    }

    public function test_unauthenticated_api_mutation_is_denied_without_write_or_audit(): void
    {
        $this->postJson('/api/v1/mobile/audit-contract-probe')->assertUnauthorized();
        $this->assertSame(0, DB::table('audit_mobile_fixture')->count());
        $this->assertSame(0, DB::table('activity_audits')->count());
        $this->assertSame(0, DB::table('activity_audit_impacts')->count());
    }

    public function test_authenticated_api_read_only_post_creates_no_false_activity(): void
    {
        $user = User::query()->create([
            'name' => 'Employee Test',
            'email' => 'audit-read@example.test',
            'password' => 'unused-hash',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/mobile/audit-read-probe')
            ->assertOk()->assertJsonPath('status', true);

        $this->assertSame(0, DB::table('activity_audits')->count());
        $this->assertSame(0, DB::table('activity_audit_impacts')->count());
    }
}
