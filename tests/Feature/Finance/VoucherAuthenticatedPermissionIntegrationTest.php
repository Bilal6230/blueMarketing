<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Tests real auth + Spatie permission checks on voucher write URLs. */
class VoucherAuthenticatedPermissionIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Only isolated SQLite :memory: is allowed.');
        }

        Schema::dropAllTables();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable();
            $t->unsignedInteger('status_id')->default(1); $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = new User(['name' => 'Test authorized user', 'email' => 'test@example.invalid', 'status_id' => 1]);
        $user->id = 1;
        $user->exists = true;
        $this->actingAs($user);
    }

    public function test_authenticated_user_without_permissions_cannot_mutate_vouchers(): void
    {
        foreach ([
            ['POST', 'commision.voucher.store', []],
            ['PUT', 'commision.voucher.update', ['id' => 41]],
            ['DELETE', 'commision.voucher.delete', ['id' => 41]],
            ['PUT', 'journal.voucher.update', ['id' => 41]],
            ['DELETE', 'journal.voucher.delete', ['id' => 41]],
        ] as [$verb, $route, $params]) {
            $this->call($verb, route($route, $params))->assertForbidden();
        }
    }

    public function test_granted_permission_reaches_validation_but_missing_project_stays_forbidden(): void
    {
        foreach (['create jv', 'edit jv', 'delete jv',
            'finance cv create', 'finance cv edit', 'finance cv delete',
            'finance jv edit', 'finance jv delete'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        auth()->user()->givePermissionTo('create jv', 'edit jv', 'delete jv',
            'finance cv create', 'finance cv edit', 'finance cv delete',
            'finance jv edit', 'finance jv delete');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Authorized writes must reach the controller's normal validation path;
        // an empty form is 422 (JSON), not a forced 409 safety error.
        foreach ([
            ['POST', 'commision.voucher.store', []],
            ['PUT', 'commision.voucher.update', ['id' => 41]],
            ['PUT', 'journal.voucher.update', ['id' => 41]],
        ] as [$verb, $route, $params]) {
            $this->call($verb, route($route, $params), [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ])->assertStatus(422)->assertJsonValidationErrors(['date', 'accounts']);
        }

        // Deletion without a selected project must remain blocked, even when
        // the user holds a granular delete grant.
        foreach (['commision.voucher.delete', 'journal.voucher.delete'] as $route) {
            $this->call('DELETE', route($route, ['id' => 41]))->assertStatus(403);
        }
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }
}
