<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectVoucherSettingsMigrationTest extends TestCase
{
    public function test_settings_tables_install_without_mutating_existing_vouchers(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $table) => $table->id());
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->unsignedBigInteger('voucher_number');
        });
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedTinyInteger('payment_type')->default(1);
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id');
            $table->unique(['id', 'project_id']);
        });
        DB::statement('PRAGMA foreign_keys = ON');
        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1], ['id' => 201, 'project_id' => 2],
        ]);
        DB::table('ledgers')->insert(['id' => 1, 'type' => 'JV', 'voucher_number' => 170]);

        foreach (['000002_create_project_voucher_display_settings',
                  '000003_create_project_custom_payment_methods',
                  '000004_create_project_voucher_fiscal_sequences',
                  '000005_link_custom_methods_to_customer_ledger',
                  '000006_create_project_voucher_display_numbers'] as $name) {
            (require database_path('migrations/2026_11_04_'.$name.'.php'))->up();
        }
        (require database_path('migrations/2026_11_03_000021_create_project_bank_account_settings.php'))->up();

        foreach (['project_voucher_display_settings', 'project_voucher_display_setting_audits',
                  'project_custom_payment_methods', 'project_custom_payment_method_audits',
                  'project_voucher_fiscal_sequences', 'project_voucher_display_numbers',
                  'project_bank_accounts', 'project_bank_account_audits'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasColumn('customer_ledger', 'custom_payment_method_id'));
        $this->assertDatabaseHas('ledgers', ['id' => 1, 'type' => 'JV', 'voucher_number' => 170]);

        $methodId = DB::table('project_custom_payment_methods')->insertGetId([
            'project_id' => 1, 'code' => 'BANK-X', 'name' => 'Bank Transfer',
            'base_method_code' => 2, 'is_active' => 1,
        ]);
        DB::table('customer_ledger')->insert([
            'project_id' => 1, 'payment_type' => 2, 'custom_payment_method_id' => $methodId,
        ]);
        try {
            DB::table('customer_ledger')->insert([
                'project_id' => 1, 'payment_type' => 1, 'custom_payment_method_id' => $methodId,
            ]);
            $this->fail('A custom method must reject a mismatched base payment behavior.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('customer_ledger', 1);
        }

        DB::table('project_bank_accounts')->insert([
            'project_id' => 1, 'account_mapping_id' => 101, 'bank_name' => 'Test Bank',
            'account_title' => 'Project 1 Account', 'is_active' => 1,
        ]);
        try {
            DB::table('project_bank_accounts')->insert([
                'project_id' => 1, 'account_mapping_id' => 201, 'bank_name' => 'Foreign Bank',
                'account_title' => 'Foreign Account', 'is_active' => 1,
            ]);
            $this->fail('A project bank profile must reject another project’s account mapping.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('project_bank_accounts', 1);
        }
    }
}
