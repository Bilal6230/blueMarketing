<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankSourceLinkedVoucherHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BankSourceLinkedVoucherHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('finance_bank_voucher_allocations',function(Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('source_method');
            $t->unsignedBigInteger('source_id');
            $t->string('voucher_type');
            $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);
            $t->timestamp('allocated_at');
        });
        Schema::create('finance_bank_voucher_allocation_releases',function(Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('allocation_id');
            $t->string('action');
            $t->string('reason');
            $t->unsignedBigInteger('released_by');
            $t->timestamp('released_at');
        });
        Schema::create('daily_voucher_approvals',function(Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('source_table');
            $t->unsignedBigInteger('source_id');
            $t->string('voucher_type');
            $t->string('status');
            $t->timestamp('decided_at')->nullable();
        });
        Schema::create('project_voucher_display_numbers',function(Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('source_kind');
            $t->unsignedBigInteger('source_id');
            $t->string('series');
            $t->string('display_number');
        });
        DB::table('finance_bank_voucher_allocations')->insert([
            ['id'=>11,'project_id'=>1,'source_method'=>'online','source_id'=>7,'voucher_type'=>'BOR','voucher_id'=>20,'amount'=>400.00,'allocated_at'=>now()],
            ['id'=>12,'project_id'=>1,'source_method'=>'online','source_id'=>7,'voucher_type'=>'BOR','voucher_id'=>21,'amount'=>600.00,'allocated_at'=>now()],
            ['id'=>13,'project_id'=>2,'source_method'=>'online','source_id'=>7,'voucher_type'=>'BOR','voucher_id'=>22,'amount'=>900.00,'allocated_at'=>now()],
            ['id'=>14,'project_id'=>1,'source_method'=>'cheque','source_id'=>7,'voucher_type'=>'BOP','voucher_id'=>23,'amount'=>50.00,'allocated_at'=>now()],
        ]);
        DB::table('project_voucher_display_numbers')->insert([
            'project_id'=>1,'source_kind'=>'customer_ledger','source_id'=>20,'series'=>'BOR','display_number'=>'BOR-20',
        ]);
        DB::table('daily_voucher_approvals')->insert([
            'project_id'=>1,'source_table'=>'customer_ledger','source_id'=>20,
            'voucher_type'=>'BOR','status'=>'approved','decided_at'=>now(),
        ]);
        DB::table('finance_bank_voucher_allocation_releases')->insert([
            'project_id'=>1,'allocation_id'=>11,'action'=>'posted_void_release',
            'reason'=>'Audited void','released_by'=>2,'released_at'=>now(),
        ]);
    }

    public function test_history_shows_allocation_and_release_evidence_without_erasing_voucher(): void
    {
        $rows=app(BankSourceLinkedVoucherHistory::class)->forSource(1,'online',7);
        $this->assertCount(2,$rows);
        $this->assertSame('BOR-20',$rows[0]['voucher_number']);
        $this->assertSame('approved',$rows[0]['approval_status']);
        $this->assertSame('posted_void_release',$rows[0]['release_action']);
        $this->assertTrue($rows[0]['released']);
        $this->assertSame('Audited void',$rows[0]['release_reason']);
        $this->assertFalse($rows[1]['released']);
    }

    public function test_history_is_scoped_by_project_and_source_method(): void
    {
        $history=app(BankSourceLinkedVoucherHistory::class);
        $this->assertCount(2,$history->forSource(1,'online',7));
        $this->assertCount(1,$history->forSource(2,'online',7));
        $this->assertCount(1,$history->forSource(1,'cheque',7));
        $this->assertSame([],$history->forSource(1,'online',8));
    }

    public function test_invalid_source_method_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(BankSourceLinkedVoucherHistory::class)->forSource(1,'cash',7);
    }
}
