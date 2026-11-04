<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\AccountingController;
use App\Models\AccountGroup;
use App\Services\Finance\AccountGroupMergePreview;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountGroupMergePreviewTest extends TestCase
{
    public function test_preview_reconciles_selected_project_without_touching_ledgers(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $t): void { $t->id(); $t->string('name'); });
        Schema::create('account_groups', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('code'); $t->string('name'); $t->boolean('is_active'); $t->timestamps();
        });
        Schema::create('head_accountings', function (Blueprint $t): void {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('acct_type');
        });
        Schema::create('project_head_account_groups', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('account_group_id'); $t->timestamps();
        });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('subhead_accounting_id');
        });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_head_subheads_id');
            $t->decimal('amount_in', 14, 2); $t->decimal('amount_out', 14, 2); $t->boolean('is_active');
        });
        Schema::create('account_group_audits', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('account_group_id'); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('head_accounting_id')->nullable(); $t->string('event');
            $t->json('before_data')->nullable(); $t->json('after_data')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable(); $t->timestamp('changed_at');
        });
        DB::table('projects')->insert([['id' => 5, 'name' => 'Five'], ['id' => 6, 'name' => 'Six']]);
        DB::table('account_groups')->insert([
            ['id' => 1, 'project_id' => 5, 'code' => 'SRC', 'name' => 'Source', 'is_active' => 1],
            ['id' => 2, 'project_id' => 5, 'code' => 'DST', 'name' => 'Target', 'is_active' => 1],
            ['id' => 3, 'project_id' => 6, 'code' => 'OTH', 'name' => 'Other', 'is_active' => 1],
        ]);
        DB::table('head_accountings')->insert([['id' => 10, 'name' => 'Cash', 'acct_type' => 1], ['id' => 20, 'name' => 'Bank', 'acct_type' => 1]]);
        DB::table('project_head_account_groups')->insert([
            ['project_id' => 5, 'head_accounting_id' => 10, 'account_group_id' => 1],
            ['project_id' => 5, 'head_accounting_id' => 20, 'account_group_id' => 2],
        ]);
        DB::table('project_head_subheads')->insert([
            ['id' => 100, 'project_id' => 5, 'head_accounting_id' => 10, 'subhead_accounting_id' => 1],
            ['id' => 200, 'project_id' => 6, 'head_accounting_id' => 10, 'subhead_accounting_id' => 2],
        ]);
        DB::table('ledgers')->insert([
            ['project_head_subheads_id' => 100, 'amount_in' => '12.50', 'amount_out' => '2.25', 'is_active' => 1],
            ['project_head_subheads_id' => 200, 'amount_in' => '999.00', 'amount_out' => '0.00', 'is_active' => 1],
        ]);

        $preview = (new AccountGroupMergePreview())->build(5, 1, 2);
        $this->assertTrue($preview['can_apply']);
        $this->assertSame(['mappings' => 1, 'ledger_rows' => 1, 'amount_in_cents' => 1250, 'amount_out_cents' => 225], $preview['project_totals']);
        $this->assertDatabaseCount('ledgers', 2);
        try {
            (new AccountGroupMergePreview())->build(5, 1, 3);
            $this->fail('Foreign project target was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        Route::get('/test-account-groups', fn () => 'ok')->name('accounting.groups');
        $request = Request::create('/', 'POST', ['target_group_id' => 2, 'confirm_merge' => '1'], ['selected_action' => 5]);
        $this->app->instance('request', $request);
        $response = (new AccountingController())->account_group_merge_apply($request, AccountGroup::findOrFail(1));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('project_head_account_groups', ['project_id' => 5, 'head_accounting_id' => 10, 'account_group_id' => 2]);
        $this->assertDatabaseHas('account_groups', ['id' => 1, 'is_active' => 0]);
        $this->assertDatabaseHas('ledgers', ['project_head_subheads_id' => 100, 'amount_in' => '12.5', 'amount_out' => '2.25']);
        $this->assertDatabaseHas('ledgers', ['project_head_subheads_id' => 200, 'amount_in' => '999', 'amount_out' => '0']);
        $this->assertDatabaseCount('ledgers', 2);
        $this->assertDatabaseHas('account_group_audits', ['project_id' => 5, 'account_group_id' => 1, 'event' => 'merge_applied']);
        try {
            (new AccountingController())->account_group_merge_apply($request, AccountGroup::findOrFail(1));
            $this->fail('The completed merge was replayed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('ledgers', 2);
        $this->assertDatabaseHas('account_group_audits', ['project_id' => 5, 'account_group_id' => 1, 'event' => 'merge_applied']);

        DB::table('account_groups')->insert(['id' => 4, 'project_id' => 5, 'code' => 'DUP', 'name' => 'Duplicate', 'is_active' => 1]);
        DB::table('head_accountings')->insert(['id' => 30, 'name' => 'Duplicate Head', 'acct_type' => 1]);
        DB::table('project_head_account_groups')->insert(['project_id' => 5, 'head_accounting_id' => 30, 'account_group_id' => 4]);
        DB::table('project_head_subheads')->insert([
            ['id' => 301, 'project_id' => 5, 'head_accounting_id' => 30, 'subhead_accounting_id' => 9],
            ['id' => 302, 'project_id' => 5, 'head_accounting_id' => 30, 'subhead_accounting_id' => 9],
        ]);
        $conflict = (new AccountGroupMergePreview())->build(5, 4, 2);
        $this->assertFalse($conflict['can_apply']);
        $this->assertNotEmpty($conflict['duplicates']);
    }
}
