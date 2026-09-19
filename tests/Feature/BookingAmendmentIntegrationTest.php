<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BookingAmendmentIntegrationTest extends TestCase
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
        config()->set('app.url', 'http://localhost');
        app('url')->forceRootUrl('http://localhost');
        $this->createSchema();
        $this->seedPristine();
    }

    public function test_unified_http_price_increase_resets_schedule_without_accounting_mutation(): void
    {
        $user = $this->authorizedPricingUser();
        DB::table('booking_details')->insert(['booking_id' => $this->booking->id, 'amount' => 100000, 'due_date' => '2026-10-01']);
        $before = $this->accountingRows();
        $this->actingAs($user)->withCookie('selected_action', '10')
            ->put(route('booking.update', ['id' => $this->booking->id], false), $this->unifiedPayload(['plot_rate' => '600000']))
            ->assertRedirect(route('booking.plot.index'))->assertSessionHasNoErrors();
        $this->assertSame(0, bccomp('6000000.00', (string) $this->booking->fresh()->total_price, 2));
        $this->assertSame(0, DB::table('booking_details')->count());
        $this->assertSame($before, $this->accountingRows());
        $audit = DB::table('booking_edit_audits')->sole();
        $this->assertSame('booking_price_amendment', $audit->operation);
        $this->assertCount(1, json_decode($audit->old_values, true)['schedule_before']);
    }

    public function test_unified_http_price_decrease_preserves_accounting_and_calculates_outstanding(): void
    {
        $user = $this->authorizedPricingUser();
        $before = $this->accountingRows();
        $this->actingAs($user)->withCookie('selected_action', '10')
            ->put(route('booking.update', ['id' => $this->booking->id], false), $this->unifiedPayload(['plot_rate' => '400000']))
            ->assertRedirect(route('booking.plot.index'))->assertSessionHasNoErrors();
        $audit = DB::table('booking_edit_audits')->sole();
        $this->assertSame('4000000.00', json_decode($audit->new_values, true)['new_outstanding']);
        $this->assertSame($before, $this->accountingRows());
    }

    public function test_unified_http_same_total_component_change_preserves_schedule(): void
    {
        $user = $this->authorizedPricingUser();
        DB::table('booking_details')->insert(['booking_id' => $this->booking->id, 'amount' => 100000, 'due_date' => '2026-10-01']);
        $before = $this->accountingRows();
        $this->actingAs($user)->withCookie('selected_action', '10')
            ->put(route('booking.update', ['id' => $this->booking->id], false), $this->unifiedPayload(['plot_rate' => '510000', 'dicount_value' => '100000']))
            ->assertRedirect(route('booking.plot.index'))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('booking_details')->count());
        $this->assertFalse(json_decode(DB::table('booking_edit_audits')->sole()->new_values, true)['schedule_reset']);
        $this->assertSame($before, $this->accountingRows());
    }

    public function test_unified_http_historical_mismatch_is_not_repaired(): void
    {
        $user = $this->authorizedPricingUser();
        $this->setBookingPricing('490000', '4900000');
        $before = $this->accountingRows();
        $this->actingAs($user)->withCookie('selected_action', '10')
            ->put(route('booking.update', ['id' => $this->booking->id], false), $this->unifiedPayload(['plot_rate' => '480000']))
            ->assertRedirect(route('booking.plot.index'))->assertSessionHasNoErrors();
        $this->assertSame(0, bccomp('4800000.00', (string) $this->booking->fresh()->total_price, 2));
        $this->assertSame($before, $this->accountingRows());
    }

    /** @dataProvider historicalVoucherStatuses */
    public function test_unified_http_nonpending_historical_voucher_is_unchanged(string $status): void
    {
        $user = $this->authorizedPricingUser();
        DB::table('journal_vouchers')->where('id', $this->voucherId)->update(['status' => $status]);
        $before = $this->accountingRows();
        $this->actingAs($user)->withCookie('selected_action', '10')
            ->put(route('booking.update', ['id' => $this->booking->id], false), $this->unifiedPayload(['plot_rate' => '480000']))
            ->assertRedirect(route('booking.plot.index'))->assertSessionHasNoErrors();
        $this->assertSame($before, $this->accountingRows());
    }

    public function historicalVoucherStatuses(): array
    {
        return ['approved' => ['approved'], 'rejected' => ['rejected']];
    }

    public function test_old_pricing_http_route_is_absent(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('booking.pricing.update'));
    }

    private function unifiedPayload(array $pricing = []): array
    {
        $booking = $this->booking->fresh();
        return array_merge([
            'project_id' => (string) $booking->project_id, 'customer_id' => (string) $booking->customer_id,
            'plot_id' => (string) $booking->plot_id, 'plot_type' => (string) $booking->plot_type,
            'plot_size' => (string) $booking->plot_size, 'booking_date' => \Carbon\Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s'),
            'status' => $booking->status, 'expected_updated_at' => $booking->updated_at?->format('Y-m-d H:i:s.u'),
            'expected_booking_date' => \Carbon\Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s'),
            'expected_status' => $booking->status,
            'expected_broker_id' => $booking->broker_id, 'broker_id' => $booking->broker_id,
            'expected_paid_to_date' => '0.00', 'reason' => 'Approved amendment',
        ], $this->snapshot(), $this->defaultPricing(), $pricing);
    }

    private function accountingRows(): array
    {
        return collect(['customer_ledger', 'journal_vouchers', 'journal_voucher_details', 'ledgers', 'project_head_subheads', 'booking_vouchers'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
    }

    private function authorizedPricingUser(): User { $user = User::factory()->create(['status_id'=>1]); $user->givePermissionTo(Permission::create(['name'=>'update booking price','guard_name'=>'web'])); return $user; }
    private function defaultPricing(): array { return ['plot_rate' => '500000', 'is_park' => 0, 'park_facing' => '0', 'is_corner' => 0, 'carner_price' => '0', 'dicount_value' => '0']; }
    private function snapshot(): array { $b = Booking::withTrashed()->findOrFail($this->booking->id); return ['expected_plot_rate' => (string) $b->plot_rate, 'expected_is_park' => (int) $b->is_park, 'expected_park_facing' => (string) $b->park_facing, 'expected_is_corner' => (int) $b->is_corner, 'expected_carner_price' => (string) $b->carner_price, 'expected_dicount_value' => (string) $b->dicount_value, 'expected_total_price' => (string) $b->total_price]; }
    private function setBookingPricing(string $rate, string $total): void { DB::table('bookings')->where('id', $this->booking->id)->update(['plot_rate' => $rate, 'total_price' => $total]); $this->booking = $this->booking->fresh(); }

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
        Schema::create('users', function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->integer('status_id')->nullable();$t->string('avatar')->nullable();$t->rememberToken();$t->timestamps();});
        Schema::create('permissions', function(Blueprint $t){$t->id();$t->string('name');$t->string('guard_name');$t->timestamps();$t->unique(['name','guard_name']);});
        Schema::create('roles', function(Blueprint $t){$t->id();$t->string('name');$t->string('guard_name');$t->timestamps();});
        Schema::create('model_has_permissions', function(Blueprint $t){$t->unsignedBigInteger('permission_id');$t->string('model_type');$t->unsignedBigInteger('model_id');$t->index(['model_id','model_type']);});
        Schema::create('model_has_roles', function(Blueprint $t){$t->unsignedBigInteger('role_id');$t->string('model_type');$t->unsignedBigInteger('model_id');});
        Schema::create('role_has_permissions', function(Blueprint $t){$t->unsignedBigInteger('permission_id');$t->unsignedBigInteger('role_id');});
        Schema::create('bookings', fn(Blueprint $t) => $this->bookingSchema($t));
        Schema::create('project_head_subheads', function(Blueprint $t){$t->id();$t->integer('project_id');$t->integer('head_accounting_id');$t->integer('subhead_accounting_id');$t->integer('plot_id')->nullable();$t->integer('customer_id')->nullable();});
        Schema::create('journal_vouchers', function(Blueprint $t){$t->id();$t->string('voucher_number');$t->string('type');$t->string('reference')->nullable();$t->integer('project_id');$t->decimal('total_debit',15,2);$t->decimal('total_credit',15,2);$t->string('status');$t->timestamps();$t->softDeletes();});
        Schema::create('journal_voucher_details', function(Blueprint $t){$t->id();$t->integer('journal_voucher_id');$t->integer('account_id');$t->decimal('debit',15,2);$t->decimal('credit',15,2);$t->string('description')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('customer_ledger', function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('project_id');$t->integer('plot_id');$t->integer('type_id')->nullable();$t->string('reference')->nullable();$t->integer('payment_type')->nullable();$t->string('t_number')->nullable();$t->integer('bank_id')->nullable();$t->date('passing_date')->nullable();$t->integer('passing_status')->nullable();$t->json('check_history')->nullable();$t->date('date')->nullable();$t->string('transaction_type');$t->decimal('amount_in',10,2);$t->decimal('amount_out',10,2);$t->string('description')->nullable();$t->boolean('is_active')->default(true);$t->boolean('is_approve')->default(false);$t->text('note')->nullable();$t->date('bank_post_at')->nullable();$t->string('delete_reason')->nullable();$t->timestamps();});
        Schema::create('ledgers', function(Blueprint $t){$t->id();$t->string('type');$t->integer('type_id');$t->string('voucher_number')->nullable();$t->integer('project_head_subheads_id');$t->integer('customer_ledger_id')->nullable();$t->string('reference')->nullable();$t->decimal('amount_in',10,2);$t->decimal('amount_out',10,2);$t->string('detail')->nullable();$t->timestamps();});
        Schema::create('booking_details', function(Blueprint $t){$t->id();$t->integer('booking_id');$t->string('installment_details')->nullable();$t->decimal('amount',10,2);$t->date('due_date');$t->timestamps();});
        Schema::create('booking_vouchers', function(Blueprint $t){$t->id();$t->integer('booking_id');$t->integer('customer_ledger_id')->nullable();$t->integer('ledger_id')->nullable();$t->integer('project_id')->nullable();$t->integer('customer_id')->nullable();$t->integer('plot_id')->nullable();$t->string('voucher_series');$t->integer('voucher_number')->nullable();$t->integer('payment_type')->nullable();$t->decimal('amount',12,2)->default(0);$t->date('receipt_date')->nullable();$t->string('description')->nullable();$t->integer('bank_id')->nullable();$t->string('t_number')->nullable();$t->date('passing_date')->nullable();$t->boolean('is_active')->default(true);$t->boolean('is_approve')->default(false);$t->integer('create_by')->nullable();$t->integer('update_by')->nullable();$t->timestamps();});
        Schema::create('booking_edit_audits', function(Blueprint $t){$t->id();$t->integer('booking_id');$t->integer('project_id');$t->integer('user_id')->nullable();$t->string('operation');$t->text('reason')->nullable();$t->json('old_values');$t->json('new_values');$t->decimal('old_total',10,2)->nullable();$t->decimal('new_total',10,2)->nullable();$t->decimal('delta',10,2)->nullable();$t->decimal('old_accounting_principal',10,2)->nullable();$t->decimal('delta_from_accounting',10,2)->nullable();$t->integer('journal_voucher_id')->nullable();$t->string('request_key')->nullable();$t->timestamps();});
    }
    private function bookingSchema(Blueprint $t): void {$t->id();$t->integer('project_id');$t->integer('customer_id');$t->integer('plot_id');$t->integer('plot_type');$t->string('plot_size');$t->decimal('plot_rate',10,2);$t->integer('is_corner');$t->integer('is_park');$t->decimal('park_facing',10,2);$t->decimal('carner_price',10,2);$t->decimal('dicount_value',10,2);$t->decimal('total_price',10,2);$t->integer('broker_id')->nullable();$t->timestamp('booking_date');$t->string('status');$t->integer('user_id');$t->string('cancel_status');$t->timestamps();$t->softDeletes();}
}
