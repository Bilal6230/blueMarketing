<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('daily_voucher_approval_policies')) {
            Schema::create('daily_voucher_approval_policies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('voucher_type', 12);
                $table->boolean('approval_required')->default(false);
                $table->unsignedBigInteger('approver_user_id')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'voucher_type'], 'daily_voucher_policy_project_type_unique');
            });
        }
        if (!Schema::hasIndex('daily_voucher_approval_policies', 'daily_voucher_policy_proj_approval_idx')) {
            Schema::table('daily_voucher_approval_policies', function (Blueprint $table): void {
                $table->index(['project_id', 'approval_required'], 'daily_voucher_policy_proj_approval_idx');
            });
        }

        if (!Schema::hasTable('daily_voucher_approvals')) {
            Schema::create('daily_voucher_approvals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('voucher_type', 12);
                $table->string('source_table', 64);
                $table->unsignedBigInteger('source_id');
                $table->string('status', 16)->default('pending');
                $table->unsignedBigInteger('assigned_approver_id');
                $table->unsignedBigInteger('submitted_by');
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->unique(['source_table', 'source_id'], 'daily_voucher_approval_source_unique');
                $table->index(['project_id', 'assigned_approver_id', 'status'], 'daily_voucher_approval_queue_index');
                $table->index(['project_id', 'voucher_type', 'status'], 'daily_voucher_approval_type_index');
            });
        }

        if (!Schema::hasTable('daily_voucher_approval_events')) {
            Schema::create('daily_voucher_approval_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('approval_id');
                $table->string('event', 24);
                $table->unsignedBigInteger('actor_id');
                $table->text('remarks')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['approval_id', 'id'], 'daily_voucher_approval_events_history_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_voucher_approval_events');
        Schema::dropIfExists('daily_voucher_approvals');
        Schema::dropIfExists('daily_voucher_approval_policies');
    }
};
