<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\CommissionVoucherController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Read-only CV listing regression with an isolated SQLite fixture. */
class CommissionVoucherListingResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Only isolated SQLite :memory: is permitted.');
        }
        Schema::dropAllTables();
        Schema::create('commision_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('voucher_number');
            $table->string('reference')->nullable();
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        // Controller index queries Role::get(), even for AJAX.
        // Provide the minimal Spatie roles table in this SQLite-only fixture.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        // Auth user with an already-loaded role, without touching any real user table.
        $user = new User();
        $user->id = 1;
        $user->setRelation('roles', collect([new \Spatie\Permission\Models\Role(['name' => 'Test Role', 'guard_name' => 'web'])]));
        Auth::setUser($user);
    }

    public function test_ajax_listing_uses_commission_header_number_scoped_to_selected_project(): void
    {
        DB::table('commision_vouchers')->insert([
            ['id' => 41, 'project_id' => 7, 'voucher_number' => '41',
             'reference' => 'CV-Own', 'total_debit' => 100,
             'created_at' => now(), 'updated_at' => now()],
            ['id' => 42, 'project_id' => 8, 'voucher_number' => '9001',
             'reference' => 'CV-Other', 'total_debit' => 100,
             'created_at' => now(), 'updated_at' => now()],
        ]);
        $request = Request::create('/commision-voucher', 'GET', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->cookies->set('selected_action', '7');
        $this->app->instance('request', $request);

        $response = app(CommissionVoucherController::class)->index($request);
        $payload = $response->getData(true);
        $this->assertSame(1, $payload['recordsTotal']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('CV-0000041', $payload['data'][0]['voucher_number']);
        $this->assertSame('CV-Own', $payload['data'][0]['reference']);
    }
}
