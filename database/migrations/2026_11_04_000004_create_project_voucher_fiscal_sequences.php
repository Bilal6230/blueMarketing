<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects')) throw new RuntimeException('Fiscal sequences require projects.');
        if (!Schema::hasTable('project_voucher_fiscal_sequences')) {
            Schema::create('project_voucher_fiscal_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('series', 4);
                $table->unsignedSmallInteger('financial_year');
                $table->unsignedBigInteger('last_number');
                $table->timestamps();
                $table->unique(['project_id', 'series', 'financial_year'], 'project_voucher_fiscal_scope_uq');
            });
        }
    }

    public function down(): void
    {
        // Retain allocated numbers; reusing them would be unsafe.
    }
};
