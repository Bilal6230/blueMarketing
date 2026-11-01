<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\TrialBalanceReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class TrialBalanceReportTest extends TestCase
{
    public function test_it_builds_opening_period_and_closing_hierarchy(): void
    {
        Schema::dropAllTables();
        Schema::create('account_types', function (Blueprint $t): void { $t->id(); $t->string('name'); });
        Schema::create('head_accountings', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->integer('acct_type'); });
        Schema::create('subhead_accountings', function (Blueprint $t): void { $t->id(); $t->string('name'); });
        Schema::create('leads', function (Blueprint $t): void { $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('business')->nullable(); });
        Schema::create('plots', function (Blueprint $t): void { $t->id(); $t->string('name'); });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->integer('project_id'); $t->integer('head_accounting_id'); $t->integer('subhead_accounting_id');
            $t->integer('customer_id')->nullable(); $t->integer('plot_id')->nullable();
        });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->integer('project_head_subheads_id'); $t->date('date');
            $t->decimal('amount_in', 15, 2); $t->decimal('amount_out', 15, 2); $t->integer('is_active');
            $t->string('type')->default('JV'); $t->integer('voucher_number')->default(1); $t->text('detail')->nullable();
        });
        DB::table('account_types')->insert(['id' => 1, 'name' => 'Assets']);
        DB::table('head_accountings')->insert(['id' => 2, 'name' => 'Current Assets', 'acct_type' => 1]);
        DB::table('subhead_accountings')->insert(['id' => 3, 'name' => 'Receivables']);
        DB::table('leads')->insert(['id' => 4, 'first_name' => 'Ali', 'last_name' => 'Store']);
        DB::table('project_head_subheads')->insert(['id' => 5, 'project_id' => 9, 'head_accounting_id' => 2, 'subhead_accounting_id' => 3, 'customer_id' => 4]);
        DB::table('ledgers')->insert([
            ['project_head_subheads_id' => 5, 'date' => '2026-08-31', 'amount_in' => 100, 'amount_out' => 20, 'is_active' => 1],
            ['project_head_subheads_id' => 5, 'date' => '2026-09-10', 'amount_in' => 50, 'amount_out' => 10, 'is_active' => 1],
            ['project_head_subheads_id' => 5, 'date' => '2026-10-01', 'amount_in' => 999, 'amount_out' => 0, 'is_active' => 1],
        ]);

        $report = (new TrialBalanceReport())->build(9, '2026-09-01', '2026-09-30');
        $account = $report['tree'][1]['heads'][2]['subheads'][3]['accounts'][0];
        $this->assertSame('Ali Store', $account['name']);
        $this->assertSame(8000, $account['amounts']['previous_debit']);
        $this->assertSame(0, $account['amounts']['previous_credit']);
        $this->assertSame(5000, $account['amounts']['period_debit']);
        $this->assertSame(1000, $account['amounts']['period_credit']);
        $this->assertSame(12000, $account['amounts']['closing_debit']);
        $this->assertSame(0, $account['amounts']['closing_credit']);
        $hierarchy = (new TrialBalanceReport())->hierarchy((new TrialBalanceReport())->flatten($report['tree']));
        $this->assertSame('Current Assets', $hierarchy['heads'][2]['name']);
        $this->assertSame('Receivables', $hierarchy['heads'][2]['subheads'][3]['name']);
        $entries = $hierarchy['heads'][2]['subheads'][3]['accounts'][0]['entries'];
        $this->assertCount(2, $entries);
        $this->assertSame('Opening Balance', $entries[0]['detail']);
        $this->assertSame('2026-09-10', $entries[1]['date']);
        $this->assertSame(12000, $entries[1]['amounts']['closing_debit']);
    }
}
