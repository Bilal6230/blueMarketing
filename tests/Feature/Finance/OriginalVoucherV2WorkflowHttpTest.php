<?php

namespace Tests\Feature\Finance;

use App\Models\{User, Permission};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Gate};
use Tests\TestCase;

class OriginalVoucherV2WorkflowHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('users', function (Blueprint $t): void {
            $t->id(); $t->integer('project_id'); $t->integer('status_id'); $t->timestamps();
        });
        Schema::create('projects', function (Blueprint $t): void { $t->id(); $t->integer('is_active'); });
        foreach (['stock_entries', 'stock_bills', 'booking_vouchers', 'labour_ledgers'] as $name) {
            Schema::create($name, fn (Blueprint $t) => $t->id());
        }
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        }
        foreach (['model_has_permissions' => 'permission_id', 'model_has_roles' => 'role_id'] as $name => $key) {
            Schema::create($name, function (Blueprint $t) use ($key): void { $t->unsignedBigInteger($key); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        }
        Schema::create('role_has_permissions', function (Blueprint $t): void { $t->unsignedBigInteger('role_id'); $t->unsignedBigInteger('permission_id'); });
        Schema::create('project_head_subheads', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->integer('head_accounting_id'); });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->string('type'); $t->integer('voucher_number'); $t->integer('project_head_subheads_id'); $t->integer('customer_ledger_id')->nullable();
            $t->integer('type_id')->nullable();
        });
        Schema::create('journal_vouchers', function (Blueprint $t): void {
            $t->id(); $t->integer('project_id'); $t->string('type'); $t->integer('voucher_number');
            $t->date('date'); $t->string('reference')->nullable(); $t->text('description')->nullable();
            $t->decimal('total_debit', 15, 2); $t->decimal('total_credit', 15, 2);
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        foreach (['journal_voucher_details' => 'journal_voucher_id', 'commision_voucher_details' => 'commision_voucher_id'] as $name => $fk) {
            Schema::create($name, function (Blueprint $t) use ($fk): void {
                $t->id(); $t->unsignedBigInteger($fk); $t->unsignedBigInteger('account_id');
                $t->decimal('debit', 15, 2); $t->decimal('credit', 15, 2); $t->text('description')->nullable();
                $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps(); $t->softDeletes();
            });
        }
        Schema::create('commision_vouchers', function (Blueprint $t): void {
            $t->id(); $t->integer('project_id'); $t->string('voucher_number'); $t->date('date'); $t->string('status');
            $t->string('reference')->nullable(); $t->text('description')->nullable(); $t->integer('created_by')->nullable();
            $t->decimal('total_debit', 15, 2); $t->decimal('total_credit', 15, 2); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id(); $t->integer('project_id'); $t->string('transaction_type'); $t->integer('type_id'); $t->date('date');
            $t->decimal('amount_in', 15, 2); $t->decimal('amount_out', 15, 2); $t->integer('is_approve'); $t->integer('is_active'); $t->timestamps();
        });
        (require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php'))->up();
        (require base_path('database/v2_migrations/2026_10_31_000110_create_v2_commission_detail_links.php'))->up();
        DB::table('projects')->insert([['id' => 1, 'is_active' => 1], ['id' => 2, 'is_active' => 1]]);
        DB::table('users')->insert(['id' => 1, 'project_id' => 1, 'status_id' => 1]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1, 'head_accounting_id' => 10],
            ['id' => 102, 'project_id' => 1, 'head_accounting_id' => 20],
            ['id' => 201, 'project_id' => 2, 'head_accounting_id' => 10],
        ]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::findOrFail(1);
        foreach (['create jv', 'can approve'] as $name) {
            $user->givePermissionTo(Permission::create(['name' => $name, 'guard_name' => 'web']));
        }
        $this->actingAs($user);
        config(['finance.v2_pilot_api_enabled' => true, 'finance.v2_pilot_write_enabled' => true]);
        Gate::define('v2-finance-post', fn ($user) => true);
    }

    private function journal(): array
    {
        return ['idempotency_key' => 'ORIGINAL-JV-CREATE-0001', 'voucher_number' => 'JV-1',
            'date' => '2026-10-31', 'description' => 'Original browser JV', 'reference' => 'REF',
            'accounts' => [10, 20], 'sub_accounts' => [101, 102], 'debit' => ['30.00', '0'], 'credit' => ['0', '30.00']];
    }

    private function workflowSchema(): void
    {
        Schema::create('labour_attendances', fn (Blueprint $t) => $t->id());
        Schema::create('bookings', fn (Blueprint $t) => $t->id());
        Schema::create('charge_types', fn (Blueprint $t) => $t->id());
        Schema::table('project_head_subheads', function (Blueprint $t): void { $t->integer('subhead_accounting_id')->nullable(); });
        DB::table('project_head_subheads')->where('id', 101)->update(['subhead_accounting_id' => 100]);
        Schema::table('customer_ledger', function (Blueprint $t): void {
            $t->string('reference')->nullable(); $t->text('description')->nullable();
            $t->integer('customer_id')->nullable(); $t->integer('plot_id')->nullable();
            $t->integer('payment_type')->default(1); $t->string('t_number')->nullable(); $t->integer('bank_id')->nullable();
            $t->date('passing_date')->nullable(); $t->integer('passing_status')->nullable();
            $t->dateTime('bank_post_at')->nullable(); $t->text('note')->nullable();
        });
        (require base_path('database/workflow_migrations/2026_10_31_000010_create_original_workflow_custody.php'))->up();
        $user = User::findOrFail(1);
        foreach (['create voucher', 'pass cheque'] as $name) {
            $user->givePermissionTo(Permission::create(['name' => $name, 'guard_name' => 'web']));
        }
        $this->actingAs($user);
    }

    private function cashInput(int $paymentType = 1): array
    {
        $input = ['request_key' => 'ORIGINAL-CASH-CREATION-001', 'voucher' => 'CR-1', 'voucher_number' => 'CR-1',
            'date' => '2026-10-31', 'amount' => '30.00', 'detail' => 'Original cash source', 'payment_type' => $paymentType,
            'accounts_id' => 10, 'subaccounts_id' => 100];
        if ($paymentType === 1) { $input['posting_lines'] = $this->approval()['posting_lines']; }
        else { $input += ['t_number' => 'CHEQUE-1', 'bank_id' => 1, 'passing_date' => '2026-11-01']; }
        return $input;
    }

    public function test_original_cash_creation_is_atomic_and_replays_the_same_typed_source(): void
    {
        $this->workflowSchema();
        $url = route('ledger.store', [], false); $input = $this->cashInput();
        $id = $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk()->json('data.source_id');
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk()->assertJsonPath('data.source_id', $id)->assertJsonPath('data.replayed', true);
        $input['amount'] = '31.00';
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertStatus(409);
        $this->assertDatabaseCount('customer_ledger', 1);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_pending_bank_source_posts_only_on_clearance_date_and_replays_without_duplicate(): void
    {
        $this->workflowSchema();
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput(2))
            ->assertOk()->assertJsonPath('data.pending_bank', true)->json('data.source_id');
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $payload = ['request_key' => 'ORIGINAL-BANK-CLEARANCE-001', 'passing_status' => 1, 'bank_post_at' => '2026-11-02',
            'note' => 'Bank statement verified', 'posting_lines' => $this->approval()['posting_lines']];
        $url = route('finance.voucher.pending_bank_payments.status', ['customerLedger' => $id], false);
        $this->assertSame('BANK_CLEARANCE_REQUIRED', (new \App\Services\Finance\V2SourceApprovalReadiness())->inspect(1, 'cash', $id)['reason_code']);
        foreach ([1, 2] as $_) { $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertOk(); }
        $this->assertTrue((new \App\Services\Finance\V2SourceApprovalReadiness())->inspect(1, 'cash', $id)['eligible']);
        $this->assertDatabaseHas('finance_posting_batches', ['effective_date' => '2026-11-02']);
        $this->assertDatabaseHas('customer_ledger', ['id' => $id, 'date' => '2026-10-31', 'passing_status' => 1]);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $payload['bank_post_at'] = '2026-11-03';
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(409);
    }

    public function test_failed_clearance_keeps_source_pending_and_memo_never_posts_money(): void
    {
        $this->workflowSchema();
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput(2))->assertOk()->json('data.source_id');
        $url = route('finance.voucher.pending_bank_payments.status', ['customerLedger' => $id], false);
        $payload = ['request_key' => 'ORIGINAL-BANK-FAILURE-001', 'passing_status' => 1, 'bank_post_at' => '2026-11-02',
            'note' => 'Bank statement verified', 'posting_lines' => $this->approval()['posting_lines']];
        $payload['posting_lines'][1]['account_id'] = 201;
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(409);
        $this->assertDatabaseHas('customer_ledger', ['id' => $id, 'passing_status' => 0, 'is_approve' => 0]);
        $this->withCookie('selected_action', '1')->postJson($url, [
            'request_key' => 'ORIGINAL-BANK-REJECTION-01', 'passing_status' => 2, 'note' => 'Cheque returned',
        ])->assertOk();
        $this->assertDatabaseHas('customer_ledger', ['id' => $id, 'passing_status' => 2, 'is_active' => 1]);
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_bank_memo_can_later_pass_with_append_only_audit_and_old_retry_never_undoes_it(): void
    {
        $this->workflowSchema();
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput(2))->assertOk()->json('data.source_id');
        $url = route('finance.voucher.pending_bank_payments.status', ['customerLedger' => $id], false);
        $memo = ['request_key' => 'BANK-MEMO-EVENT-0000001', 'passing_status' => 2, 'note' => 'Memo received'];
        $this->withCookie('selected_action', '1')->postJson($url, $memo)->assertOk();
        $this->withCookie('selected_action', '1')->postJson($url, [
            'request_key' => 'BANK-MEMO-PASS-00000001', 'passing_status' => 1, 'note' => 'Re-presented and cleared',
            'bank_post_at' => '2026-11-02', 'posting_lines' => $this->approval()['posting_lines'],
        ])->assertOk();
        $this->withCookie('selected_action', '1')->postJson($url, $memo)->assertOk()->assertJsonPath('data.current_status', 1);
        $this->assertDatabaseHas('customer_ledger', ['id' => $id, 'passing_status' => 1]);
        $this->assertDatabaseHas('finance_bank_settlement_events', ['from_status' => 0, 'to_status' => 2, 'note' => 'Memo received']);
        $this->assertDatabaseHas('finance_bank_settlement_events', ['from_status' => 2, 'to_status' => 1, 'note' => 'Re-presented and cleared']);
        $this->assertDatabaseCount('finance_bank_settlement_events', 2);
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_original_cash_out_clearance_settles_existing_receipt_instead_of_creating_a_second_source(): void
    {
        $this->workflowSchema(); $url = route('ledger.store', [], false);
        $id = $this->withCookie('selected_action', '1')->postJson($url, $this->cashInput(2))->assertOk()->json('data.source_id');
        $input = $this->cashInput(2); $input['voucher'] = $input['voucher_number'] = 'CP-1';
        $input['request_key'] = 'CASH-OUT-RECEIPT-CLEARANCE-01'; $input['selected_pending_payment_id'] = $id;
        $input['pending_status'] = 1; $input['passing_date'] = '2026-11-02'; $input['posting_lines'] = $this->approval()['posting_lines'];
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk()->assertJsonPath('data.settled_existing_receipt', true);
        $this->assertDatabaseCount('customer_ledger', 1);
        $this->assertDatabaseHas('finance_posting_cash_sources', ['customer_ledger_id' => $id]);
        $this->assertDatabaseHas('finance_posting_batches', ['voucher_series' => 'CR', 'effective_date' => '2026-11-02']);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_pending_source_money_tampering_is_refused_before_clearance(): void
    {
        $this->workflowSchema();
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput(2))->assertOk()->json('data.source_id');
        DB::table('customer_ledger')->where('id', $id)->update(['amount_in' => '99.00']);
        $this->withCookie('selected_action', '1')->postJson(route('finance.voucher.pending_bank_payments.status', ['customerLedger' => $id], false), [
            'request_key' => 'TAMPERED-BANK-CLEARANCE-001', 'passing_status' => 1, 'bank_post_at' => '2026-11-02',
            'note' => 'Forged source value', 'posting_lines' => $this->approval()['posting_lines'],
        ])->assertStatus(409);
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $this->assertDatabaseHas('customer_ledger', ['id' => $id, 'passing_status' => 0]);
    }

    public function test_workflow_custody_migration_is_repeatable_and_unposted_source_cannot_be_physically_removed(): void
    {
        $this->workflowSchema();
        (require base_path('database/workflow_migrations/2026_10_31_000010_create_original_workflow_custody.php'))->up();
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput(2))->assertOk()->json('data.source_id');
        $this->assertDatabaseCount('finance_posting_batches', 0);
        try {
            DB::table('customer_ledger')->where('id', $id)->delete();
            $this->fail('Unposted source custody FK must prevent physical deletion');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertDatabaseHas('customer_ledger', ['id' => $id]);
            $this->assertDatabaseHas('finance_original_customer_requests', ['customer_ledger_id' => $id]);
        }
    }

    private function payrollSchema(): void
    {
        $this->workflowSchema();
        Schema::create('labours', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->softDeletes(); });
        Schema::table('labour_ledgers', function (Blueprint $t): void {
            $t->integer('project_id'); $t->integer('labour_id'); $t->decimal('amount', 15, 2);
            $t->date('date'); $t->text('details'); $t->timestamps();
        });
        DB::table('labours')->insert([['id' => 1, 'project_id' => 1], ['id' => 2, 'project_id' => 1], ['id' => 3, 'project_id' => 2]]);
    }

    private function payrollInput(): array
    {
        return ['request_key' => 'ORIGINAL-PAYROLL-PAYMENT-01', 'date' => '2026-10-31', 'week' => '2026-W44',
            'labours' => [['id' => 1, 'amount' => '10.00'], ['id' => 2, 'amount' => '20.00']],
            'posting_lines' => $this->approval()['posting_lines']];
    }

    public function test_original_payroll_keeps_exact_member_fks_and_replays_once_without_legacy_legs(): void
    {
        $this->payrollSchema(); $url = route('labours.payment', [], false); $input = $this->payrollInput();
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk();
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk()->assertJsonPath('data.replayed', true);
        $this->assertDatabaseCount('labour_ledgers', 2);
        $this->assertDatabaseCount('finance_labour_payment_members', 2);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('customer_ledger', 1);
        $this->assertDatabaseCount('ledgers', 0);
        $input['labours'][0]['amount'] = '15.00'; $input['labours'][1]['amount'] = '15.00';
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertStatus(409);
        $this->assertDatabaseCount('labour_ledgers', 2);
    }

    public function test_payroll_foreign_worker_and_late_failure_leave_no_partial_payment(): void
    {
        $this->payrollSchema(); $url = route('labours.payment', [], false); $input = $this->payrollInput();
        $input['labours'][0]['id'] = 3;
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertStatus(409);
        $this->assertDatabaseCount('customer_ledger', 0);
        DB::unprepared("CREATE TRIGGER reject_member BEFORE INSERT ON finance_labour_payment_members BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->withCookie('selected_action', '1')->postJson($url, $this->payrollInput());
            $this->fail('Membership insert must fail');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertDatabaseCount('customer_ledger', 0);
            $this->assertDatabaseCount('labour_ledgers', 0);
            $this->assertDatabaseCount('finance_original_customer_requests', 0);
            $this->assertDatabaseCount('finance_posting_batches', 0);
        }
    }

    private function reportSchema(): void
    {
        $this->workflowSchema();
        Schema::table('users', function (Blueprint $t): void { $t->string('name')->default('Synthetic operator'); });
        Schema::table('projects', function (Blueprint $t): void { $t->string('project')->default('Synthetic project'); });
        Schema::create('head_accountings', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->integer('acct_type'); });
        Schema::create('subhead_accountings', function (Blueprint $t): void { $t->id(); $t->string('name'); });
        Schema::create('account_types', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->integer('status')->default(1); });
        DB::table('head_accountings')->insert([['id' => 10, 'name' => 'Cash', 'acct_type' => 1], ['id' => 20, 'name' => 'Party', 'acct_type' => 2]]);
        DB::table('subhead_accountings')->insert([['id' => 100, 'name' => 'Cash account'], ['id' => 200, 'name' => 'Party account']]);
        DB::table('account_types')->insert([['id' => 1, 'name' => 'Assets'], ['id' => 2, 'name' => 'Liabilities']]);
        DB::table('project_head_subheads')->where('id', 102)->update(['subhead_accounting_id' => 200]);
        $user = User::findOrFail(1);
        $user->givePermissionTo(Permission::create(['name' => 'master report', 'guard_name' => 'web']));
        $this->actingAs($user);
        Gate::define('v2-finance-read', fn ($user) => true);
    }

    private function attendanceSchema(): void
    {
        $this->payrollSchema();
        Schema::create('sites', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->string('site_name'); $t->integer('subhead_accounting_id'); });
        DB::table('sites')->insert(['id' => 9, 'project_id' => 1, 'site_name' => 'Synthetic site', 'subhead_accounting_id' => 102]);
        Schema::table('labour_attendances', function (Blueprint $t): void {
            $t->integer('project_id'); $t->integer('site_id'); $t->integer('labour_id'); $t->date('date');
            $t->string('status'); $t->decimal('amount', 15, 2); $t->string('voucher_status')->default('pending');
        });
        DB::table('labour_attendances')->insert([
            ['id' => 1, 'project_id' => 1, 'site_id' => 9, 'labour_id' => 1, 'date' => '2026-10-30', 'status' => 'present', 'amount' => 10],
            ['id' => 2, 'project_id' => 1, 'site_id' => 9, 'labour_id' => 2, 'date' => '2026-10-31', 'status' => 'present', 'amount' => 20],
        ]);
    }

    private function attendanceInput(): array
    {
        return ['request_key' => 'ORIGINAL-ATTENDANCE-JV-001', 'date' => '2026-10-31', 'site_id' => 9,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'attendance_ids' => '1,2',
            'amount' => '30.00', 'posting_lines' => $this->approval()['posting_lines']];
    }

    public function test_original_attendance_jv_is_exactly_linked_and_replays_without_reusing_attendance(): void
    {
        $this->attendanceSchema(); $url = route('labours.create.voucher', [], false); $input = $this->attendanceInput();
        $id = $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk()->json('voucher_id');
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk()->assertJsonPath('voucher_id', $id);
        $this->assertDatabaseCount('finance_labour_attendance_journals', 2);
        $this->assertDatabaseHas('finance_labour_attendance_journals', ['labour_attendance_id' => 1, 'journal_voucher_id' => $id]);
        $this->assertDatabaseHas('labour_attendances', ['id' => 2, 'voucher_status' => 'created']);
        $this->assertDatabaseCount('journal_vouchers', 1);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('ledgers', 0);
        $input['request_key'] = 'ORIGINAL-ATTENDANCE-OTHER-01';
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertStatus(409);
        $this->assertDatabaseCount('journal_vouchers', 1);
    }

    public function test_attendance_forged_total_and_legacy_claim_never_create_another_journal(): void
    {
        $this->attendanceSchema(); $url = route('labours.create.voucher', [], false); $input = $this->attendanceInput();
        $input['amount'] = '31.00';
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertStatus(409);
        $this->assertDatabaseCount('journal_vouchers', 0);
        DB::table('labour_attendances')->where('id', 1)->update(['voucher_status' => 'created']);
        $this->withCookie('selected_action', '1')->postJson($url, $this->attendanceInput())->assertStatus(409);
        $this->assertDatabaseCount('finance_labour_attendance_journals', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    private function bookingDepositSchema(): void
    {
        $this->workflowSchema();
        Schema::create('leads', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); });
        Schema::create('plots', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->softDeletes(); });
        Schema::table('bookings', function (Blueprint $t): void {
            $t->integer('project_id'); $t->integer('customer_id'); $t->integer('plot_id');
            $t->string('status'); $t->string('cancel_status'); $t->softDeletes();
        });
        Schema::table('booking_vouchers', function (Blueprint $t): void {
            foreach (['booking_id', 'project_id', 'customer_id', 'plot_id', 'customer_ledger_id', 'voucher_number', 'create_by'] as $name) { $t->integer($name); }
            $t->integer('ledger_id')->nullable(); $t->string('voucher_series'); $t->decimal('amount', 15, 2);
            $t->date('receipt_date'); $t->text('description'); $t->string('slip_reference');
            $t->integer('payment_type'); $t->integer('is_active'); $t->integer('is_approve');
            $t->integer('bank_id')->nullable(); $t->string('t_number')->nullable(); $t->date('passing_date')->nullable(); $t->timestamps();
        });
        DB::table('leads')->insert(['id' => 11, 'project_id' => 1]);
        DB::table('plots')->insert(['id' => 12, 'project_id' => 1]);
        DB::table('bookings')->insert(['id' => 13, 'project_id' => 1, 'customer_id' => 11, 'plot_id' => 12, 'status' => 'active', 'cancel_status' => '0']);
        DB::table('charge_types')->insert(['id' => 5]);
        $user = User::findOrFail(1);
        $user->givePermissionTo(Permission::create(['name' => 'create plot', 'guard_name' => 'web']));
        $this->actingAs($user);
    }

    private function depositInput(string $action = 'deposit', string $amount = '30.00'): array
    {
        return ['request_key' => 'BOOKING-SOURCE-CREATE-'.$action, 'action' => $action, 'date' => '2026-10-31',
            'customer_id' => 11, 'plot_id' => 12, 'amount' => $amount, 'reference' => 'RECEIPT-'.$action,
            'detail' => 'Verified booking source', 'payment_type' => 1,
            'posting_lines' => [['account_id' => 101, 'debit' => $amount, 'credit' => '0'], ['account_id' => 102, 'debit' => '0', 'credit' => $amount]]];
    }

    public function test_original_booking_receipt_creation_has_one_source_and_one_receipt_on_retry(): void
    {
        $this->bookingDepositSchema(); $url = route('booking.customer.deposit', [], false); $input = $this->depositInput();
        foreach ([1, 2] as $_) { $this->withCookie('selected_action', '1')->postJson($url, $input)->assertOk(); }
        $this->assertDatabaseCount('customer_ledger', 1);
        $this->assertDatabaseCount('booking_vouchers', 1);
        $this->assertDatabaseHas('booking_vouchers', ['booking_id' => 13, 'customer_ledger_id' => 1, 'ledger_id' => null, 'is_approve' => 1]);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $input['request_key'] = 'BOOKING-DUPLICATE-REFERENCE-01';
        $this->withCookie('selected_action', '1')->postJson($url, $input)->assertStatus(409);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_booking_bank_receipt_keeps_receipt_unapproved_until_atomic_clearance(): void
    {
        $this->bookingDepositSchema(); $input = $this->depositInput(); $input['payment_type'] = 2;
        $input += ['t_number' => 'BOOKING-CHEQUE', 'bank_id' => 1, 'passing_date' => '2026-11-01']; unset($input['posting_lines']);
        $id = $this->withCookie('selected_action', '1')->postJson(route('booking.customer.deposit', [], false), $input)->assertOk()->json('data.source_id');
        $this->assertDatabaseHas('booking_vouchers', ['customer_ledger_id' => $id, 'is_approve' => 0, 't_number' => 'BOOKING-CHEQUE']);
        $this->withCookie('selected_action', '1')->postJson(route('finance.voucher.pending_bank_payments.status', ['customerLedger' => $id], false), [
            'request_key' => 'BOOKING-BANK-CLEARANCE-01', 'passing_status' => 1, 'bank_post_at' => '2026-11-02', 'note' => 'Verified statement',
            'posting_lines' => $this->approval()['posting_lines'],
        ])->assertOk();
        $this->assertDatabaseHas('booking_vouchers', ['customer_ledger_id' => $id, 'is_approve' => 1]);
        $this->assertDatabaseHas('finance_posting_batches', ['effective_date' => '2026-11-02']);
    }

    public function test_extra_charge_and_payment_use_real_fks_and_prevent_overpayment(): void
    {
        $this->bookingDepositSchema(); $url = route('booking.customer.deposit', [], false);
        $charge = $this->depositInput('extra_charge'); $charge['payment_type'] = 0; $charge['charge_type_id'] = 5;
        $id = $this->withCookie('selected_action', '1')->postJson($url, $charge)->assertOk()->json('data.source_id');
        $this->assertDatabaseHas('finance_extra_charge_sources', ['customer_ledger_id' => $id, 'booking_id' => 13, 'charge_type_id' => 5]);
        $payment = $this->depositInput('extra_charge_payment', '20.00'); $payment['charge_id'] = $id;
        $paymentId = $this->withCookie('selected_action', '1')->postJson($url, $payment)->assertOk()->json('data.source_id');
        $this->withCookie('selected_action', '1')->postJson($url, $payment)->assertOk()->assertJsonPath('data.source_id', $paymentId);
        $this->assertDatabaseHas('finance_extra_charge_payment_sources', ['customer_ledger_id' => $paymentId, 'charge_customer_ledger_id' => $id]);
        $balance = (new \App\Services\Finance\V2ExtraChargeBalance())->read(\App\Models\CustomerLedger::findOrFail($id));
        $this->assertSame(1000, $balance['available_cents']); $this->assertSame(2000, $balance['paid_cents']);
        $over = $this->depositInput('extra_charge_payment', '15.00'); $over['request_key'] = 'EXTRA-CHARGE-OVERPAYMENT-01';
        $over['reference'] = 'OVERPAYMENT'; $over['charge_id'] = $id;
        $this->withCookie('selected_action', '1')->postJson($url, $over)->assertStatus(409);
        $this->assertDatabaseCount('customer_ledger', 2);
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_existing_extra_charge_form_identity_and_clearance_action_keep_pending_reservation(): void
    {
        $this->bookingDepositSchema(); $url = route('booking.customer.deposit', [], false);
        $charge = $this->depositInput('extra_charge'); $charge['payment_type'] = 0; $charge['charge_type_id'] = 5;
        $id = $this->withCookie('selected_action', '1')->postJson($url, $charge)->assertOk()->json('data.source_id');
        $payment = $this->depositInput('extra_charge_payment', '20.00');
        $payment['extra_charge_id'] = $id; $payment['payment_type'] = 2;
        $payment += ['t_number' => 'ECP-CHEQUE', 'bank_id' => 1, 'passing_date' => '2026-11-01']; unset($payment['posting_lines']);
        $paymentId = $this->withCookie('selected_action', '1')->postJson($url, $payment)->assertOk()->json('data.source_id');
        $balance = (new \App\Services\Finance\ExtraChargeBankSettlement())->balances(\App\Models\CustomerLedger::findOrFail($id));
        $this->assertSame(2000, $balance['pending_cents']); $this->assertSame(1000, $balance['available_cents']);
        $this->withCookie('selected_action', '1')->postJson($url, [
            'action' => 'extra_charge_clearance', 'extra_charge_payment_id' => $paymentId,
            'request_key' => 'ORIGINAL-ECP-CLEARANCE-01', 'passing_status' => 1, 'bank_post_at' => '2026-11-02', 'note' => 'Verified bank statement',
            'posting_lines' => $this->depositInput('extra_charge_payment', '20.00')['posting_lines'],
        ])->assertOk();
        $balance = (new \App\Services\Finance\ExtraChargeBankSettlement())->balances(\App\Models\CustomerLedger::findOrFail($id));
        $this->assertSame(2000, $balance['paid_cents']); $this->assertSame(0, $balance['pending_cents']);
        $this->assertDatabaseCount('finance_posting_batches', 2);
    }

    public function test_posting_form_renders_accounts_and_retry_identity_only_when_writes_are_enabled(): void
    {
        $this->reportSchema();
        $accounts = \App\Models\ProjectHeadSubhead::with(['headAccounting', 'subheadAccounting'])->where('project_id', 1)->get();
        $html = view('admin.finance.voucher.typed_posting_fields', ['postingAccounts' => $accounts, 'includePostingDate' => true])->render();
        $this->assertStringContainsString('name="request_key"', $html);
        $this->assertStringContainsString('name="posting_lines[0][account_id]"', $html);
        $this->assertStringContainsString('Cash account', $html);
        $this->assertStringContainsString('name="date"', $html);
        config(['finance.v2_pilot_write_enabled' => false]);
        $this->assertStringNotContainsString('data-original-posting-fields', view('admin.finance.voucher.typed_posting_fields')->render());
    }

    public function test_original_ledger_and_group_reports_show_typed_source_without_legacy_union(): void
    {
        $this->reportSchema();
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput())->assertOk()->json('data.source_id');
        DB::table('ledgers')->insert(['type' => 'JV', 'voucher_number' => 999, 'project_head_subheads_id' => 101]);
        config(['finance.v2_pilot_write_enabled' => false]);
        $response = $this->withCookie('selected_action', '1')->getJson(route('fetch-data-url', [], false).'?draw=1&length=100')
            ->assertOk()->assertJsonPath('scope', 'typed_v2_postings_only')->assertJsonCount(2, 'data');
        $this->assertStringStartsWith('V2-', $response->json('data.0.id'));
        $this->assertEquals($id, $response->json('data.0.source_id'));
        $this->assertSame('cash', $response->json('data.0.source_kind'));
        $this->assertSame('30.00', $response->json('data.0.amount_in'));
        $this->withCookie('selected_action', '1')->postJson(route('fetch-data_by_party', [], false), ['length' => 100])
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.total_amount_in', '30.00');
        $this->withCookie('selected_action', '1')->postJson(route('fetch_data_by_head', [], false), ['account_type' => 'account', 'length' => 100])
            ->assertOk()->assertJsonCount(2, 'data');
        $this->withCookie('selected_action', '2')->getJson(route('fetch-data-url', [], false))->assertForbidden();
    }

    public function test_cash_list_counts_documents_once_and_print_uses_project_scoped_source_identity(): void
    {
        $this->reportSchema();
        $user = User::findOrFail(1);
        $user->givePermissionTo(Permission::create(['name' => 'read voucher', 'guard_name' => 'web']));
        $this->actingAs($user);
        $input = $this->cashInput(); $input['detail'] = '<script>alert(1)</script>';
        $id = $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $input)->assertOk()->json('data.source_id');
        config(['finance.v2_pilot_write_enabled' => false]);
        $url = route('voucher.cash_in.data', [], false);
        $response = $this->withCookie('selected_action', '1')->getJson($url.'?length=-1')->assertOk()
            ->assertJsonPath('recordsTotal', 1)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'SRC-'.$id)
            ->assertJsonPath('data.0.amount', '30.00')->assertJsonPath('data.0.detail', '&lt;script&gt;alert(1)&lt;/script&gt;');
        $print = route('finance.voucher.source.print', ['customerLedger' => $id], false);
        $this->assertStringEndsWith($print, $response->json('data.0.print_url'));
        $this->withCookie('selected_action', '1')->get($print)->assertOk()->assertSee('<script>alert(1)</script>');
        $this->withCookie('selected_action', '2')->getJson($print)->assertForbidden();
        $batch = (int) DB::table('finance_posting_batches')->value('id');
        (new \App\Services\Finance\V2PostingGateway())->reversePostedBatch(1, $batch, 'CASH-REPORT-REVERSAL-001', '2026-11-02', 'Verified correction', 1);
        $this->withCookie('selected_action', '1')->getJson($url)->assertOk()->assertJsonPath('data.0.amount', '0.00')
            ->assertJsonPath('data.0.status', 'Reversed')->assertJsonCount(1, 'data');
        $this->withCookie('selected_action', '1')->getJson($url.'?date_from=2026-11-01&date_to=2026-11-03')
            ->assertOk()->assertJsonPath('data.0.amount', '-30.00');
        DB::table('finance_posting_cash_sources')->delete();
        $this->withCookie('selected_action', '1')->getJson($url)->assertStatus(409);
    }

    public function test_original_ledger_report_validates_range_and_refuses_missing_typed_source(): void
    {
        $this->reportSchema();
        $this->withCookie('selected_action', '1')->postJson(route('ledger.store', [], false), $this->cashInput())->assertOk();
        $url = route('fetch-data-url', [], false);
        $this->withCookie('selected_action', '1')->getJson($url.'?end_date=2026-10-30')->assertOk()->assertJsonCount(0, 'data');
        $this->withCookie('selected_action', '1')->getJson($url.'?start_date=2026-11-01&end_date=2026-10-01')->assertStatus(422);
        DB::table('finance_posting_cash_sources')->delete();
        $this->withCookie('selected_action', '1')->getJson($url)->assertStatus(409);
    }

    public function test_original_jv_http_route_creates_exact_typed_posting_and_replays_without_v1_ledger(): void
    {
        $url = route('journal.voucher.store', [], false);
        $this->withCookie('selected_action', '1')->post($url, $this->journal())->assertRedirect(route('journal.voucher.index'));
        $this->withCookie('selected_action', '1')->post($url, $this->journal())->assertRedirect(route('journal.voucher.index'));
        $this->assertDatabaseCount('journal_vouchers', 1);
        $this->assertDatabaseCount('journal_voucher_details', 2);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_journal_line_sources', 2);
        $this->assertDatabaseCount('ledgers', 0);
        $changed = $this->journal(); $changed['reference'] = 'DIFFERENT';
        $this->withCookie('selected_action', '1')->postJson($url, $changed)->assertStatus(409);
        $this->assertDatabaseCount('journal_vouchers', 1);
    }

    public function test_jv_gate_and_missing_key_cannot_write_or_fall_back_to_legacy_ledger(): void
    {
        Gate::define('v2-finance-post', fn ($user) => false);
        $this->withCookie('selected_action', '1')->postJson(route('journal.voucher.store', [], false), $this->journal())->assertForbidden();
        Gate::define('v2-finance-post', fn ($user) => true);
        $input = $this->journal(); unset($input['idempotency_key']);
        $this->withCookie('selected_action', '1')->postJson(route('journal.voucher.store', [], false), $input)->assertStatus(409);
        $this->assertDatabaseCount('journal_vouchers', 0);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_original_jv_reverse_route_preserves_source_and_balances_the_original_accounts(): void
    {
        $user = User::findOrFail(1);
        $user->givePermissionTo(Permission::create(['name' => 'delete jv', 'guard_name' => 'web']));
        $this->actingAs($user);
        $this->withCookie('selected_action', '1')->post(route('journal.voucher.store', [], false), $this->journal())->assertRedirect();
        $id = DB::table('journal_vouchers')->value('id');
        $this->withCookie('selected_action', '1')->delete(route('journal.voucher.delete', $id, false), [
            'request_key' => 'ORIGINAL-JV-REVERSAL-001', 'effective_date' => '2026-10-31', 'reason' => 'Verified correction',
        ])->assertRedirect(route('journal.voucher.index'));
        $this->assertDatabaseHas('journal_vouchers', ['id' => $id, 'deleted_at' => null]);
        $this->assertDatabaseCount('journal_voucher_details', 2);
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);
        foreach ([101, 102] as $accountId) {
            $query = DB::table('finance_posting_lines')->where('project_head_subhead_id', $accountId);
            $this->assertSame((float) (clone $query)->sum('debit'), (float) (clone $query)->sum('credit'));
        }
    }

    public function test_read_only_pilot_never_falls_back_to_legacy_jv_creation_or_booking_approval(): void
    {
        config(['finance.v2_pilot_write_enabled' => false]);
        $this->source();
        $this->withCookie('selected_action', '1')->postJson(route('journal.voucher.store', [], false), $this->journal())->assertStatus(409);
        $this->withCookie('selected_action', '1')->postJson(route('booking.customer.approve', [], false), $this->approval())->assertStatus(409);
        $this->assertDatabaseCount('journal_vouchers', 0);
        $this->assertDatabaseHas('customer_ledger', ['id' => 42, 'is_approve' => 0]);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_original_commission_approval_posts_draft_and_replays(): void
    {
        DB::table('commision_vouchers')->insert(['id' => 41, 'project_id' => 1, 'voucher_number' => 'CV-41',
            'date' => '2026-10-31', 'status' => 'draft', 'total_debit' => '30.00', 'total_credit' => '30.00']);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 41, 'account_id' => 101, 'debit' => 30, 'credit' => 0],
            ['commision_voucher_id' => 41, 'account_id' => 102, 'debit' => 0, 'credit' => 30],
        ]);
        $url = route('commision.voucher.approve', ['id' => 41], false);
        foreach ([1, 2] as $_) {
            $this->withCookie('selected_action', '1')->post($url, ['request_key' => 'ORIGINAL-CV-APPROVAL-0001'])
                ->assertRedirect(route('commision.voucher.index'));
        }
        $this->assertDatabaseHas('commision_vouchers', ['id' => 41, 'status' => 'approved']);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_commission_line_sources', 2);
    }

    public function test_original_commission_create_preserves_header_metadata_before_approval(): void
    {
        $payload = $this->journal(); $payload['voucher_number'] = 'CV-NEW-42';
        $this->withCookie('selected_action', '1')->post(route('commision.voucher.store', [], false), $payload)->assertRedirect();
        $this->assertDatabaseHas('commision_vouchers', [
            'voucher_number' => 'CV-NEW-42', 'reference' => 'REF', 'description' => 'Original browser JV', 'status' => 'draft',
        ]);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_commission_form_number_is_normalized_once_and_duplicate_draft_is_refused(): void
    {
        $payload = $this->journal(); $payload['voucher_number'] = 'CV-0000042';
        $url = route('commision.voucher.store', [], false);
        $this->withCookie('selected_action', '1')->post($url, $payload)->assertRedirect();
        $this->assertDatabaseHas('commision_vouchers', ['voucher_number' => '42', 'status' => 'draft']);
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(422);
        $this->assertDatabaseCount('commision_vouchers', 1);
    }

    public function test_commission_number_preview_preserves_archived_numbers_and_prints_correct_source_kind(): void
    {
        DB::table('commision_vouchers')->insert(['id' => 41, 'project_id' => 1, 'voucher_number' => 'CV-0000041',
            'date' => '2026-10-31', 'status' => 'draft', 'total_debit' => '30.00', 'total_credit' => '30.00', 'deleted_at' => now()]);
        $this->assertSame(42, \App\Services\Finance\CommissionVoucherNumber::next(1));
        $this->assertSame(1, \App\Services\Finance\CommissionVoucherNumber::next(2));
        $voucher = new \App\Models\CommisionVoucher([
            'voucher_number' => 'CV-0000041', 'date' => '2026-10-31', 'total_debit' => 30, 'total_credit' => 30,
        ]);
        $voucher->setRelation('details', collect());
        $html = view('admin.reports.vouchers.print_journal_voucher', ['voucher' => $voucher, 'sourceVoucherType' => 'CV'])->render();
        $this->assertStringContainsString('General Commission Voucher', $html);
        $this->assertStringContainsString('CV-0000041', $html);
        $this->assertStringNotContainsString('JV-0000041', $html);
        $this->assertStringNotContainsString('CV-CV-', $html);
    }

    public function test_commission_listing_actions_never_open_journal_with_the_same_numeric_id(): void
    {
        $user = User::findOrFail(1);
        foreach (['edit jv', 'print jv'] as $name) {
            $user->givePermissionTo(Permission::create(['name' => $name, 'guard_name' => 'web']));
        }
        $this->actingAs($user);
        $voucher = new \App\Models\CommisionVoucher(); $voucher->id = 41;
        $html = view('admin.reports.vouchers.actions', compact('voucher'))->render();
        $this->assertStringContainsString(route('commision.voucher.edit', 41), $html);
        $this->assertStringContainsString(route('commision.voucher.print', 41), $html);
        $this->assertStringNotContainsString(route('journal.voucher.edit', 41), $html);
        $this->assertStringNotContainsString(route('journal.voucher.print', 41), $html);
    }

    public function test_commission_approval_failure_preserves_draft_and_default_off_is_blocked(): void
    {
        DB::table('commision_vouchers')->insert(['id' => 41, 'project_id' => 1, 'voucher_number' => 'CV-41',
            'date' => '2026-10-31', 'status' => 'draft', 'total_debit' => '30.00', 'total_credit' => '30.00']);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 41, 'account_id' => 101, 'debit' => 30, 'credit' => 0],
            ['commision_voucher_id' => 41, 'account_id' => 201, 'debit' => 0, 'credit' => 30],
        ]);
        $url = route('commision.voucher.approve', ['id' => 41], false);
        $payload = ['request_key' => 'ORIGINAL-CV-FAILURE-0001'];
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(409);
        $this->assertDatabaseHas('commision_vouchers', ['id' => 41, 'status' => 'draft']);
        config(['finance.v2_pilot_write_enabled' => false]);
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(409);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_commission_approval_refuses_ambiguous_legacy_jv_collision_without_reclassifying_it(): void
    {
        DB::table('commision_vouchers')->insert(['id' => 41, 'project_id' => 1, 'voucher_number' => 'CV-41',
            'date' => '2026-10-31', 'status' => 'draft', 'total_debit' => '30.00', 'total_credit' => '30.00']);
        DB::table('commision_voucher_details')->insert([
            ['commision_voucher_id' => 41, 'account_id' => 101, 'debit' => 30, 'credit' => 0],
            ['commision_voucher_id' => 41, 'account_id' => 102, 'debit' => 0, 'credit' => 30],
        ]);
        DB::table('ledgers')->insert(['type' => 'JV', 'type_id' => 41, 'voucher_number' => 99, 'project_head_subheads_id' => 101]);
        $this->withCookie('selected_action', '1')->postJson(route('commision.voucher.approve', ['id' => 41], false),
            ['request_key' => 'ORIGINAL-CV-COLLISION-001'])->assertStatus(409);
        $this->assertDatabaseHas('commision_vouchers', ['id' => 41, 'status' => 'draft']);
        $this->assertDatabaseHas('ledgers', ['type' => 'JV', 'type_id' => 41, 'voucher_number' => 99]);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_ledger_insert_failure_rolls_back_new_original_jv_header_and_details(): void
    {
        DB::unprepared("CREATE TRIGGER reject_posting BEFORE INSERT ON finance_posting_lines BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->withCookie('selected_action', '1')->post(route('journal.voucher.store', [], false), $this->journal());
            $this->fail('Posting insert must fail');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertDatabaseCount('journal_vouchers', 0);
            $this->assertDatabaseCount('journal_voucher_details', 0);
            $this->assertDatabaseCount('finance_posting_batches', 0);
            $this->assertSame(0, DB::connection()->transactionLevel());
        }
    }

    public function test_archived_account_cannot_be_used_to_approve_customer_voucher(): void
    {
        Schema::table('project_head_subheads', function (Blueprint $t): void { $t->softDeletes(); });
        DB::table('project_head_subheads')->where('id', 102)->update(['deleted_at' => now()]);
        $this->source();
        $this->withCookie('selected_action', '1')->postJson(route('booking.customer.approve', [], false), $this->approval())->assertStatus(409);
        $this->assertDatabaseHas('customer_ledger', ['id' => 42, 'is_approve' => 0]);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    private function source(): void
    {
        DB::table('customer_ledger')->insert(['id' => 42, 'project_id' => 1, 'transaction_type' => 'PPR',
            'type_id' => 7, 'date' => '2026-10-31', 'amount_in' => '30.00', 'amount_out' => '0.00', 'is_approve' => 0, 'is_active' => 1]);
    }

    private function approval(): array
    {
        return ['id' => 42, 'is_approve' => 1, 'request_key' => 'ORIGINAL-PPR-APPROVE-001', 'posting_lines' => [
            ['account_id' => 101, 'debit' => '30.00', 'credit' => '0'],
            ['account_id' => 102, 'debit' => '0', 'credit' => '30.00'],
        ]];
    }

    public static function customerSeries(): array
    {
        return [['CR'], ['CP'], ['PPR'], ['EC'], ['ECP']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('customerSeries')]
    public function test_original_booking_approval_posts_actual_customer_source_once(string $series): void
    {
        $this->source();
        DB::table('customer_ledger')->where('id', 42)->update([
            'transaction_type' => $series,
            'amount_in' => in_array($series, ['CP', 'EC'], true) ? '0.00' : '30.00',
            'amount_out' => in_array($series, ['CP', 'EC'], true) ? '30.00' : '0.00',
        ]);
        foreach ([1, 2] as $_) {
            $this->withCookie('selected_action', '1')->post(route('booking.customer.approve', [], false), $this->approval())->assertRedirect();
        }
        $this->assertDatabaseHas('customer_ledger', ['id' => 42, 'is_approve' => 1]);
        $this->assertDatabaseHas('finance_posting_cash_sources', ['customer_ledger_id' => 42]);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_customer_approval_never_double_posts_a_legacy_linked_source(): void
    {
        $this->source();
        DB::table('ledgers')->insert(['type' => 'PPR', 'voucher_number' => 7, 'project_head_subheads_id' => 101, 'customer_ledger_id' => 42]);
        $this->withCookie('selected_action', '1')->postJson(route('booking.customer.approve', [], false), $this->approval())->assertStatus(409);
        $this->assertDatabaseHas('customer_ledger', ['id' => 42, 'is_approve' => 0]);
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $this->assertDatabaseCount('ledgers', 1);
    }

    public function test_failed_booking_posting_rolls_back_approval_and_wrong_project_is_refused(): void
    {
        $this->source(); $input = $this->approval(); $input['posting_lines'][1]['account_id'] = 201;
        $this->withCookie('selected_action', '1')->postJson(route('booking.customer.approve', [], false), $input)->assertStatus(409);
        $this->assertDatabaseHas('customer_ledger', ['id' => 42, 'is_approve' => 0]);
        $this->withCookie('selected_action', '2')->postJson(route('booking.customer.approve', [], false), $this->approval())->assertForbidden();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_legacy_booking_approval_is_project_scoped_and_cannot_revoke_posted_source(): void
    {
        config(['finance.v2_pilot_api_enabled' => false, 'finance.v2_pilot_write_enabled' => false]);
        $this->source();
        $url = route('booking.customer.approve', [], false);
        $this->withCookie('selected_action', '2')->postJson($url, ['id' => 42, 'is_approve' => 1])->assertNotFound();
        DB::table('ledgers')->insert(['type' => 'PPR', 'voucher_number' => 7, 'project_head_subheads_id' => 101, 'customer_ledger_id' => 42]);
        $this->withCookie('selected_action', '1')->postJson($url, ['id' => 42, 'is_approve' => 0])->assertStatus(409);
        $this->assertDatabaseCount('ledgers', 1);
    }

    private function stockSources(): void
    {
        Schema::table('stock_entries', function (Blueprint $t): void {
            $t->date('entry_date'); $t->string('slip_no'); $t->string('flow'); $t->integer('party_id');
            $t->timestamp('billed_at')->nullable(); $t->softDeletes();
            $t->string('vehicle_no')->nullable(); $t->string('driver_name')->nullable(); $t->text('reason')->nullable();
            $t->integer('created_by')->nullable(); $t->integer('updated_by')->nullable(); $t->timestamps();
        });
        Schema::table('stock_bills', function (Blueprint $t): void {
            $t->date('bill_date'); $t->string('bill_no'); $t->string('type'); $t->integer('party_id');
            $t->integer('entry_id')->nullable(); $t->bigInteger('total_minor'); $t->softDeletes();
        });
        foreach (['stock_entry_lines' => 'entry_id', 'stock_bill_lines' => 'bill_id'] as $name => $fk) {
            Schema::create($name, function (Blueprint $t) use ($fk): void {
                $t->id(); $t->integer($fk); $t->integer('item_id'); $t->decimal('qty', 12, 3);
                $t->bigInteger('rate_minor'); $t->bigInteger('amount_minor');
                $t->timestamps();
            });
        }
        (require base_path('database/v2_migrations/2026_10_31_000120_create_v2_stock_project_approvals.php'))->up();
        DB::table('stock_entries')->insert(['id' => 70, 'entry_date' => '2026-10-31', 'slip_no' => '70', 'flow' => 'IN', 'party_id' => 1]);
        DB::table('stock_bills')->insert(['id' => 80, 'bill_date' => '2026-10-31', 'bill_no' => '80', 'type' => 'purchase', 'party_id' => 1, 'total_minor' => 3000]);
        DB::table('stock_entry_lines')->insert(['entry_id' => 70, 'item_id' => 1, 'qty' => '1.000', 'rate_minor' => 3000, 'amount_minor' => 3000]);
        DB::table('stock_bill_lines')->insert(['bill_id' => 80, 'item_id' => 1, 'qty' => '1.000', 'rate_minor' => 3000, 'amount_minor' => 3000]);
        Gate::define('approve-v2-stock-finance', fn ($user) => true);
    }

    private function inventorySchema(): void
    {
        $this->stockSources();
        Schema::create('stock_items', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('unit'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('stock_parties', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('type'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('stock_counters', function (Blueprint $t): void { $t->string('key')->primary(); $t->integer('next_number'); $t->timestamps(); });
        Schema::create('stock_item_balances', function (Blueprint $t): void { $t->integer('item_id')->primary(); $t->decimal('on_hand_qty', 18, 3); $t->timestamps(); });
        Schema::create('stock_movements', function (Blueprint $t): void {
            $t->id(); $t->date('movement_date'); $t->string('direction'); $t->integer('item_id'); $t->decimal('qty', 18, 3);
            $t->integer('rate_minor'); $t->integer('amount_minor'); $t->string('source_type'); $t->integer('source_id');
            $t->integer('source_line_id'); $t->integer('party_id'); $t->integer('created_by'); $t->timestamps();
        });
        DB::table('stock_items')->insert(['id' => 1, 'name' => 'Cement', 'unit' => 'bag']);
        DB::table('stock_parties')->insert(['id' => 1, 'name' => 'Supplier', 'type' => 'Supplier']);
    }

    public function test_original_stock_create_keeps_inventory_pending_then_explicit_approval_posts_finance(): void
    {
        $this->inventorySchema();
        $data = ['date' => '2026-10-31', 'party_id' => 1, 'flow' => 'IN', 'lines' => [['item_id' => 1, 'qty' => '1.000', 'rate' => 3000]]];
        $id = $this->withCookie('selected_action', '1')->postJson(route('stocks.entries.store', [], false), $data)
            ->assertOk()->assertJsonPath('message', 'Entry saved. Finance approval is required.')->json('data.id');
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $this->assertDatabaseCount('ledgers', 0);
        $payload = ['request_key' => 'ORIGINAL-STOCK-CREATED-001', 'evidence_reference' => 'OWNER-DOC-CREATED-2026', 'posting_lines' => $this->approval()['posting_lines']];
        $this->assertSame('2026-10-31', (new \App\Services\Finance\V2StockSourceSnapshot())->read('stock_entry', $id)['date']);
        $this->withCookie('selected_action', '1')->postJson(route('stocks.finance.approve', ['kind' => 'stock_entry', 'id' => $id], false), $payload)->assertOk();
        $this->assertDatabaseHas('finance_posting_stock_entry_sources', ['stock_entry_id' => $id]);
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_original_stock_create_cannot_use_archived_item_or_party(): void
    {
        $this->inventorySchema();
        $data = ['date' => '2026-10-31', 'party_id' => 1, 'flow' => 'IN', 'lines' => [['item_id' => 1, 'qty' => '1.000', 'rate' => 3000]]];
        DB::table('stock_items')->where('id', 1)->update(['deleted_at' => now()]);
        $this->withCookie('selected_action', '1')->postJson(route('stocks.entries.store', [], false), $data)->assertStatus(422);
        DB::table('stock_items')->where('id', 1)->update(['deleted_at' => null]);
        DB::table('stock_parties')->where('id', 1)->update(['deleted_at' => now()]);
        $this->withCookie('selected_action', '1')->postJson(route('stocks.entries.store', [], false), $data)->assertStatus(422);
        $this->assertDatabaseCount('stock_entries', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_original_mobile_stock_create_and_approval_share_typed_flow_without_legacy_posting(): void
    {
        $this->inventorySchema();
        Schema::table('projects', function (Blueprint $t): void { $t->string('project')->default('Synthetic project'); });
        foreach (['stock_items', 'stock_parties'] as $name) {
            Schema::table($name, function (Blueprint $t): void { $t->integer('is_active')->default(1); });
        }
        $user = User::findOrFail(1);
        $user->givePermissionTo(Permission::create(['name' => 'create stock', 'guard_name' => 'web']));
        $this->actingAs($user, 'sanctum');
        $data = ['project_id' => 1, 'date' => '2026-10-31', 'party_id' => 1, 'lines' => [['item_id' => 1, 'qty' => '1.000', 'rate' => 3000]]];
        $id = $this->postJson('/api/v1/mobile/stock/entries/in', $data)->assertOk()->json('data.id');
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('ledgers', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $payload = ['project_id' => 1, 'request_key' => 'MOBILE-STOCK-APPROVAL-001',
            'evidence_reference' => 'OWNER-MOBILE-DOC-2026', 'posting_lines' => $this->approval()['posting_lines']];
        $this->postJson('/api/v1/mobile/stock/finance/stock_entry/'.$id.'/approve', $payload)->assertOk();
        $this->assertDatabaseHas('finance_posting_stock_entry_sources', ['stock_entry_id' => $id]);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_original_stock_entry_and_bill_approval_post_exact_source_and_replay(): void
    {
        $this->stockSources();
        foreach (['stock_entry' => 70, 'stock_bill' => 80] as $kind => $id) {
            $payload = ['request_key' => 'ORIGINAL-STOCK-APPROVE-'.$id, 'evidence_reference' => 'OWNER-DOC-2026-'.$id,
                'posting_lines' => $this->approval()['posting_lines']];
            $url = route('stocks.finance.approve', ['kind' => $kind, 'id' => $id], false);
            $first = $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertOk()->json('posting_batch_id');
            $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertOk()->assertJsonPath('posting_batch_id', $first);
        }
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseHas('finance_posting_stock_entry_sources', ['stock_entry_id' => 70]);
        $this->assertDatabaseHas('finance_posting_stock_bill_sources', ['stock_bill_id' => 80]);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_failed_stock_post_rolls_back_source_approval_and_gate_remains_mandatory(): void
    {
        $this->stockSources();
        $payload = ['request_key' => 'ORIGINAL-STOCK-FAILURE-001', 'evidence_reference' => 'OWNER-DOC-2026-FAILURE',
            'posting_lines' => $this->approval()['posting_lines']];
        $payload['posting_lines'][1]['account_id'] = 201;
        $url = route('stocks.finance.approve', ['kind' => 'stock_entry', 'id' => 70], false);
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(409);
        $this->assertDatabaseCount('finance_stock_entry_project_approvals', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
        Gate::define('approve-v2-stock-finance', fn ($user) => false);
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('finance_stock_entry_project_approvals', 0);
    }

    public function test_original_stock_approval_refuses_existing_legacy_financial_posting(): void
    {
        $this->stockSources();
        Schema::table('ledgers', function (Blueprint $t): void { $t->string('reference')->nullable(); });
        DB::table('ledgers')->insert(['type' => 'JV', 'voucher_number' => 7, 'project_head_subheads_id' => 101, 'reference' => 'STOCK-ENTRY:70']);
        $payload = ['request_key' => 'ORIGINAL-STOCK-LEGACY-001', 'evidence_reference' => 'OWNER-DOC-2026-LEGACY',
            'posting_lines' => $this->approval()['posting_lines']];
        $url = route('stocks.finance.approve', ['kind' => 'stock_entry', 'id' => 70], false);
        $this->withCookie('selected_action', '1')->postJson($url, $payload)->assertStatus(409);
        $this->assertDatabaseCount('ledgers', 1);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }
}
