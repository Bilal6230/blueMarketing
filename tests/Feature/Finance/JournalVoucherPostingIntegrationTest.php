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

    public function test_two_sequential_vouchers_receive_distinct_project_jv_numbers(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        app(JournalVoucherController::class)->store($this->request($this->payload()));

        $this->assertSame(2, DB::table('journal_vouchers')->count());
        $numbers = DB::table('journal_vouchers')->orderBy('id')->pluck('voucher_number')->toArray();
        $this->assertEquals(['1', '2'], array_map('strval', $numbers));
        $this->assertSame(4, DB::table('journal_voucher_details')->count());
        $this->assertSame(4, DB::table('ledgers')->count());
    }

    public function test_both_debit_and_credit_on_same_line_are_rejected_without_writes(): void
    {
        $p = $this->payload();
        $p['credit'][0] = '1.00';
        try {
            app(JournalVoucherController::class)->store($this->request($p));
            $this->fail('Mixed debit/credit posting was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('debit', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_both_zero_on_same_line_are_rejected_without_writes(): void
    {
        $p = $this->payload();
        $p['debit'][0] = '0.00';
        try {
            app(JournalVoucherController::class)->store($this->request($p));
            $this->fail('Empty financial posting line was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('debit', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_large_two_decimal_amount_is_preserved_exactly_in_all_postings(): void
    {
        $p = $this->payload();
        $p['debit'][0] = '123456789.01';
        $p['credit'][1] = '123456789.01';

        app(JournalVoucherController::class)->store($this->request($p));

        $this->assertEquals('123456789.01', DB::table('journal_vouchers')->value('total_debit'));
        $this->assertEquals('123456789.01', DB::table('journal_vouchers')->value('total_credit'));
        $this->assertEquals('123456789.01', DB::table('journal_voucher_details')->where('account_id', 11)->value('debit'));
        $this->assertEquals('123456789.01', DB::table('journal_voucher_details')->where('account_id', 22)->value('credit'));
        $this->assertEquals('123456789.01', DB::table('ledgers')->where('project_head_subheads_id', 11)->value('amount_in'));
        $this->assertEquals('123456789.01', DB::table('ledgers')->where('project_head_subheads_id', 22)->value('amount_out'));
    }

    public function test_second_ledger_failure_rolls_back_all_header_detail_and_ledger_writes(): void
    {
        $writes = 0;
        Ledger::creating(function () use (&$writes) {
            $writes++;
            if ($writes === 2) {
                throw new \RuntimeException('Simulated second ledger failure');
            }
        });
        try {
            app(JournalVoucherController::class)->store($this->request($this->payload()));
            $this->fail('Expected second ledger failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated second ledger failure', $e->getMessage());
        } finally {
            Ledger::flushEventListeners();
        }
        $this->assertSame(2, $writes);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('journal_voucher_details')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_existing_soft_deleted_jv_serial_is_not_reused(): void
    {
        DB::table('journal_vouchers')->insert([
            'voucher_number' => '40', 'type' => 'JV', 'project_id' => 7,
            'date' => '2026-10-01', 'description' => 'Historical removed voucher',
            'total_debit' => 10, 'total_credit' => 10, 'deleted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->assertEquals(
            ['40', '41'],
            DB::table('journal_vouchers')->orderBy('id')->pluck('voucher_number')
                ->map(fn ($number) => (string) $number)->toArray()
        );
        $this->assertSame(2, DB::table('journal_vouchers')->count());
    }

    public function test_jv_header_serial_stays_scoped_to_selected_project(): void
    {
        DB::table('journal_vouchers')->insert([
            'voucher_number' => '800', 'type' => 'JV', 'project_id' => 8,
            'date' => '2026-10-01', 'description' => 'Other project voucher',
            'total_debit' => 10, 'total_credit' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->assertEquals('1', (string) DB::table('journal_vouchers')
            ->where('project_id', 7)->value('voucher_number'));
        $this->assertEquals('800', (string) DB::table('journal_vouchers')
            ->where('project_id', 8)->value('voucher_number'));
    }

    public function test_missing_selected_project_rejects_before_financial_writes(): void
    {
        $request = Request::create('/admin/journal-voucher/create', 'POST', $this->payload());
        $this->app->instance('request', $request);
        try {
            app(JournalVoucherController::class)->store($request);
            $this->fail('Unselected project was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('project', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_unknown_subaccount_rejects_before_financial_writes(): void
    {
        $p = $this->payload();
        $p['sub_accounts'][1] = 999999;
        try {
            app(JournalVoucherController::class)->store($this->request($p));
            $this->fail('Unknown subaccount was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sub_accounts', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_three_line_balanced_journal_preserves_totals_and_row_count(): void
    {
        $p = $this->payload();
        $p['accounts'] = [1, 1, 2];
        $p['sub_accounts'] = [11, 11, 22];
        $p['debit'] = ['40.10', '60.15', '0.00'];
        $p['credit'] = ['0.00', '0.00', '100.25'];
        $p['line_description'] = ['first', 'second', 'third'];
        app(JournalVoucherController::class)->store($this->request($p));
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(3, DB::table('journal_voucher_details')->count());
        $this->assertSame(3, DB::table('ledgers')->count());
        $this->assertEquals(100.25, DB::table('ledgers')->sum('amount_in'));
        $this->assertEquals(100.25, DB::table('ledgers')->sum('amount_out'));
    }

    public function test_failed_posting_does_not_consume_next_header_serial(): void
    {
        $attempts = 0;
        Ledger::creating(function () use (&$attempts) {
            if (++$attempts === 2) {
                throw new \RuntimeException('Injected rollback');
            }
        });
        try {
            app(JournalVoucherController::class)->store($this->request($this->payload()));
            $this->fail('Expected injected failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected rollback', $e->getMessage());
        } finally {
            Ledger::flushEventListeners();
        }
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->assertEquals('1', (string) DB::table('journal_vouchers')->value('voucher_number'));
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_read_only_inspector_reports_consistent_header_and_details(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $result = app(\App\\Services\\Finance\\JournalVoucherIntegrityInspector::class)->inspect(7, 1);
        $this->assertTrue($result['header_details_consistent']);
        $this->assertSame([], $result['issues']);
        $this->assertSame(2, $result['active_detail_count']);
        $this->assertSame(10025, $result['detail_debit_cents']);
        $this->assertSame(10025, $result['detail_credit_cents']);
        $this->assertSame('unverified_legacy_type_id_collision_possible', $result['ledger_provenance']);
    }

    public function test_inspector_detects_header_detail_mismatch_without_mutation(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        DB::table('journal_vouchers')->where('id', 1)->update(['total_debit' => '99.99']);
        $result = app(\App\\Services\\Finance\\JournalVoucherIntegrityInspector::class)->inspect(7, 1);
        $this->assertFalse($result['header_details_consistent']);
        $this->assertContains('header_detail_mismatch', $result['issues']);
        $this->assertEquals(99.99, DB::table('journal_vouchers')->where('id', 1)->value('total_debit'));
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_inspector_denies_other_project_voucher_lookup(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->expectException(\Illuminate\\Database\\Eloquent\\ModelNotFoundException::class);
        app(\App\\Services\\Finance\\JournalVoucherIntegrityInspector::class)->inspect(8, 1);
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
