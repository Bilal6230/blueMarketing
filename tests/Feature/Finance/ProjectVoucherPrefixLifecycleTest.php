<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\ProjectVoucherDisplayIdentity;
use App\Services\Finance\ProjectVoucherPrefixCounter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProjectVoucherPrefixLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default','sqlite');
        config()->set('database.connections.sqlite.database',':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('projects',function(Blueprint $t){$t->id();});
        Schema::create('customer_ledger',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('transaction_type',12);$t->unsignedBigInteger('type_id');
        });
        Schema::create('project_voucher_display_settings',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('series',4);
            $t->string('prefix',12);$t->timestamps();
            $t->unique(['project_id','series']);
        });
        Schema::create('project_voucher_prefix_counters',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('series',4);
            $t->string('prefix',12);$t->unsignedBigInteger('next_number');
            $t->unsignedBigInteger('issued_count')->default(0);$t->timestamps();
            $t->unique(['project_id','series','prefix']);
        });
        Schema::create('project_voucher_display_numbers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('source_kind',24);
            $t->unsignedBigInteger('source_id');$t->string('series',4);
            $t->unsignedSmallInteger('financial_year');$t->string('display_number',40);
            $t->timestamp('created_at')->nullable();
            $t->unique(['project_id','source_kind','source_id']);
            $t->unique(['project_id','display_number']);
        });
        DB::table('projects')->insert(['id'=>3]);
        DB::table('customer_ledger')->insert(['id'=>100,'project_id'=>3,
            'transaction_type'=>'CR','type_id'=>831]);
    }

    private function setPrefix(string $prefix, ?int $start=null): array
    {
        return DB::transaction(function()use($prefix,$start){
            DB::table('projects')->where('id',3)->lockForUpdate()->firstOrFail();
            $counter=app(ProjectVoucherPrefixCounter::class);
            $old=DB::table('project_voucher_display_settings')
                ->where('project_id',3)->where('series','CR')->value('prefix') ?: 'CR';
            if ($old!==$prefix) $counter->configure(3,'CR',$old,null);
            $state=$counter->configure(3,'CR',$prefix,$start);
            DB::table('project_voucher_display_settings')->updateOrInsert(
                ['project_id'=>3,'series'=>'CR'],['prefix'=>$prefix]);
            return $state;
        });
    }

    private function makeSource(int $id,int $number): string
    {
        return DB::transaction(function()use($id,$number){
            DB::table('customer_ledger')->insert(['id'=>$id,'project_id'=>3,
                'transaction_type'=>'CR','type_id'=>$number]);
            return app(ProjectVoucherDisplayIdentity::class)->assign(
                3,'customer_ledger',$id,'CR','2026-11-28');
        });
    }

    public function test_old_prefix_resumes_at_832_after_multiple_custom_series(): void
    {
        self::assertSame(832,app(ProjectVoucherPrefixCounter::class)
            ->status(3,'CR','CR')['next_number']);
        self::assertSame(1,$this->setPrefix('CRV')['next_number']);
        self::assertSame('CRV-1',$this->makeSource(101,832));
        self::assertSame('CRV-2',$this->makeSource(102,833));
        self::assertSame(50,$this->setPrefix('CRVS',50)['next_number']);
        self::assertSame('CRVS-50',$this->makeSource(103,834));
        self::assertSame(3,$this->setPrefix('CRV')['next_number']);
        self::assertSame('CRV-3',$this->makeSource(104,835));
        self::assertSame(832,$this->setPrefix('CR')['next_number']);
        self::assertSame('CR-832',$this->makeSource(105,836));
        self::assertSame('CRV-1',app(ProjectVoucherDisplayIdentity::class)
            ->lookup(3,'customer_ledger',101));
        self::assertSame(832,(int)DB::table('customer_ledger')->where('id',101)->value('type_id'));
        self::assertSame(6,DB::table('customer_ledger')->count());
    }

    public function test_default_cr_first_issuance_does_not_skip_source_number(): void
    {
        self::assertSame('CR-832',$this->makeSource(101,832));
        self::assertSame(833,app(ProjectVoucherPrefixCounter::class)
            ->status(3,'CR','CR')['next_number']);
        self::assertSame('CR-832',app(ProjectVoucherDisplayIdentity::class)
            ->lookup(3,'customer_ledger',101));
    }

    public function test_existing_prefix_start_number_cannot_be_rewound(): void
    {
        $this->setPrefix('CRV',50);
        self::assertSame('CRV-50',$this->makeSource(101,832));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->setPrefix('CRV',3);
    }

    public function test_failed_transaction_does_not_consume_display_sequence(): void
    {
        $this->setPrefix('CRV');
        try {
            DB::transaction(function(){
                DB::table('customer_ledger')->insert(['id'=>101,'project_id'=>3,
                    'transaction_type'=>'CR','type_id'=>832]);
                app(ProjectVoucherDisplayIdentity::class)->assign(
                    3,'customer_ledger',101,'CR','2026-11-28');
                throw new \RuntimeException('simulated posting failure');
            });
        } catch (\RuntimeException $exception) {
            self::assertSame('simulated posting failure',$exception->getMessage());
        }
        self::assertSame(1,DB::table('project_voucher_prefix_counters')
            ->where('project_id',3)->where('prefix','CRV')->value('next_number'));
        self::assertSame(0,DB::table('project_voucher_display_numbers')->count());
    }

    public function test_prefix_is_isolated_per_project_and_family(): void
    {
        $this->setPrefix('CRV');
        DB::table('projects')->insert(['id'=>4]);
        $state=app(ProjectVoucherPrefixCounter::class)->status(4,'CR','CRV');
        self::assertSame(1,$state['next_number']);
        self::assertFalse($state['conflict']);
    }
}
