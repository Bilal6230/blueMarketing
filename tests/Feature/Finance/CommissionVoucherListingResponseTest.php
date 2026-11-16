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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
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
        Schema::create('permissions', function (Blueprint $table) {
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
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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

    public function test_ajax_pagination_returns_total_and_filtered_counts_without_page_truncation(): void
    {
        DB::table('commision_vouchers')->insert([
            ['id'=>1,'project_id'=>7,'voucher_number'=>'1','reference'=>'MATCH',
                'created_at'=>now(),'updated_at'=>now()],
            ['id'=>2,'project_id'=>7,'voucher_number'=>'2','reference'=>'MATCH',
                'created_at'=>now(),'updated_at'=>now()],
            ['id'=>3,'project_id'=>7,'voucher_number'=>'3','reference'=>'OTHER',
                'created_at'=>now(),'updated_at'=>now()],
            ['id'=>4,'project_id'=>8,'voucher_number'=>'4','reference'=>'MATCH',
                'created_at'=>now(),'updated_at'=>now()],
        ]);
        $request=Request::create('/commision-voucher','GET',[
            'draw'=>2,'start'=>1,'length'=>1,'filter_reference'=>'MATCH'
        ]);
        $request->headers->set('X-Requested-With','XMLHttpRequest');
        $request->cookies->set('selected_action','7');
        $this->app->instance('request',$request);
        $data=app(CommissionVoucherController::class)->index($request)->getData(true);
        $this->assertSame(3,$data['recordsTotal']);
        $this->assertSame(2,$data['recordsFiltered']);
        $this->assertCount(1,$data['data']);
        $this->assertSame(1,$data['data'][0]['id']);
    }

    public function test_foreign_project_commission_edit_is_not_found(): void
    {
        DB::table('commision_vouchers')->insert([
            'id'=>41,'project_id'=>8,'voucher_number'=>'41',
            'created_at'=>now(),'updated_at'=>now()
        ]);
        $request=Request::create('/commision-voucher/41/edit','GET');
        $request->cookies->set('selected_action','7');
        $this->app->instance('request',$request);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CommissionVoucherController::class)->edit(41);
    }

    public function test_foreign_project_commission_print_is_not_found(): void
    {
        DB::table('commision_vouchers')->insert([
            'id'=>41,'project_id'=>8,'voucher_number'=>'41',
            'created_at'=>now(),'updated_at'=>now()
        ]);
        $request=Request::create('/commision-voucher/41/print','GET');
        $request->cookies->set('selected_action','7');
        $this->app->instance('request',$request);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CommissionVoucherController::class)->print(41);
    }

    public function test_ajax_date_range_remains_project_scoped_and_returns_correct_page_counts(): void
    {
        DB::table('commision_vouchers')->insert([
            ['id'=>11,'project_id'=>7,'voucher_number'=>'11','date'=>'2026-11-09',
                'reference'=>'OUTSIDE','created_at'=>now(),'updated_at'=>now()],
            ['id'=>12,'project_id'=>7,'voucher_number'=>'12','date'=>'2026-11-12',
                'reference'=>'INSIDE-A','created_at'=>now(),'updated_at'=>now()],
            ['id'=>13,'project_id'=>7,'voucher_number'=>'13','date'=>'2026-11-15',
                'reference'=>'INSIDE-B','created_at'=>now(),'updated_at'=>now()],
            ['id'=>14,'project_id'=>8,'voucher_number'=>'14','date'=>'2026-11-13',
                'reference'=>'FOREIGN','created_at'=>now(),'updated_at'=>now()],
        ]);
        $request=Request::create('/commision-voucher','GET',[
            'draw'=>3,'start'=>0,'length'=>1,
            'fvd_date_from'=>'2026-11-10','fvd_date_to'=>'2026-11-15',
            'fvd_sort_column'=>'date','fvd_sort_direction'=>'asc',
        ]);
        $request->headers->set('X-Requested-With','XMLHttpRequest');
        $request->cookies->set('selected_action','7');
        $this->app->instance('request',$request);

        $payload=app(CommissionVoucherController::class)->index($request)->getData(true);
        $this->assertSame(3,$payload['recordsTotal']);
        $this->assertSame(2,$payload['recordsFiltered']);
        $this->assertCount(1,$payload['data']);
        $this->assertSame(12,$payload['data'][0]['id']);
        $this->assertSame('INSIDE-A',$payload['data'][0]['reference']);
    }

    public function test_ajax_listing_supports_authenticated_user_without_an_assigned_role(): void
    {
        $user=Auth::user();
        $user->setRelation('roles',collect());
        DB::table('commision_vouchers')->insert([
            'id'=>21,'project_id'=>7,'voucher_number'=>'21',
            'reference'=>'ROLELESS','created_at'=>now(),'updated_at'=>now(),
        ]);
        $request=Request::create('/commision-voucher','GET',[
            'draw'=>4,'start'=>0,'length'=>10,
        ]);
        $request->headers->set('X-Requested-With','XMLHttpRequest');
        $request->cookies->set('selected_action','7');
        $this->app->instance('request',$request);

        $payload=app(CommissionVoucherController::class)->index($request)->getData(true);
        $this->assertSame(1,$payload['recordsTotal']);
        $this->assertSame('ROLELESS',$payload['data'][0]['reference']);
    }

    public function test_cv_same_from_and_to_date_excludes_all_other_dates(): void
    {
        DB::table('commision_vouchers')->insert([
            ['id'=>71,'project_id'=>7,'voucher_number'=>'71','date'=>'2026-11-03',
                'created_at'=>now(),'updated_at'=>now()],
            ['id'=>72,'project_id'=>7,'voucher_number'=>'72','date'=>'2026-11-16',
                'created_at'=>now(),'updated_at'=>now()],
        ]);
        $request=Request::create('/commision-voucher','GET',[
            'draw'=>5,'start'=>0,'length'=>25,
            'fvd_date_from'=>'2026-11-03','fvd_date_to'=>'2026-11-03',
        ]);
        $request->headers->set('X-Requested-With','XMLHttpRequest');
        $request->cookies->set('selected_action','7');
        $this->app->instance('request',$request);
        $payload=app(CommissionVoucherController::class)->index($request)->getData(true);
        $this->assertSame(2,$payload['recordsTotal']);
        $this->assertSame(1,$payload['recordsFiltered']);
        $this->assertSame('2026-11-03',$payload['data'][0]['date']);
    }

}
