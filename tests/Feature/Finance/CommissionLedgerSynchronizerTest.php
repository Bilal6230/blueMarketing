<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\CommissionDraftWorkflow;
use App\Services\Finance\CommissionLedgerSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Every test runs against an isolated SQLite database, never local MySQL. */
final class CommissionLedgerSynchronizerTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite required.');
        }
        $this->previousConnection=(string)config('database.default');
        config(['database.connections.cv_ledgers_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
            'foreign_key_constraints'=>true,
        ],'database.default'=>'cv_ledgers_test']);
        DB::purge('cv_ledgers_test');

        Schema::create('projects',function(Blueprint $t) {
            $t->id();$t->boolean('is_active')->default(1);
        });
        Schema::create('head_accountings',function(Blueprint $t) {
            $t->id();$t->boolean('is_active')->default(1);
        });
        Schema::create('subhead_accountings',function(Blueprint $t) {
            $t->id();$t->boolean('is_active')->default(1);
        });
        Schema::create('project_head_subheads',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('subhead_accounting_id');
        });
        Schema::create('commision_vouchers',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('voucher_number');$t->date('date');
            $t->string('status');$t->string('reference')->nullable();
            $t->string('description')->nullable();
            $t->decimal('total_debit',16,2);$t->decimal('total_credit',16,2);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();$t->softDeletes();
        });
        Schema::create('commision_voucher_details',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('commision_voucher_id');
            $t->unsignedBigInteger('account_id');
            $t->decimal('debit',16,2);$t->decimal('credit',16,2);
            $t->string('description')->nullable();
            $t->timestamps();$t->softDeletes();
        });
        Schema::create('ledgers',function(Blueprint $t) {
            $t->id();$t->string('type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('commision_voucher_detail_id');
            $t->foreign('commision_voucher_detail_id')
                ->references('id')->on('commision_voucher_details')->restrictOnDelete();
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('voucher_number')->nullable();$t->string('reference')->nullable();
            $t->decimal('amount_in',16,2);$t->decimal('amount_out',16,2);
            $t->string('detail')->nullable();$t->date('date');
            $t->string('status');$t->boolean('is_active');$t->unsignedBigInteger('create_by')->nullable();
            $t->timestamps();
        });
        Schema::create('finance_posting_batches',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('project_id');
            $t->timestamp('posted_at')->nullable();
            $t->unsignedBigInteger('reversal_of_batch_id')->nullable();
        });
        Schema::create('finance_posting_commission_sources',function(Blueprint $t) {
            $t->unsignedBigInteger('posting_batch_id');
            $t->unsignedBigInteger('commision_voucher_id');
        });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('head_accountings')->insert(['id'=>1,'is_active'=>1]);
        DB::table('subhead_accountings')->insert(['id'=>1,'is_active'=>1]);
        DB::table('project_head_subheads')->insert([
            ['id'=>101,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
            ['id'=>102,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
            ['id'=>201,'project_id'=>2,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->previousConnection)) {
            config(['database.default'=>$this->previousConnection]);
            DB::purge('cv_ledgers_test');
        }
        parent::tearDown();
    }

    private function lines(string $amount='1000.00'): array
    {
        return [
            ['account_id'=>101,'debit'=>$amount,'credit'=>'0.00','description'=>'Debit'],
            ['account_id'=>102,'debit'=>'0.00','credit'=>$amount,'description'=>'Credit'],
        ];
    }

    public function test_draft_create_and_noop_edit_keep_exact_foreign_keys_and_inactive_ledger_rows(): void
    {
        $draft=new CommissionDraftWorkflow();
        $id=$draft->createDraft(1,'CV-TEST-1','2026-11-18',$this->lines(),1,
            ['reference'=>'REF','description'=>'Draft']);
        $details=DB::table('commision_voucher_details')->where('commision_voucher_id',$id)
            ->whereNull('deleted_at')->orderBy('id')->pluck('id')->all();
        $ledger=DB::table('ledgers')->where('type','CV')->where('type_id',$id)
            ->orderBy('id')->get();
        self::assertCount(2,$details);
        self::assertSame($details,$ledger->pluck('commision_voucher_detail_id')->all());
        self::assertSame([0,0],$ledger->pluck('is_active')->map(fn($v)=>(int)$v)->all());
        $ids=$ledger->pluck('id')->all();

        $draft->updateDraft(1,$id,'2026-11-18',$this->lines(),
            ['reference'=>'REF','description'=>'Draft']);
        self::assertSame($ids,DB::table('ledgers')->where('type','CV')
            ->where('type_id',$id)->orderBy('id')->pluck('id')->all());
        self::assertSame($details,DB::table('commision_voucher_details')
            ->where('commision_voucher_id',$id)->whereNull('deleted_at')
            ->orderBy('id')->pluck('id')->all());
    }

    public function test_draft_edit_retains_old_inactive_evidence_then_approve_activates_once(): void
    {
        $draft=new CommissionDraftWorkflow();
        $id=$draft->createDraft(1,'CV-TEST-2','2026-11-18',$this->lines(),1);
        $oldDetails=DB::table('commision_voucher_details')->where('commision_voucher_id',$id)
            ->pluck('id')->all();
        $draft->updateDraft(1,$id,'2026-11-18',$this->lines('1500.00'));
        self::assertSame(4,DB::table('ledgers')->where('type','CV')->where('type_id',$id)->count());
        self::assertSame(0,DB::table('ledgers')->where('type','CV')
            ->where('type_id',$id)->where('is_active',1)->count());
        self::assertSame(2,DB::table('commision_voucher_details')->where('commision_voucher_id',$id)
            ->whereNull('deleted_at')->count());

        DB::table('commision_vouchers')->where('id',$id)->update(['status'=>'approved']);
        DB::table('finance_posting_batches')->insert(['id'=>22,'project_id'=>1,'posted_at'=>now()]);
        DB::table('finance_posting_commission_sources')->insert([
            'posting_batch_id'=>22,'commision_voucher_id'=>$id,
        ]);
        $sync=new CommissionLedgerSynchronizer();
        $sync->activateApproved(1,$id);
        $sync->activateApproved(1,$id);
        $active=DB::table('ledgers')->where('type','CV')->where('type_id',$id)
            ->where('is_active',1)->get();
        self::assertCount(2,$active);
        self::assertEqualsCanonicalizing(['1500.00'],array_unique(
            $active->pluck('amount_in')->filter(fn($v)=>(float)$v>0)->map(fn($v)=>
                number_format((float)$v,2,'.',''))->all()));
        self::assertSame(0,DB::table('ledgers')->whereIn('commision_voucher_detail_id',$oldDetails)
            ->where('is_active',1)->count());
        $sync->deactivate(1,$id);
        self::assertSame(0,DB::table('ledgers')->where('type','CV')
            ->where('type_id',$id)->where('is_active',1)->count());
    }

    public function test_existing_draft_backfills_only_missing_fk_linked_ledger_rows(): void
    {
        DB::table('commision_vouchers')->insert([
            'id'=>1,'project_id'=>1,'voucher_number'=>'CV-EXISTING',
            'date'=>'2026-11-18','status'=>'draft',
            'total_debit'=>1000,'total_credit'=>1000,
        ]);
        DB::table('commision_voucher_details')->insert([
            ['id'=>11,'commision_voucher_id'=>1,'account_id'=>101,'debit'=>1000,'credit'=>0],
            ['id'=>12,'commision_voucher_id'=>1,'account_id'=>102,'debit'=>0,'credit'=>1000],
        ]);
        $sync=new CommissionLedgerSynchronizer();
        $sync->ensureDraft(1,1);
        $sync->ensureDraft(1,1);
        self::assertSame([11,12],DB::table('ledgers')->where('type','CV')
            ->where('type_id',1)->orderBy('commision_voucher_detail_id')
            ->pluck('commision_voucher_detail_id')->all());
        self::assertSame(2,DB::table('ledgers')->where('type','CV')->where('type_id',1)->count());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('ledgers')->insert([
            'type'=>'CV','type_id'=>1,'commision_voucher_detail_id'=>99999,
            'project_head_subheads_id'=>101,'amount_in'=>1,'amount_out'=>0,
            'date'=>'2026-11-18','status'=>'0','is_active'=>0,
        ]);
    }
    public function test_draft_backfill_command_is_read_only_until_apply_and_idempotent(): void
    {
        DB::table('commision_vouchers')->insert([
            'id'=>3,'project_id'=>1,'voucher_number'=>'CV-OLD-3',
            'date'=>'2026-11-18','status'=>'draft',
            'total_debit'=>500,'total_credit'=>500,
        ]);
        DB::table('commision_voucher_details')->insert([
            ['id'=>31,'commision_voucher_id'=>3,'account_id'=>101,'debit'=>500,'credit'=>0],
            ['id'=>32,'commision_voucher_id'=>3,'account_id'=>102,'debit'=>0,'credit'=>500],
        ]);
        $artisan=\Illuminate\Support\Facades\Artisan::class;
        self::assertSame(0,$artisan::call('finance:sync-commission-draft-ledgers'));
        self::assertSame(0,DB::table('ledgers')->count());
        self::assertSame(0,$artisan::call('finance:sync-commission-draft-ledgers',['--apply'=>true]));
        self::assertSame(2,DB::table('ledgers')->count());
        self::assertSame(0,$artisan::call('finance:sync-commission-draft-ledgers',['--apply'=>true]));
        self::assertSame(2,DB::table('ledgers')->count());
        self::assertSame(0,DB::table('ledgers')->where('is_active',1)->count());
    }

}
