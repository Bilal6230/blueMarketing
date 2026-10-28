<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\StockLedgerPostingGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockLedgerPostingGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('SQLite :memory: only.');
        }

        Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
        });
        DB::table('projects')->insert(['id' => 5]);

        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
        });
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('reference');
            $table->string('transaction_type');
            $table->decimal('amount_in', 15, 2)->default(0);
            $table->decimal('amount_out', 15, 2)->default(0);
            $table->date('date');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->string('reference');
            $table->string('voucher_number');
            $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->unsignedBigInteger('project_head_subheads_id');
            $table->decimal('amount_in', 15, 2);
            $table->decimal('amount_out', 15, 2);
            $table->date('date');
            $table->boolean('is_active')->default(true);
        });

        DB::table('project_head_subheads')->insert([
            ['id' => 11, 'project_id' => 5],
            ['id' => 12, 'project_id' => 5],
            ['id' => 21, 'project_id' => 9],
        ]);
        DB::table('customer_ledger')->insert([
            'id' => 99, 'project_id' => 5, 'reference' => 'STOCK-ENTRY:123', 'transaction_type' => 'JV',
            'date' => '2026-10-20', 'is_active' => 1, 'amount_in' => '10.00', 'amount_out' => 0,
        ]);
    }

    private function legs(): array
    {
        return [
            ['project_head_subheads_id' => 11, 'amount_in' => '10.00', 'amount_out' => 0],
            ['project_head_subheads_id' => 12, 'amount_in' => 0, 'amount_out' => '10.00'],
        ];
    }

    private function insertLegs(): void
    {
        foreach ($this->legs() as $leg) {
            DB::table('ledgers')->insert(array_merge($leg, [
                'type' => 'JV',
                'type_id' => 77,
                'reference' => 'STOCK-ENTRY:123',
                'voucher_number' => '100',
                'customer_ledger_id' => 99,
                'date' => '2026-10-20',
                'is_active' => 1,
            ]));
        }
    }

    private function verify(): bool
    {
        return app(StockLedgerPostingGuard::class)->alreadyPosted(
            'STOCK-ENTRY:123', 5, '2026-10-20', $this->legs()
        );
    }

    public function test_empty_stock_posting_may_be_created(): void
    {
        $this->assertFalse($this->verify());
    }

    public function test_stock_posting_rejects_missing_project_before_financial_writes(): void
    {
        DB::table('projects')->where('id', 5)->delete();

        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_posting_rejects_foreign_expected_account(): void
    {
        $legs = $this->legs();
        $legs[1]['project_head_subheads_id'] = 21;

        $this->expectException(\DomainException::class);
        app(StockLedgerPostingGuard::class)->alreadyPosted('STOCK-ENTRY:123', 5, '2026-10-20', $legs);
    }

    public function test_fresh_stock_posting_rejects_missing_expected_account(): void
    {
        $legs = $this->legs();
        $legs[1]['project_head_subheads_id'] = 99999;

        $this->expectException(\DomainException::class);
        app(StockLedgerPostingGuard::class)->alreadyPosted('STOCK-ENTRY:123', 5, '2026-10-20', $legs);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidExpectedStockLegs')]
    public function test_invalid_expected_legs_are_rejected_before_posting(array $legs): void
    {
        $this->expectException(\DomainException::class);
        app(StockLedgerPostingGuard::class)->alreadyPosted('STOCK-ENTRY:123', 5, '2026-10-20', $legs);
    }

    public static function invalidExpectedStockLegs(): array
    {
        return [
            'both debits' => [[
                ['project_head_subheads_id' => 11, 'amount_in' => '10.00', 'amount_out' => 0],
                ['project_head_subheads_id' => 12, 'amount_in' => '10.00', 'amount_out' => 0],
            ]],
            'unbalanced' => [[
                ['project_head_subheads_id' => 11, 'amount_in' => '10.00', 'amount_out' => 0],
                ['project_head_subheads_id' => 12, 'amount_in' => 0, 'amount_out' => '9.99'],
            ]],
            'negative amount' => [[
                ['project_head_subheads_id' => 11, 'amount_in' => '-10.00', 'amount_out' => 0],
                ['project_head_subheads_id' => 12, 'amount_in' => 0, 'amount_out' => '-10.00'],
            ]],
            'both sides on one leg' => [[
                ['project_head_subheads_id' => 11, 'amount_in' => '10.00', 'amount_out' => '10.00'],
                ['project_head_subheads_id' => 12, 'amount_in' => 0, 'amount_out' => 0],
            ]],
            'same account' => [[
                ['project_head_subheads_id' => 11, 'amount_in' => '10.00', 'amount_out' => 0],
                ['project_head_subheads_id' => 11, 'amount_in' => 0, 'amount_out' => '10.00'],
            ]],
            'zero money' => [[
                ['project_head_subheads_id' => 11, 'amount_in' => 0, 'amount_out' => 0],
                ['project_head_subheads_id' => 12, 'amount_in' => 0, 'amount_out' => 0],
            ]],
        ];
    }

    public function test_posted_stock_replay_rejects_additional_hidden_source(): void
    {
        $this->insertLegs();
        DB::table('customer_ledger')->insert([
            'id' => 100, 'project_id' => 5, 'reference' => 'STOCK-ENTRY:123',
            'transaction_type' => 'JV', 'date' => '2026-10-20', 'is_active' => 1, 'amount_in' => '10.00', 'amount_out' => 0,
        ]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_posted_stock_replay_rejects_missing_linked_source(): void
    {
        $this->insertLegs();
        DB::table('customer_ledger')->where('id', 99)->delete();
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_rejects_stranded_source_amount_mismatch(): void
    {
        DB::table('customer_ledger')->where('id', 99)->update(['amount_in' => '11.00']);

        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_posted_stock_rejects_source_amount_mismatch(): void
    {
        $this->insertLegs();
        DB::table('customer_ledger')->where('id', 99)->update(['amount_in' => '9.00']);

        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_stock_source_out_side_amount_matches_for_stock_in(): void
    {
        DB::table('customer_ledger')->where('id', 99)->update(['amount_in' => 0, 'amount_out' => '10.00']);

        $this->assertFalse($this->verify());
        $this->insertLegs();
        $this->assertTrue($this->verify());
    }

    public function test_exact_two_legs_are_recognized(): void
    {
        $this->insertLegs();
        $this->assertTrue($this->verify());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('conflicts')]
    public function test_conflicting_stock_posting_is_rejected(string $column, mixed $value): void
    {
        $this->insertLegs();
        DB::table('ledgers')->where('project_head_subheads_id', 12)->update([$column => $value]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public static function conflicts(): array
    {
        return [
            'wrong amount' => ['amount_out', '11.00'],
            'wrong date' => ['date', '2026-10-21'],
            'wrong voucher number' => ['voucher_number', '101'],
            'wrong type id' => ['type_id', 79],
            'inactive leg' => ['is_active', 0],
            'foreign project' => ['project_head_subheads_id', 21],
        ];
    }

    public function test_one_leg_cannot_be_silently_completed(): void
    {
        $this->insertLegs();
        DB::table('ledgers')->where('project_head_subheads_id', 12)->delete();
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_duplicate_legs_are_rejected(): void
    {
        $this->insertLegs();
        DB::table('ledgers')->insert([
            'type' => 'JV', 'type_id' => 77,
            'reference' => 'STOCK-ENTRY:123', 'voucher_number' => '100',
            'customer_ledger_id' => 99, 'project_head_subheads_id' => 11,
            'amount_in' => '10.00', 'amount_out' => '0.00',
            'date' => '2026-10-20', 'is_active' => 1, 'amount_in' => '10.00', 'amount_out' => 0,
        ]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_customer_ledger_from_other_project_is_rejected(): void
    {
        $this->insertLegs();
        DB::table('customer_ledger')->where('id', 99)->update(['project_id' => 9]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }
    public function test_customer_ledger_date_mismatch_is_rejected(): void
    {
        $this->insertLegs();
        DB::table('customer_ledger')->where('id', 99)->update(['date' => '2026-10-19']);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_inactive_customer_ledger_is_rejected(): void
    {
        $this->insertLegs();
        DB::table('customer_ledger')->where('id', 99)->update(['is_active' => 0]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_posting_rejects_stranded_foreign_project_source(): void
    {
        DB::table('customer_ledger')->where('id', 99)->update(['project_id' => 9]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_posting_rejects_stranded_wrong_date_source(): void
    {
        DB::table('customer_ledger')->where('id', 99)->update(['date' => '2026-10-19']);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_posting_rejects_stranded_inactive_source(): void
    {
        DB::table('customer_ledger')->where('id', 99)->update(['is_active' => 0]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_posting_rejects_duplicate_customer_sources(): void
    {
        DB::table('customer_ledger')->insert([
            'id' => 100,
            'project_id' => 5,
            'reference' => 'STOCK-ENTRY:123',
            'transaction_type' => 'JV',
            'date' => '2026-10-20',
            'is_active' => 1,
        ]);
        $this->expectException(\DomainException::class);
        $this->verify();
    }

    public function test_fresh_stock_posting_with_matching_existing_customer_source_can_proceed(): void
    {
        $this->assertFalse($this->verify());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

}
