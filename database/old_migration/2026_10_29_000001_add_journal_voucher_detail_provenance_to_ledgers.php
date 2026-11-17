<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New JV provenance only. Existing historical ledger rows stay NULL.
 * Do not backfill by polymorphic type_id.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('ledgers', 'journal_voucher_detail_id')) {
            Schema::table('ledgers', function (Blueprint $table) {
                $table->unsignedBigInteger('journal_voucher_detail_id')->nullable();
                // Multiple legacy NULLs remain valid; only new identified
                // JV detail postings must be unique.
                $table->unique('journal_voucher_detail_id', 'ledgers_unique_jv_detail');
                $table->foreign('journal_voucher_detail_id', 'ledgers_jv_detail_fk')
                    ->references('id')->on('journal_voucher_details')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ledgers', 'journal_voucher_detail_id')) {
            Schema::table('ledgers', function (Blueprint $table) {
                $table->dropForeign('ledgers_jv_detail_fk');
                $table->dropUnique('ledgers_unique_jv_detail');
                $table->dropColumn('journal_voucher_detail_id');
            });
        }
    }
};
