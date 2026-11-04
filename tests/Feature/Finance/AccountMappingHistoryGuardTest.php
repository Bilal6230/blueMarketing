<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\AccountMappingHistoryGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountMappingHistoryGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing schema setup outside SQLite :memory:.');
        }

        Schema::dropAllTables();
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_head_subheads_id');
        });
        Schema::create('project_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_mapping_id');
        });
    }

    public function test_financial_history_blocks_reassignment_even_if_only_one_ledger_row_exists(): void
    {
        DB::table('ledgers')->insert(['project_head_subheads_id' => 41]);

        $this->expectException(\DomainException::class);
        app(AccountMappingHistoryGuard::class)->assertNoFinancialHistory(41);
    }

    public function test_bank_profile_reference_blocks_deletion_but_not_financial_history_check(): void
    {
        DB::table('project_bank_accounts')->insert(['account_mapping_id' => 42]);
        $guard = app(AccountMappingHistoryGuard::class);
        $guard->assertNoFinancialHistory(42);

        $this->expectException(\DomainException::class);
        $guard->assertUnused(42);
    }

    public function test_unreferenced_mapping_passes_when_optional_tables_are_absent(): void
    {
        app(AccountMappingHistoryGuard::class)->assertUnused(43);
        $this->assertTrue(true);
    }
}
