<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customer_ledger', 'deleted_at')) {
            Schema::table('customer_ledger', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('booking_vouchers', 'deleted_at')) {
            Schema::table('booking_vouchers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('ledgers', 'deleted_at')) {
            Schema::table('ledgers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_ledger', 'deleted_at')) {
            Schema::table('customer_ledger', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('booking_vouchers', 'deleted_at')) {
            Schema::table('booking_vouchers', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('ledgers', 'deleted_at')) {
            Schema::table('ledgers', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
