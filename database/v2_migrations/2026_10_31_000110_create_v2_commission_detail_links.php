<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add exact CV detail identity without altering historical source tables.
 * Must be applied to a NEW empty isolated V2 target or a vetted V2 test DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['commision_voucher_details', 'finance_posting_lines',
            'finance_posting_commission_sources'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('V2 commission FK baseline missing: ' . $table);
            }
        }
        if (Schema::hasTable('finance_commission_line_sources')) {
            throw new RuntimeException('V2 commission line FK already exists');
        }
        Schema::create('finance_commission_line_sources', function (Blueprint $table): void {
            $table->foreignId('posting_line_id')->primary()
                ->constrained('finance_posting_lines')->restrictOnDelete();
            $table->foreignId('commision_voucher_detail_id')->unique()
                ->constrained('commision_voucher_details')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        Schema::dropIfExists('finance_commission_line_sources');
    }
};
