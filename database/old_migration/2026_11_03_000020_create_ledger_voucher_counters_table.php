<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            throw new RuntimeException('Voucher counters require the projects table.');
        }
        if (!Schema::hasTable('ledger_voucher_counters')) {
            Schema::create('ledger_voucher_counters', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id');
                $table->string('voucher_type', 16);
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->primary(['project_id', 'voucher_type'], 'ledger_voucher_counter_pk');
                $table->foreign('project_id', 'ledger_voucher_counter_project_fk')->references('id')->on('projects')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Keep sequence state once created; rolling back must not allow reuse.
    }
};
