<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinancialHistoryDeletionGuardTest extends TestCase
{
    public function test_posted_ledger_identity_and_amount_cannot_be_overwritten(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->string('type'); $t->integer('voucher_number');
            $t->integer('project_head_subheads_id'); $t->decimal('amount_in', 12, 2);
            $t->text('detail')->nullable(); $t->integer('update_by')->nullable(); $t->timestamps();
        });
        DB::table('ledgers')->insert(['id' => 1, 'type' => 'JV', 'voucher_number' => 19,
            'project_head_subheads_id' => 7, 'amount_in' => '125.50', 'detail' => 'original']);

        foreach ([['voucher_number' => 20], ['project_head_subheads_id' => 8], ['amount_in' => '0.00']] as $change) {
            try {
                \App\Models\Ledger::findOrFail(1)->update($change);
                $this->fail('Posted ledger identity cannot be overwritten');
            } catch (\DomainException $e) {
                $this->assertSame('FINANCIAL_HISTORY_UPDATE_REQUIRES_REVERSAL', $e->getMessage());
            }
        }

        \App\Models\Ledger::findOrFail(1)->update(['detail' => 'reviewed', 'update_by' => 3]);
        $this->assertDatabaseHas('ledgers', ['id' => 1, 'type' => 'JV', 'voucher_number' => 19,
            'project_head_subheads_id' => 7, 'amount_in' => '125.50', 'detail' => 'reviewed']);
    }

    public function test_linked_customer_source_value_cannot_be_overwritten(): void
    {
        Schema::dropAllTables();
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id(); $t->integer('project_id'); $t->integer('customer_id');
            $t->integer('type_id'); $t->string('reference'); $t->date('date');
            $t->string('transaction_type'); $t->decimal('amount_in', 12, 2);
            $t->decimal('amount_out', 12, 2); $t->integer('passing_status')->default(0);
            $t->timestamps();
        });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id');
        });
        DB::table('customer_ledger')->insert(['id' => 4, 'project_id' => 1, 'customer_id' => 2,
            'type_id' => 9, 'reference' => 'R-9', 'date' => '2026-10-31',
            'transaction_type' => 'CR', 'amount_in' => '50.00', 'amount_out' => '0.00']);
        DB::table('ledgers')->insert(['id' => 8, 'customer_ledger_id' => 4]);

        try {
            \App\Models\CustomerLedger::findOrFail(4)->update(['amount_in' => '60.00']);
            $this->fail('Linked source amount cannot be overwritten');
        } catch (\DomainException $e) {
            $this->assertSame('FINANCIAL_SOURCE_UPDATE_REQUIRES_REVERSAL', $e->getMessage());
        }
        \App\Models\CustomerLedger::findOrFail(4)->update(['passing_status' => 1]);
        $this->assertDatabaseHas('customer_ledger', ['id' => 4, 'amount_in' => '50.00', 'passing_status' => 1]);
    }

    public static function histories(): array
    {
        return [[\App\Models\Ledger::class], [\App\Models\CustomerLedger::class], [\App\Models\LabourLedger::class]];
    }

    #[DataProvider('histories')]
    public function test_model_bulk_and_force_deletes_cannot_remove_financial_history(string $class): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        $table = (new $class())->getTable();
        Schema::create($table, function (Blueprint $t): void { $t->id(); $t->decimal('amount', 12, 2); });
        DB::table($table)->insert(['id' => 1, 'amount' => '125.50']);
        foreach ([
            fn () => $class::findOrFail(1)->delete(),
            fn () => $class::whereKey(1)->delete(),
            fn () => $class::findOrFail(1)->forceDelete(),
            fn () => $class::whereKey(1)->forceDelete(),
            fn () => $class::destroy(1),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Financial history cannot be deleted');
            } catch (\DomainException $e) {
                $this->assertSame('FINANCIAL_HISTORY_DELETE_FORBIDDEN', $e->getMessage());
            }
            $this->assertDatabaseHas($table, ['id' => 1, 'amount' => '125.50']);
        }
    }
}
