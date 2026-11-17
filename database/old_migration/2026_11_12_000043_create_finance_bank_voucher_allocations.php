<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_online_voucher_claims')
            || !Schema::hasTable('finance_cheque_voucher_claims')) {
            throw new \RuntimeException('Existing online/cheque claim migrations must be applied first.');
        }
        if (!Schema::hasTable('finance_bank_voucher_allocations')) {
            Schema::create('finance_bank_voucher_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('source_method', 12);
                $table->unsignedBigInteger('source_id');
                $table->string('voucher_type', 16);
                $table->unsignedBigInteger('voucher_id');
                $table->decimal('amount', 18, 2);
                $table->unsignedBigInteger('allocated_by');
                $table->timestamp('allocated_at');
                $table->timestamps();
                $table->index(['project_id', 'source_method', 'source_id'], 'fin_bank_alloc_source');
                $table->unique(['project_id', 'voucher_type', 'voucher_id'], 'fin_bank_alloc_unique_voucher');
            });
        }
    }

    public function down(): void
    {
        // Immutable financial allocation evidence is intentionally never dropped.
    }
};
