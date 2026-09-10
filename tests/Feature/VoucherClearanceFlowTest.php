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
            'status' => 1,
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

    /**
     * @dataProvider pendingReceiptPaymentTypesProvider
     */
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

    /**
     * @dataProvider pendingReceiptPaymentTypesProvider
     */
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
