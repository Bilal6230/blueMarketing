<?php

namespace Tests\Unit\Finance;

use App\Models\PlotDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlotDefinitionEffectiveRateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('plot_definitions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('kind'); $table->string('code'); $table->string('name');
            $table->timestamps();
        });
        Schema::create('plot_definition_rate_revisions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plot_definition_id'); $table->unsignedBigInteger('project_id');
            $table->decimal('rate_per_unit', 12, 2); $table->date('effective_from'); $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['plot_definition_id', 'effective_from']);
        });
        DB::table('plot_definitions')->insert(['id' => 7, 'project_id' => 5, 'kind' => 'size', 'code' => '10M', 'name' => '10 Marla']);
        DB::table('plot_definition_rate_revisions')->insert([
            ['plot_definition_id' => 7, 'project_id' => 5, 'rate_per_unit' => 100, 'effective_from' => '2026-01-01'],
            ['plot_definition_id' => 7, 'project_id' => 5, 'rate_per_unit' => 125, 'effective_from' => '2026-04-01'],
        ]);
    }

    public function test_effective_rate_uses_latest_revision_on_or_before_booking_date(): void
    {
        $definition = PlotDefinition::findOrFail(7);

        $this->assertSame('100.00', $definition->effectiveRate('2026-03-31'));
        $this->assertSame('125.00', $definition->effectiveRate('2026-04-01'));
        $this->assertNull($definition->effectiveRate('2025-12-31'));
    }

    public function test_rate_history_rejects_duplicate_effective_date_for_same_definition(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('plot_definition_rate_revisions')->insert([
            'plot_definition_id' => 7, 'project_id' => 5, 'rate_per_unit' => 150,
            'effective_from' => '2026-04-01',
        ]);
    }
}
