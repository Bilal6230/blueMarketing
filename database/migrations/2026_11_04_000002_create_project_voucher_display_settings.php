<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects')) throw new RuntimeException('Voucher settings require projects.');
        if (!Schema::hasTable('project_voucher_display_settings')) {
            Schema::create('project_voucher_display_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('series', 4);
                $table->string('prefix', 12);
                $table->unsignedTinyInteger('financial_year_start_month')->default(1);
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'series'], 'project_voucher_display_scope_uq');
            });
        }
        if (!Schema::hasTable('project_voucher_display_setting_audits')) {
            Schema::create('project_voucher_display_setting_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('series', 4);
                $table->string('old_prefix', 12)->nullable();
                $table->string('new_prefix', 12);
                $table->foreignId('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Financial display settings and audit history are retained.
    }
};
