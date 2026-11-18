<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_plot_voucher_edit_audits')) return;
        Schema::create('finance_plot_voucher_edit_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind',24);
            $table->unsignedBigInteger('source_id');
            $table->text('previous_narration');
            $table->text('updated_narration');
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->timestamps();
            $table->index(['project_id','source_kind','source_id'], 'plot_edit_audit_source_idx');
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('edited_by')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        // Financial edit audit evidence must not be erased by rollback.
    }
};
