<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects')) throw new RuntimeException('Voucher display numbers require projects.');
        if (!Schema::hasTable('project_voucher_display_numbers')) {
            Schema::create('project_voucher_display_numbers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('source_kind', 24);
                $table->unsignedBigInteger('source_id');
                $table->string('series', 4);
                $table->unsignedSmallInteger('financial_year');
                $table->string('display_number', 40);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['project_id', 'source_kind', 'source_id'], 'voucher_display_source_uq');
                $table->unique(['project_id', 'display_number'], 'voucher_display_project_number_uq');
                $table->index(['project_id', 'series', 'financial_year'], 'voucher_display_project_series_year_idx');
            });
        }
    }

    public function down(): void
    {
        // Display identities are retained to prevent historical renumbering.
    }
};
