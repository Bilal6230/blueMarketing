<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankTransferWriter;
use App\Services\Finance\TypedBankVoucherCustody;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Inherits the proven isolated BD/BW ledger fixture, with bank-management intake custody. */
final class TypedBankVoucherCustodyTest extends BankTransferWriterTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('finance_cheque_intakes',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('status');$t->string('direction');
            $t->unsignedBigInteger('account_mapping_id');
            $t->string('cheque_number');$t->decimal('amount',18,2);
        });
        Schema::create('finance_cheque_intake_actions',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('cheque_intake_id');$t->string('action');
            $t->timestamp('verification_confirmed_at')->nullable();
            $t->date('action_date');
        });
        Schema::create('finance_cheque_voucher_claims',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('cheque_intake_id');
        });
        Schema::create('finance_bank_voucher_allocations',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('source_method');$t->unsignedBigInteger('source_id');
            $t->string('voucher_type');$t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);$t->unsignedBigInteger('allocated_by');
            $t->timestamp('allocated_at');$t->timestamps();
            $t->unique(['project_id','voucher_type','voucher_id']);
        });
        Schema::create('finance_bank_voucher_allocation_releases',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('allocation_id')->unique();
            $t->string('source_method');$t->unsignedBigInteger('source_id');
            $t->string('voucher_type');$t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);$t->string('action',24);
            $t->string('reason',500);$t->unsignedBigInteger('released_by');
            $t->timestamp('released_at');$t->timestamps();
        });
        Schema::create('finance_bank_transfer_approval_drafts',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('transfer_kind');$t->unsignedBigInteger('bank_account_id');
            $t->unsignedBigInteger('cash_account_id');
            $t->unsignedBigInteger('linked_customer_ledger_id')->nullable();
            $t->string('passed_source_method')->nullable();
            $t->unsignedBigInteger('passed_source_id')->nullable();
            $t->date('effective_date');$t->decimal('amount',18,2);
            $t->text('narration');$t->string('request_key');
            $t->string('payload_sha256')->nullable();
            $t->unsignedBigInteger('voucher_number');
            $t->string('status');$t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('posting_batch_id')->nullable();$t->timestamps();
        });
        Schema::create('daily_voucher_approvals',function(Blueprint $t):void{
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('source_table');$t->unsignedBigInteger('source_id');
            $t->string('voucher_type');$t->string('status');
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->string('rejection_reason')->nullable();$t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
        DB::table('users')->insert(['id'=>7]);
        DB::table('finance_cheque_intakes')->insert([
            ['id'=>77,'project_id'=>1,'status'=>'cleared','direction'=>'in',
             'account_mapping_id'=>102,'cheque_number'=>'CHQ-77','amount'=>'75.00'],
            ['id'=>78,'project_id'=>1,'status'=>'cleared','direction'=>'out',
             'account_mapping_id'=>102,'cheque_number'=>'CHQ-78','amount'=>'60.00'],
        ]);
        DB::table('finance_cheque_intake_actions')->insert([
            ['project_id'=>1,'cheque_intake_id'=>77,'action'=>'cleared',
             'verification_confirmed_at'=>now(),'action_date'=>'2026-11-23'],
            ['project_id'=>1,'cheque_intake_id'=>78,'action'=>'cleared',
             'verification_confirmed_at'=>now(),'action_date'=>'2026-11-23'],
        ]);
    }

    public function test_verified_bd_claims_exact_incoming_cheque_and_releases_once_without_removing_evidence(): void
    {
        $result=(new BankTransferWriter())->post(
            1,1,'deposit','BANK-VERIFIED-CHEQUE-POST-0001','2026-11-23',
            '75.00','Cleared incoming cheque',
            [['account_id'=>102,'amount'=>'75.00']],7,null,null,'cheque',77
        );
        $batch=(int)$result['posting_batch_id'];
        $this->assertDatabaseHas('finance_bank_voucher_allocations',[
            'project_id'=>1,'source_method'=>'cheque','source_id'=>77,
            'voucher_type'=>'BD','voucher_id'=>$batch,'amount'=>75.00,
        ]);
        $this->assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->whereNotNull('legacy_ledger_id')->count());
        $this->expectException(\DomainException::class);
        try{
            (new BankTransferWriter())->post(
                1,1,'deposit','BANK-VERIFIED-CHEQUE-POST-0002','2026-11-23',
                '1.00','Cannot spend verified cheque twice',
                [['account_id'=>102,'amount'=>'1.00']],7,null,null,'cheque',77
            );
        }finally{
            $this->assertDatabaseCount('finance_bank_voucher_allocations',1);
            $result=app(TypedBankVoucherCustody::class)->release(
                1,'BD',$batch,7,'Management source replaced',false);
            $this->assertSame('posted_void_release',$result['action']);
            $this->assertSame(0,DB::table('finance_posting_lines')
                ->where('posting_batch_id',$batch)->where('is_active',1)->count());
            $this->assertSame(0,DB::table('ledgers')
                ->where('type','BD')->where('type_id',$batch)->where('is_active',1)->count());
            $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')
                ->where('action','posted_void_release')->count());
        }
    }

    public function test_pending_bw_cheque_claim_transfers_to_posted_batch_without_double_spending(): void
    {
        $draftId=DB::table('finance_bank_transfer_approval_drafts')->insertGetId([
            'project_id'=>1,'transfer_kind'=>'withdrawal',
            'bank_account_id'=>1,'cash_account_id'=>102,
            'passed_source_method'=>'cheque','passed_source_id'=>78,
            'effective_date'=>'2026-11-23','amount'=>'60.00',
            'narration'=>'Cleared outgoing cheque','request_key'=>'BANK-PENDING-CHEQUE-BW-0001',
            'voucher_number'=>987,'status'=>'pending','created_by'=>7,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('daily_voucher_approvals')->insert([
            'project_id'=>1,'source_table'=>'finance_bank_transfer_approval_drafts',
            'source_id'=>$draftId,'voucher_type'=>'BW','status'=>'pending',
        ]);
        DB::transaction(function()use($draftId):void{
            app(TypedBankVoucherCustody::class)->reserveDraft(
                1,'BW',$draftId,'cheque',78,'60.00',7);
        });
        $this->assertSame(1,DB::table('finance_bank_voucher_allocations')
            ->where('voucher_type','BW_DRAFT')->count());
        $result=(new BankTransferWriter())->post(
            1,1,'withdrawal','BANK-PENDING-CHEQUE-BW-0001','2026-11-23',
            '60.00','Cleared outgoing cheque',
            [['account_id'=>102,'amount'=>'60.00']],7,null,$draftId,'cheque',78
        );
        $batch=(int)$result['posting_batch_id'];
        $this->assertDatabaseHas('finance_bank_voucher_allocations',[
            'voucher_type'=>'BW','voucher_id'=>$batch,'source_method'=>'cheque','source_id'=>78,
        ]);
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')
            ->where('action','approval_transfer')->count());
        $this->assertSame(1,DB::table('finance_bank_voucher_allocations')
            ->where('voucher_type','BW_DRAFT')->count());
        $this->assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->whereNull('legacy_ledger_id')->count());
    }
}
