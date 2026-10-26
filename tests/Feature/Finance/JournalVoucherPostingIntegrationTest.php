<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\JournalVoucherController;
use App\Models\Ledger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalVoucherPostingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('SQLite :memory: only.');
        }

        Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $t) { $t->id(); $t->string('project')->nullable(); });
        Schema::create('project_head_subheads', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('head_accounting_id');
        });
        Schema::create('journal_vouchers', function (Blueprint $t) {
            $t->id(); $t->string('voucher_number'); $t->string('type'); $t->unsignedBigInteger('project_id');
            $t->string('reference')->nullable(); $t->date('date'); $t->text('description');
            $t->decimal('total_debit', 15, 2); $t->decimal('total_credit', 15, 2);
            $t->unsignedBigInteger('created_by')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('journal_voucher_details', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('journal_voucher_id'); $t->unsignedBigInteger('account_id');
            $t->decimal('debit', 15, 2); $t->decimal('credit', 15, 2); $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('ledgers', function (Blueprint $t) {
            $t->id(); $t->string('voucher_number'); $t->string('type'); $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('project_head_subheads_id'); $t->string('reference')->nullable();
            $t->decimal('amount_in', 15, 2); $t->decimal('amount_out', 15, 2); $t->text('detail')->nullable();
            $t->unsignedBigInteger('create_by')->nullable(); $t->boolean('is_active'); $t->string('status');
            $t->date('date'); $t->timestamps();
        });

        DB::table('projects')->insert([['id'=>7,'project'=>'Test project'],['id'=>8,'project'=>'Other project']]);
        DB::table('project_head_subheads')->insert([
            ['id'=>11,'project_id'=>7,'head_accounting_id'=>1],
            ['id'=>22,'project_id'=>7,'head_accounting_id'=>2],
            ['id'=>33,'project_id'=>8,'head_accounting_id'=>2],
        ]);
    }

    private function payload(): array
    {
        return [
            'voucher_number'=>'1', 'reference'=>'test', 'date'=>'2026-10-26',
            'description'=>'Balanced journal', 'accounts'=>[1,2], 'sub_accounts'=>[11,22],
            'debit'=>['100.25','0.00'], 'credit'=>['0.00','100.25'],
            'line_description'=>['Debit line','Credit line'],
        ];
    }

    private function request(array $payload): Request
    {
        $r = Request::create('/admin/journal-voucher/create','POST',$payload);
        $r->cookies->set('selected_action','7');
        $this->app->instance('request',$r);
        return $r;
    }

    public function test_balanced_jv_posts_header_two_details_and_two_ledgers(): void
    {
        $response = app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('journal_voucher_details')->count());
        $this->assertSame(2, DB::table('ledgers')->count());
        $this->assertEquals(100.25, DB::table('journal_vouchers')->value('total_debit'));
        $this->assertEquals(100.25, DB::table('journal_vouchers')->value('total_credit'));
        $this->assertEquals(100.25, DB::table('ledgers')->sum('amount_in'));
        $this->assertEquals(100.25, DB::table('ledgers')->sum('amount_out'));
    }

    public function test_cross_project_account_is_rejected_without_any_posting(): void
    {
        $p = $this->payload();
        $p['sub_accounts'][1] = 33;
        try {
            app(JournalVoucherController::class)->store($this->request($p));
            $this->fail('Cross-project posting was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sub_accounts', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_wrong_head_subaccount_pair_is_rejected_before_posting(): void
    {
        $p = $this->payload();
        $p['accounts'][1] = 1;
        try {
            app(JournalVoucherController::class)->store($this->request($p));
            $this->fail('Incorrect head/subaccount pairing was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('accounts', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_unbalanced_journal_is_rejected_before_posting(): void
    {
        $p = $this->payload();
        $p['credit'][1] = '100.24';
        try {
            app(JournalVoucherController::class)->store($this->request($p));
            $this->fail('Unbalanced journal was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('debit', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_ledger_failure_rolls_back_header_and_details(): void
    {
        Ledger::creating(function () { throw new \RuntimeException('Simulated ledger write failure'); });
        try {
            app(JournalVoucherController::class)->store($this->request($this->payload()));
            $this->fail('Expected simulated ledger write failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated ledger write failure', $e->getMessage());
        } finally {
            Ledger::flushEventListeners();
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('journal_voucher_details')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }
}
