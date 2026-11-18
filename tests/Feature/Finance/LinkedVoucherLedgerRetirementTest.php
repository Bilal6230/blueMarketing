<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\LinkedVoucherLedgerRetirement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the real transactional service on ephemeral in-memory SQLite,
 * NOT the configured MySQL project database.
 */
final class LinkedVoucherLedgerRetirementTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite required for isolated ledger lifecycle test.');
        }
        $this->previousConnection=(string)config('database.default');
        config(['database.connections.voucher_retirement_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
            'foreign_key_constraints'=>true,
        ],'database.default'=>'voucher_retirement_test']);
        DB::purge('voucher_retirement_test');
        Schema::create('project_head_subheads',function(Blueprint $t){$t->id();$t->unsignedBigInteger('project_id');});
        foreach (['journal_vouchers','commision_vouchers'] as $table) {
            Schema::create($table,function(Blueprint $t){$t->id();$t->unsignedBigInteger('project_id');$t->softDeletes();});
        }
        Schema::create('journal_voucher_details',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('journal_voucher_id');
            $t->unsignedBigInteger('account_id');$t->decimal('debit');$t->decimal('credit');$t->softDeletes();
        });
        Schema::create('commision_voucher_details',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('commision_voucher_id');
            $t->unsignedBigInteger('account_id');$t->decimal('debit');$t->decimal('credit');$t->softDeletes();
        });
        Schema::create('ledgers',function(Blueprint $t){
            $t->id();$t->string('type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->unsignedBigInteger('journal_voucher_detail_id')->nullable();
            $t->unsignedBigInteger('commision_voucher_detail_id')->nullable();
            $t->decimal('amount_in');$t->decimal('amount_out');
            $t->boolean('is_active');$t->timestamps();
        });
        DB::table('project_head_subheads')->insert([['id'=>10,'project_id'=>5],['id'=>11,'project_id'=>5],['id'=>12,'project_id'=>9]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->previousConnection)) {
            config(['database.default'=>$this->previousConnection]);
            DB::purge('voucher_retirement_test');
        }
        parent::tearDown();
    }

    private function seedVoucher(string $series): void
    {
        $head=$series==='JV'?'journal_vouchers':'commision_vouchers';
        $details=$series==='JV'?'journal_voucher_details':'commision_voucher_details';
        $fk=$series==='JV'?'journal_voucher_id':'commision_voucher_id';
        DB::table($head)->insert(['id'=>33,'project_id'=>5]);
        DB::table($details)->insert([
            ['id'=>101,$fk=>33,'account_id'=>10,'debit'=>50,'credit'=>0],
            ['id'=>102,$fk=>33,'account_id'=>11,'debit'=>0,'credit'=>50],
        ]);
        DB::table('ledgers')->insert([
            ['id'=>201,'type'=>$series,'type_id'=>33,'project_head_subheads_id'=>10,
                'journal_voucher_detail_id'=>$series==='JV'?101:null,
                'commision_voucher_detail_id'=>$series==='CV'?101:null,
                'amount_in'=>50,'amount_out'=>0,'is_active'=>1],
            ['id'=>202,'type'=>$series,'type_id'=>33,'project_head_subheads_id'=>11,
                'journal_voucher_detail_id'=>$series==='JV'?102:null,
                'commision_voucher_detail_id'=>$series==='CV'?102:null,
                'amount_in'=>0,'amount_out'=>50,'is_active'=>1],
        ]);
    }

    public function test_jv_and_cv_retire_exact_ledgers_without_adding_reversals_or_deleting_rows(): void
    {
        foreach (['JV','CV'] as $series) {
            $this->seedVoucher($series);
            $count=DB::transaction(fn()=>app(LinkedVoucherLedgerRetirement::class)->retire(5,$series,33));
            self::assertSame(2,$count);
            self::assertSame(2,DB::table('ledgers')->where('type',$series)->where('type_id',33)->count());
            self::assertSame(0,DB::table('ledgers')->where('type',$series)->where('type_id',33)->where('is_active',1)->count());
            DB::table('ledgers')->delete();
            DB::table($series==='JV'?'journal_voucher_details':'commision_voucher_details')->delete();
            DB::table($series==='JV'?'journal_vouchers':'commision_vouchers')->delete();
        }
    }

    public function test_cross_project_leg_aborts_without_changing_any_ledgers(): void
    {
        $this->seedVoucher('JV');
        DB::table('ledgers')->where('id',202)->update(['project_head_subheads_id'=>12]);
        try {
            DB::transaction(fn()=>app(LinkedVoucherLedgerRetirement::class)->retire(5,'JV',33));
            self::fail('Cross-project voucher should be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('cross-project',$exception->getMessage());
        }
        self::assertSame(2,DB::table('ledgers')->where('is_active',1)->count());
    }

    public function test_amount_mismatch_aborts_without_changing_any_ledgers(): void
    {
        $this->seedVoucher('CV');
        DB::table('ledgers')->where('id',201)->update(['amount_in'=>51]);
        try {
            DB::transaction(fn()=>app(LinkedVoucherLedgerRetirement::class)->retire(5,'CV',33));
            self::fail('Mismatched voucher should be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('mismatch',$exception->getMessage());
        }
        self::assertSame(2,DB::table('ledgers')->where('is_active',1)->count());
    }
    public function test_commission_wrong_detail_link_rejects_retirement_even_when_amounts_match(): void
    {
        $this->seedVoucher('CV');
        DB::table('ledgers')->where('id',202)->update(['commision_voucher_detail_id'=>101]);
        try {
            DB::transaction(fn()=>app(LinkedVoucherLedgerRetirement::class)->retire(5,'CV',33));
            self::fail('Duplicate CV detail identity must be refused.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('duplicate voucher detail',$e->getMessage());
        }
        self::assertSame(2,DB::table('ledgers')->where('is_active',1)->count());
        self::assertSame(2,DB::table('commision_voucher_details')->whereNull('deleted_at')->count());
    }

    public function test_commission_swapped_detail_links_reject_even_if_voucher_total_balances(): void
    {
        $this->seedVoucher('CV');
        DB::table('ledgers')->where('id',201)->update(['commision_voucher_detail_id'=>102]);
        DB::table('ledgers')->where('id',202)->update(['commision_voucher_detail_id'=>101]);
        try {
            DB::transaction(fn()=>app(LinkedVoucherLedgerRetirement::class)->retire(5,'CV',33));
            self::fail('Swapped CV detail identifiers must not be accepted.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('detail account or amount mismatch',$e->getMessage());
        }
        self::assertSame(2,DB::table('ledgers')->where('is_active',1)->count());
    }

    public function test_legacy_null_commission_detail_links_remain_retirable_by_exact_amounts(): void
    {
        $this->seedVoucher('CV');
        DB::table('ledgers')->where('type','CV')
            ->update(['commision_voucher_detail_id'=>null]);
        self::assertSame(2,DB::transaction(fn()=>app(LinkedVoucherLedgerRetirement::class)->retire(5,'CV',33)));
        self::assertSame(0,DB::table('ledgers')->where('is_active',1)->count());
    }

}
