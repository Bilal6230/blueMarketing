<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middlewares\PermissionMiddleware;
use Tests\TestCase;

class BookingEditPageTest extends TestCase
{
    private User $user;
    private int $projectId;
    private int $otherProjectId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        $this->user = User::factory()->create(['status_id' => 1]);
        $this->projectId = DB::table('projects')->insertGetId(['project' => 'A Long Demonstration Project Name']);
        $this->otherProjectId = DB::table('projects')->insertGetId(['project' => 'Other Project']);
    }

    public function test_authorized_controller_loads_read_only_edit_page_with_booking_values(): void
    {
        $booking = $this->createBooking();

        $view = $this->callEditController($booking->id, $this->projectId);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.booking.edit', $view->name());
        $hydrated = $view->getData()['booking'];
        $this->assertTrue($hydrated->relationLoaded('project'));
        $this->assertTrue($hydrated->relationLoaded('customer'));
        $this->assertTrue($hydrated->relationLoaded('plot'));
        $this->assertTrue($hydrated->relationLoaded('broker'));
        $this->assertEquals(25000, $hydrated->dicount_value);

        $html = file_get_contents(resource_path('views/admin/booking/edit.blade.php'));
        $this->assertStringContainsString('$booking->dicount_value', $html);
        $this->assertStringNotContainsString('$booking->discount_value', $html);
        $this->assertStringContainsString('Customer changes are handled through File Transfer.', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }

    public function test_other_project_booking_cannot_be_opened(): void
    {
        $booking = $this->createBooking(['project_id' => $this->otherProjectId]);

        $this->expectException(ModelNotFoundException::class);
        $this->callEditController($booking->id, $this->projectId);
    }

    public function test_cancelled_booking_cannot_be_opened(): void
    {
        $booking = $this->createBooking(['cancel_status' => '1']);

        $this->expectException(ModelNotFoundException::class);
        $this->callEditController($booking->id, $this->projectId);
    }

    public function test_unauthorized_user_cannot_open_route(): void
    {
        $booking = $this->createBooking();
        $request = Request::create(route('booking.edit', ['id' => $booking->id], false), 'GET');
        $this->actingAs($this->user);

        $this->expectException(UnauthorizedException::class);
        app(PermissionMiddleware::class)->handle($request, fn () => response('opened'), 'update plot');
    }

    public function test_edit_route_is_get_only_and_requires_update_plot_permission(): void
    {
        $route = app('router')->getRoutes()->getByName('booking.edit');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('permission:update plot', $route->gatherMiddleware());
    }

    public function test_loading_page_performs_no_accounting_mutations(): void
    {
        $booking = $this->createBooking();
        $before = [
            'customer_ledger' => DB::table('customer_ledger')->count(),
            'journal_vouchers' => DB::table('journal_vouchers')->count(),
            'journal_voucher_details' => DB::table('journal_voucher_details')->count(),
            'ledgers' => DB::table('ledgers')->count(),
            'booking_details' => DB::table('booking_details')->count(),
        ];

        $this->callEditController($booking->id, $this->projectId);

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table . ' was mutated');
        }
    }

    private function callEditController(int $bookingId, int $selectedProjectId): View
    {
        $request = Request::create('/admin/booking/' . $bookingId . '/edit', 'GET');
        $request->cookies->set('selected_action', (string) $selectedProjectId);
        $this->app->instance('request', $request);
        auth()->setUser($this->user);

        return app(BookingController::class)->editBooking($bookingId);
    }

    private function createBooking(array $overrides = []): Booking
    {
        $customerId = DB::table('leads')->insertGetId([
            'first_name' => 'Customer With A Very Long',
            'last_name' => 'Display Name',
        ]);
        $plotId = DB::table('plots')->insertGetId([
            'name' => 'R-1001',
            'unit' => 'Marla',
        ]);
        $brokerId = DB::table('subhead_accountings')->insertGetId(['name' => 'Example Broker']);

        return Booking::create(array_merge([
            'project_id' => $this->projectId,
            'customer_id' => $customerId,
            'plot_id' => $plotId,
            'plot_type' => 1,
            'plot_size' => '10',
            'plot_rate' => 500000,
            'is_corner' => 1,
            'is_park' => 1,
            'park_facing' => 100000,
            'carner_price' => 75000,
            'dicount_value' => 25000,
            'total_price' => 5150000,
            'broker_id' => $brokerId,
            'booking_date' => '2026-09-08 00:00:00',
            'status' => 'active',
            'user_id' => $this->user->id,
            'cancel_status' => '0',
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->integer('status_id')->nullable();
            $table->string('avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project');
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->nullable();
        });
        Schema::create('subhead_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->integer('customer_id');
            $table->integer('plot_id');
            $table->integer('plot_type');
            $table->string('plot_size');
            $table->decimal('plot_rate', 12, 2);
            $table->integer('is_corner')->default(0);
            $table->integer('is_park')->default(0);
            $table->decimal('park_facing', 12, 2);
            $table->decimal('carner_price', 12, 2);
            $table->decimal('dicount_value', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->integer('broker_id')->nullable();
            $table->timestamp('booking_date');
            $table->string('status');
            $table->integer('user_id');
            $table->string('cancel_status')->default('0');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        foreach (['customer_ledger', 'journal_vouchers', 'journal_voucher_details', 'ledgers', 'booking_details'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
            });
        }
    }
}
