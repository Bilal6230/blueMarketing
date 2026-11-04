<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('create_by')->nullable();
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign(['parent_id', 'project_id'], 'account_groups_parent_project_fk')
                ->references(['id', 'project_id'])->on('account_groups')->restrictOnDelete();
            $table->unique(['project_id', 'code'], 'account_groups_project_code_uq');
            $table->unique(['id', 'project_id'], 'account_groups_id_project_uq');
            $table->index(['project_id', 'parent_id', 'is_active'], 'account_groups_project_parent_active_idx');
        });

        Schema::create('project_head_account_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('head_accounting_id');
            $table->unsignedBigInteger('account_group_id');
            $table->unsignedBigInteger('create_by')->nullable();
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('head_accounting_id')->references('id')->on('head_accountings')->restrictOnDelete();
            $table->foreign(['account_group_id', 'project_id'], 'project_head_group_scope_fk')
                ->references(['id', 'project_id'])->on('account_groups')->restrictOnDelete();
            $table->unique(['project_id', 'head_accounting_id'], 'project_head_group_head_uq');
            $table->index(['project_id', 'account_group_id'], 'project_head_group_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_head_account_groups');
        Schema::dropIfExists('account_groups');
    }
};
