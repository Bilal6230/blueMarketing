<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingPlotCustomerIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('plots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plot_id');
            $table->unsignedBigInteger('customer_id');
        });
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
        });

        DB::table('plots')->insert([
            ['id' => 10, 'project_id' => 5],
            ['id' => 20, 'project_id' => 6],
        ]);
        DB::table('leads')->insert([
            ['id' => 100, 'first_name' => 'Selected project customer'],
            ['id' => 200, 'first_name' => 'Other project customer'],
        ]);
        DB::table('bookings')->insert([
            ['id' => 1, 'plot_id' => 10, 'customer_id' => 100],
            ['id' => 2, 'plot_id' => 20, 'customer_id' => 200],
        ]);
    }

    public function test_plot_customer_lookup_is_limited_to_selected_project(): void
    {
        $request = Request::create('/get-plot-customer', 'POST', ['plot_id' => 20], ['selected_action' => '5']);
        $this->app->instance('request', $request);

        $response = (new BookingController())->getPlotCustomer($request);

        $this->assertEmpty($response->getData(true));
    }

    public function test_plot_customer_lookup_returns_customer_for_selected_project(): void
    {
        $request = Request::create('/get-plot-customer', 'POST', ['plot_id' => 10], ['selected_action' => '5']);
        $this->app->instance('request', $request);

        $response = (new BookingController())->getPlotCustomer($request);

        $this->assertSame('Selected project customer', $response->getData(true)['first_name']);
    }
}
