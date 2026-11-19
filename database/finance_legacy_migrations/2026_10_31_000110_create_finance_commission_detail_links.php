<?php

use App\Services\Finance\FinanceConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add exact CV detail identity without altering historical source tables.
 * Run only when the existing finance source schema is present and the target tables are absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        FinanceConnectionGuard::assertAllowed();
        foreach (['commision_voucher_details', 'finance_posting_lines',
            'finance_posting_commission_sources'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('Commission FK baseline missing: ' . $table);
            }
        }
        if (Schema::hasTable('finance_commission_line_sources')) {
            throw new RuntimeException('Commission line FK already exists');
        }
        Schema::create('finance_commission_line_sources', function (Blueprint $table): void {
            $table->foreignId('posting_line_id')->primary()
                ->constrained('finance_posting_lines')->restrictOnDelete();
            // MySQL identifiers have a 64-character limit. The default
            // Laravel FK/unique names for this long source identifier exceed
            // that limit; preserve the real FK with short explicit names.
            $table->foreignId('commision_voucher_detail_id')
                ->unique('v2_cv_detail_line_unique');
            $table->foreign('commision_voucher_detail_id', 'v2_cv_detail_source_fk')
                ->references('id')->on('commision_voucher_details')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        FinanceConnectionGuard::assertAllowed();
        Schema::dropIfExists('finance_commission_line_sources');
    }
};
