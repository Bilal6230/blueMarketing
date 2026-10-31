<?php

namespace Tests\Feature;

use App\Models\{Lead, User, Role, Permission};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class AssignmentArchiveIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        DB::statement('PRAGMA foreign_keys = ON');
        foreach (['users', 'leads'] as $name) {
            Schema::create($name, function (Blueprint $t): void { $t->id(); $t->timestamps(); });
            DB::table($name)->insert([['id' => 1], ['id' => 2]]);
        }
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $t): void {
                $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
            });
        }
        Schema::create('lead_user', function (Blueprint $t): void {
            $t->id(); $t->foreignId('lead_id')->constrained('leads');
            $t->foreignId('user_id')->constrained('users'); $t->timestamps();
            $t->unique(['lead_id', 'user_id']);
        });
        foreach (['model_has_roles' => 'role', 'model_has_permissions' => 'permission'] as $name => $kind) {
            Schema::create($name, function (Blueprint $t) use ($kind): void {
                $t->foreignId($kind.'_id')->constrained($kind.'s');
                $t->unsignedBigInteger('model_id'); $t->string('model_type');
                $t->primary([$kind.'_id', 'model_id', 'model_type']);
            });
        }
        Schema::create('role_has_permissions', function (Blueprint $t): void {
            $t->foreignId('role_id')->constrained('roles');
            $t->foreignId('permission_id')->constrained('permissions');
            $t->primary(['role_id', 'permission_id']);
        });
        $migration = require base_path('database/migrations/2026_10_31_000020_add_assignment_archives.php');
        $migration->up();
        $migration->up();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_crm_sync_archives_and_reattaches_same_row_without_duplicates(): void
    {
        $lead = Lead::findOrFail(1);
        $lead->users()->attach(1, ['created_at' => '2020-01-01 00:00:00']);
        $id = DB::table('lead_user')->value('id');
        $lead->users()->sync([2]);
        $this->assertSoftDeleted('lead_user', ['id' => $id, 'user_id' => 1]);
        $this->assertSame([2], $lead->fresh()->users->modelKeys());
        $this->assertSame([2], User::whereHas('Leads')->pluck('id')->all());
        $lead->users()->sync([1]);
        $this->assertDatabaseHas('lead_user', ['id' => $id, 'user_id' => 1, 'deleted_at' => null, 'created_at' => '2020-01-01 00:00:00']);
        $this->assertDatabaseCount('lead_user', 2);
        $this->assertSame([1], Lead::with('users')->findOrFail(1)->users->modelKeys());
    }

    public function test_user_role_sync_revokes_old_role_and_preserves_polymorphic_identity(): void
    {
        $a = Role::create(['name' => 'old', 'guard_name' => 'web']);
        $b = Role::create(['name' => 'new', 'guard_name' => 'web']);
        $user = User::findOrFail(1);
        $user->assignRole($a);
        DB::table('model_has_roles')->insert(['role_id' => $a->id, 'model_id' => 1, 'model_type' => 'OtherModel']);
        $user->syncRoles($b);
        $this->assertFalse($user->fresh()->hasRole('old'));
        $this->assertTrue($user->fresh()->hasRole('new'));
        $this->assertSoftDeleted('model_has_roles', ['role_id' => $a->id, 'model_type' => User::class]);
        $this->assertDatabaseHas('model_has_roles', ['role_id' => $a->id, 'model_type' => 'OtherModel', 'deleted_at' => null]);
        $user->syncRoles($a);
        $this->assertTrue($user->fresh()->hasRole('old'));
        $this->assertFalse($user->fresh()->hasRole('new'));
        $this->assertDatabaseCount('model_has_roles', 3);
    }

    public function test_role_permission_sync_invalidates_cached_access_and_restores_composite_rows(): void
    {
        $a = Permission::create(['name' => 'old-permission', 'guard_name' => 'web']);
        $b = Permission::create(['name' => 'new-permission', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'operator', 'guard_name' => 'web']);
        $user = User::findOrFail(1);
        $user->assignRole($role);
        $role->givePermissionTo($a);
        $this->assertTrue($user->fresh()->checkPermissionTo('old-permission'));
        $role->syncPermissions($b);
        $this->assertFalse($user->fresh()->checkPermissionTo('old-permission'));
        $this->assertTrue($user->fresh()->checkPermissionTo('new-permission'));
        $this->assertSoftDeleted('role_has_permissions', ['permission_id' => $a->id]);
        $role->syncPermissions($a);
        $this->assertDatabaseCount('role_has_permissions', 2);
        $this->assertTrue($user->fresh()->checkPermissionTo('old-permission'));
        $this->assertFalse($user->fresh()->checkPermissionTo('new-permission'));
    }

    public function test_direct_permission_revoke_and_reattach_preserves_history(): void
    {
        $permission = Permission::create(['name' => 'direct-permission', 'guard_name' => 'web']);
        $user = User::findOrFail(1);
        $user->givePermissionTo($permission);
        $user->revokePermissionTo($permission);
        $this->assertFalse($user->fresh()->checkPermissionTo('direct-permission'));
        $this->assertSoftDeleted('model_has_permissions', ['permission_id' => $permission->id]);
        $user->givePermissionTo($permission);
        $this->assertTrue($user->fresh()->checkPermissionTo('direct-permission'));
        $this->assertDatabaseCount('model_has_permissions', 1);
    }

    public function test_failed_reassignment_rolls_back_archival_and_leaves_original_assignment_active(): void
    {
        $lead = Lead::findOrFail(1);
        $lead->users()->attach(1);
        try {
            $lead->users()->sync([999]);
            $this->fail('Missing target must fail FK check');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertDatabaseHas('lead_user', ['user_id' => 1, 'deleted_at' => null]);
            $this->assertDatabaseCount('lead_user', 1);
        }
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_missing_archive_column_never_falls_back_to_physical_detach(): void
    {
        Schema::table('lead_user', function (Blueprint $t): void { $t->dropColumn('deleted_at'); });
        $lead = Lead::findOrFail(1);
        $lead->users()->attach(1);
        try {
            $lead->users()->sync([2]);
            $this->fail('Archive migration is required for replacement');
        } catch (\DomainException $e) {
            $this->assertSame('ASSIGNMENT_ARCHIVE_MIGRATION_REQUIRED', $e->getMessage());
        }
        $this->assertDatabaseHas('lead_user', ['user_id' => 1]);
        $this->assertDatabaseCount('lead_user', 1);
    }

    public function test_mobile_assignment_approval_archives_previous_owner_and_creates_missing_assignment(): void
    {
        Schema::create('pending_updates', function (Blueprint $t): void {
            $t->id(); $t->string('table_name'); $t->unsignedBigInteger('record_id');
            $t->json('new_values'); $t->string('status');
            $t->unsignedBigInteger('submitted_by'); $t->unsignedBigInteger('approved_by')->nullable(); $t->timestamps();
        });
        $controller = new class extends \App\Http\Controllers\Api\V1\Mobile\MobileApprovalController {
            public function applyForTest(\App\Models\PendingUpdate $pending, User $user): void
            {
                DB::transaction(fn () => $this->applyPendingUpdate($pending, $user));
            }
        };
        Lead::findOrFail(1)->users()->attach(1);
        foreach ([1, 2] as $leadId) {
            $pending = \App\Models\PendingUpdate::create([
                'table_name' => 'leads', 'record_id' => $leadId, 'new_values' => [],
                'status' => 'pending', 'submitted_by' => 2,
            ]);
            $controller->applyForTest($pending, User::findOrFail(1));
            $this->assertSame([2], Lead::findOrFail($leadId)->users->modelKeys());
            $this->assertDatabaseHas('pending_updates', ['id' => $pending->id, 'status' => 'approved', 'approved_by' => 1]);
        }
        $this->assertSoftDeleted('lead_user', ['lead_id' => 1, 'user_id' => 1]);
        $this->assertDatabaseCount('lead_user', 3);
    }
}
