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
use Spatie\Permission\Models\Permission;
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
        config()->set('app.url', 'http://localhost');
        app('url')->forceRootUrl('http://localhost');

        $this->createSchema();
        $this->user = User::factory()->create(['status_id' => 1]);
        $this->projectId = DB::table('projects')->insertGetId(['project' => 'A Long Demonstration Project Name']);
        $this->otherProjectId = DB::table('projects')->insertGetId(['project' => 'Other Project']);
    }

    public function test_authorized_controller_loads_broker_only_edit_page_with_project_brokers(): void
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
        $this->assertCount(1, $view->getData()['brokers']);
        $this->assertEquals(25000, $hydrated->dicount_value);

        $html = file_get_contents(resource_path('views/admin/booking/edit.blade.php'));
        $this->assertStringContainsString('$booking->dicount_value', $html);
        $this->assertStringNotContainsString('$booking->discount_value', $html);
        $this->assertStringContainsString('Customer changes must be completed through File Transfer.', $html);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('Save Broker Change', $html);
        $this->assertStringContainsString('Save Pricing Change', $html);
        $this->assertStringContainsString('class="col-md-7"', $html);
        $this->assertStringContainsString('class="col-md-5" id="pricing"', $html);
        $this->assertStringContainsString("name=\"broker_id\"", $html);
        $this->assertStringContainsString('name="expected_{{ $field }}"', $html);
        $this->assertStringContainsString("'dicount_value', 'total_price'", $html);
        $this->assertStringNotContainsString("name=\"total_price\"", $html);
        $this->assertSame(1, substr_count($html, 'name="plot_rate"'));
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

    public function test_inactive_booking_edit_reports_disabled_pricing_with_safe_message(): void
    {
        $booking = $this->createBooking(['status' => 'inactive']);
        $view = $this->callEditController($booking->id, $this->projectId);
        $lifecycle = $view->getData()['pricingLifecycle'];
        $blade = file_get_contents(resource_path('views/admin/booking/edit.blade.php'));

        $this->assertFalse($lifecycle['pricing_edit_allowed']);
        $this->assertContains('BOOKING_NOT_ACTIVE', $lifecycle['pricing_block_reasons']);
        $this->assertStringContainsString("'BOOKING_NOT_ACTIVE' => 'Booking is inactive.'", $blade);
        $this->assertStringContainsString('@disabled(!$pricingEnabled)', $blade);
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
        $this->assertContains('permission:update plot|update booking price', $route->gatherMiddleware());
    }

    public function test_update_route_is_put_only_and_requires_update_plot_permission(): void
    {
        $route = app('router')->getRoutes()->getByName('booking.update');

        $this->assertNotNull($route);
        $this->assertSame(['PUT'], $route->methods());
        $this->assertContains('permission:update plot', $route->gatherMiddleware());
    }

    public function test_pricing_route_is_put_only_and_requires_financial_permission(): void
    {
        $route = app('router')->getRoutes()->getByName('booking.pricing.update');
        $this->assertNotNull($route);
        $this->assertSame(['PUT'], $route->methods());
        $this->assertContains('permission:update booking price', $route->gatherMiddleware());
    }

    public function test_unauthenticated_pricing_update_uses_normal_auth_redirect(): void
    {
        $booking = $this->createBooking();
        $this->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.pricing.update', ['id' => $booking->id], false), [])
            ->assertRedirect(route('login'));
    }

    public function test_legacy_price_get_redirects_authorized_user_to_safe_edit_section(): void
    {
        $booking = $this->createBooking();
        $permission = Permission::create(['name' => 'update booking price', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->get(route('booking.price.update', ['id' => $booking->id], false))
            ->assertRedirect(route('booking.edit', ['id' => $booking->id], false) . '#pricing');
    }

    public function test_edit_permission_matrix_keeps_broker_and_pricing_actions_independent(): void
    {
        $booking = $this->createBooking();
        $pricing = Permission::create(['name' => 'update booking price', 'guard_name' => 'web']);
        $broker = Permission::create(['name' => 'update plot', 'guard_name' => 'web']);

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->get(route('booking.edit', ['id' => $booking->id], false))->assertForbidden();

        $this->user->givePermissionTo($broker);
        $request = Request::create(route('booking.edit', ['id' => $booking->id], false), 'GET');
        $this->assertSame('opened', app(PermissionMiddleware::class)->handle($request, fn () => 'opened', 'update plot|update booking price'));
        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.pricing.update', ['id' => $booking->id], false), [])->assertForbidden();

        $this->user->revokePermissionTo($broker);
        $this->user->givePermissionTo($pricing);
        $this->assertSame('opened', app(PermissionMiddleware::class)->handle($request, fn () => 'opened', 'update plot|update booking price'));
        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking))->assertForbidden();

        $this->user->givePermissionTo($broker);
        $this->assertSame('opened', app(PermissionMiddleware::class)->handle($request, fn () => 'opened', 'update plot|update booking price'));
    }

    public function test_unauthenticated_update_is_blocked(): void
    {
        $booking = $this->createBooking();

        $this->withCookie('selected_action', (string) $this->projectId)
            ->put('/admin/booking/' . $booking->id, $this->updatePayload($booking))
            ->assertRedirect();
    }

    public function test_unauthorized_update_returns_forbidden(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking))
            ->assertForbidden();
    }

    public function test_valid_project_broker_is_the_only_changed_value_and_accounting_is_untouched(): void
    {
        $booking = $this->createBooking();
        $newBroker = $this->createBroker('New Broker', $this->projectId);
        $this->authorizeUpdate();
        $tables = ['customer_ledger', 'journal_vouchers', 'journal_voucher_details', 'ledgers', 'project_head_subheads', 'booking_details', 'booking_vouchers'];
        $before = $this->snapshot($tables);
        $bookingBefore = $booking->fresh()->getAttributes();

        $this->actingAs($this->user)
            ->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking, ['broker_id' => $newBroker]))
            ->assertRedirect(route('booking.edit', ['id' => $booking->id], false))
            ->assertSessionHas('success', 'Booking updated successfully.');

        $after = $booking->fresh()->getAttributes();
        $this->assertSame($newBroker, (int) $after['broker_id']);
        foreach ($bookingBefore as $key => $value) {
            if (!in_array($key, ['broker_id', 'updated_at'], true)) {
                $this->assertSame($value, $after[$key], $key . ' changed');
            }
        }
        $this->assertSame($before, $this->snapshot($tables));
        $audit = DB::table('booking_edit_audits')->sole();
        $this->assertSame('broker_update', $audit->operation);
        $this->assertSame($this->user->id, (int) $audit->user_id);
        $this->assertSame(['broker_id' => $bookingBefore['broker_id']], json_decode($audit->old_values, true));
        $this->assertSame(['broker_id' => $newBroker], json_decode($audit->new_values, true));
    }

    public function test_same_broker_is_a_true_no_op_and_nullable_broker_is_allowed(): void
    {
        $booking = $this->createBooking();
        $this->authorizeUpdate();
        $originalUpdatedAt = $booking->updated_at->format('Y-m-d H:i:s.u');

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking))
            ->assertSessionHasNoErrors();
        $this->assertSame($originalUpdatedAt, $booking->fresh()->updated_at->format('Y-m-d H:i:s.u'));
        $this->assertSame(0, DB::table('booking_edit_audits')->count());

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking->fresh(), ['broker_id' => null]))
            ->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->broker_id);
    }

    public function test_existing_schedule_does_not_block_broker_metadata_update(): void
    {
        $booking = $this->createBooking();
        $newBroker = $this->createBroker('Schedule Safe Broker', $this->projectId);
        DB::table('booking_details')->insert(['id' => 1]);
        $this->authorizeUpdate();

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking, ['broker_id' => $newBroker]))
            ->assertSessionHasNoErrors();

        $this->assertSame($newBroker, (int) $booking->fresh()->broker_id);
        $this->assertSame(1, DB::table('booking_details')->count());
    }

    public function test_legacy_null_updated_at_booking_loads_and_updates_without_accounting_mutation(): void
    {
        $booking = $this->createBooking();
        $newBroker = $this->createBroker('Legacy Booking Broker', $this->projectId);
        DB::table('bookings')->where('id', $booking->id)->update(['updated_at' => null]);
        $booking = $booking->fresh();
        $this->assertNull($booking->updated_at);
        $view = $this->callEditController($booking->id, $this->projectId);
        $this->assertNull($view->getData()['booking']->updated_at);
        $this->authorizeUpdate();
        $tables = $this->protectedTables();
        $before = $this->snapshot($tables);

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking, ['broker_id' => $newBroker]))
            ->assertSessionHasNoErrors();

        $this->assertSame($newBroker, (int) $booking->fresh()->broker_id);
        $this->assertSame($before, $this->snapshot($tables));
    }

    public function test_null_expected_timestamp_rejects_when_database_timestamp_becomes_non_null(): void
    {
        $booking = $this->createBooking();
        $newBroker = $this->createBroker('Null Race Broker', $this->projectId);
        DB::table('bookings')->where('id', $booking->id)->update(['updated_at' => null]);
        $booking = $booking->fresh();
        $payload = $this->updatePayload($booking, ['broker_id' => $newBroker]);
        DB::table('bookings')->where('id', $booking->id)->update(['updated_at' => '2026-09-10 00:00:00']);
        $this->authorizeUpdate();
        $before = $this->snapshot($this->protectedTables());

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $payload)
            ->assertSessionHasErrors('expected_updated_at');

        $this->assertNotSame($newBroker, (int) $booking->fresh()->broker_id);
        $this->assertSame($before, $this->snapshot($this->protectedTables()));
    }

    public function test_expected_broker_snapshot_blocks_same_timestamp_race(): void
    {
        $booking = $this->createBooking();
        $brokerTwo = $this->createBroker('Concurrent Broker', $this->projectId);
        $brokerThree = $this->createBroker('Requested Broker', $this->projectId);
        $payload = $this->updatePayload($booking, ['broker_id' => $brokerThree]);
        $sameTimestamp = $booking->updated_at->format('Y-m-d H:i:s');
        DB::table('bookings')->where('id', $booking->id)->update([
            'broker_id' => $brokerTwo,
            'updated_at' => $sameTimestamp,
        ]);
        $this->authorizeUpdate();
        $before = $this->snapshot($this->protectedTables());

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $payload)
            ->assertSessionHasErrors('expected_updated_at');

        $this->assertSame($brokerTwo, (int) $booking->fresh()->broker_id);
        $this->assertSame($before, $this->snapshot($this->protectedTables()));
    }

    public function test_cross_project_random_and_unmapped_brokers_are_rejected(): void
    {
        $booking = $this->createBooking();
        $crossProjectBroker = $this->createBroker('Other Broker', $this->otherProjectId);
        $unmappedBroker = DB::table('subhead_accountings')->insertGetId(['name' => 'Unmapped Broker']);
        $this->authorizeUpdate();

        foreach ([$crossProjectBroker, $unmappedBroker, 999999] as $brokerId) {
            $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
                ->from(route('booking.edit', ['id' => $booking->id], false))
                ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking, ['broker_id' => $brokerId]))
                ->assertSessionHasErrors('broker_id');
            $this->assertSame((int) $booking->broker_id, (int) $booking->fresh()->broker_id);
        }
        $this->assertSame(0, DB::table('booking_edit_audits')->count());
    }

    /** @dataProvider immutableTamperingProvider */
    public function test_immutable_and_financial_tampering_is_rejected(string $field, $value): void
    {
        $booking = $this->createBooking();
        $this->authorizeUpdate();
        $before = $booking->fresh()->getAttributes();

        $response = $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->from(route('booking.edit', ['id' => $booking->id], false))
            ->put(route('booking.update', ['id' => $booking->id], false), $this->updatePayload($booking, [$field => $value]));

        $response->assertSessionHasErrors($field);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        if ($field === 'customer_id') {
            $this->assertStringContainsString('File Transfer', session('errors')->first('customer_id'));
        }
    }

    public function immutableTamperingProvider(): array
    {
        return collect(['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size', 'booking_date', 'status', 'plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'])
            ->mapWithKeys(fn ($field) => [$field => [$field, $field === 'booking_date' ? '2020-01-01 00:00:00' : '999']])
            ->all();
    }

    public function test_stale_update_cross_project_cancelled_and_deleted_bookings_are_not_changed(): void
    {
        $booking = $this->createBooking();
        $newBroker = $this->createBroker('New Broker', $this->projectId);
        $this->authorizeUpdate();
        $payload = $this->updatePayload($booking, ['broker_id' => $newBroker]);
        DB::table('bookings')->where('id', $booking->id)->update(['updated_at' => '2026-09-09 00:00:00']);

        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $booking->id], false), $payload)
            ->assertSessionHasErrors('expected_updated_at');
        $this->assertNotSame($newBroker, (int) $booking->fresh()->broker_id);

        foreach ([
            $this->createBooking(['project_id' => $this->otherProjectId]),
            $this->createBooking(['cancel_status' => '1']),
        ] as $blocked) {
            $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
                ->put(route('booking.update', ['id' => $blocked->id], false), $this->updatePayload($blocked))
                ->assertNotFound();
        }

        $deleted = $this->createBooking();
        $deleted->delete();
        $this->actingAs($this->user)->withCookie('selected_action', (string) $this->projectId)
            ->put(route('booking.update', ['id' => $deleted->id], false), $this->updatePayload($deleted))
            ->assertNotFound();
        $this->assertSame(0, DB::table('booking_edit_audits')->count());
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
        $brokerId = $this->createBroker('Example Broker', (int) ($overrides['project_id'] ?? $this->projectId));

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

    private function createBroker(string $name, int $projectId): int
    {
        $brokerId = DB::table('subhead_accountings')->insertGetId(['name' => $name]);
        DB::table('project_head_subheads')->insert([
            'project_id' => $projectId,
            'head_accounting_id' => 6,
            'subhead_accounting_id' => $brokerId,
        ]);
        return $brokerId;
    }

    private function authorizeUpdate(): void
    {
        $permission = Permission::create(['name' => 'update plot', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }

    private function updatePayload(Booking $booking, array $overrides = []): array
    {
        $payload = collect(['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size', 'status', 'plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'])
            ->mapWithKeys(fn ($field) => [$field => (string) $booking->{$field}])
            ->all();
        $payload['booking_date'] = \Carbon\Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s');
        $payload['expected_updated_at'] = $booking->updated_at?->format('Y-m-d H:i:s.u');
        $payload['expected_broker_id'] = $booking->broker_id;
        $payload['broker_id'] = $booking->broker_id;
        return array_merge($payload, $overrides);
    }

    private function protectedTables(): array
    {
        return ['customer_ledger', 'journal_vouchers', 'journal_voucher_details', 'ledgers', 'project_head_subheads', 'booking_details', 'booking_vouchers'];
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
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
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->integer('head_accounting_id');
            $table->integer('subhead_accounting_id');
            $table->integer('plot_id')->nullable();
            $table->integer('customer_id')->nullable();
        });
        Schema::create('booking_edit_audits', function (Blueprint $table) {
            $table->id();
            $table->integer('booking_id');
            $table->integer('project_id');
            $table->integer('user_id')->nullable();
            $table->string('operation');
            $table->text('reason')->nullable();
            $table->json('old_values');
            $table->json('new_values');
            $table->decimal('old_total', 10, 2)->nullable();
            $table->decimal('new_total', 10, 2)->nullable();
            $table->decimal('delta', 10, 2)->nullable();
            $table->decimal('old_accounting_principal', 10, 2)->nullable();
            $table->decimal('delta_from_accounting', 10, 2)->nullable();
            $table->integer('journal_voucher_id')->nullable();
            $table->string('request_key')->nullable();
            $table->timestamps();
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

        Schema::create('journal_vouchers', function(Blueprint $t){$t->id();$t->string('voucher_number')->nullable();$t->string('type')->nullable();$t->string('reference')->nullable();$t->integer('project_id')->nullable();$t->decimal('total_debit',15,2)->default(0);$t->decimal('total_credit',15,2)->default(0);$t->string('status')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('journal_voucher_details', function(Blueprint $t){$t->id();$t->integer('journal_voucher_id')->nullable();$t->integer('account_id')->nullable();$t->decimal('debit',15,2)->default(0);$t->decimal('credit',15,2)->default(0);$t->string('description')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('customer_ledger', function(Blueprint $t){$t->id();$t->integer('customer_id')->nullable();$t->integer('project_id')->nullable();$t->integer('plot_id')->nullable();$t->string('transaction_type')->nullable();$t->decimal('amount_in',15,2)->default(0);$t->decimal('amount_out',15,2)->default(0);});
        Schema::create('ledgers', function(Blueprint $t){$t->id();$t->string('type')->nullable();$t->integer('type_id')->nullable();$t->integer('project_head_subheads_id')->nullable();$t->integer('customer_ledger_id')->nullable();$t->string('reference')->nullable();$t->decimal('amount_in',15,2)->default(0);$t->decimal('amount_out',15,2)->default(0);$t->string('detail')->nullable();});
        Schema::create('booking_details', function(Blueprint $t){$t->id();$t->integer('booking_id')->nullable();});
        Schema::create('booking_vouchers', function(Blueprint $t){$t->id();$t->integer('booking_id')->nullable();$t->string('voucher_series')->nullable();});
    }
}
