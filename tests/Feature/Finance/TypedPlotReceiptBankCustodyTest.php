<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\PlotEntryWriter;
use App\Services\Finance\TypedPlotReceiptBankCustody;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reuses the exact in-memory typed Plot writer fixture, never local client DB.
 * Inherited PlotEntryWriterTest regressions also run with custody tables.
 */
class TypedPlotReceiptBankCustodyTest extends PlotEntryWriterTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('project_bank_accounts',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('account_mapping_id');$t->boolean('is_active')->default(true);
        });
        Schema::create('finance_cheque_intakes',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('status');
            $t->string('direction');$t->unsignedBigInteger('account_mapping_id');
            $t->string('cheque_number');$t->decimal('amount',18,2);
        });
        Schema::create('finance_cheque_intake_actions',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('cheque_intake_id');$t->string('action');
            $t->timestamp('verification_confirmed_at')->nullable();$t->date('action_date');
        });
        Schema::create('finance_cheque_voucher_claims',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('cheque_intake_id');
        });
        Schema::create('finance_bank_voucher_allocations',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('source_method');
            $t->unsignedBigInteger('source_id');$t->string('voucher_type');
            $t->unsignedBigInteger('voucher_id');$t->decimal('amount',18,2);
            $t->unsignedBigInteger('allocated_by');$t->timestamp('allocated_at');$t->timestamps();
            $t->unique(['project_id','voucher_type','voucher_id']);
        });
        Schema::create('finance_bank_voucher_allocation_releases',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('allocation_id')->unique();
            $t->string('source_method');$t->unsignedBigInteger('source_id');
            $t->string('voucher_type');$t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);$t->string('action');
            $t->string('reason');$t->unsignedBigInteger('released_by');
            $t->timestamp('released_at');$t->timestamps();
        });
        DB::table('project_bank_accounts')->insert([
            'id'=>1,'project_id'=>1,'account_mapping_id'=>101,'is_active'=>1]);
        DB::table('finance_cheque_intakes')->insert([
            'id'=>77,'project_id'=>1,'status'=>'cleared','direction'=>'in',
            'account_mapping_id'=>102,'cheque_number'=>'PPR-TYPED-CHQ77','amount'=>'70.00']);
        DB::table('finance_cheque_intake_actions')->insert([
            'project_id'=>1,'cheque_intake_id'=>77,'action'=>'cleared',
            'verification_confirmed_at'=>now(),'action_date'=>'2026-11-23',
        ]);
    }

    private function typedPayload(string $number='PPR-1'): array
    {
        return [
            'date'=>'2026-11-23','receipt_number'=>$number,
            'amount'=>'70.00','cash_account_id'=>101,
            'narration'=>'Multi-plot verified cheque receipt',
            'payment_type'=>3,'passed_source_method'=>'cheque','passed_source_id'=>77,
            'allocations'=>[[
                'booking_id'=>31,'plot_id'=>11,
                'receivable_account_id'=>102,'amount'=>'70.00',
            ]],
        ];
    }

    public function test_verified_multi_plot_ppr_posts_balanced_bank_receivable_and_releases_once(): void
    {
        $posted=(new PlotEntryWriter())->receive(1,5,
            'PPR-TYPED-CHQ-POST-00000001',$this->typedPayload(),7);
        $id=(int)$posted['source_id'];
        $batch=(int)$posted['posting_batch_id'];
        $this->assertDatabaseHas('finance_bank_voucher_allocations',[
            'project_id'=>1,'voucher_type'=>TypedPlotReceiptBankCustody::POSTED,
            'voucher_id'=>$id,'source_method'=>'cheque','source_id'=>77,'amount'=>70.00,
        ]);
        $this->assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->count());
        $this->assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->whereNull('legacy_ledger_id')->count());
        try{
            (new PlotEntryWriter())->receive(1,5,
                'PPR-TYPED-CHQ-DOUBLE-000002',$this->typedPayload('PPR-2'),7);
            $this->fail('Previously fully allocated cleared cheque must not fund another PPR.');
        }catch(\DomainException $error){
            $this->assertStringContainsString('remaining verified bank balance',$error->getMessage());
        }
        $released=app(TypedPlotReceiptBankCustody::class)->release(
            1,TypedPlotReceiptBankCustody::POSTED,$id,7,
            'Bank clearance source reverified');
        $this->assertSame('posted_void_release',$released['action']);
        $this->assertSame('PPR-1',$released['voucher_number']);
        $this->assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->count());
        $this->assertSame(0,DB::table('ledgers')
            ->where('type','PPR')->where('type_id',$id)->where('is_active',1)->count());
        $this->assertSame(0,DB::table('finance_plot_receipts')
            ->where('id',$id)->where('is_active',1)->count());
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')
            ->where('action','posted_void_release')->count());
        $this->expectException(\DomainException::class);
        app(TypedPlotReceiptBankCustody::class)->release(
            1,TypedPlotReceiptBankCustody::POSTED,$id,7,'Second release must be blocked');
    }

    public function test_typed_verified_receipt_rejects_cash_account_not_backed_by_bank_profile(): void
    {
        DB::table('project_bank_accounts')->where('id',1)->update(['is_active'=>0]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('PPR_TYPED_BANK_DEBIT_ACCOUNT_REQUIRED');
        (new PlotEntryWriter())->receive(1,5,
            'PPR-TYPED-NO-BANK-00000001',$this->typedPayload(),7);
    }
}
