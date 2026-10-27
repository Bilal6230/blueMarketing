<?php
namespace Tests\Feature\Finance;

use App\Services\Finance\LegacyEvidenceCoverageInspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyEvidenceCoverageIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('SQLite :memory: required.');
        }
        Schema::dropAllTables();
        Schema::create('projects',function(Blueprint $t){$t->id();});
        Schema::create('project_head_subheads',function(Blueprint $t){$t->id();$t->unsignedBigInteger('project_id');});
        Schema::create('ledgers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_head_subheads_id');$t->string('type');
        });
        DB::table('projects')->insert([['id'=>6],['id'=>7]]);
        DB::table('project_head_subheads')->insert([['id'=>10,'project_id'=>6],['id'=>11,'project_id'=>7]]);
        DB::table('ledgers')->insert([
            ['id'=>1,'project_head_subheads_id'=>10,'type'=>'JV'],
            ['id'=>2,'project_head_subheads_id'=>10,'type'=>'JV'],
            ['id'=>3,'project_head_subheads_id'=>10,'type'=>'JV'],
            ['id'=>4,'project_head_subheads_id'=>11,'type'=>'JV'],
            ['id'=>5,'project_head_subheads_id'=>10,'type'=>'CV'],
        ]);
    }
    public function test_partial_coverage_reports_exact_missing_count_and_bounded_sample(): void
    {
        $r=app(LegacyEvidenceCoverageInspector::class)->inspect(6,[1],1);
        $this->assertSame(3,$r['total_unverified']);
        $this->assertSame(2,$r['remaining_unreviewed']);
        $this->assertSame([2],$r['missing_sample_ledger_ids']);
        $this->assertTrue($r['sample_incomplete']);
        $this->assertFalse($r['source_verified']);
    }
    public function test_cross_project_and_non_jv_review_ids_cannot_count_as_coverage(): void
    {
        $r=app(LegacyEvidenceCoverageInspector::class)->inspect(6,[1,4,5]);
        $this->assertSame(2,$r['outside_scope_ids']);
        $this->assertSame(2,$r['remaining_unreviewed']);
        $this->assertContains('review_ids_outside_current_unverified_scope',$r['issues']);
    }
    public function test_full_coverage_still_blocks_if_attribution_schema_is_absent(): void
    {
        $r=app(LegacyEvidenceCoverageInspector::class)->inspect(6,[1,2,3]);
        $this->assertSame('blocked',$r['status']);
        $this->assertContains('attribution_schema_missing',$r['issues']);
    }
    public function test_full_coverage_with_schema_is_not_provenance_certification(): void
    {
        Schema::create('ledger_source_attributions',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('ledger_id');
        });
        $r=app(LegacyEvidenceCoverageInspector::class)->inspect(6,[1,2,3]);
        $this->assertSame('coverage_only_complete',$r['status']);
        $this->assertFalse($r['source_verified']);
        $this->assertSame(0,$r['remaining_unreviewed']);
    }
    public function test_already_attributed_rows_do_not_reappear_as_missing_review(): void
    {
        Schema::create('ledger_source_attributions',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('ledger_id');
        });
        DB::table('ledger_source_attributions')->insert(['ledger_id'=>3]);
        $r=app(LegacyEvidenceCoverageInspector::class)->inspect(6,[1,2]);
        $this->assertSame(2,$r['total_unverified']);
        $this->assertSame(0,$r['remaining_unreviewed']);
        $this->assertSame('coverage_only_complete',$r['status']);
    }
    public function test_duplicate_review_ids_fail_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(LegacyEvidenceCoverageInspector::class)->inspect(6,[1,1]);
    }
}
