<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_bank_voucher_allocations')) {
            throw new \RuntimeException('Apply bank voucher allocation migration 000043 first.');
        }
        if (!Schema::hasTable('finance_bank_voucher_allocation_releases')) {
            Schema::create('finance_bank_voucher_allocation_releases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('allocation_id');
                $table->string('source_method',12);
                $table->unsignedBigInteger('source_id');
                $table->string('voucher_type',16);
                $table->unsignedBigInteger('voucher_id');
                $table->decimal('amount',18,2);
                $table->string('action',24);
                $table->string('reason',500);
                $table->unsignedBigInteger('released_by');
                $table->timestamp('released_at');
                $table->timestamps();
                $table->unique('allocation_id','fin_bank_release_once');
                $table->index(['project_id','source_method','source_id'],'fin_bank_release_source');
                $table->index(['project_id','voucher_type','voucher_id'],'fin_bank_release_voucher');
            });
        }
    }

    public function down(): void
    {
        // Financial release evidence is append-only; never automatically drop.
    }
};
