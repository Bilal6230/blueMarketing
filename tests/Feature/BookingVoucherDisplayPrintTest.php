<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingVoucherDisplayPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('customer_ledger', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable(); $table->string('transaction_type'); $table->integer('type_id')->nullable();
            $table->date('date')->nullable(); $table->decimal('amount_in', 12, 2)->default(0); $table->decimal('amount_out', 12, 2)->default(0);
            $table->text('description')->nullable(); $table->boolean('is_active')->default(true); $table->boolean('is_approve')->default(true);
            $table->integer('payment_type')->nullable(); $table->text('note')->nullable(); $table->timestamps();
        });
        Schema::create('booking_vouchers', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('customer_ledger_id'); });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('plot_id');
            $table->string('cancel_status')->default('0');
        });
        Schema::create('leads', function (Blueprint $table): void { $table->id(); $table->string('first_name')->nullable(); $table->string('last_name')->nullable(); });
        Schema::create('plots', function (Blueprint $table): void { $table->id(); $table->string('name')->nullable(); $table->integer('type')->nullable(); $table->softDeletes(); });
        Schema::create('projects', function (Blueprint $table): void { $table->id(); $table->string('project')->nullable(); });
        Schema::create('subhead_accountings', function (Blueprint $table): void { $table->id(); $table->string('name')->nullable(); });
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id')->nullable(); $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
        });
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_ledger_id')->nullable(); $table->unsignedBigInteger('project_head_subheads_id')->nullable();
            $table->string('type')->nullable(); $table->string('voucher_number')->nullable(); $table->string('voucher')->nullable();
        });
        Schema::create('project_voucher_display_numbers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('source_kind'); $table->unsignedBigInteger('source_id');
            $table->string('display_number');
        });

        DB::table('customer_ledger')->insert([
            'id' => 41, 'project_id' => 5, 'customer_id' => null, 'plot_id' => null, 'transaction_type' => 'PPR',
            'type_id' => 9, 'date' => '2026-11-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_voucher_display_numbers')->insert([
            'project_id' => 5, 'source_kind' => 'customer_ledger', 'source_id' => 41, 'display_number' => 'PPR-2026-0009',
        ]);
    }

    public function test_booking_receipt_print_view_receives_saved_display_number(): void
    {
        $request = Request::create('/booking/plot/voucher/print/41', 'GET', [], ['selected_action' => '5']);
        $this->app->instance('request', $request);

        $view = (new BookingController())->printCashIn(41);

        $this->assertSame('admin.booking.print_receive', $view->name());
        $this->assertSame('PPR-2026-0009', $view->getData()['displayNumber']);
    }
}
