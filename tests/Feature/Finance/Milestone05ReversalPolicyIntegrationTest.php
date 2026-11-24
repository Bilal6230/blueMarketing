<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\PostingReversalWriter;
use App\Services\Finance\VoucherReversalPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class Milestone05ReversalPolicyIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('projects', fn (Blueprint $table) => $table->id());
        Schema::create('daily_voucher_approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('voucher_type', 12);
            $table->boolean('approval_required')->default(false);
            $table->boolean('reversal_enabled')->default(true);
            $table->unique(['project_id','voucher_type']);
        });
        Schema::create('finance_posting_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('voucher_series');
            $table->unsignedBigInteger('voucher_number')->nullable();
            $table->date('effective_date');
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('reversal_of_batch_id')->nullable();
        });
        Schema::create('finance_posting_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('posting_batch_id');
        });
        Schema::create('finance_posting_source_claims', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('posting_batch_id');
            $table->string('source_kind');
        });
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
        });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('finance_posting_batches')->insert([
            'id'=>51,'project_id'=>1,'voucher_series'=>'BD',
            'voucher_number'=>5,'effective_date'=>'2026-11-24','posted_at'=>'2026-11-24 10:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_disabled_policy_rejects_a_direct_reversal_without_creating_postings(): void
    {
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id'=>1,'voucher_type'=>'BD','reversal_enabled'=>false,
        ]);
        $before=DB::table('finance_posting_batches')->count();
        try {
            (new PostingReversalWriter())->reverse(
                1,51,'reversalpolicytest20261124','2026-11-24','Testing reversal',1
            );
            self::fail('A direct reversal must be stopped by the server-side policy.');
        } catch (\DomainException $exception) {
            self::assertSame('VOUCHER_REVERSAL_DISABLED',$exception->getMessage());
        }
        self::assertSame($before,DB::table('finance_posting_batches')->count());
        self::assertDatabaseMissing('finance_posting_batches',['reversal_of_batch_id'=>51]);
    }

    public function test_policy_isolated_by_project_and_voucher_series(): void
    {
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id'=>1,'voucher_type'=>'BD','reversal_enabled'=>false,
        ]);
        $policy=new VoucherReversalPolicy();
        self::assertFalse($policy->enabled(1,'BD'));
        self::assertTrue($policy->enabled(1,'BW'));
        self::assertTrue($policy->enabled(2,'BD'));
        self::assertTrue($policy->enabled(1,'PPR'));
        DB::table('daily_voucher_approval_policies')->insert([
            ['project_id'=>1,'voucher_type'=>'BOR','reversal_enabled'=>false],
            ['project_id'=>1,'voucher_type'=>'BOP','reversal_enabled'=>false],
        ]);
        self::assertFalse($policy->enabled(1,'CHR'));
        self::assertFalse($policy->enabled(1,'CHP'));
        self::assertTrue($policy->enabled(2,'CHR'));
        self::assertFalse($policy->enabled(1,'REV'));
        self::assertFalse($policy->enabled(0,'BD'));
    }

    public function test_plot_receipt_pr_batch_obeys_ppr_switch_for_direct_api_reversal(): void
    {
        DB::table('finance_posting_batches')->insert([
            'id'=>52,'project_id'=>1,'voucher_series'=>'PR',
            'voucher_number'=>8,'effective_date'=>'2026-11-24','posted_at'=>'2026-11-24 10:00:00',
        ]);
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id'=>1,'voucher_type'=>'PPR','reversal_enabled'=>false,
        ]);
        self::assertFalse((new VoucherReversalPolicy())->enabled(1,'PR'));
        self::assertFalse((new VoucherReversalPolicy())->enabled(1,'PPR'));
        self::assertTrue((new VoucherReversalPolicy())->enabled(2,'PR'));
        $before=DB::table('finance_posting_batches')->count();
        try {
            (new PostingReversalWriter())->reverse(
                1,52,'plotreceiptm05disable2026','2026-11-24','Test disabled plot receipt',1
            );
            self::fail('PPR OFF must reject a direct PR posting reversal.');
        } catch (\DomainException $exception) {
            self::assertSame('VOUCHER_REVERSAL_DISABLED',$exception->getMessage());
        }
        self::assertSame($before,DB::table('finance_posting_batches')->count());
    }

    public function test_existing_reversal_defaults_to_on_without_a_policy_row(): void
    {
        $policy=new VoucherReversalPolicy();
        self::assertTrue($policy->enabled(1,'BD'));
        self::assertTrue($policy->enabled(2,'PP'));
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id'=>1,'voucher_type'=>'BD','reversal_enabled'=>true,
        ]);
        self::assertTrue($policy->enabled(1,'BD'));
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','BD')
            ->update(['reversal_enabled'=>false]);
        self::assertFalse($policy->enabled(1,'BD'));
    }
}
