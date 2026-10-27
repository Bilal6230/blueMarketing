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
        Schema::create('commision_vouchers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('voucher_number');
            $t->decimal('total_debit', 15, 2); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('commision_voucher_details', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commision_voucher_id');
            $t->unsignedBigInteger('account_id'); $t->decimal('debit', 15, 2);
            $t->decimal('credit', 15, 2); $t->softDeletes(); $t->timestamps();
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
        $result = app(\App\Services\Finance\JournalVoucherIntegrityInspector::class)->inspect(7, 1);
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
        $result = app(\App\Services\Finance\JournalVoucherIntegrityInspector::class)->inspect(7, 1);
        $this->assertFalse($result['header_details_consistent']);
        $this->assertContains('header_detail_mismatch', $result['issues']);
        $this->assertEquals(99.99, DB::table('journal_vouchers')->where('id', 1)->value('total_debit'));
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_inspector_denies_other_project_voucher_lookup(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Services\Finance\JournalVoucherIntegrityInspector::class)->inspect(8, 1);
    }

    public function test_artisan_read_only_jv_audit_reports_consistent_project_voucher(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->artisan('finance:audit-jv', ['project' => 7, '--limit' => 10])
            ->expectsOutputToContain('Read-only audit: 1 vouchers, 0 flagged.')
            ->assertExitCode(0);
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_artisan_read_only_jv_audit_returns_failure_for_header_detail_mismatch(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        DB::table('journal_vouchers')->where('id', 1)->update(['total_credit' => '99.00']);
        $this->artisan('finance:audit-jv', ['project' => 7, '--limit' => 10])
            ->expectsOutputToContain('Read-only audit: 1 vouchers, 1 flagged.')
            ->assertExitCode(1);
        $this->assertEquals(99.00, DB::table('journal_vouchers')->where('id', 1)->value('total_credit'));
    }

    public function test_artisan_jv_audit_rejects_invalid_project_before_querying(): void
    {
        $this->artisan('finance:audit-jv', ['project' => '0'])
            ->expectsOutputToContain('Provide positive project ID')
            ->assertExitCode(2);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
    }

    public function test_cv_integrity_inspector_and_artisan_report_are_read_only(): void
    {
        DB::table('commision_vouchers')->insert([
            'id' => 10, 'project_id' => 7, 'voucher_number' => 'CV-10',
            'total_debit' => '85.20', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 10, 'account_id' => 11, 'debit' => '85.20', 'credit' => '0.00'],
            ['commision_voucher_id' => 10, 'account_id' => 22, 'debit' => '0.00', 'credit' => '85.20'],
        ]);
        $report = app(\App\Services\Finance\CommissionVoucherIntegrityInspector::class)->inspect(7, 10);
        $this->assertTrue($report['header_details_consistent']);
        $this->assertSame(8520, $report['detail_debit_cents']);
        $this->artisan('finance:audit-jv', ['project' => 7, '--type' => 'CV'])
            ->expectsOutputToContain('Read-only audit: 1 vouchers, 0 flagged.')
            ->assertExitCode(0);
        $this->assertSame(2, DB::table('commision_voucher_details')->count());
    }

    public function test_cv_audit_detects_mismatch_without_modifying_historical_rows(): void
    {
        DB::table('commision_vouchers')->insert([
            'id' => 10, 'project_id' => 7, 'voucher_number' => 'CV-10',
            'total_debit' => '90.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 10, 'account_id' => 11, 'debit' => '80.00', 'credit' => '0.00'],
            ['commision_voucher_id' => 10, 'account_id' => 22, 'debit' => '0.00', 'credit' => '80.00'],
        ]);
        $this->artisan('finance:audit-jv', ['project' => 7, '--type' => 'CV'])
            ->expectsOutputToContain('Read-only audit: 1 vouchers, 1 flagged.')
            ->assertExitCode(1);
        $this->assertEquals(90, DB::table('commision_vouchers')->value('total_debit'));
    }

    public function test_cv_audit_rejects_cross_project_lookup(): void
    {
        DB::table('commision_vouchers')->insert([
            'id' => 10, 'project_id' => 7, 'voucher_number' => 'CV-10',
            'total_debit' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Services\Finance\CommissionVoucherIntegrityInspector::class)->inspect(8, 10);
    }

    public function test_combined_jv_cv_audit_detects_mismatch_without_writing(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        DB::table('commision_vouchers')->insert([
            'id' => 40, 'project_id' => 7, 'voucher_number' => 'CV-40',
            'total_debit' => '99.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 40, 'account_id' => 11, 'debit' => '90.00', 'credit' => '0.00'],
            ['commision_voucher_id' => 40, 'account_id' => 22, 'debit' => '0.00', 'credit' => '90.00'],
        ]);

        $this->artisan('finance:audit-jv', ['project' => 7, '--type' => 'ALL', '--only-issues' => true])
            ->expectsOutputToContain('Read-only audit: 2 vouchers, 1 flagged.')
            ->assertExitCode(1);
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertEquals(99.00, DB::table('commision_vouchers')->value('total_debit'));
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_audit_limit_reports_incomplete_scan_instead_of_false_success(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        $this->artisan('finance:audit-jv', ['project' => 7, '--limit' => 1])
            ->expectsOutputToContain('INCOMPLETE SCAN: 1 additional vouchers not inspected')
            ->assertExitCode(1);
        $this->assertSame(2, DB::table('journal_vouchers')->count());
    }

    public function test_invalid_audit_type_is_rejected_before_financial_scan(): void
    {
        $this->artisan('finance:audit-jv', ['project' => 7, '--type' => 'OTHER'])
            ->expectsOutputToContain('Provide positive project ID')
            ->assertExitCode(2);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
    }

    public function test_collision_inventory_detects_overlapping_jv_cv_header_ids_without_claiming_ownership(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        DB::table('commision_vouchers')->insert([
            'id' => 1, 'project_id' => 7, 'voucher_number' => 'CV-1',
            'total_debit' => '100.25', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInspector::class)->inspect(7);
        $this->assertSame(1, $report['overlap_total']);
        $this->assertSame(1, $report['rows'][0]['overlapping_header_id']);
        $this->assertSame(2, $report['rows'][0]['candidate_jv_ledger_rows']);
        $this->assertFalse($report['rows'][0]['source_identified']);
        $this->artisan('finance:audit-voucher-collisions', ['project' => 7])
            ->expectsOutputToContain('Potential JV/CV header ID overlaps: 1; reported: 1.')
            ->assertExitCode(1);
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_collision_inventory_ignores_header_ids_from_other_projects(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        DB::table('commision_vouchers')->insert([
            'id' => 1, 'project_id' => 8, 'voucher_number' => 'CV-1',
            'total_debit' => '100.25', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInspector::class)->inspect(7);
        $this->assertSame(0, $report['overlap_total']);
        $this->artisan('finance:audit-voucher-collisions', ['project' => 7])->assertExitCode(0);
    }

    public function test_balanced_voucher_with_colliding_cv_id_is_flagged_by_combined_audit(): void
    {
        app(JournalVoucherController::class)->store($this->request($this->payload()));
        DB::table('commision_vouchers')->insert([
            'id' => 1, 'project_id' => 7, 'voucher_number' => 'CV-1',
            'total_debit' => '100.25', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 1, 'account_id' => 11, 'debit' => '100.25', 'credit' => '0.00'],
            ['commision_voucher_id' => 1, 'account_id' => 22, 'debit' => '0.00', 'credit' => '100.25'],
        ]);
        $this->artisan('finance:audit-jv', ['project' => 7, '--type' => 'ALL', '--only-issues' => true])
            ->expectsOutputToContain('Read-only audit: 2 vouchers, 2 flagged.')
            ->assertExitCode(1);
        $this->assertSame(2, DB::table('ledgers')->count());
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(1, DB::table('commision_vouchers')->count());
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
    private function installFinanceMetadata(): void
    {
        $migration = require base_path('database/migrations/2026_10_27_000001_create_financial_source_identity_tables.php');
        $migration->up();
    }

    public function test_typed_jv_posting_records_both_source_rows_atomically(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();
        $payload['idempotency_key']='jv_typed_post_123456789';
        app(JournalVoucherController::class)->store($this->request($payload));
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('ledger_source_attributions')->count());
        $this->assertSame(['journal_voucher','journal_voucher'],
            DB::table('ledger_source_attributions')->orderBy('id')->pluck('source_kind')->all());
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_real_jv_store_with_retry_key_posts_only_once(): void
    {
        $this->installFinanceMetadata();
        $payload = $this->payload();
        $payload['idempotency_key'] = 'jv_request_123456789012';
        app(JournalVoucherController::class)->store($this->request($payload));
        app(JournalVoucherController::class)->store($this->request($payload));
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('ledgers')->count());
        $this->assertSame(2, DB::table('ledger_source_attributions')->count());
        $this->assertSame(1, DB::table('financial_posting_requests')->count());
    }

    public function test_real_jv_store_rejects_conflicting_retry_without_changes(): void
    {
        $this->installFinanceMetadata();
        $payload = $this->payload();
        $payload['idempotency_key'] = 'jv_request_123456789012';
        app(JournalVoucherController::class)->store($this->request($payload));
        $payload['description'] = 'Changed description on same key';
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Changed retry was accepted.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('different financial payload', $e->getMessage());
        }
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('ledgers')->count());
    }

    public function test_real_jv_store_rejects_retry_key_when_schema_missing(): void
    {
        $payload = $this->payload();
        $payload['idempotency_key'] = 'jv_request_123456789012';
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Request key without metadata schema was accepted.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
    }

    public function test_partial_metadata_attribution_table_blocks_financial_posting(): void
    {
        \Illuminate\Support\Facades\Schema::create('ledger_source_attributions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        try {
            app(JournalVoucherController::class)->store($this->request($this->payload()));
            $this->fail('Posting must fail on incomplete finance metadata installation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Incomplete financial metadata schema', $e->getMessage());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_partial_metadata_request_table_blocks_financial_posting(): void
    {
        \Illuminate\Support\Facades\Schema::create('financial_posting_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        try {
            app(JournalVoucherController::class)->store($this->request($this->payload()));
            $this->fail('Posting must fail on incomplete finance metadata installation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Incomplete financial metadata schema', $e->getMessage());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_invalid_short_retry_key_is_rejected_without_writes(): void
    {
        $payload = $this->payload();
        $payload['idempotency_key'] = 'short';
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Expected retry key validation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_jv_form_source_emits_retry_key_only_when_finance_schema_available(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $template = file_get_contents(resource_path('views/admin/reports/vouchers/journal_voucher.blade.php'));
        $this->assertStringContainsString("financeRetryReady", $controller);
        $this->assertStringContainsString("Str::uuid()", $controller);
        $this->assertStringContainsString("@if (\$financeRetryReady ?? false)", $template);
        $this->assertStringContainsString('name="idempotency_key"', $template);
        $this->assertStringContainsString('value="{{ $financeRetryKey }}"', $template);
        $this->assertStringContainsString('let journalSubmissionStarted = false;', $template);
        $this->assertStringContainsString('if (journalSubmissionStarted)', $template);
        $this->assertStringContainsString("journalSubmissionStarted = true;", $template);
        $this->assertStringContainsString("$(this).prop('disabled', true);", $template);
    }

    public function test_real_jv_form_retry_deduplicates_even_after_failed_redirect_retry(): void
    {
        $this->installFinanceMetadata();
        $payload = $this->payload();
        // A normal created form reuses one server-issued UUID across browser retries.
        $payload['idempotency_key'] = (string) \Illuminate\Support\Str::uuid();
        app(JournalVoucherController::class)->store($this->request($payload));
        app(JournalVoucherController::class)->store($this->request($payload));
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('ledger_source_attributions')->count());
        $this->assertSame(1, DB::table('financial_posting_requests')->count());
    }


    public function test_typed_schema_refuses_missing_key_before_any_accounting_write(): void
    {
        $this->installFinanceMetadata();
        try {
            app(JournalVoucherController::class)->store($this->request($this->payload()));
            $this->fail('Typed financial posting without request key succeeded.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key',$e->errors());
        }
        $this->assertSame(0,DB::table('journal_vouchers')->count());
        $this->assertSame(0,DB::table('ledgers')->count());
        $this->assertSame(0,DB::table('financial_posting_requests')->count());
    }

    public function test_metadata_tables_without_request_unique_index_block_real_jv_store(): void
    {
        $this->installFinanceMetadata();
        Schema::drop('financial_posting_requests');
        Schema::create('financial_posting_requests',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('project_id');$t->string('source_kind');
            $t->string('request_key');$t->char('payload_hash',64);$t->string('status');
            $t->unsignedBigInteger('voucher_id')->nullable();$t->timestamps();
        });
        $payload=$this->payload();
        $payload['idempotency_key']='jv_typed_post_123456789';
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Broken uniqueness allowed posting.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('financial_request_unique_index_missing',$e->getMessage());
        }
        $this->assertSame(0,DB::table('journal_vouchers')->count());
        $this->assertSame(0,DB::table('ledgers')->count());
    }

    public function test_typed_ledger_failure_rolls_back_request_header_and_source_rows(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();
        $payload['idempotency_key']='jv_typed_post_123456789';
        Ledger::creating(function () {throw new \RuntimeException('Simulated typed ledger failure');});
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Expected ledger failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated typed ledger failure',$e->getMessage());
        } finally {
            Ledger::flushEventListeners();
        }
        $this->assertSame(0,DB::table('journal_vouchers')->count());
        $this->assertSame(0,DB::table('journal_voucher_details')->count());
        $this->assertSame(0,DB::table('ledgers')->count());
        $this->assertSame(0,DB::table('ledger_source_attributions')->count());
        $this->assertSame(0,DB::table('financial_posting_requests')->count());
    }

    public function test_conflicting_keyed_retry_preserves_existing_typed_source_rows(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();
        $payload['idempotency_key']='jv_typed_post_123456789';
        app(JournalVoucherController::class)->store($this->request($payload));
        $payload['debit'][0]='200.25';
        $payload['credit'][1]='200.25';
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Different balanced payload reused same request key.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('different financial payload',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
        $this->assertSame(2,DB::table('ledger_source_attributions')->count());
        $this->assertSame(1,DB::table('financial_posting_requests')->count());
    }

    public function test_typed_retry_rejects_deleted_source_attribution_without_reposting(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();
        $payload['idempotency_key']='jv_replay_check_123456789';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('ledger_source_attributions')->where('ledger_id',1)->delete();
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Broken attribution allowed successful financial replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('source attribution',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
        $this->assertSame(2,DB::table('ledgers')->count());
        $this->assertSame(1,DB::table('ledger_source_attributions')->count());
    }

    public function test_typed_retry_rejects_modified_ledger_amount_without_reposting(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();
        $payload['idempotency_key']='jv_replay_check_123456789';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('ledgers')->where('id',1)->update(['amount_in'=>'99.99']);
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Changed ledger amount allowed successful financial replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mismatched posting lines',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
        $this->assertSame(2,DB::table('ledgers')->count());
    }

    public function test_typed_retry_rejects_missing_detail_without_reposting(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();
        $payload['idempotency_key']='jv_replay_check_123456789';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('journal_voucher_details')->where('id',1)->delete();
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Missing detail allowed successful financial replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('missing posting details',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
        $this->assertSame(2,DB::table('ledgers')->count());
    }

    public function test_typed_retry_rejects_modified_voucher_header_totals(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();$payload['idempotency_key']='jv_replay_audit_12345678';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('journal_vouchers')->where('id',1)->update(['total_debit'=>'101.25']);
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Header amount mismatch should block replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mismatched voucher totals',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
    }

    public function test_typed_retry_rejects_non_balanced_detail_totals(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();$payload['idempotency_key']='jv_replay_audit_12345678';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('journal_voucher_details')->where('id',1)->update(['debit'=>'101.25']);
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Detail amount mismatch should block replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mismatched voucher totals',$e->getMessage());
        }
        $this->assertSame(2,DB::table('ledgers')->count());
    }

    public function test_typed_retry_rejects_foreign_project_ledger_collision(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();$payload['idempotency_key']='jv_replay_audit_12345678';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('ledgers')->where('id',1)->update(['project_head_subheads_id'=>33]);
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Foreign project ledger link should block replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cross-project ledger links',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
    }

    public function test_typed_retry_rejects_extra_foreign_project_ledger_link(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();$payload['idempotency_key']='jv_replay_foreign_123456';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('ledgers')->insert([
            'voucher_number'=>'FOREIGN','type'=>'JV','type_id'=>1,
            'project_head_subheads_id'=>33,'reference'=>'foreign',
            'amount_in'=>'1.00','amount_out'=>'0.00','detail'=>'foreign',
            'is_active'=>1,'status'=>'0','date'=>'2026-10-26',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Extra cross-project link must not replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cross-project ledger links',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
        $this->assertSame(3,DB::table('ledgers')->count());
    }

    public function test_typed_retry_distinguishes_missing_ledger_from_foreign_project_link(): void
    {
        $this->installFinanceMetadata();
        $payload=$this->payload();$payload['idempotency_key']='jv_replay_missing_123456';
        app(JournalVoucherController::class)->store($this->request($payload));
        DB::table('ledgers')->where('id',1)->delete();
        try {
            app(JournalVoucherController::class)->store($this->request($payload));
            $this->fail('Missing ledger must not replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ledger/detail count mismatch',$e->getMessage());
        }
        $this->assertSame(1,DB::table('journal_vouchers')->count());
        $this->assertSame(1,DB::table('ledgers')->count());
    }

    public function test_foreign_project_jv_edit_and_print_are_not_found(): void
    {
        DB::table('journal_vouchers')->insert([
            'id'=>41,'project_id'=>8,'voucher_number'=>'41','type'=>'JV',
            'date'=>'2026-10-26','description'=>'Other project','total_debit'=>'0',
            'total_credit'=>'0','created_at'=>now(),'updated_at'=>now(),
        ]);
        $request=$this->request($this->payload());
        foreach (['edit','print'] as $method) {
            try {
                app(JournalVoucherController::class)->$method(41);
                $this->fail('Other project JV was exposed by '.$method);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
                $this->assertNotNull($exception);
            }
        }
    }

    public function test_jv_edit_and_print_queries_are_project_scoped(): void
    {
        $source=file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $this->assertStringContainsString(
            "JournalVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail(\$id)",$source);
        $this->assertStringContainsString(
            "JournalVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->where('project_id', getSelectedTown())->findOrFail(\$id)",$source);
    }
}
