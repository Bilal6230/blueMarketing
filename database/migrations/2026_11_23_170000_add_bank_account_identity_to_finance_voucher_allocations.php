<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the exact project bank account used by newly balanced BOR/BOP.
 * NULL preserves pre-upgrade single-leg bank vouchers and all other series.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_bank_voucher_allocations')) {
            throw new RuntimeException('Finance bank allocation baseline must exist.');
        }
        if (!Schema::hasColumn('finance_bank_voucher_allocations', 'bank_account_id')) {
            Schema::table('finance_bank_voucher_allocations', function (Blueprint $table): void {
                $table->unsignedBigInteger('bank_account_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Preserve historical immutable bank-voucher source identity.
    }
};
