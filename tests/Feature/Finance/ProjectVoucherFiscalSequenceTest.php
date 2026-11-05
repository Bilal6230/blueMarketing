<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\ProjectVoucherFiscalSequence;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectVoucherFiscalSequenceTest extends TestCase
{
    public function test_sequence_is_scoped_by_project_series_and_calendar_year(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $table) => $table->id());
        Schema::create('project_voucher_fiscal_sequences', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('series');
            $table->unsignedSmallInteger('financial_year'); $table->unsignedBigInteger('last_number');
            $table->timestamps(); $table->unique(['project_id', 'series', 'financial_year']);
        });
        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        $service = new ProjectVoucherFiscalSequence();
        $result = DB::transaction(fn () => [
            $service->next(1, 'CR', '2026-12-31'), $service->next(1, 'CR', '2026-12-31'),
            $service->next(1, 'CR', '2027-01-01'), $service->next(1, 'CP', '2026-12-31'),
            $service->next(2, 'CR', '2026-12-31'),
        ]);
        $this->assertSame([1, 2, 1, 1, 1], $result);
        $this->assertDatabaseHas('project_voucher_fiscal_sequences', ['project_id' => 1, 'series' => 'CR', 'financial_year' => 2026, 'last_number' => 2]);
        $this->expectException(\DomainException::class);
        $service->next(1, 'CR', '2027-01-02');
    }
}
