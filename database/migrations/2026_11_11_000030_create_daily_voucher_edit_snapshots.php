<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('daily_voucher_edit_snapshots')) return;
        Schema::create('daily_voucher_edit_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->string('status', 16);
            $table->json('before_snapshot');
            $table->json('proposed_snapshot');
            $table->text('reason');
            $table->unsignedBigInteger('submitted_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamps();
            $table->index(['project_id','source_id','status'], 'voucher_edit_project_source_status');
            $table->index(['approval_id','status'], 'voucher_edit_approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_voucher_edit_snapshots');
    }
};
