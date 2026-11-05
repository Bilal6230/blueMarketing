<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\AccountingController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InactiveAccountingWriteTest extends TestCase
{
    public function test_status_change_rolls_back_when_audit_cannot_be_written(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('account_types', function (Blueprint $t): void { $t->id(); });
        Schema::create('head_accountings', function (Blueprint $t): void {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('acct_type');
            $t->boolean('is_active'); $t->timestamps();
        });
        DB::table('account_types')->insert(['id' => 1]);
        DB::table('head_accountings')->insert(['id' => 2, 'name' => 'Cash', 'acct_type' => 1, 'is_active' => 1]);
        $user = new User(); $user->id = 7; auth()->setUser($user);
        $request = Request::create('/', 'POST', [
            'id' => 2, 'name' => 'Cash changed', 'acct_type' => 1,
            'is_active' => 0, 'status_change_reason' => 'Closing account',
        ]);
        $this->app->instance('request', $request);
        (new AccountingController())->head_update($request);
        $this->assertDatabaseHas('head_accountings', ['id' => 2, 'name' => 'Cash', 'is_active' => 1]);
    }

    public function test_forged_inactive_or_foreign_account_cannot_create_expense(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('head_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active'); });
        Schema::create('subhead_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active'); });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('subhead_accounting_id'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('accounting_expenses', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_head_subheads_id'); $t->decimal('price', 12, 2);
            $t->text('detail'); $t->boolean('cash_in'); $t->unsignedBigInteger('create_by'); $t->timestamps();
        });
        DB::table('head_accountings')->insert([['id' => 1, 'is_active' => 1], ['id' => 2, 'is_active' => 0]]);
        DB::table('subhead_accountings')->insert(['id' => 1, 'is_active' => 1]);
        DB::table('project_head_subheads')->insert([
            ['id' => 10, 'project_id' => 5, 'head_accounting_id' => 1, 'subhead_accounting_id' => 1],
            ['id' => 20, 'project_id' => 5, 'head_accounting_id' => 2, 'subhead_accounting_id' => 1],
            ['id' => 30, 'project_id' => 6, 'head_accounting_id' => 1, 'subhead_accounting_id' => 1],
        ]);
        $user = new User(); $user->id = 7; auth()->setUser($user);
        foreach ([['head' => 2, 'project' => 5], ['head' => 1, 'project' => 6]] as $case) {
            $request = Request::create('/', 'POST', [
                'price' => '100.00', 'detail' => 'Forged account', 'accounts_id' => $case['head'],
                'subaccounts_id' => 1, 'projects_id' => $case['project'],
            ], ['selected_action' => 5]);
            $this->app->instance('request', $request);
            try {
                (new AccountingController())->store($request);
                $this->fail('Forged account was accepted.');
            } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame($case['project'] === 5 ? 422 : 403,
                    $e instanceof ValidationException ? $e->status : $e->getStatusCode());
            }
            $this->assertDatabaseCount('accounting_expenses', 0);
        }
    }
}
