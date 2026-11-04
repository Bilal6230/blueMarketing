<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_group_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_group_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('head_accounting_id')->nullable();
            $table->string('event', 32);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign(['account_group_id', 'project_id'], 'account_group_audit_scope_fk')
                ->references(['id', 'project_id'])->on('account_groups')->restrictOnDelete();
            $table->foreign('head_accounting_id')->references('id')->on('head_accountings')->restrictOnDelete();
            $table->index(['project_id', 'account_group_id', 'changed_at'], 'account_group_audit_group_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_group_audits');
    }
};
