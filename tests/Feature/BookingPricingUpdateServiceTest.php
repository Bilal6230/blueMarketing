<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\Booking\BookingPricingUpdateService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingPricingUpdateServiceTest extends TestCase
{
    private Booking $booking;
    private int $voucherId;
    private int $customerPivotId;
    private int $totalSalePivotId;
    private int $customerLedgerId;
    private int $customerDetailId;
    private int $totalSaleDetailId;
    private int $customerLedgerLineId;
    private int $totalSaleLedgerLineId;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->seedPristine();
    }

    /** @dataProvider pricingChanges */
    public function test_successful_pricing_changes_sync_every_principal_representation(array $pricing, string $expected): void
    {
        $beforeIds = $this->ids();
        $voucherNumber = DB::table('journal_vouchers')->where('id', $this->voucherId)->value('voucher_number');

        $result = $this->service()->updatePristineBooking($this->booking->id, 10, $this->snapshot(), $pricing, 99);

        $this->assertTrue($result->changed);
        $this->assertPrincipal($expected);
        $this->assertSame($beforeIds, $this->ids());
        $this->assertSame($voucherNumber, DB::table('journal_vouchers')->where('id', $this->voucherId)->value('voucher_number'));
        $this->assertSame($this->voucherId, (int) DB::table('journal_vouchers')->value('id'));
        $this->assertIdentityUnchanged();
        $audit = DB::table('booking_edit_audits')->sole();
        $this->assertSame('pricing_update', $audit->operation);
        $this->assertSame(99, (int) $audit->user_id);
        $this->assertSame($this->voucherId, (int) $audit->journal_voucher_id);
        $this->assertSame(0, bccomp((string) $audit->old_total, '5000000.00', 2));
        $this->assertSame(0, bccomp((string) $audit->new_total, $expected, 2));
        $this->assertSame(0, bccomp((string) $audit->delta, bcsub($expected, '5000000.00', 2), 2));
        $this->assertSame(0, bccomp((string) $audit->old_accounting_principal, '5000000.00', 2));
        $this->assertSame('5000000.00', json_decode($audit->old_values, true)['total_price']);
        $this->assertSame($expected, json_decode($audit->new_values, true)['total_price']);
    }

    public function pricingChanges(): array
    {
        return [
            'rate only' => [['plot_rate' => '510000', 'is_park' => 0, 'park_facing' => '0', 'is_corner' => 0, 'carner_price' => '0', 'dicount_value' => '0'], '5100000.00'],
            'discount only' => [['plot_rate' => '500000', 'is_park' => 0, 'park_facing' => '0', 'is_corner' => 0, 'carner_price' => '0', 'dicount_value' => '25000'], '4975000.00'],
            'park only' => [['plot_rate' => '500000', 'is_park' => 1, 'park_facing' => '100000', 'is_corner' => 0, 'carner_price' => '0', 'dicount_value' => '0'], '5100000.00'],
            'corner only' => [['plot_rate' => '500000', 'is_park' => 0, 'park_facing' => '0', 'is_corner' => 1, 'carner_price' => '75000', 'dicount_value' => '0'], '5075000.00'],
            'combined' => [['plot_rate' => '500000', 'is_park' => 1, 'park_facing' => '100000', 'is_corner' => 1, 'carner_price' => '75000', 'dicount_value' => '25000'], '5150000.00'],
        ];
    }

    public function test_original_bug_shape_repairs_coherent_stale_accounting(): void
    {
        $this->setBookingPricing('1092750', '10927500');
        $this->setAccountingPrincipal('11625000');
        $snapshot = $this->snapshot();
        $beforeIds = $this->ids();

        $result = $this->service()->updatePristineBooking($this->booking->id, 10, $snapshot, [
            'plot_rate' => '1092750', 'is_park' => 0, 'park_facing' => '0',
            'is_corner' => 0, 'carner_price' => '0', 'dicount_value' => '0',
        ]);

        $this->assertTrue($result->changed);
        $this->assertSame('10927500.00', $result->oldBookingTotal);
        $this->assertSame('11625000.00', $result->oldAccountingPrincipal);
        $this->assertPrincipal('10927500.00');
        $this->assertSame($beforeIds, $this->ids());
        $audit = DB::table('booking_edit_audits')->sole();
        $this->assertSame('pricing_accounting_repair', $audit->operation);
        $this->assertSame(0, bccomp((string) $audit->old_total, '10927500.00', 2));
        $this->assertSame(0, bccomp((string) $audit->old_accounting_principal, '11625000.00', 2));
    }

    public function test_internally_inconsistent_accounting_blocks_without_mutation(): void
    {
        $this->setAccountingPrincipal('11625000');
        DB::table('journal_voucher_details')->where('id', $this->customerDetailId)->update(['credit' => '11000000']);
        $before = $this->allRows();

        $this->expectBlocked('ACCOUNTING_PRINCIPAL_INCONSISTENT');

        $this->assertSame($before, $this->allRows());
    }

    public function test_matching_booking_and_accounting_is_idempotent_no_op(): void
    {
        $before = $this->allRows();
        $updatedAt = $this->booking->updated_at;
        $result = $this->service()->updatePristineBooking($this->booking->id, 10, $this->snapshot(), $this->defaultPricing());

        $this->assertFalse($result->changed);
        $this->assertSame($before, $this->allRows());
        $this->assertEquals($updatedAt, $this->booking->fresh()->updated_at);
        $this->assertSame(0, DB::table('booking_edit_audits')->count());
    }

    /** @dataProvider lifecycleBlocks */
    public function test_lifecycle_and_voucher_status_blocks_without_mutation(string $case, string $code): void
    {
        $this->applyBlock($case);
        $before = $this->allRows();
        $this->expectBlocked($code);
        $this->assertSame($before, $this->allRows());
    }

    public function lifecycleBlocks(): array
    {
        return [
            'schedule' => ['schedule', 'SCHEDULE_EXISTS'],
            'primary payment' => ['primary_payment', 'PAYMENT_ACTIVITY_EXISTS'],
            'legacy payment' => ['legacy_payment', 'PAYMENT_ACTIVITY_EXISTS'],
            'transfer' => ['transfer', 'TRANSFER_HISTORY_EXISTS'],
            'resale' => ['resale', 'RESALE_HISTORY_EXISTS'],
            'approved' => ['approved', 'VOUCHER_NOT_PENDING'],
            'rejected' => ['rejected', 'VOUCHER_NOT_PENDING'],
            'cancelled' => ['cancelled', 'Illuminate\\Database\\Eloquent\\ModelNotFoundException'],
            'deleted' => ['deleted', 'Illuminate\\Database\\Eloquent\\ModelNotFoundException'],
            'unexpected detail' => ['unexpected_detail', 'UNEXPECTED_VOUCHER_LINES'],
        ];
    }

    /** @dataProvider accountingShapeFailures */
    public function test_missing_and_duplicate_accounting_shapes_block_without_repair(string $case, string $code): void
    {
        $this->applyShapeFailure($case);
        $before = $this->allRows();
        $this->expectBlocked($code);
        $this->assertSame($before, $this->allRows());
    }

    public function accountingShapeFailures(): array
    {
        return [
            'missing voucher' => ['missing_voucher', 'ORIGINAL_VOUCHER_MISSING'],
            'duplicate voucher' => ['duplicate_voucher', 'ORIGINAL_VOUCHER_DUPLICATE'],
            'missing customer pivot' => ['missing_customer_pivot', 'CUSTOMER_PIVOT_MISSING'],
            'duplicate customer pivot' => ['duplicate_customer_pivot', 'CUSTOMER_PIVOT_DUPLICATE'],
            'missing sale pivot' => ['missing_sale_pivot', 'TOTAL_SALE_PIVOT_MISSING'],
            'duplicate sale pivot' => ['duplicate_sale_pivot', 'TOTAL_SALE_PIVOT_DUPLICATE'],
            'missing customer detail' => ['missing_customer_detail', 'CUSTOMER_DETAIL_MISSING'],
            'duplicate customer detail' => ['duplicate_customer_detail', 'CUSTOMER_DETAIL_DUPLICATE'],
            'missing sale detail' => ['missing_sale_detail', 'TOTAL_SALE_DETAIL_MISSING'],
            'duplicate sale detail' => ['duplicate_sale_detail', 'TOTAL_SALE_DETAIL_DUPLICATE'],
            'missing customer ledger' => ['missing_customer_ledger', 'CUSTOMER_LEDGER_LINE_MISSING'],
            'duplicate customer ledger' => ['duplicate_customer_ledger', 'CUSTOMER_LEDGER_LINE_DUPLICATE'],
            'missing sale ledger' => ['missing_sale_ledger', 'TOTAL_SALE_LEDGER_LINE_MISSING'],
            'duplicate sale ledger' => ['duplicate_sale_ledger', 'TOTAL_SALE_LEDGER_LINE_DUPLICATE'],
            'invalid initial ledger' => ['invalid_initial_ledger', 'INITIAL_CUSTOMER_LEDGER_INVALID'],
            'unexpected ledger' => ['unexpected_ledger', 'UNEXPECTED_VOUCHER_LINES'],
            'invalid principal direction' => ['invalid_direction', 'ACCOUNTING_DIRECTION_INVALID'],
            'voucher header mismatch' => ['header_mismatch', 'VOUCHER_UNBALANCED'],
            'shared initial ledger' => ['shared_initial_ledger', 'INITIAL_CUSTOMER_LEDGER_INVALID'],
        ];
    }

    public function test_stale_booking_pricing_snapshot_blocks_without_mutation(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['expected_total_price'] = '4999999';
        $before = $this->allRows();
        try {
            $this->service()->updatePristineBooking($this->booking->id, 10, $snapshot, $this->defaultPricing());
            $this->fail('Expected stale pricing to block.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_BOOKING_PRICING', $exception->getMessage());
        }
        $this->assertSame($before, $this->allRows());
    }

    public function test_post_update_invariant_failure_rolls_back_financial_rows_and_audit(): void
    {
        DB::statement('CREATE TRIGGER corrupt_updated_voucher AFTER UPDATE ON journal_vouchers BEGIN UPDATE journal_vouchers SET total_debit = 0 WHERE id = NEW.id; END');
        $before = $this->allRows();
        try {
            $this->service()->updatePristineBooking($this->booking->id, 10, $this->snapshot(), array_merge($this->defaultPricing(), ['plot_rate' => '510000']));
            $this->fail('Expected post-update invariant failure.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('POST_UPDATE_VOUCHER_UNBALANCED', $exception->getMessage());
        }
        $this->assertSame($before, $this->allRows());
    }

    private function expectBlocked(string $code): void
    {
        try {
            $this->service()->updatePristineBooking($this->booking->id, 10, $this->snapshot(), array_merge($this->defaultPricing(), ['plot_rate' => '510000']));
            $this->fail('Expected synchronization to block.');
        } catch (\Throwable $exception) {
            if (str_starts_with($code, 'Illuminate\\')) {
                $this->assertInstanceOf($code, $exception);
            } else {
                $this->assertSame($code, $exception->getMessage());
            }
        }
    }

    private function applyBlock(string $case): void
    {
        match ($case) {
            'schedule' => DB::table('booking_details')->insert(['booking_id' => $this->booking->id, 'amount' => 1, 'due_date' => '2026-10-01']),
            'primary_payment' => DB::table('booking_vouchers')->insert(['booking_id' => $this->booking->id, 'voucher_series' => 'PPR']),
            'legacy_payment' => DB::table('customer_ledger')->insert(['customer_id' => 20, 'project_id' => 10, 'plot_id' => 30, 'transaction_type' => 'PPR', 'amount_in' => 0, 'amount_out' => 1]),
            'transfer' => DB::table('journal_vouchers')->insert($this->voucherRow('SV-T', 'TRANSFER-' . $this->booking->id, 'pending')),
            'resale' => DB::table('ledgers')->insert(array_merge($this->ledgerRow(999, 999, 999, 1, 0), ['detail' => 'RESALE_PROFIT#' . $this->booking->id])),
            'approved', 'rejected' => DB::table('journal_vouchers')->where('id', $this->voucherId)->update(['status' => $case]),
            'cancelled' => DB::table('bookings')->where('id', $this->booking->id)->update(['cancel_status' => '1']),
            'deleted' => $this->booking->delete(),
            'unexpected_detail' => DB::table('journal_voucher_details')->insert($this->detailRow($this->voucherId, 999, 1, 1)),
        };
    }

    private function applyShapeFailure(string $case): void
    {
        switch ($case) {
            case 'missing_voucher': DB::table('journal_vouchers')->where('id', $this->voucherId)->delete(); break;
            case 'duplicate_voucher': DB::table('journal_vouchers')->insert($this->voucherRow('SV-2', 'BOOKING-' . $this->booking->id, 'pending')); break;
            case 'missing_customer_pivot': DB::table('project_head_subheads')->where('id', $this->customerPivotId)->delete(); break;
            case 'duplicate_customer_pivot': DB::table('project_head_subheads')->insert($this->customerPivotRow()); break;
            case 'missing_sale_pivot': DB::table('project_head_subheads')->where('id', $this->totalSalePivotId)->delete(); break;
            case 'duplicate_sale_pivot': DB::table('project_head_subheads')->insert($this->totalSalePivotRow()); break;
            case 'missing_customer_detail': DB::table('journal_voucher_details')->where('id', $this->customerDetailId)->delete(); break;
            case 'duplicate_customer_detail': DB::table('journal_voucher_details')->insert($this->detailRow($this->voucherId, $this->customerPivotId, 0, 5000000)); break;
            case 'missing_sale_detail': DB::table('journal_voucher_details')->where('id', $this->totalSaleDetailId)->delete(); break;
            case 'duplicate_sale_detail': DB::table('journal_voucher_details')->insert($this->detailRow($this->voucherId, $this->totalSalePivotId, 5000000, 0)); break;
            case 'missing_customer_ledger': DB::table('ledgers')->where('id', $this->customerLedgerLineId)->delete(); break;
            case 'duplicate_customer_ledger': DB::table('ledgers')->insert($this->ledgerRow($this->customerPivotId, $this->customerLedgerId, $this->voucherId, 0, 5000000)); break;
            case 'missing_sale_ledger': DB::table('ledgers')->where('id', $this->totalSaleLedgerLineId)->delete(); break;
            case 'duplicate_sale_ledger': DB::table('ledgers')->insert($this->ledgerRow($this->totalSalePivotId, $this->customerLedgerId, $this->voucherId, 5000000, 0)); break;
            case 'invalid_initial_ledger': DB::table('customer_ledger')->where('id', $this->customerLedgerId)->update(['customer_id' => 999]); break;
            case 'unexpected_ledger': DB::table('ledgers')->insert($this->ledgerRow(999, 999, $this->voucherId, 1, 1)); break;
            case 'invalid_direction': DB::table('journal_voucher_details')->where('id', $this->customerDetailId)->update(['debit' => 1]); break;
            case 'header_mismatch': DB::table('journal_vouchers')->where('id', $this->voucherId)->update(['total_debit' => 4999999]); break;
            case 'shared_initial_ledger': DB::table('ledgers')->insert($this->ledgerRow(999, $this->customerLedgerId, 999, 1, 0)); break;
        }
    }

    private function service(): BookingPricingUpdateService { return app(BookingPricingUpdateService::class); }
    private function defaultPricing(): array { return ['plot_rate' => '500000', 'is_park' => 0, 'park_facing' => '0', 'is_corner' => 0, 'carner_price' => '0', 'dicount_value' => '0']; }
    private function snapshot(): array { $b = Booking::withTrashed()->findOrFail($this->booking->id); return ['expected_plot_rate' => (string) $b->plot_rate, 'expected_is_park' => (int) $b->is_park, 'expected_park_facing' => (string) $b->park_facing, 'expected_is_corner' => (int) $b->is_corner, 'expected_carner_price' => (string) $b->carner_price, 'expected_dicount_value' => (string) $b->dicount_value, 'expected_total_price' => (string) $b->total_price]; }
    private function protectedTables(): array { return ['bookings', 'customer_ledger', 'journal_vouchers', 'journal_voucher_details', 'ledgers', 'project_head_subheads', 'booking_details', 'booking_vouchers', 'booking_edit_audits']; }
    private function allRows(): array { return collect($this->protectedTables())->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all(); }
    private function ids(): array { return collect(array_diff($this->protectedTables(), ['booking_edit_audits']))->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->pluck('id')->all()])->all(); }

    private function assertPrincipal(string $total): void
    {
        $this->assertSame(0, bccomp((string) DB::table('bookings')->where('id', $this->booking->id)->value('total_price'), $total, 2));
        foreach ([
            ['customer_ledger', $this->customerLedgerId, 'amount_in'], ['journal_voucher_details', $this->customerDetailId, 'credit'],
            ['journal_voucher_details', $this->totalSaleDetailId, 'debit'], ['ledgers', $this->customerLedgerLineId, 'amount_out'],
            ['ledgers', $this->totalSaleLedgerLineId, 'amount_in'], ['journal_vouchers', $this->voucherId, 'total_debit'],
            ['journal_vouchers', $this->voucherId, 'total_credit'],
        ] as [$table, $id, $column]) {
            $this->assertSame(0, bccomp((string) DB::table($table)->where('id', $id)->value($column), $total, 2), "$table.$column");
        }
    }

    private function assertIdentityUnchanged(): void
    {
        $booking = $this->booking->fresh();
        $this->assertSame([10, 20, 30, 1, '10', '2026-09-08', 'active'], [(int)$booking->project_id, (int)$booking->customer_id, (int)$booking->plot_id, (int)$booking->plot_type, (string)$booking->plot_size, $booking->booking_date, $booking->status]);
    }

    private function setBookingPricing(string $rate, string $total): void { DB::table('bookings')->where('id', $this->booking->id)->update(['plot_rate' => $rate, 'total_price' => $total]); $this->booking = $this->booking->fresh(); }
    private function setAccountingPrincipal(string $amount): void
    {
        DB::table('customer_ledger')->where('id', $this->customerLedgerId)->update(['amount_in' => $amount]);
        DB::table('journal_voucher_details')->where('id', $this->customerDetailId)->update(['credit' => $amount]);
        DB::table('journal_voucher_details')->where('id', $this->totalSaleDetailId)->update(['debit' => $amount]);
        DB::table('ledgers')->where('id', $this->customerLedgerLineId)->update(['amount_out' => $amount]);
        DB::table('ledgers')->where('id', $this->totalSaleLedgerLineId)->update(['amount_in' => $amount]);
        DB::table('journal_vouchers')->where('id', $this->voucherId)->update(['total_debit' => $amount, 'total_credit' => $amount]);
    }

    private function seedPristine(): void
    {
        $this->booking = Booking::create(['project_id'=>10,'customer_id'=>20,'plot_id'=>30,'plot_type'=>1,'plot_size'=>'10','plot_rate'=>500000,'is_corner'=>0,'is_park'=>0,'park_facing'=>0,'carner_price'=>0,'dicount_value'=>0,'total_price'=>5000000,'broker_id'=>null,'booking_date'=>'2026-09-08','status'=>'active','user_id'=>1,'cancel_status'=>'0']);
        $this->customerPivotId = DB::table('project_head_subheads')->insertGetId($this->customerPivotRow());
        $this->totalSalePivotId = DB::table('project_head_subheads')->insertGetId($this->totalSalePivotRow());
        $this->voucherId = DB::table('journal_vouchers')->insertGetId($this->voucherRow('SV-1', 'BOOKING-'.$this->booking->id, 'pending'));
        $this->customerLedgerId = DB::table('customer_ledger')->insertGetId(['customer_id'=>20,'project_id'=>10,'plot_id'=>30,'transaction_type'=>'Bo','amount_in'=>5000000,'amount_out'=>0]);
        $this->customerDetailId = DB::table('journal_voucher_details')->insertGetId($this->detailRow($this->voucherId,$this->customerPivotId,0,5000000));
        $this->totalSaleDetailId = DB::table('journal_voucher_details')->insertGetId($this->detailRow($this->voucherId,$this->totalSalePivotId,5000000,0));
        $this->customerLedgerLineId = DB::table('ledgers')->insertGetId($this->ledgerRow($this->customerPivotId,$this->customerLedgerId,$this->voucherId,0,5000000));
        $this->totalSaleLedgerLineId = DB::table('ledgers')->insertGetId($this->ledgerRow($this->totalSalePivotId,$this->customerLedgerId,$this->voucherId,5000000,0));
    }
    private function voucherRow(string $number,string $reference,string $status): array { return ['voucher_number'=>$number,'type'=>'SV','reference'=>$reference,'project_id'=>10,'total_debit'=>5000000,'total_credit'=>5000000,'status'=>$status]; }
    private function customerPivotRow(): array { return ['project_id'=>10,'head_accounting_id'=>16,'subhead_accounting_id'=>200,'plot_id'=>30,'customer_id'=>20]; }
    private function totalSalePivotRow(): array { return ['project_id'=>10,'head_accounting_id'=>16,'subhead_accounting_id'=>123,'plot_id'=>null,'customer_id'=>null]; }
    private function detailRow(int $voucher,int $account,$debit,$credit): array { return ['journal_voucher_id'=>$voucher,'account_id'=>$account,'debit'=>$debit,'credit'=>$credit,'description'=>'principal']; }
    private function ledgerRow(int $pivot,int $customerLedger,int $voucher,$in,$out): array { return ['type'=>'SV','type_id'=>$voucher,'voucher_number'=>'SV-1','project_head_subheads_id'=>$pivot,'customer_ledger_id'=>$customerLedger,'reference'=>$this->booking->id,'amount_in'=>$in,'amount_out'=>$out,'detail'=>'principal']; }

    private function createSchema(): void
    {
        Schema::create('bookings', fn(Blueprint $t) => $this->bookingSchema($t));
        Schema::create('project_head_subheads', function(Blueprint $t){$t->id();$t->integer('project_id');$t->integer('head_accounting_id');$t->integer('subhead_accounting_id');$t->integer('plot_id')->nullable();$t->integer('customer_id')->nullable();});
        Schema::create('journal_vouchers', function(Blueprint $t){$t->id();$t->string('voucher_number');$t->string('type');$t->string('reference')->nullable();$t->integer('project_id');$t->decimal('total_debit',15,2);$t->decimal('total_credit',15,2);$t->string('status');$t->timestamps();$t->softDeletes();});
        Schema::create('journal_voucher_details', function(Blueprint $t){$t->id();$t->integer('journal_voucher_id');$t->integer('account_id');$t->decimal('debit',15,2);$t->decimal('credit',15,2);$t->string('description')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('customer_ledger', function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('project_id');$t->integer('plot_id');$t->string('transaction_type');$t->decimal('amount_in',10,2);$t->decimal('amount_out',10,2);$t->timestamps();});
        Schema::create('ledgers', function(Blueprint $t){$t->id();$t->string('type');$t->integer('type_id');$t->string('voucher_number')->nullable();$t->integer('project_head_subheads_id');$t->integer('customer_ledger_id')->nullable();$t->string('reference')->nullable();$t->decimal('amount_in',10,2);$t->decimal('amount_out',10,2);$t->string('detail')->nullable();$t->timestamps();});
        Schema::create('booking_details', function(Blueprint $t){$t->id();$t->integer('booking_id');$t->decimal('amount',10,2);$t->date('due_date');});
        Schema::create('booking_vouchers', function(Blueprint $t){$t->id();$t->integer('booking_id');$t->string('voucher_series');$t->decimal('amount',12,2)->default(0);});
        Schema::create('booking_edit_audits', function(Blueprint $t){$t->id();$t->integer('booking_id');$t->integer('project_id');$t->integer('user_id')->nullable();$t->string('operation');$t->text('reason')->nullable();$t->json('old_values');$t->json('new_values');$t->decimal('old_total',10,2)->nullable();$t->decimal('new_total',10,2)->nullable();$t->decimal('delta',10,2)->nullable();$t->decimal('old_accounting_principal',10,2)->nullable();$t->decimal('delta_from_accounting',10,2)->nullable();$t->integer('journal_voucher_id')->nullable();$t->string('request_key')->nullable();$t->timestamps();});
    }
    private function bookingSchema(Blueprint $t): void {$t->id();$t->integer('project_id');$t->integer('customer_id');$t->integer('plot_id');$t->integer('plot_type');$t->string('plot_size');$t->decimal('plot_rate',10,2);$t->integer('is_corner');$t->integer('is_park');$t->decimal('park_facing',10,2);$t->decimal('carner_price',10,2);$t->decimal('dicount_value',10,2);$t->decimal('total_price',10,2);$t->integer('broker_id')->nullable();$t->timestamp('booking_date');$t->string('status');$t->integer('user_id');$t->string('cancel_status');$t->timestamps();$t->softDeletes();}
}
