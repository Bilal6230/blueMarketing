<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\LedgerVoucherDisplayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LinkedVoucherNumberDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('This financial fixture requires isolated SQLite :memory:.');
        }
        Schema::dropAllTables();
        Schema::create('project_head_subheads', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('ledgers', function(Blueprint $t) {
            $t->id(); $t->string('type'); $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('voucher_number');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->unsignedBigInteger('customer_ledger_id')->nullable();
            $t->unsignedBigInteger('journal_voucher_detail_id')->nullable();
            $t->unsignedBigInteger('commision_voucher_detail_id')->nullable();
        });
        Schema::create('customer_ledger', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('transaction_type'); $t->unsignedBigInteger('type_id');
        });
        Schema::create('booking_vouchers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('customer_ledger_id');
            $t->string('voucher_series'); $t->unsignedBigInteger('voucher_number');
        });
        Schema::create('journal_vouchers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('type'); $t->unsignedBigInteger('voucher_number');
        });
        Schema::create('journal_voucher_details', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('journal_voucher_id');
        });
        Schema::create('commision_vouchers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('voucher_number');
        });
        Schema::create('commision_voucher_details', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commision_voucher_id');
        });
        DB::table('project_head_subheads')->insert([
            ['id'=>7,'project_id'=>3], ['id'=>8,'project_id'=>4],
        ]);
    }

    public function test_customer_source_ledger_uses_actual_type_and_series_without_cross_project_collisions(): void
    {
        DB::table('customer_ledger')->insert([
            ['id'=>10,'project_id'=>3,'transaction_type'=>'BOR','type_id'=>6],
            ['id'=>11,'project_id'=>3,'transaction_type'=>'BOP','type_id'=>2],
            ['id'=>12,'project_id'=>3,'transaction_type'=>'EC','type_id'=>4],
            ['id'=>13,'project_id'=>4,'transaction_type'=>'BOR','type_id'=>9],
        ]);
        DB::table('ledgers')->insert([
            ['id'=>21,'project_head_subheads_id'=>7,'type'=>'BOR','type_id'=>6,'voucher_number'=>99,'customer_ledger_id'=>10],
            ['id'=>22,'project_head_subheads_id'=>7,'type'=>'BOP','type_id'=>2,'voucher_number'=>200,'customer_ledger_id'=>11],
            ['id'=>23,'project_head_subheads_id'=>7,'type'=>'EC','type_id'=>4,'voucher_number'=>300,'customer_ledger_id'=>12],
            ['id'=>24,'project_head_subheads_id'=>8,'type'=>'BOR','type_id'=>9,'voucher_number'=>444,'customer_ledger_id'=>13],
        ]);
        $service = new LedgerVoucherDisplayService();
        self::assertSame('BOR-6',$service->display(DB::table('ledgers')->find(21),3));
        self::assertSame('BOP-2',$service->display(DB::table('ledgers')->find(22),3));
        self::assertSame('EC-4',$service->display(DB::table('ledgers')->find(23),3));
        self::assertSame('BOR-9',$service->display(DB::table('ledgers')->find(24),4));
        self::assertSame('BOR-444',$service->display(DB::table('ledgers')->find(24),3));
    }

    public function test_booking_journal_sales_and_commission_detail_fks_use_real_header_numbers(): void
    {
        DB::table('customer_ledger')->insert([
            'id'=>15,'project_id'=>3,'transaction_type'=>'PPR','type_id'=>53,
        ]);
        DB::table('booking_vouchers')->insert([
            'id'=>70,'project_id'=>3,'customer_ledger_id'=>15,
            'voucher_series'=>'PPR','voucher_number'=>54,
        ]);
        DB::table('journal_vouchers')->insert([
            ['id'=>31,'project_id'=>3,'type'=>'JV','voucher_number'=>128],
            ['id'=>32,'project_id'=>3,'type'=>'SV','voucher_number'=>4],
        ]);
        DB::table('journal_voucher_details')->insert([
            ['id'=>41,'journal_voucher_id'=>31],['id'=>42,'journal_voucher_id'=>32],
        ]);
        DB::table('commision_vouchers')->insert([
            'id'=>51,'project_id'=>3,'voucher_number'=>'5',
        ]);
        DB::table('commision_voucher_details')->insert([
            'id'=>52,'commision_voucher_id'=>51,
        ]);
        // Laravel bulk-insert sorts associative keys; every row needs the
        // same columns, otherwise the FK can be written into the id column.
        DB::table('ledgers')->insert([
            ['id'=>30,'project_head_subheads_id'=>7,'type'=>'PPR','type_id'=>53,'voucher_number'=>500,
                'customer_ledger_id'=>15,'journal_voucher_detail_id'=>null,'commision_voucher_detail_id'=>null],
            ['id'=>31,'project_head_subheads_id'=>7,'type'=>'JV','type_id'=>31,'voucher_number'=>900,
                'customer_ledger_id'=>null,'journal_voucher_detail_id'=>41,'commision_voucher_detail_id'=>null],
            ['id'=>32,'project_head_subheads_id'=>7,'type'=>'SV','type_id'=>32,'voucher_number'=>900,
                'customer_ledger_id'=>null,'journal_voucher_detail_id'=>42,'commision_voucher_detail_id'=>null],
            ['id'=>33,'project_head_subheads_id'=>7,'type'=>'CV','type_id'=>51,'voucher_number'=>900,
                'customer_ledger_id'=>null,'journal_voucher_detail_id'=>null,'commision_voucher_detail_id'=>52],
        ]);
        $service = new LedgerVoucherDisplayService();
        self::assertSame('PPR-54',$service->display(DB::table('ledgers')->find(30),3));
        self::assertSame('JV-0000128',$service->display(DB::table('ledgers')->find(31),3));
        self::assertSame('SV-4',$service->display(DB::table('ledgers')->find(32),3));
        self::assertSame('CV-0000005',$service->display(DB::table('ledgers')->find(33),3));
    }
}
