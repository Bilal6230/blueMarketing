<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordActivityAudit;
use App\Models\User;
use App\Http\Controllers\Api\V1\Mobile\MobileCrmController;
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
            $table->unsignedInteger('project_id')->nullable();
            $table->timestamps();
        });
        // Only in-memory fixtures for the real MobileCrmController::store path.
        Schema::create('projects', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('project');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('project_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number')->unique();
            $table->string('mobile_number')->nullable()->unique();
            $table->string('nic_number')->nullable();
            // The real CRM controller explicitly inserts these nullable
            // attributes even when the API payload omits their values.
            $table->string('gender')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->integer('type')->nullable();
            $table->string('business')->nullable();
            $table->string('designation')->nullable();
            $table->unsignedInteger('zone_id')->nullable();
            $table->text('home_address')->nullable();
            $table->text('office_address')->nullable();
            $table->timestamp('follow_up')->nullable();
            $table->integer('follow_status')->nullable();
            $table->integer('is_active')->nullable();
            $table->unsignedInteger('create_by')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_user', function (Blueprint $table): void {
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('user_id');
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
            ->post('/api/v1/mobile/crm/actual-controller-probe', [MobileCrmController::class, 'store'])
            ->name('mobile.crm.actual-controller.probe');

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

    public function test_real_mobile_crm_store_controller_audits_new_lead_and_actor(): void
    {
        DB::table('projects')->insert(['id' => 3, 'project' => 'Local API fixture', 'is_active' => 1]);
        $stored = User::query()->create([
            'name' => 'CRM Test Employee',
            'email' => 'real-crm-audit@example.test',
            'password' => 'unused-hash',
            'project_id' => 3,
        ]);
        // Permission checks are isolated: the real API controller, Eloquent
        // writes and middleware still execute end-to-end.
        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->setRawAttributes($stored->getAttributes(), true);
        $actor->exists = true;
        $actor->shouldReceive('can')->with('create lead')->andReturn(true);
        $actor->shouldReceive('hasRole')->with('superadmin')->andReturn(false);
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/mobile/crm/actual-controller-probe', [
            'project_id' => 3,
            'first_name' => 'CRM',
            'last_name' => 'Audit',
            'phone_number' => '03000000001',
        ])->assertOk()->assertJsonPath('status', true);

        $this->assertSame(1, DB::table('leads')->count());
        $this->assertSame(1, DB::table('lead_user')->count());
        $lead = DB::table('leads')->first();
        $this->assertSame((int) $stored->id, (int) $lead->create_by);
        $event = DB::table('activity_audits')->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $stored->id, (int) $event->actor_id);
        $this->assertSame('mobile.crm.actual-controller.probe', $event->route_name);
        $this->assertSame('POST', $event->action);
        // Without MySQL row triggers, the same committed Eloquent insert
        // has two complementary observations: identified model row + SQL
        // statement/table impact. They must not be mistaken for duplicate
        // business writes or collapsed into a single capture type.
        $leadImpacts = DB::table('activity_audit_impacts')
            ->where('activity_audit_id', $event->id)
            ->where('table_name', 'leads')
            ->get();
        $this->assertCount(2, $leadImpacts);
        $this->assertSame(['eloquent_row', 'sql_statement'],
            $leadImpacts->pluck('capture_type')->sort()->values()->all());
        $this->assertSame((string) $lead->id,
            (string) $leadImpacts->firstWhere('capture_type', 'eloquent_row')->record_id);
        $this->assertSame(1, (int) $leadImpacts->firstWhere('capture_type', 'sql_statement')->statement_count);
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
