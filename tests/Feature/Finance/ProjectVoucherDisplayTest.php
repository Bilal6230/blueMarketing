<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\ProjectVoucherDisplay;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectVoucherDisplayTest extends TestCase
{
    public function test_project_prefix_only_changes_display_and_does_not_touch_legacy_numbers(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('project_voucher_display_settings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('series'); $table->string('prefix');
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->unsignedBigInteger('voucher_number');
        });
        DB::table('ledgers')->insert(['id' => 7, 'type' => 'CR', 'voucher_number' => 42]);
        DB::table('project_voucher_display_settings')->insert(['project_id' => 1, 'series' => 'CR', 'prefix' => 'REC']);

        $display = new ProjectVoucherDisplay();
        $this->assertSame('REC-2026-0042', $display->format(1, 'CR', 42, '2026-11-04'));
        $this->assertSame('CR-2026-0042', $display->format(2, 'CR', 42, '2026-11-04'));
        $this->assertSame('CP-2026-0042', $display->format(1, 'CP', 42, '2026-11-04'));
        $this->assertDatabaseHas('ledgers', ['id' => 7, 'type' => 'CR', 'voucher_number' => 42]);
        $this->expectException(\DomainException::class);
        $display->format(1, 'CR', 42, '2026-02-30');
    }
}
