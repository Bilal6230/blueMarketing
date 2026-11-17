<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add read-path indexes informed by real EXPLAIN plans on the restored local DB.
 *
 * - JV listing filters project+type, then orders by id DESC.
 * - Ledger source lookup filters type+type_id+is_active and orders by id DESC.
 * - Customer voucher listing filters project+transaction_type, then orders by id DESC.
 *
 * Keep existing foreign-key and unique indexes intact. Rollback drops only
 * these three newly-added indexes; it does not touch application data.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_vouchers', function (Blueprint $table): void {
            $table->index(['project_id', 'type', 'id'], 'idx_jv_project_type_recent');
        });

        Schema::table('ledgers', function (Blueprint $table): void {
            $table->index(['type', 'type_id', 'is_active', 'id'], 'idx_ledgers_source_active_recent');
        });

        Schema::table('customer_ledger', function (Blueprint $table): void {
            $table->index(['project_id', 'transaction_type', 'id'], 'idx_customer_project_type_recent');
        });
    }

    public function down(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table): void {
            $table->dropIndex('idx_customer_project_type_recent');
        });

        Schema::table('ledgers', function (Blueprint $table): void {
            $table->dropIndex('idx_ledgers_source_active_recent');
        });

        Schema::table('journal_vouchers', function (Blueprint $table): void {
            $table->dropIndex('idx_jv_project_type_recent');
        });
    }
};
