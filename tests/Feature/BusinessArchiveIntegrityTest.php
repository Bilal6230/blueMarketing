<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessArchiveIntegrityTest extends TestCase
{
    public static function models(): array
    {
        return array_map(fn ($name) => ['App\\Models\\'.$name], [
            'User', 'Plot', 'Labour', 'Setting', 'DastiCash', 'DraftLedger',
            'BookingDetail', 'StockItem', 'StockParty', 'ProjectHeadSubhead', 'Role', 'Permission', 'HoldPlot',
        ]);
    }

    #[DataProvider('models')]
    public function test_delete_retains_row_hides_it_and_can_be_restored(string $class): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $model = new $class();
        $table = $model->getTable();
        Schema::dropAllTables();
        Schema::create($table, function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
        });
        DB::table($table)->insert(['id' => 1]);
        $migration = require base_path('database/migrations/2026_10_31_000010_add_business_record_archives.php');
        $migration->up();
        $migration->up();
        $record = $class::findOrFail(1);
        $record->delete();
        $this->assertSoftDeleted($table, ['id' => 1]);
        $this->assertSame(0, $class::count());
        $this->assertSame(1, $class::withTrashed()->count());
        $this->assertTrue($class::onlyTrashed()->firstOrFail()->restore());
        $this->assertSame(1, $class::count());
        try {
            $record->forceDelete();
            $this->fail('Permanent deletion must be refused');
        } catch (\DomainException $e) {
            $this->assertSame('HARD_DELETE_FORBIDDEN', $e->getMessage());
        }
        $this->assertDatabaseCount($table, 1);
        try {
            $class::whereKey(1)->forceDelete();
            $this->fail('Bulk permanent deletion must be refused');
        } catch (\DomainException $e) {
            $this->assertSame('HARD_DELETE_FORBIDDEN', $e->getMessage());
        }
        $this->assertDatabaseCount($table, 1);
    }

    public function test_unmigrated_schema_allows_reads_but_never_falls_back_to_hard_delete(): void
    {
        Schema::dropAllTables();
        Schema::create('stock_items', function (Blueprint $t): void { $t->id(); $t->timestamps(); });
        DB::table('stock_items')->insert(['id' => 1]);
        $record = \App\Models\StockItem::findOrFail(1);
        try {
            $record->delete();
            $this->fail('Archive schema must be installed first');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertDatabaseHas('stock_items', ['id' => 1]);
        }
    }

    public function test_bulk_schedule_delete_preserves_rows_and_active_totals(): void
    {
        Schema::dropAllTables();
        Schema::create('booking_details', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('booking_id'); $t->decimal('amount', 12, 2);
            $t->timestamps(); $t->softDeletes();
        });
        DB::table('booking_details')->insert(['booking_id' => 9, 'amount' => '100.00']);
        \App\Models\BookingDetail::where('booking_id', 9)->delete();
        DB::table('booking_details')->insert(['booking_id' => 9, 'amount' => '60.00']);
        $this->assertDatabaseCount('booking_details', 2);
        $this->assertSame(60.0, (float) \App\Models\BookingDetail::where('booking_id', 9)->sum('amount'));
        $this->assertSame(1, \App\Models\BookingDetail::onlyTrashed()->count());
    }

    public function test_archived_roles_and_permissions_stop_granting_access_but_keep_assignments(): void
    {
        Schema::dropAllTables();
        Schema::create('users', function (Blueprint $t): void { $t->id(); $t->timestamps(); $t->softDeletes(); });
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $t): void {
                $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); $t->softDeletes();
            });
        }
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $name => $key) {
            Schema::create($name, function (Blueprint $t) use ($key): void {
                $t->unsignedBigInteger($key); $t->string('model_type'); $t->unsignedBigInteger('model_id');
            });
        }
        Schema::create('role_has_permissions', function (Blueprint $t): void {
            $t->unsignedBigInteger('role_id'); $t->unsignedBigInteger('permission_id');
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('users')->insert(['id' => 1]);
        $user = \App\Models\User::findOrFail(1);
        $role = \App\Models\Role::create(['name' => 'archive-test', 'guard_name' => 'web']);
        $permission = \App\Models\Permission::create(['name' => 'archive-permission', 'guard_name' => 'web']);
        $user->assignRole($role);
        $user->givePermissionTo($permission);
        $this->assertTrue($user->fresh()->hasRole('archive-test'));
        $this->assertTrue($user->fresh()->checkPermissionTo('archive-permission'));
        $role->delete();
        $permission->delete();
        $this->assertFalse($user->fresh()->hasRole('archive-test'));
        $this->assertFalse($user->fresh()->checkPermissionTo('archive-permission'));
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('model_has_permissions', 1);
        $user->delete();
        $this->assertNull(\App\Models\User::find(1));
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_archived_actor_remains_resolvable_in_ledger_history_with_foreign_keys_enabled(): void
    {
        Schema::dropAllTables();
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('users', function (Blueprint $t): void {
            $t->id(); $t->string('name'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->foreignId('create_by')->constrained('users')->restrictOnDelete();
        });
        DB::table('users')->insert(['id' => 1, 'name' => 'Historical creator']);
        DB::table('ledgers')->insert(['id' => 9, 'create_by' => 1]);
        \App\Models\User::findOrFail(1)->delete();
        $this->assertNull(\App\Models\User::find(1));
        $this->assertSame('Historical creator', \App\Models\Ledger::findOrFail(9)->createdBy->name);
        $this->assertDatabaseHas('ledgers', ['id' => 9, 'create_by' => 1]);
    }
}
