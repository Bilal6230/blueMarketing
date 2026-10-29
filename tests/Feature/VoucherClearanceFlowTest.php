<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Finance\VoucherController;
use App\Http\Controllers\LedgerController;
use App\Models\Booking;
use App\Models\BookingVoucher;
use App\Models\CustomerLedger;
use App\Models\Ledger;
use App\Models\Lead;
use App\Models\Plot;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class VoucherClearanceFlowTest extends TestCase
{
    use WithFaker;

    private User $user;
    private Project $project;
    private Project $otherProject;
    private Lead $customer;
    private Plot $plot;
    private ProjectHeadSubhead $bookingReceiptAccount;
    private ProjectHeadSubhead $cashVoucherAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->withoutMiddleware();
        $this->createSchema();
        $this->seedBaseData();
        $this->actingAs($this->user);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCancellationAdjustments')]
    public function test_cancellation_rejects_invalid_adjustment_without_mutating_booking_or_finance(string $effect, string $amount): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $request = Request::create('/admin/booking/cancel', 'POST', [
            'booking_id' => $booking->id,
            'action_type' => 'plot_purchase',
            'cancel_effect' => $effect,
            'deduction_amount' => $amount,
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        TestResponse::fromBaseResponse(app(BookingController::class)->cancel($request))->assertStatus(422);
        $this->assertSame('0', (string) $booking->fresh()->cancel_status);
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public static function invalidCancellationAdjustments(): array
    {
        return [
            'none with hidden fine' => ['none', '100'],
            'negative deduction' => ['charge_customer', '-100'],
            'scientific profit' => ['give_profit', '1e5'],
            'fractional cents' => ['charge_customer', '0.001'],
            'text input' => ['give_profit', '100abc'],
        ];
    }

    public function test_paid_cancellation_rejects_existing_ledger_marker_before_booking_or_plot_mutation(): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        Ledger::create([
            'type' => 'SV',
            'type_id' => 10,
            'voucher_number' => 10,
            'project_head_subheads_id' => $this->bookingReceiptAccount->id,
            'reference' => (string) $booking->id,
            'amount_in' => 0,
            'amount_out' => 100,
            'date' => '2026-05-17',
            'detail' => 'CANCEL#' . $booking->id . ' | Original cancellation',
            'is_active' => 1,
        ]);
        $bookingBefore = $booking->fresh()->getAttributes();
        $plotBefore = $this->plot->fresh()->getAttributes();
        $request = Request::create('/admin/booking/cancel', 'POST', [
            'booking_id' => $booking->id,
            'action_type' => 'plot_purchase',
            'cancel_effect' => 'none',
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        TestResponse::fromBaseResponse(app(BookingController::class)->cancel($request))
            ->assertStatus(409)->assertJson(['error' => 'cancellation_requires_financial_reversal']);
        $this->assertSame($bookingBefore, $booking->fresh()->getAttributes());
        $this->assertSame($plotBefore, $this->plot->fresh()->getAttributes());
        $this->assertSame(1, DB::table('ledgers')->count());
        $this->assertSame(0, DB::table('customer_ledger')->count());
    }

    public function test_extra_charge_rejects_unsupported_immediate_bank_payment_without_financial_writes(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $response = $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-NOMAP', 'payment_type' => 2,
        ]));
        $response->assertStatus(302)->assertSessionHasErrors('msg');
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_extra_charge_rejects_source_reference_delimiter_injection(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-SECURE-SOURCE',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge_payment', 'extra_charge_id' => $charge->id,
            'reference' => 'INVALID#SPLIT',
        ]))->assertSessionHasErrors('msg');
        $this->assertSame(0, CustomerLedger::where('transaction_type', 'ECP')->count());
    }

    public function test_extra_charge_refuses_foreign_series_ledger_attached_to_settled_payment(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-STRICT-LEDGER',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge_payment', 'extra_charge_id' => $charge->id,
            'reference' => 'EC-FIRST-RECEIPT', 'amount' => '400',
        ]))->assertSessionHasNoErrors();
        $payment = CustomerLedger::where('transaction_type', 'ECP')->firstOrFail();
        DB::table('ledgers')->insert([
            'customer_ledger_id' => $payment->id, 'type' => 'CR',
            'type_id' => 900, 'voucher_number' => 900,
            'project_head_subheads_id' => $this->bookingReceiptAccount->id,
            'reference' => 'FOREIGN-SERIES',
            'amount_in' => '0.00', 'amount_out' => '0.00',
            'date' => '2026-05-17', 'is_active' => 1,
        ]);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge_payment', 'extra_charge_id' => $charge->id,
            'reference' => 'EC-SECOND-RECEIPT', 'amount' => '100',
        ]))->assertSessionHasErrors('msg');
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'ECP')->count());
    }

    public function test_extra_charge_mixed_cash_pending_rejected_and_cleared_balances(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1, 'reference' => 'EC-MIX-DUE',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $cash = $this->depositPayload(1, [
            'action' => 'extra_charge_payment', 'extra_charge_id' => $charge->id,
            'reference' => 'EC-MIX-CASH', 'amount' => '400',
        ]);
        $this->callDeposit($cash)->assertSessionHasNoErrors();
        $bank = $this->depositPayload(2, [
            'action' => 'extra_charge_payment', 'extra_charge_id' => $charge->id,
            'reference' => 'EC-MIX-PENDING', 'amount' => '600',
        ]);
        $this->callDeposit($bank)->assertSessionHasNoErrors();
        $balance = app(\App\Services\Finance\ExtraChargeBankSettlement::class)->balances($charge);
        $this->assertSame(40000, $balance['paid_cents']);
        $this->assertSame(60000, $balance['pending_cents']);
        $this->assertSame(85000, $balance['outstanding_cents']);
        $this->assertSame(25000, $balance['available_cents']);
        $this->callDeposit(array_merge($cash, [
            'reference' => 'EC-MIX-OVER', 'amount' => '251',
        ]))->assertSessionHasErrors('msg');
        $this->assertSame(2, CustomerLedger::where('transaction_type', 'ECP')->count());
        $pending = CustomerLedger::where('transaction_type', 'ECP')
            ->where('payment_type', 2)->firstOrFail();
        $this->app['session.store']->forget('errors');
        $this->callDeposit($this->depositPayload(2, [
            'action' => 'extra_charge_clearance',
            'extra_charge_payment_id' => $pending->id,
            'passing_status' => 2, 'note' => 'Bank returned',
        ]))->assertSessionHasNoErrors();
        $balance = app(\App\Services\Finance\ExtraChargeBankSettlement::class)->balances($charge);
        $this->assertSame(0, $balance['pending_cents']);
        $this->assertSame(85000, $balance['available_cents']);
        $this->callDeposit(array_merge($cash, [
            'reference' => 'EC-MIX-FINAL', 'amount' => '850',
        ]))->assertSessionHasNoErrors();
        $balance = app(\App\Services\Finance\ExtraChargeBankSettlement::class)->balances($charge);
        $this->assertSame(125000, $balance['paid_cents']);
        $this->assertSame(0, $balance['outstanding_cents']);
        $this->assertSame(0, $balance['available_cents']);
    }

    public function test_extra_charge_online_pending_clearance_and_replay_protection(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-BANK-DUE',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $bankPayload = $this->depositPayload(2, [
            'action' => 'extra_charge_payment',
            'extra_charge_id' => $charge->id,
            'reference' => 'EC-BANK-RECEIPT',
            'amount' => '500',
        ]);
        $this->callDeposit($bankPayload)->assertSessionHasNoErrors();
        $pending = CustomerLedger::where('transaction_type', 'ECP')->firstOrFail();
        $this->assertSame(0, (int) $pending->passing_status);
        $this->assertSame(0, Ledger::where('customer_ledger_id', $pending->id)->count());
        $this->callDeposit($bankPayload)->assertSessionHasErrors('msg');
        $this->callDeposit(array_merge($bankPayload, [
            'reference' => 'EC-BANK-OVER', 'amount' => '751',
        ]))->assertSessionHasErrors('msg');
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'ECP')->count());

        $clear = $this->depositPayload(2, [
            'action' => 'extra_charge_clearance',
            'extra_charge_payment_id' => $pending->id,
            'passing_status' => 1,
            'bank_post_at' => '2026-05-20',
        ]);
        $this->app['session.store']->forget('errors');
        $this->callDeposit($clear)->assertSessionHasNoErrors();
        $this->assertSame(1, (int) $pending->fresh()->passing_status);
        $this->assertSame(2, Ledger::where('customer_ledger_id', $pending->id)->count());
        $autoBank = DB::table('project_head_subheads as p')
            ->join('head_accountings as h', 'h.id', '=', 'p.head_accounting_id')
            ->join('subhead_accountings as s', 's.id', '=', 'p.subhead_accounting_id')
            ->where('p.project_id', $this->project->id)
            ->where('h.name', 'Extra Charge')->where('s.name', 'Extra Charge Bank')
            ->select('p.id as account_id')
            ->first();
        $this->assertNotNull($autoBank);
        $this->assertSame(1, Ledger::where('customer_ledger_id', $pending->id)
            ->where('project_head_subheads_id', $autoBank->account_id)->count());
        $this->assertEquals(500, Ledger::where('customer_ledger_id', $pending->id)->sum('amount_in'));
        $this->assertEquals(500, Ledger::where('customer_ledger_id', $pending->id)->sum('amount_out'));
        $this->callDeposit($clear)->assertSessionHasErrors('msg');
        $this->assertSame(2, Ledger::where('customer_ledger_id', $pending->id)->count());
    }

    public function test_extra_charge_cheque_rejection_releases_reserved_amount(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-CHEQUE-DUE',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $bankPayload = $this->depositPayload(3, [
            'action' => 'extra_charge_payment',
            'extra_charge_id' => $charge->id,
            'reference' => 'EC-CHEQUE-PENDING',
            'amount' => '1250',
        ]);
        $this->callDeposit($bankPayload)->assertSessionHasNoErrors();
        $pending = CustomerLedger::where('transaction_type', 'ECP')->firstOrFail();
        $this->callDeposit($this->depositPayload(3, [
            'action' => 'extra_charge_clearance',
            'extra_charge_payment_id' => $pending->id,
            'passing_status' => 2,
            'note' => 'Cheque returned',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $pending->fresh()->passing_status);
        $this->assertSame(0, Ledger::where('type', 'ECP')->count());

        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge_payment',
            'extra_charge_id' => $charge->id,
            'reference' => 'EC-CHEQUE-REPLACED',
            'amount' => '1250',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, Ledger::where('type', 'ECP')->count());
    }

    public function test_extra_charge_deferred_cash_partial_full_and_overpayment_are_atomic(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-DUE-1',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();

        $base = $this->depositPayload(1, [
            'action' => 'extra_charge_payment',
            'extra_charge_id' => $charge->id,
            'reference' => 'EC-PAY-1',
            'amount' => '400',
        ]);
        $this->callDeposit($base)->assertSessionHasNoErrors();
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'ECP')->count());
        $this->assertSame(2, Ledger::where('type', 'ECP')->count());
        $this->callDeposit($base)->assertSessionHasErrors('msg');
        $this->callDeposit(array_merge($base, [
            'reference' => 'EC-OVERPAY',
            'amount' => '851',
        ]))->assertSessionHasErrors('msg');
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'ECP')->count());

        // These are multiple HTTP requests in one test. Laravel keeps
        // previously flashed validation errors until session aging, so an
        // earlier intentional rejection must not pollute a later success.
        $this->app['session.store']->forget('errors');
        $this->callDeposit(array_merge($base, [
            'reference' => 'EC-PAY-2',
            'amount' => '850',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, CustomerLedger::where('transaction_type', 'ECP')->count());
        $this->assertSame(4, Ledger::where('type', 'ECP')->count());
        $this->assertEquals(1250, Ledger::where('type', 'ECP')->sum('amount_in'));
        $this->assertEquals(1250, Ledger::where('type', 'ECP')->sum('amount_out'));
        $this->assertEquals(1250, $charge->fresh()->amount_in);
    }

    public function test_extra_charge_deferred_payment_auto_creates_cash_account_without_manual_mapping(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-AUTO-CASH',
        ]))->assertSessionHasNoErrors();
        $charge = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge_payment',
            'extra_charge_id' => $charge->id,
            'reference' => 'EC-AUTO-CASH-RECEIPT',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'ECP')->count());
        $this->assertSame(2, Ledger::where('type', 'ECP')->count());
        $cash = DB::table('project_head_subheads as p')
            ->join('head_accountings as h', 'h.id', '=', 'p.head_accounting_id')
            ->join('subhead_accountings as s', 's.id', '=', 'p.subhead_accounting_id')
            ->where('p.project_id', $this->project->id)
            ->where('h.name', 'Extra Charge')->where('s.name', 'Extra Charge Cash')
            ->select('p.id as account_id')
            ->first();
        $this->assertNotNull($cash);
        $this->assertSame(1, Ledger::where('type', 'ECP')
            ->where('project_head_subheads_id', $cash->account_id)->count());
    }

    public function test_extra_charge_auto_creates_project_accounts_and_reuses_income_account(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'AUTO-EC-1',
        ]))->assertSessionHasNoErrors();
        $first = CustomerLedger::where('reference', 'AUTO-EC-1')->firstOrFail();
        $this->assertNull($first->payment_type);
        $legs = Ledger::where('customer_ledger_id', $first->id)->get();
        $this->assertCount(2, $legs);
        $this->assertEquals(1250, $legs->sum('amount_in'));
        $this->assertEquals(1250, $legs->sum('amount_out'));
        $this->assertSame(1, DB::table('head_accountings')->where('name', 'Extra Charge')->count());
        $this->assertSame(2, DB::table('subhead_accountings')->whereIn('name', [
            'Extra Charge Income', 'Extra Charge Receivable',
        ])->count());
        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'AUTO-EC-2',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('head_accountings')->where('name', 'Extra Charge')->count());
        $this->assertSame(2, DB::table('subhead_accountings')->whereIn('name', [
            'Extra Charge Income', 'Extra Charge Receivable',
        ])->count());
        $this->assertSame(2, CustomerLedger::where('transaction_type', 'EC')->count());
    }

    public function test_shared_journal_allocator_reserves_header_and_ledger_numbers(): void
    {
        DB::transaction(function () {
            $allocator = app(\App\Services\Finance\ProjectVoucherAllocator::class);
            $this->assertSame(1, $allocator->nextJournal($this->project->id));
            DB::table('journal_vouchers')->insert([
                'project_id' => $this->project->id,
                'type' => 'JV', 'voucher_number' => 14,
                'reference' => 'RESERVE-JV',
                'date' => '2026-05-17',
                'description' => 'Historical journal header',
                'total_debit' => 100,
                'total_credit' => 100,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->assertSame(15, $allocator->nextJournal($this->project->id));
            Ledger::create([
                'type' => 'JV', 'type_id' => 19,
                'voucher_number' => 19,
                'project_head_subheads_id' => $this->bookingReceiptAccount->id,
                'reference' => 'SURVIVING-JV-LEDGER',
                'amount_in' => 100, 'amount_out' => 0,
                'date' => '2026-05-17',
            ]);
            $this->assertSame(20, $allocator->nextJournal($this->project->id));
        });
    }

    public function test_finance_source_contract_rejects_cross_project_and_wrong_series_before_posting(): void
    {
        $source = CustomerLedger::create([
            'project_id' => $this->project->id, 'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id, 'transaction_type' => 'CR',
            'type_id' => 12, 'reference' => 'OWNERSHIP-CR-12',
            'amount_in' => 100, 'amount_out' => 0,
            'date' => '2026-05-17', 'is_active' => 1,
        ]);
        $foreign = ProjectHeadSubhead::create([
            'project_id' => $this->otherProject->id,
            'head_accounting_id' => 16, 'subhead_accounting_id' => 20,
        ]);
        $contract = app(\App\Services\Finance\FinancialSourceOwnership::class);
        $leg = [
            'customer_ledger_id' => $source->id, 'type' => 'CR',
            'type_id' => 12, 'voucher_number' => 12,
            'project_head_subheads_id' => $foreign->id,
            'reference' => 'OWNERSHIP-CR-12', 'amount_in' => 100,
            'amount_out' => 0, 'date' => '2026-05-17', 'is_active' => 1,
        ];
        try {
            Ledger::create($leg);
            $this->fail('Cross-project linked posting should be rejected');
        } catch (\DomainException $e) {
            $this->assertSame('FINANCE_SOURCE_OWNER_MISMATCH', $e->getMessage());
        }
        $this->assertSame(0, Ledger::where('customer_ledger_id', $source->id)->count());
        $contract->assertUnique($this->project->id, 'CR', 13, 'NEW-CR-13');
        try {
            $contract->assertUnique($this->project->id, 'CR', 12, 'NEW-CR-13');
            $this->fail('Reused voucher number should be rejected');
        } catch (\DomainException $e) {
            $this->assertSame('FINANCE_SOURCE_DUPLICATE', $e->getMessage());
        }
        try {
            $contract->assertUnique($this->project->id, 'CR', 13, 'OWNERSHIP-CR-12');
            $this->fail('Reused reference should be rejected');
        } catch (\DomainException $e) {
            $this->assertSame('FINANCE_SOURCE_DUPLICATE', $e->getMessage());
        }
    }

    public function test_project_audit_detects_duplicate_source_identities_read_only(): void
    {
        foreach ([1, 2] as $n) {
            CustomerLedger::create([
                'project_id' => $this->project->id, 'customer_id' => $this->customer->id,
                'plot_id' => $this->plot->id, 'transaction_type' => 'CP',
                'type_id' => 42, 'reference' => 'DUPLICATE-CP-42',
                'amount_in' => 0, 'amount_out' => 100,
                'date' => '2026-05-17', 'is_active' => 1,
            ]);
        }
        $before = CustomerLedger::where('transaction_type', 'CP')->count();
        $code = \Illuminate\Support\Facades\Artisan::call('finance:audit-project-vouchers', [
            'project' => $this->project->id,
        ]);
        $this->assertSame(1, $code);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('duplicate_project_source_number', $output);
        $this->assertStringContainsString('duplicate_project_source_reference', $output);
        $this->assertSame($before, CustomerLedger::where('transaction_type', 'CP')->count());
    }

    public function test_project_audit_accepts_pending_ecp_without_ledger(): void
    {
        $charge = CustomerLedger::create([
            'project_id' => $this->project->id, 'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id, 'transaction_type' => 'EC',
            'type_id' => 1, 'reference' => 'AUDIT-EC-1',
            'amount_in' => 100, 'amount_out' => 0, 'date' => '2026-05-17',
            'is_active' => 1,
        ]);
        foreach ([[$this->bookingReceiptAccount->id, 100, 0],
                  [$this->cashVoucherAccount->id, 0, 100]] as [$account, $in, $out]) {
            Ledger::create([
                'customer_ledger_id' => $charge->id, 'type' => 'EC',
                'type_id' => 1, 'voucher_number' => 1,
                'project_head_subheads_id' => $account, 'reference' => 'AUDIT-EC-1',
                'amount_in' => $in, 'amount_out' => $out,
                'date' => '2026-05-17', 'is_active' => 1,
            ]);
        }
        CustomerLedger::create([
            'project_id' => $this->project->id, 'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id, 'transaction_type' => 'ECP',
            'type_id' => 1, 'reference' => 'EC_PAYMENT#' . $charge->id . '#PENDING',
            'amount_in' => 25, 'payment_type' => 2, 'passing_status' => 0,
            'date' => '2026-05-17', 'is_active' => 1,
        ]);
        $code = \Illuminate\Support\Facades\Artisan::call('finance:audit-project-vouchers', [
            'project' => $this->project->id,
        ]);
        $this->assertSame(0, $code);
        $this->assertStringNotContainsString('source_without_ledger',
            \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_project_audit_flags_rejected_ecp_with_foreign_series_ledger(): void
    {
        $source = CustomerDB::table('ledgers')->insert([
            'project_id' => $this->project->id, 'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id, 'transaction_type' => 'ECP',
            'type_id' => 7, 'reference' => 'EC_PAYMENT#123#BAD',
            'amount_in' => 75, 'payment_type' => 3, 'passing_status' => 2,
            'date' => '2026-05-17', 'is_active' => 1,
        ]);
        Ledger::create([
            'customer_ledger_id' => $source->id, 'type' => 'CR',
            'type_id' => 55, 'voucher_number' => 55,
            'project_head_subheads_id' => $this->bookingReceiptAccount->id,
            'reference' => 'WRONG', 'amount_in' => 75, 'amount_out' => 0,
            'date' => '2026-05-17', 'is_active' => 1,
        ]);
        $code = \Illuminate\Support\Facades\Artisan::call('finance:audit-project-vouchers', [
            'project' => $this->project->id,
        ]);
        $this->assertSame(1, $code);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('pending_or_rejected_ecp_has_ledger', $output);
        $this->assertStringContainsString('ecp_wrong_or_missing_charge', $output);
        $this->assertStringContainsString('source_ledger_identity_mismatch', $output);
    }

    public function test_project_voucher_audit_reports_orphan_cr_source_read_only(): void
    {
        $source = CustomerLedger::create([
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id,
            'transaction_type' => 'CR',
            'type_id' => 99,
            'reference' => 'AUDIT-CR-ORPHAN',
            'amount_in' => 100,
            'amount_out' => 0,
            'date' => '2026-05-17',
        ]);
        $status = \Illuminate\Support\Facades\Artisan::call('finance:audit-project-vouchers', [
            'project' => $this->project->id,
        ]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('source_without_ledger', \Illuminate\Support\Facades\Artisan::output());
        $this->assertNotNull($source->fresh());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_shared_ppr_allocator_reserves_booking_voucher_and_orphan_source(): void
    {
        DB::transaction(function () {
            $allocator = app(\App\Services\Finance\ProjectVoucherAllocator::class);
            $this->assertSame(1, $allocator->nextBookingReceipt($this->project->id));
            DB::table('booking_vouchers')->insert([
                'project_id' => $this->project->id,
                'voucher_series' => 'PPR',
                'voucher_number' => 17,
                'slip_reference' => 'HISTORIC-PPR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertSame(18, $allocator->nextBookingReceipt($this->project->id));
            CustomerLedger::create([
                'project_id' => $this->project->id,
                'customer_id' => $this->customer->id,
                'plot_id' => $this->plot->id,
                'transaction_type' => 'PPR',
                'type_id' => 26,
                'reference' => 'ORPHAN-PPR-SOURCE',
                'amount_out' => 120,
                'amount_in' => 0,
                'date' => '2026-05-17',
            ]);
            $this->assertSame(27, $allocator->nextBookingReceipt($this->project->id));
        });
    }

    public function test_shared_project_allocator_preserves_sources_and_ledger_numbers(): void
    {
        $allocator = app(\App\Services\Finance\ProjectVoucherAllocator::class);
        try {
            $allocator->next($this->project->id, 'EC');
            $this->fail('Allocation outside a transaction must fail.');
        } catch (\DomainException $exception) {
            $this->assertSame('VOUCHER_ALLOCATION_REQUIRES_TRANSACTION', $exception->getMessage());
        }

        DB::transaction(function () use ($allocator) {
            $this->assertSame(1, $allocator->next($this->project->id, 'EC'));
            CustomerLedger::create([
                'project_id' => $this->project->id,
                'customer_id' => $this->customer->id,
                'plot_id' => $this->plot->id,
                'transaction_type' => 'EC',
                'type_id' => 19,
                'reference' => 'ALLOCATOR-ORPHAN',
                'amount_in' => 120,
                'amount_out' => 0,
                'date' => '2026-05-17',
            ]);
            $this->assertSame(20, $allocator->next($this->project->id, 'EC'));
            Ledger::create([
                'type' => 'EC', 'type_id' => 25,
                'voucher_number' => 25,
                'project_head_subheads_id' => $this->bookingReceiptAccount->id,
                'reference' => 'ALLOCATOR-LEDGER',
                'amount_in' => 120, 'amount_out' => 0,
                'date' => '2026-05-17',
            ]);
            $this->assertSame(26, $allocator->next($this->project->id, 'EC'));
            $this->assertSame(1, $allocator->next($this->project->id, 'CP'));
        });
    }

    public function test_ec_audit_command_reports_clean_empty_project_without_writes(): void
    {
        $status = \Illuminate\Support\Facades\Artisan::call('finance:audit-ec-sources', [
            'project' => $this->project->id,
        ]);
        $this->assertSame(0, $status);
        $this->assertStringContainsString('"read_only":true', \Illuminate\Support\Facades\Artisan::output());
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_ec_audit_command_detects_orphan_source_without_repair(): void
    {
        $source = CustomerLedger::create([
            'project_id' => $this->project->id, 'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id, 'transaction_type' => 'EC',
            'type_id' => 14, 'reference' => 'EC-ORPHAN-AUDIT',
            'amount_in' => 100, 'amount_out' => 0,
            'payment_type' => 1, 'date' => '2026-05-17',
        ]);
        $status = \Illuminate\Support\Facades\Artisan::call('finance:audit-ec-sources', [
            'project' => $this->project->id,
        ]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('leg_count_not_two', \Illuminate\Support\Facades\Artisan::output());
        $this->assertNotNull($source->fresh());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_extra_charge_number_skips_orphaned_typed_source(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $otherAccount = ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 16,
            'subhead_accounting_id' => 102,
        ]);
        config(["finance.extra_charge_accounts.{$this->project->id}.1" => [
            'approved' => true,
            'debit_account_id' => $this->bookingReceiptAccount->id,
            'credit_account_id' => $otherAccount->id,
        ]]);
        CustomerLedger::create([
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id,
            'transaction_type' => 'EC',
            'type_id' => 21,
            'reference' => 'ORPHAN-EC',
            'amount_in' => 100,
            'amount_out' => 0,
            'date' => '2026-05-17',
            'payment_type' => 1,
        ]);

        $this->callDeposit($this->depositPayload(1, [
            'action' => 'extra_charge',
            'charge_type_id' => 1,
            'reference' => 'EC-AFTER-ORPHAN',
        ]))->assertStatus(302)->assertSessionHasNoErrors();

        $source = CustomerLedger::where('reference', 'EC-AFTER-ORPHAN')->firstOrFail();
        $this->assertSame(22, (int) $source->type_id);
        $legs = Ledger::where('customer_ledger_id', $source->id)->get();
        $this->assertCount(2, $legs);
        $this->assertEquals(1250, $legs->sum('amount_in'));
        $this->assertEquals(1250, $legs->sum('amount_out'));
        $this->assertSame(0, Ledger::where('reference', 'ORPHAN-EC')->count());
    }

    public function test_extra_charge_cash_posts_balanced_typed_source_once(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        DB::table('charge_types')->insert(['id' => 1, 'name' => 'Test charge']);
        $otherAccount = ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 16,
            'subhead_accounting_id' => 101,
        ]);
        config(["finance.extra_charge_accounts.{$this->project->id}.1" => [
            'approved' => true,
            'debit_account_id' => $this->bookingReceiptAccount->id,
            'credit_account_id' => $otherAccount->id,
        ]]);
        $payload = $this->depositPayload(1, [
            'action' => 'extra_charge', 'charge_type_id' => 1,
            'reference' => 'EC-BALANCED',
        ]);
        $this->callDeposit($payload)->assertStatus(302)->assertSessionHasNoErrors();
        $source = CustomerLedger::where('transaction_type', 'EC')->firstOrFail();
        $legs = Ledger::where('customer_ledger_id', $source->id)->where('type', 'EC')->get();
        $this->assertCount(2, $legs);
        $this->assertEquals(1250, $legs->sum('amount_in'));
        $this->assertEquals(1250, $legs->sum('amount_out'));
        $this->assertCount(2, $legs->pluck('project_head_subheads_id')->unique());
        $this->callDeposit($payload)->assertSessionHasErrors('msg');
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'EC')->count());
        $this->assertSame(2, Ledger::where('type', 'EC')->count());
    }

    public function test_no_payment_cancellation_without_financial_history_posts_no_reversal(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cancel_type')->nullable();
            $table->string('cancel_effect')->nullable();
            $table->decimal('cancel_sale_amount', 12, 2)->nullable();
            $table->decimal('cancel_adjustment_amount', 12, 2)->nullable();
            $table->decimal('cancel_net_amount', 12, 2)->nullable();
            $table->timestamp('cancel_posted_at')->nullable();
        });
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $this->callBookingCancel($booking->id)
            ->assertOk()->assertJson(['message' => 'Booking canceled successfully.']);
        $this->assertSame('1', (string) $booking->fresh()->cancel_status);
        $this->assertSame(0, (int) $this->plot->fresh()->sold);
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
        $this->callBookingCancel($booking->id)->assertOk();
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_no_payment_cancellation_blocks_posted_financial_history_without_state_change(): void
    {
        $this->callDeposit($this->depositPayload(1))->assertStatus(302);
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $beforePlot = $this->plot->fresh()->getAttributes();
        $sourceCount = DB::table('customer_ledger')->count();
        $ledgerCount = DB::table('ledgers')->count();
        $response = $this->callBookingCancel($booking->id);
        $response->assertStatus(409)->assertJson(['error' => 'cancellation_requires_financial_reversal']);
        $this->assertSame('0', (string) $booking->fresh()->cancel_status);
        $this->assertSame($beforePlot, $this->plot->fresh()->getAttributes());
        $this->assertSame($sourceCount, DB::table('customer_ledger')->count());
        $this->assertSame($ledgerCount, DB::table('ledgers')->count());
    }

    public function test_no_payment_cancellation_rejects_foreign_project_booking(): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $this->callBookingCancel($booking->id, $this->otherProject->id)
            ->assertStatus(500)->assertJson(['message' => 'Cancellation failed']);
        $this->assertSame('0', (string) $booking->fresh()->cancel_status);
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    private function callBookingCancel(int $bookingId, ?int $projectId = null): TestResponse
    {
        $request = Request::create('/admin/booking/cancel', 'POST', [
            'booking_id' => $bookingId,
            'action_type' => 'payment_not_received',
            'cancel_effect' => 'none',
        ]);
        $request->cookies->set('selected_action', (string) ($projectId ?? $this->project->id));
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        return TestResponse::fromBaseResponse(app(BookingController::class)->cancel($request));
    }

    public function test_booking_delete_unposted_source_preserves_plot_history_without_financial_rows(): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $request = Request::create('/admin/booking/delete', 'POST', ['id' => $booking->id]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        TestResponse::fromBaseResponse(app(BookingController::class)->destroy_booking($request))
            ->assertOk()->assertJson(['message' => 'Booking deleted successfully.']);
        $this->assertNotNull(Booking::withTrashed()->findOrFail($booking->id)->deleted_at);
        $this->assertSame(0, (int) $this->plot->fresh()->sold);
        $history = json_decode((string) $this->plot->fresh()->plot_history, true);
        $this->assertSame((int) $booking->id, (int) $history[0]['booking_id']);
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public function test_booking_delete_rejects_posted_ppr_without_hiding_booking_or_plot(): void
    {
        $this->callDeposit($this->depositPayload(1))->assertStatus(302);
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $plotSold = (int) $this->plot->fresh()->sold;
        $sourceCount = DB::table('customer_ledger')->count();
        $ledgerCount = DB::table('ledgers')->count();

        $request = Request::create('/admin/booking/delete', 'POST', ['id' => $booking->id]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        TestResponse::fromBaseResponse(app(BookingController::class)->destroy_booking($request))
            ->assertStatus(409)->assertJson(['error' => 'booking_has_financial_history']);

        $this->assertNull($booking->fresh()->deleted_at);
        $this->assertSame($plotSold, (int) $this->plot->fresh()->sold);
        $this->assertSame($sourceCount, DB::table('customer_ledger')->count());
        $this->assertSame($ledgerCount, DB::table('ledgers')->count());
    }

    public function test_booking_delete_rejects_foreign_project_booking(): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        $request = Request::create('/admin/booking/delete', 'POST', ['id' => $booking->id]);
        $request->cookies->set('selected_action', (string) $this->otherProject->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        try {
            app(BookingController::class)->destroy_booking($request);
            $this->fail('Foreign project booking must not be deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertNotNull($exception);
        }
        $this->assertNull($booking->fresh()->deleted_at);
    }

    public function test_booking_delete_rejects_stranded_customer_financial_source(): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        CustomerLedger::create([
            'customer_id' => $booking->customer_id,
            'project_id' => $booking->project_id,
            'plot_id' => $booking->plot_id,
            'transaction_type' => 'PPR',
            'type_id' => 5,
            'reference' => 'STRANDED-RECEIPT',
            'amount_out' => 500,
            'amount_in' => 0,
            'date' => '2026-05-17',
            'payment_type' => 1,
            'is_active' => 0,
        ]);
        $request = Request::create('/admin/booking/delete', 'POST', ['id' => $booking->id]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        TestResponse::fromBaseResponse(app(BookingController::class)->destroy_booking($request))
            ->assertStatus(409)->assertJson(['error' => 'booking_has_financial_history']);
        $this->assertNull($booking->fresh()->deleted_at);
        $this->assertSame(1, CustomerLedger::where('reference', 'STRANDED-RECEIPT')->count());
    }

    public function test_booking_delete_rejects_stranded_ledger_without_customer_source(): void
    {
        $booking = Booking::where('project_id', $this->project->id)->firstOrFail();
        Ledger::create([
            'type' => 'PPR',
            'type_id' => 77,
            'voucher_number' => 77,
            'project_head_subheads_id' => $this->bookingReceiptAccount->id,
            'reference' => 'ORPHAN-LEDGER',
            'amount_in' => 120,
            'amount_out' => 0,
            'date' => '2026-05-17',
            'is_active' => 0,
        ]);
        $request = Request::create('/admin/booking/delete', 'POST', ['id' => $booking->id]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        TestResponse::fromBaseResponse(app(BookingController::class)->destroy_booking($request))
            ->assertStatus(409)->assertJson(['error' => 'booking_has_financial_history']);
        $this->assertNull($booking->fresh()->deleted_at);
        $this->assertSame(1, DB::table('ledgers')->where('reference', 'ORPHAN-LEDGER')->count());
    }

    public function test_ppr_cash_received_payment_creates_customer_ledger_ledger_and_booking_voucher(): void
    {
        $response = $this->callDeposit($this->depositPayload(1));

        $response->assertStatus(302);

        $customerLedger = CustomerLedger::where('transaction_type', 'PPR')->first();
        $ledger = Ledger::where('customer_ledger_id', $customerLedger->id)->where('type', 'PPR')->first();
        $bookingVoucher = BookingVoucher::where('customer_ledger_id', $customerLedger->id)->first();

        $this->assertNotNull($customerLedger);
        $this->assertNotNull($ledger);
        $this->assertNotNull($bookingVoucher);
        $this->assertSame($ledger->id, $bookingVoucher->ledger_id);
        $this->assertNull($customerLedger->passing_status);
    }

    public function test_failed_booking_create_rolls_back_all_partial_writes_and_hides_exception(): void
    {
        $loggedError = null;
        Log::shouldReceive('error')->once()->andReturnUsing(function (string $message, array $context) use (&$loggedError): void {
            $loggedError = compact('message', 'context');
        });
        $before = [
            'bookings' => DB::table('bookings')->count(),
            'customer_ledger' => DB::table('customer_ledger')->count(),
            'subhead_accountings' => DB::table('subhead_accountings')->count(),
            'project_head_subheads' => DB::table('project_head_subheads')->count(),
            'journal_vouchers' => DB::table('journal_vouchers')->count(),
            'journal_voucher_details' => DB::table('journal_voucher_details')->count(),
            'ledgers' => DB::table('ledgers')->count(),
        ];

        $request = Request::create('/admin/booking', 'POST', [
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => (string) $this->plot->id,
            'plot_type' => 1,
            'plot_size' => '5',
            'plot_rate' => '100,000',
            'total_price' => '500,000',
            'booking_date' => '2026-05-17',
            'status' => 'active',
            'broker_id' => 1,
        ]);
        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/booking/sale');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        $response = app(BookingController::class)->store($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($session->has('errors'), $session->has('errors') ? $session->get('errors')->first() : '');
        $this->assertSame('/admin/booking/sale', $response->getTargetUrl());
        $this->assertStringNotContainsString('Credit account ID not found.', $response->getContent());
        $sessionState = json_encode($session->all(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Unable to save the booking. Please try again or contact support.', $sessionState);
        $this->assertStringNotContainsString('Credit account ID not found.', $sessionState);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Unexpected partial write in {$table}.");
        }
        $this->assertSame(0, (int) $this->plot->fresh()->sold);
        $this->assertSame(0, DB::transactionLevel());

        $this->assertSame('Error storing booking', $loggedError['message']);
        $this->assertInstanceOf(\Throwable::class, $loggedError['context']['exception']);
        $this->assertSame('Credit account ID not found.', $loggedError['context']['exception']->getMessage());
        $this->assertSame($this->user->id, $loggedError['context']['user_id']);
        $this->assertSame($this->project->id, $loggedError['context']['project_id']);
        $this->assertSame($this->customer->id, $loggedError['context']['customer_id']);
        $this->assertSame((string) $this->plot->id, $loggedError['context']['plot_id']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('canonicalBookingStatuses')]
    public function test_successful_booking_create_redirects_to_booking_list(string $status): void
    {
        ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 16,
            'subhead_accounting_id' => 123,
        ]);
        $request = Request::create('/admin/booking', 'POST', [
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => (string) $this->plot->id,
            'plot_type' => 1,
            'plot_size' => '5',
            'plot_rate' => '100,000',
            'total_price' => '500,000',
            'booking_date' => '2026-05-17',
            'status' => $status,
            'broker_id' => 1,
        ]);
        $session = app('session.store');
        $session->start();
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        $response = app(BookingController::class)->store($request);

        $this->assertSame(route('booking.plot.index'), $response->getTargetUrl());
        $this->assertSame(2, DB::table('bookings')->count());
        $this->assertSame($status, DB::table('bookings')->latest('id')->value('status'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function canonicalBookingStatuses(): array
    {
        return ['active' => ['active'], 'inactive' => ['inactive']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBookingStatuses')]
    public function test_booking_create_rejects_noncanonical_status($status): void
    {
        $before = DB::table('bookings')->count();
        $request = Request::create('/admin/booking', 'POST', [
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => (string) $this->plot->id,
            'plot_type' => 1,
            'plot_size' => '5',
            'plot_rate' => '100,000',
            'total_price' => '500,000',
            'booking_date' => '2026-05-17',
            'status' => $status,
            'broker_id' => 1,
        ]);
        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/booking/sale');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        $response = app(BookingController::class)->store($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($session->get('errors')->has('status'));
        $this->assertSame($before, DB::table('bookings')->count());
    }

    public static function invalidBookingStatuses(): array
    {
        return ['numeric active' => [1], 'numeric inactive' => [0], 'arbitrary string' => ['enabled']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pendingReceiptPaymentTypesProvider')]
    public function test_ppr_pending_bank_received_payment_creates_ledger_immediately(int $paymentType): void
    {
        $response = $this->callDeposit($this->depositPayload($paymentType, [
            'detail' => 'Installment payment',
        ]));

        $response->assertStatus(302);

        $customerLedger = CustomerLedger::where('transaction_type', 'PPR')->first();
        $ledger = Ledger::where('customer_ledger_id', $customerLedger->id)->where('type', 'PPR')->first();
        $bookingVoucher = BookingVoucher::where('customer_ledger_id', $customerLedger->id)->first();

        $this->assertNotNull($ledger);
        $this->assertSame(0, (int) $customerLedger->passing_status);
        $this->assertSame($ledger->id, $bookingVoucher->ledger_id);
        $this->assertStringContainsString('PPR |', $ledger->detail);
        $this->assertStringContainsString('Plot R-101', $ledger->detail);
        $this->assertStringContainsString('Cust: Ali Khan', $ledger->detail);
        $this->assertStringContainsString('Date: 2026-05-17', $ledger->detail);
        $this->assertStringContainsString('Pass: 2026-05-18', $ledger->detail);
        $this->assertStringNotContainsString('Received plot payment for', $ledger->detail);

        if ($paymentType === 2) {
            $this->assertStringContainsString('Method: Online', $ledger->detail);
            $this->assertStringContainsString('Bank: Bank Alfalah', $ledger->detail);
            $this->assertStringContainsString('Txn: TXN-001', $ledger->detail);
        } else {
            $this->assertStringContainsString('Method: Cheque', $ledger->detail);
            $this->assertStringContainsString('Bank: Bank Alfalah', $ledger->detail);
            $this->assertStringContainsString('Chq: TXN-001', $ledger->detail);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pendingReceiptPaymentTypesProvider')]
    public function test_cr_online_and_check_vouchers_create_ledger_immediately_and_remain_pending(int $paymentType): void
    {
        $response = $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CR-1',
            'voucher_number' => 'CR-1',
            'payment_type' => $paymentType,
            't_number' => 'TXN-' . $paymentType,
            'bank_id' => 1,
            'passing_date' => '2026-05-17',
        ]));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'flow_type' => 'posted_pending_clearance',
            ]);

        $customerLedger = CustomerLedger::where('transaction_type', 'CR')->first();
        $ledger = Ledger::where('customer_ledger_id', $customerLedger->id)->where('type', 'CR')->first();

        $this->assertNotNull($ledger);
        $this->assertSame(0, (int) $customerLedger->passing_status);

        if ($paymentType === 2) {
            $this->assertStringContainsString('CR |', $ledger->detail);
            $this->assertStringContainsString('Method: Online', $ledger->detail);
            $this->assertStringContainsString('Txn: TXN-2', $ledger->detail);
            $this->assertStringNotContainsString('Account No', $ledger->detail);
        } else {
            $this->assertStringContainsString('CR |', $ledger->detail);
            $this->assertStringContainsString('Method: Cheque', $ledger->detail);
            $this->assertStringContainsString('Chq: TXN-3', $ledger->detail);
        }
    }

    public function test_cash_voucher_editor_cannot_zero_an_unrelated_jv_posting(): void
    {
        // Use a real synthetic posting with a valid account, then attempt the
        // generic cash editor on a JV row. The journal's amount must survive.
        $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'payment_type' => 1,
            'amount' => '1750',
            'reference' => 'CASH-GUARD',
        ]));

        $ledger = Ledger::where('type', 'CP')->firstOrFail();
        $ledger->update(['type' => 'JV']);

        $payload = [
            'id' => $ledger->id,
            'amount' => '2000',
            'detail' => 'Attempt to rewrite JV',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
            'subaccounts_id' => $this->cashVoucherAccount->subhead_accounting_id,
            'date' => '2026-05-18',
            'payment_type' => 1,
        ];

        $request = Request::create('/admin/ledger/update', 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        $response = (new LedgerController())->update($request);

        $this->assertSame(500, $response->status());
        $ledger->refresh();
        $this->assertSame('JV', $ledger->type);
        $this->assertEquals(1750, $ledger->amount_out);
        $this->assertEquals(0, $ledger->amount_in);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBankClearanceSources')]
    public function test_bank_clearance_does_not_mutate_an_invalid_source(string $type, int $paymentType): void
    {
        $receipt = $this->createPendingReceipt($type, $paymentType, $this->project->id, 1500);
        $before = DB::table('ledgers')->count();

        $request = Request::create('/admin/reports/bank-posting-check', 'POST', [
            'id' => $receipt->id,
            'passing_status' => 1,
            'note' => 'Attempted clearance',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
            'subaccounts_id' => $this->cashVoucherAccount->subhead_accounting_id,
            'bank_post_at' => '2026-05-20',
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $this->app->make(\App\Http\Controllers\ReportController::class)->bank_posting_check($request);

        $this->assertSame(0, (int) $receipt->fresh()->passing_status);
        $this->assertSame($before, DB::table('ledgers')->count());
        $this->assertDatabaseMissing('ledgers', ['type' => 'BR']);
    }

    public static function invalidBankClearanceSources(): array
    {
        return [
            'cash receipt' => ['CR', 1],
            'other source' => ['CP', 2],
        ];
    }

    public function test_legacy_jv_edit_and_delete_remain_blocked_without_mutating_ledgers(): void
    {
        $before = DB::table('ledgers')->count();
        $controller = $this->app->make(\App\Http\Controllers\JournalVoucherController::class);

        foreach (['update', 'destroy'] as $method) {
            try {
                if ($method === 'update') {
                    $controller->update(Request::create('/journal-voucher/999', 'PUT'), 999);
                } else {
                    $controller->destroy(999);
                }
                $this->fail('Legacy JV mutation must fail closed.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
        }

        $this->assertSame($before, DB::table('ledgers')->count());
    }

    public function test_manual_cash_out_still_creates_cp_ledger(): void
    {
        $response = $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'payment_type' => 1,
            'amount' => '1750',
            'reference' => 'CP-REF-001',
        ]));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'flow_type' => 'posted',
                'voucher_number' => 1,
                'voucher_display' => 'CP-1',
            ]);

        $customerLedger = CustomerLedger::where('transaction_type', 'CP')->first();
        $ledger = Ledger::where('customer_ledger_id', $customerLedger->id)->where('type', 'CP')->first();

        $this->assertNotNull($customerLedger);
        $this->assertNotNull($ledger);
        $this->assertSame('CP-REF-001', $ledger->reference);
        $this->assertSame(1750.0, (float) $ledger->amount_out);
        $this->assertSame(0.0, (float) $ledger->amount_in);
    }

    public function test_cash_out_pending_status_update_does_not_create_cp_records(): void
    {
        $pending = $this->createPendingReceipt('PPR', 2, $this->project->id, 1500);

        $response = $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'payment_type' => 2,
            'amount' => '1500',
            't_number' => 'CLR-1500',
            'bank_id' => 1,
            'selected_pending_payment_id' => $pending->id,
            'pending_status' => 2,
            'passing_date' => '2026-05-20',
        ]));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'flow_type' => 'pending_payment_status_updated',
                'pending_status' => 2,
                'selected_pending_payment_id' => $pending->id,
            ]);

        $pending->refresh();

        $this->assertSame(2, (int) $pending->passing_status);
        $this->assertSame('General voucher detail', $pending->note);
        $this->assertSame('2026-05-20', $pending->bank_post_at);
        $this->assertDatabaseCount('customer_ledger', 1);
        $this->assertDatabaseMissing('ledgers', ['type' => 'CP']);
        $this->assertDatabaseMissing('ledgers', ['type' => 'BR']);
        $this->assertCount(1, $pending->check_history ?? []);
        $pendingRowsAfterReturn = $this->callPendingBankPayments()->json('data');
        $pendingIdsAfterReturn = collect($pendingRowsAfterReturn)->pluck('id')->all();
        $this->assertNotContains($pending->id, $pendingIdsAfterReturn);
    }

    public function test_passing_a_pending_payment_creates_one_cp_ledger_only_once(): void
    {
        $pending = $this->createPendingReceipt('CR', 3, $this->project->id, 2200, [
            'voucher_number' => 1,
            'transaction_number' => 'CHK-001',
        ]);

        $payload = $this->cashVoucherPayload([
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'payment_type' => 3,
            'amount' => '2200',
            't_number' => 'CLR-2200',
            'bank_id' => 1,
            'selected_pending_payment_id' => $pending->id,
            'pending_status' => 1,
            'passing_date' => '2026-05-21',
        ]);

        $firstResponse = $this->callLedgerStore($payload);

        $firstResponse->assertOk()
            ->assertJson([
                'status' => 'success',
                'flow_type' => 'pending_payment_status_updated',
                'pending_status' => 1,
                'selected_pending_payment_id' => $pending->id,
                'voucher_display' => 'CP-1',
            ]);

        $pending->refresh();

        $this->assertDatabaseHas('ledgers', [
            'customer_ledger_id' => $pending->id,
            'type' => 'CP',
            'reference' => 'CASH_OUT_CLEARANCE#' . $pending->id,
            'project_head_subheads_id' => $this->cashVoucherAccount->id,
        ]);
        $this->assertDatabaseMissing('ledgers', [
            'customer_ledger_id' => $pending->id,
            'type' => 'BR',
        ]);
        $this->assertSame(1, Ledger::where('customer_ledger_id', $pending->id)->where('type', 'CP')->where('reference', 'CASH_OUT_CLEARANCE#' . $pending->id)->count());
        $clearanceLedger = Ledger::where('customer_ledger_id', $pending->id)->where('type', 'CP')->where('reference', 'CASH_OUT_CLEARANCE#' . $pending->id)->first();
        $this->assertNotNull($clearanceLedger);
        $this->assertStringContainsString('CP Clear', $clearanceLedger->detail);
        $this->assertStringContainsString('Src: CR-1', $clearanceLedger->detail);
        $this->assertStringContainsString('Orig: Cheque/Bank Alfalah/Chq CHK-001', $clearanceLedger->detail);
        $this->assertStringContainsString('To: Cash Voucher/Customer Account', $clearanceLedger->detail);
        $this->assertStringContainsString('Date: 2026-05-21', $clearanceLedger->detail);
        $this->assertLessThanOrEqual(260, strlen($clearanceLedger->detail));
        $this->assertSame(1, (int) $pending->passing_status);
        $pendingRowsAfterPass = $this->callPendingBankPayments()->json('data');
        $pendingIdsAfterPass = collect($pendingRowsAfterPass)->pluck('id')->all();
        $this->assertNotContains($pending->id, $pendingIdsAfterPass);

        $secondResponse = $this->callLedgerStore($payload);

        $secondResponse->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'pending_payment_invalid_state',
            ]);

        $this->assertSame(1, Ledger::where('customer_ledger_id', $pending->id)->where('type', 'CP')->where('reference', 'CASH_OUT_CLEARANCE#' . $pending->id)->count());
    }

    public function test_cash_out_pending_clearance_cp_detail_for_original_ppr_contains_expected_narration(): void
    {
        $pending = $this->createPendingReceipt('PPR', 2, $this->project->id, 1800, [
            'reference' => '006230',
            'detail' => 'Installment payment',
            'voucher_number' => 13,
            'transaction_number' => '18408074163',
            'receipt_date' => '2026-06-03',
            'passing_date' => '2026-06-03',
        ]);

        $response = $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CP-252',
            'voucher_number' => 'CP-252',
            'payment_type' => 2,
            'amount' => '1800',
            't_number' => 'CLR-1800',
            'bank_id' => 1,
            'selected_pending_payment_id' => $pending->id,
            'pending_status' => 1,
            'passing_date' => '2026-06-03',
            'detail' => 'passed',
        ]));

        $response->assertOk()
            ->assertJson([
                'voucher_display' => 'CP-252',
            ]);

        $clearanceLedger = Ledger::where('customer_ledger_id', $pending->id)
            ->where('type', 'CP')
            ->where('reference', 'CASH_OUT_CLEARANCE#' . $pending->id)
            ->first();

        $this->assertNotNull($clearanceLedger);
        $this->assertStringContainsString('CP Clear', $clearanceLedger->detail);
        $this->assertStringContainsString('Src: PPR-13', $clearanceLedger->detail);
        $this->assertStringContainsString('Plot: Plot R-101', $clearanceLedger->detail);
        $this->assertStringContainsString('Cust: Ali Khan', $clearanceLedger->detail);
        $this->assertStringContainsString('Orig: Online/Bank Alfalah/Txn 18408074163', $clearanceLedger->detail);
        $this->assertStringContainsString('To: Cash Voucher/Customer Account', $clearanceLedger->detail);
        $this->assertStringContainsString('Date: 2026-06-03', $clearanceLedger->detail);
        $this->assertLessThanOrEqual(260, strlen($clearanceLedger->detail));
    }

    public function test_return_or_bounce_updates_history_without_creating_br(): void
    {
        $pending = $this->createPendingReceipt('PPR', 2, $this->project->id, 1800);

        $response = $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'payment_type' => 2,
            'amount' => '1800',
            't_number' => 'CLR-1800',
            'bank_id' => 1,
            'selected_pending_payment_id' => $pending->id,
            'pending_status' => 3,
            'passing_date' => '2026-05-22',
        ]));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'flow_type' => 'pending_payment_status_updated',
                'pending_status' => 3,
                'selected_pending_payment_id' => $pending->id,
            ]);
        $pending->refresh();

        $this->assertSame(3, (int) $pending->passing_status);
        $this->assertSame(1, count($pending->check_history ?? []));
        $this->assertDatabaseMissing('ledgers', [
            'customer_ledger_id' => $pending->id,
            'type' => 'CP',
            'reference' => 'CASH_OUT_CLEARANCE#' . $pending->id,
        ]);
        $pendingRowsAfterBounce = $this->callPendingBankPayments()->json('data');
        $pendingIdsAfterBounce = collect($pendingRowsAfterBounce)->pluck('id')->all();
        $this->assertNotContains($pending->id, $pendingIdsAfterBounce);
    }

    public function test_cross_project_selected_pending_payment_id_is_rejected(): void
    {
        $foreignPending = $this->createPendingReceipt('PPR', 2, $this->otherProject->id, 900);

        $response = $this->callLedgerStore($this->cashVoucherPayload([
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'payment_type' => 2,
            'amount' => '900',
            't_number' => 'CLR-0900',
            'bank_id' => 1,
            'selected_pending_payment_id' => $foreignPending->id,
            'pending_status' => 1,
            'passing_date' => '2026-05-23',
        ]));

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'pending_payment_invalid_state',
            ]);
    }

    public function test_pending_bank_payments_endpoint_returns_only_project_scoped_cr_and_ppr_rows(): void
    {
        $includedOne = $this->createPendingReceipt('CR', 2, $this->project->id, 1110);
        $includedTwo = $this->createPendingReceipt('PPR', 3, $this->project->id, 2220);
        $excludedType = $this->createPendingReceipt('CP', 2, $this->project->id, 3330);
        $excludedProject = $this->createPendingReceipt('CR', 2, $this->otherProject->id, 4440);

        $response = $this->callPendingBankPayments();

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'count' => 2,
            ]);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($includedOne->id, $ids);
        $this->assertContains($includedTwo->id, $ids);
        $this->assertNotContains($excludedType->id, $ids);
        $this->assertNotContains($excludedProject->id, $ids);
        $this->assertTrue((bool) collect($response->json('data'))->firstWhere('id', $includedOne->id)['ledger_exists']);
        $this->assertNotNull(collect($response->json('data'))->firstWhere('id', $includedOne->id)['ledger_id']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPprFinancialInputs')]
    public function test_ppr_rejects_invalid_financial_inputs_without_posting(array $override): void
    {
        $before = [
            'customer_ledger' => DB::table('customer_ledger')->count(),
            'ledgers' => DB::table('ledgers')->count(),
            'booking_vouchers' => DB::table('booking_vouchers')->count(),
        ];
        if (array_key_exists('reference', $override) && trim((string) $override['reference']) === '') {
            // Direct controller invocation bypasses Laravel's exception handler;
            // required-field validation throws before entering the posting branch.
            try {
                $this->callDeposit($this->depositPayload(1, $override));
                $this->fail('An empty PPR reference must fail request validation.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('reference', $exception->errors());
            }
        } else {
            $this->callDeposit($this->depositPayload(1, $override))
                ->assertStatus(302)->assertSessionHasErrors('msg');
        }
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function invalidPprFinancialInputs(): array
    {
        return [
            'negative' => [['amount' => '-100']],
            'zero' => [['amount' => '0']],
            'scientific' => [['amount' => '1e3']],
            'fractional precision' => [['amount' => '100.001']],
            'invalid date' => [['date' => '2026-02-30']],
            'relative date' => [['date' => 'tomorrow']],
            'empty reference' => [['reference' => '   ']],
        ];
    }

    public function test_ppr_fails_closed_when_selected_project_does_not_exist(): void
    {
        $before = [
            'customer_ledger' => DB::table('customer_ledger')->count(),
            'ledgers' => DB::table('ledgers')->count(),
            'booking_vouchers' => DB::table('booking_vouchers')->count(),
        ];
        $request = Request::create('/admin/booking/plot/voucher', 'POST', $this->depositPayload(1));
        $request->cookies->set('selected_action', '999999');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        TestResponse::fromBaseResponse(app(BookingController::class)->deposit($request))
            ->assertStatus(302)->assertSessionHasErrors('msg');
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_duplicate_ppr_same_source_key_with_different_transaction_number_is_rejected(): void
    {
        $firstResponse = $this->callDeposit($this->depositPayload(2));
        $firstResponse->assertStatus(302);

        $secondResponse = $this->callDeposit($this->depositPayload(2, [
            't_number' => 'TXN-999',
        ]));

        $secondResponse->assertStatus(302);
        $secondResponse->assertSessionHasErrors('msg');
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'PPR')->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pprVoucherDrift')]
    public function test_ppr_replay_rejects_booking_voucher_drift_without_mutating_records(array $changes): void
    {
        $payload = $this->depositPayload(1);
        $this->callDeposit($payload)->assertStatus(302);

        $source = CustomerLedger::where('transaction_type', 'PPR')->firstOrFail();
        $voucher = BookingVoucher::where('customer_ledger_id', $source->id)->firstOrFail();
        $ledger = Ledger::where('customer_ledger_id', $source->id)->where('type', 'PPR')->firstOrFail();
        $voucher->update($changes);
        $before = $voucher->fresh()->getAttributes();
        $ledgerBefore = $ledger->fresh()->getAttributes();

        $this->callDeposit($payload)->assertStatus(302)->assertSessionHasErrors('msg');

        $this->assertSame($before, $voucher->fresh()->getAttributes());
        $this->assertSame($ledgerBefore, $ledger->fresh()->getAttributes());
        $this->assertSame(1, BookingVoucher::where('customer_ledger_id', $source->id)->count());
        $this->assertSame(1, Ledger::where('customer_ledger_id', $source->id)->count());
    }

    public static function pprVoucherDrift(): array
    {
        return [
            'wrong project' => [['project_id' => 99999]],
            'wrong customer' => [['customer_id' => 99999]],
            'wrong plot' => [['plot_id' => 99999]],
            'wrong type' => [['voucher_series' => 'CR']],
            'inactive' => [['is_active' => 0]],
            'wrong source reference' => [['slip_reference' => 'OTHER']],
            'wrong source date' => [['receipt_date' => '2026-05-16']],
            'wrong amount' => [['amount' => 999]],
            'wrong ledger' => [['ledger_id' => 99999]],
            'wrong voucher number' => [['voucher_number' => 99999]],
            'wrong booking owner' => [['booking_id' => 99999]],
            'wrong payment method' => [['payment_type' => 3]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pprBankInstrumentDrift')]
    public function test_ppr_bank_replay_rejects_payment_instrument_drift(array $changes): void
    {
        $payload = $this->depositPayload(2);
        $this->callDeposit($payload)->assertStatus(302);
        $source = CustomerLedger::where('transaction_type', 'PPR')->firstOrFail();
        $voucher = BookingVoucher::where('customer_ledger_id', $source->id)->firstOrFail();
        $ledger = Ledger::where('customer_ledger_id', $source->id)->firstOrFail();
        $voucher->update($changes);
        $before = $voucher->fresh()->getAttributes();
        $ledgerBefore = $ledger->fresh()->getAttributes();

        $this->callDeposit($payload)->assertStatus(302)->assertSessionHasErrors('msg');

        $this->assertSame($before, $voucher->fresh()->getAttributes());
        $this->assertSame($ledgerBefore, $ledger->fresh()->getAttributes());
        $this->assertSame(1, BookingVoucher::where('customer_ledger_id', $source->id)->count());
        $this->assertSame(1, Ledger::where('customer_ledger_id', $source->id)->count());
    }

    public static function pprBankInstrumentDrift(): array
    {
        return [
            'bank account' => [['bank_id' => 999]],
            'transaction number' => [['t_number' => 'OTHER-TXN']],
            'passing date' => [['passing_date' => '2026-05-20']],
        ];
    }

    public function test_ppr_replay_rejects_duplicate_ledger_and_preserves_original(): void
    {
        $payload = $this->depositPayload(1);
        $this->callDeposit($payload)->assertStatus(302);

        $source = CustomerLedger::where('transaction_type', 'PPR')->firstOrFail();
        $original = Ledger::where('customer_ledger_id', $source->id)->where('type', 'PPR')->firstOrFail();
        $duplicate = $original->replicate();
        $duplicate->voucher_number = (int) $original->voucher_number + 1000;
        $duplicate->save();

        $this->callDeposit($payload)->assertStatus(302)->assertSessionHasErrors('msg');

        $this->assertSame(2, Ledger::where('type', 'PPR')->where('reference', $source->reference)->count());
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'PPR')->count());
        $this->assertSame((int) $original->voucher_number, (int) $original->fresh()->voucher_number);
    }

    public function test_ppr_replay_rejects_inactive_source(): void
    {
        $payload = $this->depositPayload(1);
        $this->callDeposit($payload)->assertStatus(302);

        $source = CustomerLedger::where('transaction_type', 'PPR')->firstOrFail();
        $source->update(['is_active' => 0]);

        $this->callDeposit($payload)->assertStatus(302)->assertSessionHasErrors('msg');

        $this->assertSame(0, (int) $source->fresh()->is_active);
        $this->assertSame(1, Ledger::where('customer_ledger_id', $source->id)->where('type', 'PPR')->count());
    }

    public function test_ppr_replay_rejects_mismatched_financial_amount(): void
    {
        $payload = $this->depositPayload(1);
        $this->callDeposit($payload)->assertStatus(302);

        $source = CustomerLedger::where('transaction_type', 'PPR')->firstOrFail();
        $ledger = Ledger::where('customer_ledger_id', $source->id)->where('type', 'PPR')->firstOrFail();
        $ledger->update(['amount_in' => 999.00]);

        $this->callDeposit($payload)->assertStatus(302)->assertSessionHasErrors('msg');

        $this->assertSame(999.0, (float) $ledger->fresh()->amount_in);
        $this->assertSame(1, CustomerLedger::where('transaction_type', 'PPR')->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLabourAttendanceIds')]
    public function test_labour_voucher_rejects_invalid_attendance_identity_before_posting(string $attendanceIds): void
    {
        $request = Request::create('/admin/labour/voucher/create', 'POST', [
            'site_id' => 1,
            'amount' => '2500.00',
            'attendance_ids' => $attendanceIds,
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(\App\Http\Controllers\LabourController::class)->createVoucher($request);
        TestResponse::fromBaseResponse($response)->assertStatus(422)
            ->assertJson(['error_key' => 'invalid_attendance_ids']);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public static function invalidLabourAttendanceIds(): array
    {
        return [
            'empty' => [''],
            'duplicate' => ['1,1'],
            'noninteger' => ['1,abc'],
            'zero' => ['0'],
            'trailing separator' => ['1,'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLabourMoney')]
    public function test_labour_voucher_rejects_noncanonical_money_before_posting(string $amount): void
    {
        $request = Request::create('/admin/labour/voucher/create', 'POST', [
            'site_id' => 1,
            'amount' => $amount,
            'attendance_ids' => '1',
            'detail' => '[{"name":"Test","days":1,"rate":100}]',
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        $response = $this->app->make(\App\Http\Controllers\LabourController::class)->createVoucher($request);
        TestResponse::fromBaseResponse($response)->assertStatus(422)->assertJson(['error_key' => 'invalid_amount']);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public static function invalidLabourMoney(): array
    {
        return [
            'scientific' => ['1e5'],
            'three decimals' => ['2.999'],
            'negative' => ['-100'],
            'overflow' => ['999999999999999999999'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLabourDetails')]
    public function test_labour_voucher_rejects_malformed_detail_before_posting(string $detail): void
    {
        $request = Request::create('/admin/labour/voucher/create', 'POST', [
            'site_id' => 1,
            'amount' => '100.00',
            'attendance_ids' => '1',
            'detail' => $detail,
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        $response = $this->app->make(\App\Http\Controllers\LabourController::class)->createVoucher($request);
        TestResponse::fromBaseResponse($response)->assertStatus(422)->assertJson(['error_key' => 'invalid_labour_detail']);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public static function invalidLabourDetails(): array
    {
        return [
            'invalid JSON' => ['not-json'],
            'empty' => ['[]'],
            'object' => ['{"name":"Test"}'],
            'missing fields' => ['[{"name":"Test"}]'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLabourPeriods')]
    public function test_labour_voucher_rejects_invalid_business_period_before_posting(string $startDate, string $endDate): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('site_name');
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->timestamps();
        });
        DB::table('sites')->insert([
            'id' => 1,
            'project_id' => $this->project->id,
            'site_name' => 'Test Site',
            'subhead_accounting_id' => $this->bookingReceiptAccount->id,
        ]);

        $request = Request::create('/admin/labour/voucher/create', 'POST', [
            'site_id' => 1,
            'amount' => '2500.00',
            'attendance_ids' => '1',
            'detail' => '[{"name":"Test","days":1,"rate":100}]',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = app(\App\Http\Controllers\LabourController::class)->createVoucher($request);
        TestResponse::fromBaseResponse($response)->assertStatus(422)
            ->assertJson(['error_key' => 'invalid_labour_period']);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
    }

    public static function invalidLabourPeriods(): array
    {
        return [
            'missing start' => ['', '2026-10-29'],
            'relative start' => ['tomorrow', '2026-10-29'],
            'invalid calendar day' => ['2026-02-30', '2026-03-01'],
            'reversed range' => ['2026-10-30', '2026-10-29'],
        ];
    }

    public function test_labour_jv_posts_balanced_header_details_and_ledger_then_blocks_replay(): void
    {
        $this->createLabourPostingFixtures();
        $payload = $this->labourPostingPayload();
        $first = $this->callLabourVoucher($payload);
        $first->assertOk()->assertJson(['success' => true]);

        $header = DB::table('journal_vouchers')->first();
        $this->assertNotNull($header);
        $details = DB::table('journal_voucher_details')->where('journal_voucher_id', $header->id)->get();
        $legs = DB::table('ledgers')->where('type', 'JV')->where('type_id', $header->id)->get();
        $this->assertCount(2, $details);
        $this->assertCount(2, $legs);
        $this->assertSame(250000, (int) round((float) $header->total_debit * 100));
        $this->assertSame(250000, (int) round((float) $header->total_credit * 100));
        $this->assertSame(250000, (int) round($details->sum('debit') * 100));
        $this->assertSame(250000, (int) round($details->sum('credit') * 100));
        $this->assertSame(250000, (int) round($legs->sum('amount_in') * 100));
        $this->assertSame(250000, (int) round($legs->sum('amount_out') * 100));
        foreach ($legs as $leg) {
            $this->assertSame((int) $header->voucher_number, (int) $leg->voucher_number);
            $this->assertSame((string) $header->date, (string) $leg->date);
            $this->assertContains((int) $leg->project_head_subheads_id, $details->pluck('account_id')->map(fn ($id) => (int) $id)->all());
        }
        $this->assertSame('created', (string) DB::table('labour_attendances')->value('voucher_status'));

        $replay = $this->callLabourVoucher($payload);
        $replay->assertStatus(422)->assertJson(['success' => false, 'error_key' => 'labour_financial_source_conflict']);
        $this->assertSame(1, DB::table('journal_vouchers')->count());
        $this->assertSame(2, DB::table('journal_voucher_details')->count());
        $this->assertSame(2, DB::table('ledgers')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('labourSourceConflicts')]
    public function test_labour_jv_rejects_source_conflicts_without_partial_posting(array $changes): void
    {
        $this->createLabourPostingFixtures();
        DB::table('labour_attendances')->where('id', 1)->update($changes);
        $response = $this->callLabourVoucher($this->labourPostingPayload());
        $response->assertStatus(422)->assertJson(['success' => false, 'error_key' => 'labour_financial_source_conflict']);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('journal_voucher_details')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function labourSourceConflicts(): array
    {
        return [
            'wrong amount' => [['amount' => '2400.00']],
            'wrong date' => [['date' => '2026-10-01']],
            'not present' => [['status' => 'absent']],
            'already created' => [['voucher_status' => 'created']],
        ];
    }

    private function createLabourPostingFixtures(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('site_name');
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->timestamps();
        });
        Schema::create('labour_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('site_id');
            $table->date('date');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('voucher_status')->nullable();
            $table->timestamps();
        });
        DB::table('head_accountings')->insert([
            'name' => 'Labour', 'acct_type' => 0, 'is_active' => 1, 'create_by' => $this->user->id,
        ]);
        DB::table('subhead_accountings')->insert([
            'name' => 'Labour Party', 'is_active' => 1, 'create_by' => $this->user->id,
        ]);
        DB::table('sites')->insert([
            'id' => 1, 'project_id' => $this->project->id, 'site_name' => 'Fixture Site',
            'subhead_accounting_id' => $this->bookingReceiptAccount->id,
        ]);
        DB::table('labour_attendances')->insert([
            'id' => 1, 'project_id' => $this->project->id, 'site_id' => 1,
            'date' => '2026-10-28', 'status' => 'present', 'amount' => '2500.00',
            'voucher_status' => 'pending',
        ]);
    }

    private function labourPostingPayload(): array
    {
        return [
            'site_id' => 1, 'attendance_ids' => '1', 'amount' => '2500.00',
            'detail' => '[{"name":"Fixture Labour","days":1,"rate":2500}]',
            'start_date' => '2026-10-28', 'end_date' => '2026-10-29',
        ];
    }

    private function callLabourVoucher(array $payload): TestResponse
    {
        $request = Request::create('/admin/labour/voucher/create', 'POST', $payload);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        return TestResponse::fromBaseResponse(
            app(\App\Http\Controllers\LabourController::class)->createVoucher($request)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('extraChargePaymentTypes')]
    public function test_ambiguous_extra_charge_does_not_create_any_financial_rows(int $paymentType): void
    {
        $tables = ['customer_ledger', 'ledgers', 'journal_vouchers', 'journal_voucher_details', 'booking_vouchers'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();

        $response = $this->callDeposit($this->depositPayload($paymentType, [
            'action' => 'extra_charge',
            'reference' => 'EXTRA-001',
            'voucher' => 'CR-999',
            'charge_type_id' => 1,
        ]));

        $response->assertStatus(302)->assertSessionHasErrors('msg');
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Unexpected extra-charge write in {$table}.");
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function extraChargePaymentTypes(): array
    {
        return ['cash' => [1], 'online' => [2], 'cheque' => [3]];
    }

    public function test_ppr_update_preserves_voucher_number_and_updates_compact_detail(): void
    {
        $mockUser = Mockery::mock($this->user)->makePartial();
        $mockUser->shouldReceive('cannot')->with('direct-update')->andReturnFalse();
        Auth::setUser($mockUser);

        $this->callDeposit($this->depositPayload(2, [
            'reference' => 'UPD-001',
            'detail' => 'Initial note',
            't_number' => 'TXN-INIT',
        ]))->assertStatus(302);

        $customerLedger = CustomerLedger::where('transaction_type', 'PPR')->firstOrFail();
        $ledger = Ledger::where('customer_ledger_id', $customerLedger->id)->where('type', 'PPR')->firstOrFail();
        $bookingVoucher = BookingVoucher::where('customer_ledger_id', $customerLedger->id)->firstOrFail();
        $originalLedgerVoucherNumber = (int) $ledger->voucher_number;
        $originalBookingVoucherNumber = (int) $bookingVoucher->voucher_number;

        $response = $this->callUpdateCashIn($customerLedger->id, [
            'reference' => 'UPD-001',
            'date' => '2026-05-19',
            'amount_out' => '2500',
            'description' => 'Updated note',
            'payment_type' => 3,
            't_number' => 'CHQ-12345',
            'bank_id' => 1,
            'passing_date' => '2026-05-20',
        ]);

        $response->assertStatus(302);

        $customerLedger->refresh();
        $ledger->refresh();
        $bookingVoucher->refresh();

        $this->assertSame($originalLedgerVoucherNumber, (int) $ledger->voucher_number);
        $this->assertSame($originalBookingVoucherNumber, (int) $bookingVoucher->voucher_number);
        $this->assertSame(1, Ledger::where('customer_ledger_id', $customerLedger->id)->where('type', 'PPR')->count());
        $this->assertStringContainsString('PPR |', $ledger->detail);
        $this->assertStringContainsString('Method: Cheque', $ledger->detail);
        $this->assertStringContainsString('Chq: CHQ-12345', $ledger->detail);
        $this->assertStringContainsString('Note: Updated note', $ledger->detail);
    }

    public function test_ppr_create_ignores_forged_project_id_and_uses_selected_town(): void
    {
        $response = $this->callDeposit($this->depositPayload(2, [
            'project_id' => $this->otherProject->id,
            'reference' => 'FORGED-001',
        ]));

        $response->assertStatus(302);

        $customerLedger = CustomerLedger::where('transaction_type', 'PPR')->where('reference', 'FORGED-001')->firstOrFail();
        $this->assertSame($this->project->id, (int) $customerLedger->project_id);
        $this->assertDatabaseMissing('customer_ledger', [
            'reference' => 'FORGED-001',
            'project_id' => $this->otherProject->id,
        ]);
    }

    public function test_ppr_update_rejects_cross_project_even_with_forged_project_id(): void
    {
        $foreignPlot = Plot::create([
            'name' => '202',
            'type' => '1',
            'size' => '5',
            'unit' => 'marla',
            'description' => 'Foreign Plot',
            'project_id' => $this->otherProject->id,
            'road_id' => 1,
            'facing_id' => 1,
            'is_active' => 1,
            'create_by' => $this->user->id,
            'amount' => 0,
        ]);

        $foreignLedger = CustomerLedger::create([
            'customer_id' => $this->customer->id,
            'project_id' => $this->otherProject->id,
            'plot_id' => $foreignPlot->id,
            'type_id' => 33,
            'reference' => 'FOREIGN-UPD',
            'payment_type' => 2,
            't_number' => 'TXN-FOREIGN',
            'bank_id' => 1,
            'passing_date' => '2026-05-18',
            'passing_status' => 0,
            'date' => '2026-05-17',
            'transaction_type' => 'PPR',
            'amount_in' => 0,
            'amount_out' => 1200,
            'description' => 'Foreign pending payment',
            'is_active' => 1,
            'is_approve' => 0,
        ]);

        Ledger::create([
            'customer_ledger_id' => $foreignLedger->id,
            'voucher_number' => 44,
            'type' => 'PPR',
            'type_id' => 44,
            'project_head_subheads_id' => ProjectHeadSubhead::create([
                'project_id' => $this->otherProject->id,
                'head_accounting_id' => 16,
                'subhead_accounting_id' => 20,
                'plot_id' => $foreignPlot->id,
                'customer_id' => $this->customer->id,
            ])->id,
            'reference' => 'FOREIGN-UPD',
            'amount_in' => 1200,
            'amount_out' => 0,
            'is_active' => 1,
            'date' => '2026-05-17',
            'detail' => 'Foreign detail',
            'update_by' => $this->user->id,
            'create_by' => $this->user->id,
            'status' => 0,
        ]);

        BookingVoucher::create([
            'booking_id' => null,
            'customer_ledger_id' => $foreignLedger->id,
            'ledger_id' => Ledger::latest('id')->value('id'),
            'project_id' => $this->otherProject->id,
            'customer_id' => $this->customer->id,
            'plot_id' => $foreignPlot->id,
            'voucher_series' => 'PPR',
            'voucher_number' => 44,
            'slip_reference' => 'FOREIGN-UPD',
            'payment_type' => 2,
            'amount' => 1200,
            'receipt_date' => '2026-05-17',
            'description' => 'Foreign pending payment',
            'bank_id' => 1,
            't_number' => 'TXN-FOREIGN',
            'passing_date' => '2026-05-18',
            'is_active' => 1,
            'is_approve' => 0,
            'create_by' => $this->user->id,
            'update_by' => $this->user->id,
        ]);

        $response = $this->callUpdateCashIn($foreignLedger->id, [
            'reference' => 'FOREIGN-UPD',
            'date' => '2026-05-20',
            'amount_out' => '1400',
            'description' => 'Should fail',
            'payment_type' => 2,
            't_number' => 'TXN-FOREIGN-2',
            'bank_id' => 1,
            'passing_date' => '2026-05-21',
            'project_id' => $this->project->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('msg');
        $foreignLedger->refresh();
        $this->assertSame('Foreign pending payment', $foreignLedger->description);
        $this->assertSame($this->otherProject->id, (int) $foreignLedger->project_id);
    }

    public function test_update_pending_bank_payment_status_rejects_non_online_check_payment_types(): void
    {
        $pending = $this->createPendingReceipt('CR', 1, $this->project->id, 1000);

        $response = $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 2,
            'note' => 'Status update attempt',
            'bank_post_at' => '2026-05-24',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'pending_payment_invalid_state',
            ]);
    }

    public function test_update_pending_bank_payment_status_stores_updated_by_in_check_history(): void
    {
        $pending = $this->createPendingReceipt('PPR', 2, $this->project->id, 1400);

        $response = $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 2,
            'note' => 'Manual clearance status update',
            'bank_post_at' => '2026-05-24',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $pending->refresh();
        $history = $pending->check_history ?? [];

        $this->assertCount(1, $history);
        $this->assertSame($this->user->id, $history[0]['updated_by']);
    }

    public function test_finance_clearance_pass_requires_business_date(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);

        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1,
            'note' => 'Missing passed date',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
            'subaccounts_id' => $this->cashVoucherAccount->subhead_accounting_id,
        ])->assertStatus(422)->assertJson(['error_key' => 'pending_payment_invalid_state']);

        $this->assertSame(0, (int) $pending->fresh()->passing_status);
        $this->assertDatabaseMissing('ledgers', ['type' => 'BR']);
    }

    public function test_finance_clearance_rejects_inactive_source_without_mutating(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);
        $pending->update(['is_active' => 0]);

        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1,
            'note' => 'Inactive source',
            'bank_post_at' => '2026-05-20',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
            'subaccounts_id' => $this->cashVoucherAccount->subhead_accounting_id,
        ])->assertStatus(422)->assertJson(['error_key' => 'pending_payment_invalid_state']);

        $this->assertSame(0, (int) $pending->fresh()->passing_status);
        $this->assertDatabaseMissing('ledgers', ['type' => 'BR']);
    }

    public function test_finance_clearance_posts_positive_br_for_cr_receipt(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);

        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1,
            'note' => 'Passed payment',
            'bank_post_at' => '2026-05-20',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
            'subaccounts_id' => $this->cashVoucherAccount->subhead_accounting_id,
        ])->assertOk()->assertJson(['status' => 'success']);

        $br = Ledger::where('type', 'BR')->firstOrFail();
        $this->assertSame($pending->id, (int) $br->customer_ledger_id);
        $this->assertSame(1500.0, (float) $br->amount_out);
        $this->assertSame(0.0, (float) $br->amount_in);
        $this->assertSame('2026-05-20', substr((string) $br->date, 0, 10));
    }

    public function test_finance_clearance_rejects_foreign_br_reference_collision(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);
        $other = $this->createPendingReceipt('PPR', 2, $this->project->id, 600);
        Ledger::create([
            'customer_ledger_id' => $other->id,
            'type' => 'BR',
            'type_id' => 999,
            'voucher_number' => 999,
            'project_head_subheads_id' => $this->cashVoucherAccount->id,
            'reference' => 'BANK_CLEARANCE#' . $pending->id,
            'amount_in' => 0,
            'amount_out' => 1500,
            'is_active' => 1,
            'date' => '2026-05-20',
        ]);

        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1,
            'note' => 'Collision',
            'bank_post_at' => '2026-05-20',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
            'subaccounts_id' => $this->cashVoucherAccount->subhead_accounting_id,
        ])->assertStatus(422)->assertJson(['error_key' => 'pending_payment_invalid_state']);

        $this->assertSame(0, (int) $pending->fresh()->passing_status);
        $this->assertSame(1, Ledger::where('type', 'BR')->count());
    }

    public function test_finance_clearance_rejects_foreign_project_fallback_account(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);

        $foreignAccount = ProjectHeadSubhead::create([
            'project_id' => $this->otherProject->id,
            'head_accounting_id' => $this->cashVoucherAccount->head_accounting_id,
            'subhead_accounting_id' => $this->cashVoucherAccount->subhead_accounting_id,
        ]);

        Ledger::where('customer_ledger_id', $pending->id)
            ->update(['project_head_subheads_id' => $foreignAccount->id]);

        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1,
            'note' => 'Fallback project collision',
            'bank_post_at' => '2026-05-20',
        ])->assertStatus(422)->assertJson(['error_key' => 'pending_payment_invalid_state']);

        $this->assertSame(0, (int) $pending->fresh()->passing_status);
        $this->assertDatabaseMissing('ledgers', ['type' => 'BR']);
    }

    public function test_finance_clearance_accepts_valid_same_project_fallback_account(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);

        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1,
            'note' => 'Same project fallback',
            'bank_post_at' => '2026-05-20',
        ])->assertOk();

        $this->assertSame($this->cashVoucherAccount->id,
            (int) Ledger::where('type', 'BR')->firstOrFail()->project_head_subheads_id);
    }

    public function test_finance_clearance_rejects_invalid_explicit_account_without_fallback(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);
        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1, 'note' => 'Invalid account',
            'bank_post_at' => '2026-05-20',
            'accounts_id' => 999999, 'subaccounts_id' => 999999,
        ])->assertStatus(422)->assertJson(['error_key' => 'pending_payment_invalid_state']);
        $this->assertSame(0, (int) $pending->fresh()->passing_status);
        $this->assertDatabaseMissing('ledgers', ['type' => 'BR']);
    }

    public function test_finance_clearance_rejects_partial_explicit_account_without_fallback(): void
    {
        $pending = $this->createPendingReceipt('CR', 2, $this->project->id, 1500);
        $this->callUpdatePendingBankPaymentStatus($pending->id, [
            'passing_status' => 1, 'note' => 'Partial account',
            'bank_post_at' => '2026-05-20',
            'accounts_id' => $this->cashVoucherAccount->head_accounting_id,
        ])->assertStatus(422)->assertJson(['error_key' => 'pending_payment_invalid_state']);
        $this->assertSame(0, (int) $pending->fresh()->passing_status);
    }

    public static function pendingReceiptPaymentTypesProvider(): array
    {
        return [
            'online' => [2],
            'check' => [3],
        ];
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('address')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('1');
            $table->string('size')->default('5');
            $table->string('unit')->default('marla');
            $table->text('description')->nullable();
            $table->integer('project_id');
            $table->integer('road_id')->default(1);
            $table->integer('facing_id')->default(1);
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('sold')->default(false);
            $table->longText('plot_history')->nullable();
            $table->timestamps();
        });

        Schema::create('head_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('acct_type')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('subhead_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('urdu_name')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->string('cnic')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('head_accounting_id')->nullable();
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->integer('customer_id');
            $table->integer('plot_id');
            $table->tinyInteger('plot_type')->default(0);
            $table->string('plot_size')->default('5 marla');
            $table->decimal('plot_rate', 10, 2)->default(0);
            $table->integer('is_corner')->default(0);
            $table->integer('is_park')->default(0);
            $table->decimal('park_facing', 10, 2)->default(0);
            $table->decimal('carner_price', 10, 2)->default(0);
            $table->decimal('dicount_value', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->integer('broker_id')->default(1);
            $table->timestamp('booking_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('user_id');
            $table->enum('cancel_status', ['0', '1'])->default('0');
            $table->longText('reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedTinyInteger('payment_type')->nullable();
            $table->string('t_number')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->date('passing_date')->nullable();
            $table->unsignedTinyInteger('passing_status')->nullable();
            $table->json('check_history')->nullable();
            $table->date('date')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('amount_in', 12, 2)->default(0);
            $table->decimal('amount_out', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approve')->default(false);
            $table->text('note')->nullable();
            $table->date('bank_post_at')->nullable();
            $table->string('delete_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('voucher_number');
            $table->string('type');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->date('date');
            $table->decimal('total_debit', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_voucher_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->integer('type_id')->nullable();
            $table->unsignedBigInteger('project_head_subheads_id')->nullable();
            $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('amount_in', 12, 2)->default(0);
            $table->decimal('amount_out', 12, 2)->default(0);
            $table->text('detail')->nullable();
            $table->integer('create_by')->nullable();
            $table->integer('update_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
            $table->date('date')->nullable();
            $table->unsignedInteger('voucher_number')->nullable();
            $table->string('delete_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('customer_ledger_id')->nullable()->unique();
            $table->unsignedBigInteger('ledger_id')->nullable()->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->string('voucher_series', 20)->nullable();
            $table->unsignedInteger('voucher_number')->nullable();
            $table->string('slip_reference')->nullable();
            $table->unsignedTinyInteger('payment_type')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('receipt_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('t_number')->nullable();
            $table->date('passing_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approve')->default(false);
            $table->unsignedBigInteger('create_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->timestamps();
        });

        Schema::create('draft_ledgers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    private function seedBaseData(): void
    {
        $this->user = User::create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->project = Project::create([
            'project' => 'Main Project',
            'address' => 'Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);

        $this->otherProject = Project::create([
            'project' => 'Other Project',
            'address' => 'Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);

        $this->customer = Lead::create([
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'phone_number' => '03000000000',
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);

        $this->plot = Plot::create([
            'name' => '101',
            'type' => '1',
            'size' => '5',
            'unit' => 'marla',
            'description' => 'Plot',
            'project_id' => $this->project->id,
            'road_id' => 1,
            'facing_id' => 1,
            'is_active' => 1,
            'create_by' => $this->user->id,
            'amount' => 0,
        ]);

        DB::table('head_accountings')->insert([
            ['id' => 16, 'name' => 'Booking Receipt', 'acct_type' => 1, 'is_active' => 1, 'create_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Cash Voucher', 'acct_type' => 1, 'is_active' => 1, 'create_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('subhead_accountings')->insert([
            'id' => 20,
            'name' => 'Customer Account',
            'is_active' => 1,
            'create_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bookingReceiptAccount = ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 16,
            'subhead_accounting_id' => 20,
            'plot_id' => $this->plot->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->cashVoucherAccount = ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 10,
            'subhead_accounting_id' => 20,
            'plot_id' => $this->plot->id,
            'customer_id' => $this->customer->id,
        ]);

        Booking::create([
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id,
            'plot_type' => 1,
            'plot_size' => '5 marla',
            'plot_rate' => 1000,
            'is_corner' => 0,
            'is_park' => 0,
            'park_facing' => 0,
            'carner_price' => 0,
            'total_price' => 500000,
            'broker_id' => 1,
            'booking_date' => now(),
            'status' => 'active',
            'user_id' => $this->user->id,
            'cancel_status' => '0',
        ]);
    }

    private function depositPayload(int $paymentType, array $overrides = []): array
    {
        return array_merge([
            'action' => 'deposit',
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'plot_id' => $this->plot->id,
            'amount' => '1250',
            'detail' => 'Booking installment',
            'payment_type' => $paymentType,
            'reference' => 'REF-001',
            'date' => '2026-05-17',
            't_number' => $paymentType === 1 ? null : 'TXN-001',
            'bank_id' => $paymentType === 1 ? null : 1,
            'passing_date' => $paymentType === 1 ? null : '2026-05-18',
        ], $overrides);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('manualCashSourceReplayTypes')]
    public function test_manual_cash_voucher_replay_preserves_single_financial_source(string $series): void
    {
        $payload = $this->cashVoucherPayload([
            'voucher' => $series . '-1',
            'voucher_number' => $series . '-1',
            'reference' => 'REPLAY-' . $series,
            'payment_type' => 1,
        ]);
        $first = $this->callLedgerStore($payload);
        $first->assertOk()->assertJson(['status' => 'success']);
        $beforeSource = DB::table('customer_ledger')->get()->toArray();
        $beforeLedgers = DB::table('ledgers')->get()->toArray();

        $second = $this->callLedgerStore($payload);
        $this->assertNotSame('success', $second->json('status'));
        $this->assertEquals($beforeSource, DB::table('customer_ledger')->get()->toArray());
        $this->assertEquals($beforeLedgers, DB::table('ledgers')->get()->toArray());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function manualCashSourceReplayTypes(): array
    {
        return ['cash receipt' => ['CR'], 'cash payment' => ['CP']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidManualCashVoucherMoney')]
    public function test_manual_cash_voucher_rejects_invalid_money_without_financial_rows(string $amount): void
    {
        $beforeLedgers = DB::table('ledgers')->count();
        $beforeSources = DB::table('customer_ledger')->count();
        $this->callLedgerStore($this->cashVoucherPayload(['amount' => $amount]))
            ->assertStatus(422)->assertJson(['error_key' => 'validation_error']);
        $this->assertSame($beforeLedgers, DB::table('ledgers')->count());
        $this->assertSame($beforeSources, DB::table('customer_ledger')->count());
    }

    public static function invalidManualCashVoucherMoney(): array
    {
        return [
            'negative' => ['-10'],
            'zero' => ['0'],
            'scientific' => ['1e3'],
            'three decimals' => ['123.456'],
            'overflow' => ['999999999999999999999'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidManualCashVoucherIdentity')]
    public function test_manual_cash_voucher_rejects_mismatched_identity_without_financial_rows(array $override): void
    {
        $before = [
            'ledgers' => DB::table('ledgers')->count(),
            'customer_ledger' => DB::table('customer_ledger')->count(),
        ];
        $this->callLedgerStore($this->cashVoucherPayload($override))
            ->assertStatus(422)->assertJson(['error_key' => 'invalid_voucher_number']);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function invalidManualCashVoucherIdentity(): array
    {
        return [
            'different sequence' => [['voucher' => 'CR-1', 'voucher_number' => 'CR-2']],
            'different series' => [['voucher' => 'CR-1', 'voucher_number' => 'CP-1']],
            'zero series number' => [['voucher' => 'CR-0', 'voucher_number' => 'CR-0']],
        ];
    }

    private function cashVoucherPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => '1000',
            'detail' => 'General voucher detail',
            'accounts_id' => 10,
            'subaccounts_id' => 20,
            'payment_type' => 1,
            'date' => '2026-05-17',
            'voucher' => 'CR-1',
            'voucher_number' => 'CR-1',
            'reference' => 'CR-REF-001',
        ], $overrides);
    }

    private function createPendingReceipt(string $transactionType, int $paymentType, int $projectId, float $amount, array $overrides = []): CustomerLedger
    {
        $customerLedger = CustomerLedger::create([
            'customer_id' => $this->customer->id,
            'project_id' => $projectId,
            'plot_id' => $this->plot->id,
            'type_id' => $overrides['type_id'] ?? 1,
            'reference' => $overrides['reference'] ?? (strtoupper($transactionType) . '-REF-' . $this->faker->unique()->numerify('###')),
            'payment_type' => $paymentType,
            't_number' => $overrides['transaction_number'] ?? 'CHK-' . $this->faker->unique()->numerify('###'),
            'bank_id' => $overrides['bank_id'] ?? 1,
            'passing_date' => $overrides['passing_date'] ?? '2026-05-18',
            'passing_status' => $overrides['passing_status'] ?? 0,
            'date' => $overrides['receipt_date'] ?? '2026-05-17',
            'transaction_type' => $transactionType,
            'amount_in' => $transactionType === 'CR' ? $amount : 0,
            'amount_out' => $transactionType === 'PPR' ? $amount : 0,
            'description' => $overrides['detail'] ?? 'Pending payment',
            'is_active' => 1,
            'is_approve' => 0,
        ]);

        $projectHeadSubheadId = $projectId === $this->project->id
            ? $this->cashVoucherAccount->id
            : ProjectHeadSubhead::create([
                'project_id' => $projectId,
                'head_accounting_id' => 10,
                'subhead_accounting_id' => 20,
                'plot_id' => $this->plot->id,
                'customer_id' => $this->customer->id,
            ])->id;

        Ledger::create([
            'customer_ledger_id' => $customerLedger->id,
            'voucher_number' => $overrides['voucher_number'] ?? (Ledger::count() + 1),
            'type' => $transactionType,
            'type_id' => Ledger::count() + 1,
            'project_head_subheads_id' => $projectHeadSubheadId,
            'reference' => $customerLedger->reference,
            'amount_in' => $customerLedger->amount_in,
            'amount_out' => $customerLedger->amount_out,
            'is_active' => 1,
            'date' => $customerLedger->date,
            'detail' => $overrides['ledger_detail'] ?? 'Pending detail',
            'update_by' => $this->user->id,
            'create_by' => $this->user->id,
            'status' => 0,
        ]);

        if ($transactionType === 'PPR') {
            BookingVoucher::create([
                'booking_id' => 1,
                'customer_ledger_id' => $customerLedger->id,
                'ledger_id' => Ledger::latest('id')->value('id'),
                'project_id' => $projectId,
                'customer_id' => $this->customer->id,
                'plot_id' => $this->plot->id,
                'voucher_series' => 'PPR',
                'voucher_number' => $overrides['voucher_number'] ?? 1,
                'slip_reference' => $customerLedger->reference,
                'payment_type' => $paymentType,
                'amount' => $amount,
                'receipt_date' => $customerLedger->date,
                'description' => $overrides['detail'] ?? 'Pending receipt',
                'bank_id' => $overrides['bank_id'] ?? 1,
                't_number' => $customerLedger->t_number,
                'passing_date' => $customerLedger->passing_date,
                'is_active' => 1,
                'is_approve' => 0,
                'create_by' => $this->user->id,
                'update_by' => $this->user->id,
            ]);
        }

        return $customerLedger->fresh();
    }

    private function callDeposit(array $payload): TestResponse
    {
        $request = Request::create('/admin/booking/plot/voucher', 'POST', $payload);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(BookingController::class)->deposit($request);

        return TestResponse::fromBaseResponse($response);
    }

    private function callUpdateCashIn(int $id, array $payload, ?int $selectedProjectId = null): TestResponse
    {
        $request = Request::create('/admin/booking/plot/voucher/update/' . $id, 'POST', $payload);
        $request->cookies->set('selected_action', (string) ($selectedProjectId ?? $this->project->id));
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(BookingController::class)->updateCashIn($request, $id);

        return TestResponse::fromBaseResponse($response);
    }

    private function callLedgerStore(array $payload, ?int $selectedProjectId = null): TestResponse
    {
        $request = Request::create('/admin/accounting/ledger/store', 'POST', $payload);
        $request->cookies->set('selected_action', (string) ($selectedProjectId ?? $this->project->id));
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(LedgerController::class)->store($request);

        return TestResponse::fromBaseResponse($response);
    }

    private function callPendingBankPayments(?int $selectedProjectId = null): TestResponse
    {
        $request = Request::create('/admin/finance/voucher/pending-bank-payments', 'GET');
        $request->cookies->set('selected_action', (string) ($selectedProjectId ?? $this->project->id));
        $request->headers->set('Accept', 'application/json');
        $this->app->instance('request', $request);

        $response = $this->app->make(VoucherController::class)->pendingBankPayments($request);

        return TestResponse::fromBaseResponse($response);
    }

    private function callUpdatePendingBankPaymentStatus(int $customerLedgerId, array $payload, ?int $selectedProjectId = null): TestResponse
    {
        $request = Request::create('/admin/finance/voucher/pending-bank-payments/' . $customerLedgerId . '/status', 'POST', $payload);
        $request->cookies->set('selected_action', (string) ($selectedProjectId ?? $this->project->id));
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        try {
            $response = $this->app->make(VoucherController::class)->updatePendingBankPaymentStatus($request, $customerLedgerId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = response()->json([
                'status' => 'error',
                'error_key' => 'not_found',
                'message' => 'Pending payment not found.',
            ], 404);
        }

        return TestResponse::fromBaseResponse($response);
    }
}
