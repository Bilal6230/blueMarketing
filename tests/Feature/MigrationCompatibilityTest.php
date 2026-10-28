<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MigrationCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Only isolated SQLite :memory: is allowed.');
        }

        Schema::dropAllTables();
    }

    public function test_permission_migration_uses_installed_registrar_pivot_names(): void
    {
        require_once base_path('database/migrations/2022_03_19_102456_create_permission_tables.php');

        (new \CreatePermissionTables())->up();

        $registrar = app(PermissionRegistrar::class);

        $this->assertSame('permission_id', $registrar->pivotPermission);
        $this->assertSame('role_id', $registrar->pivotRole);
        $this->assertTrue(Schema::hasColumn('model_has_permissions', $registrar->pivotPermission));
        $this->assertTrue(Schema::hasColumn('model_has_roles', $registrar->pivotRole));
        $this->assertTrue(Schema::hasColumn('role_has_permissions', $registrar->pivotPermission));
        $this->assertTrue(Schema::hasColumn('role_has_permissions', $registrar->pivotRole));

        $permissionPrimary = collect(Schema::getIndexes('model_has_permissions'))
            ->firstWhere('primary', true);
        $rolePrimary = collect(Schema::getIndexes('model_has_roles'))
            ->firstWhere('primary', true);

        $this->assertNotNull($permissionPrimary);
        $this->assertNotNull($rolePrimary);
        $this->assertSame(['permission_id', 'model_id', 'model_type'], $permissionPrimary['columns']);
        $this->assertSame(['role_id', 'model_id', 'model_type'], $rolePrimary['columns']);
        $this->assertContains('permissions', array_column(Schema::getForeignKeys('model_has_permissions'), 'foreign_table'));
        $this->assertContains('roles', array_column(Schema::getForeignKeys('model_has_roles'), 'foreign_table'));
    }

    public function test_site_migration_adds_only_nullable_site_column_to_fresh_shape(): void
    {
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('description')->nullable();
        });

        DB::table('journal_vouchers')->insert([
            'project_id' => 6,
            'description' => 'historical row must remain unchanged',
        ]);

        $migration = require base_path('database/migrations/2026_03_31_000001_add_site_id_to_journal_vouchers_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('journal_vouchers', 'site_id'));
        $this->assertNull(DB::table('journal_vouchers')->value('site_id'));
        $this->assertSame('historical row must remain unchanged', DB::table('journal_vouchers')->value('description'));
    }

    public function test_site_migration_refuses_preexisting_site_column_without_touching_rows(): void
    {
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('description')->nullable();
        });

        DB::table('journal_vouchers')->insert([
            'project_id' => 6,
            'site_id' => 91,
            'description' => 'preserve existing attribution',
        ]);

        $migration = require base_path('database/migrations/2026_03_31_000001_add_site_id_to_journal_vouchers_table.php');

        try {
            $migration->up();
            $this->fail('Expected preexisting site_id to block migration execution.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('reconcile schema and migration history manually', $exception->getMessage());
        }

        $this->assertSame(91, DB::table('journal_vouchers')->value('site_id'));
        $this->assertSame('preserve existing attribution', DB::table('journal_vouchers')->value('description'));
    }

    public function test_site_migration_refuses_missing_table(): void
    {
        $migration = require base_path('database/migrations/2026_03_31_000001_add_site_id_to_journal_vouchers_table.php');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('journal_vouchers does not exist');

        $migration->up();
    }

    public function test_site_migration_refuses_existing_generated_index_name_before_ddl(): void
    {
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->index('project_id', 'journal_vouchers_site_id_index');
        });

        $migration = require base_path('database/migrations/2026_03_31_000001_add_site_id_to_journal_vouchers_table.php');

        try {
            $migration->up();
            $this->fail('Expected existing generated index name to block migration execution.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('journal_vouchers_site_id_index already exists', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('journal_vouchers', 'site_id'));
        $this->assertTrue(Schema::hasColumn('journal_vouchers', 'project_id'));
    }

    public function test_site_migration_down_is_non_destructive(): void
    {
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable();
        });

        DB::table('journal_vouchers')->insert(['site_id' => 91]);
        $migration = require base_path('database/migrations/2026_03_31_000001_add_site_id_to_journal_vouchers_table.php');

        try {
            $migration->down();
            $this->fail('Expected automatic rollback to be disabled.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('column ownership cannot be proven', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('journal_vouchers', 'site_id'));
        $this->assertSame(91, DB::table('journal_vouchers')->value('site_id'));
    }

    public function test_commission_details_down_drops_only_its_own_table(): void
    {
        Schema::create('commision_vouchers', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id();
            $table->string('sentinel');
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
        });

        DB::table('journal_voucher_details')->insert(['sentinel' => 'preserve-me']);
        DB::table('ledgers')->insert(['reference' => 'unrelated-finance-row']);

        require_once base_path('database/migrations/2025_09_25_143458_create_commision_voucher_details_table.php');
        $migration = new \CreateCommisionVoucherDetailsTable();
        $migration->up();

        $this->assertTrue(Schema::hasTable('commision_voucher_details'));
        $migration->down();

        $this->assertFalse(Schema::hasTable('commision_voucher_details'));
        $this->assertTrue(Schema::hasTable('journal_voucher_details'));
        $this->assertSame('preserve-me', DB::table('journal_voucher_details')->value('sentinel'));
        $this->assertSame('unrelated-finance-row', DB::table('ledgers')->value('reference'));
    }
}
