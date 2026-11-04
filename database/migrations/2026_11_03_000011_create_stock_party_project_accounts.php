<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_parties') || !Schema::hasTable('project_head_subheads')) {
            throw new LogicException('Stock party account links require stock_parties and project_head_subheads.');
        }

        if (!Schema::hasIndex('project_head_subheads', 'project_head_subheads_id_project_unique')) {
            Schema::table('project_head_subheads', function (Blueprint $table): void {
                $table->unique(['id', 'project_id'], 'project_head_subheads_id_project_unique');
            });
        }

        if (!Schema::hasTable('stock_party_project_accounts')) {
            Schema::create('stock_party_project_accounts', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('stock_party_id');
                $table->unsignedBigInteger('project_head_subhead_id');
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'stock_party_id'], 'stock_party_project_account_unique');
                $table->foreign('project_id', 'stock_party_project_account_project_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
                $table->foreign('stock_party_id', 'stock_party_project_account_party_fk')
                    ->references('id')->on('stock_parties')->restrictOnDelete();
                $table->foreign(['project_head_subhead_id', 'project_id'], 'stock_party_project_account_account_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('stock_party_project_account_audits')) {
            Schema::create('stock_party_project_account_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('stock_party_id');
                $table->unsignedBigInteger('previous_account_id')->nullable();
                $table->unsignedBigInteger('new_account_id');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('project_id', 'stock_party_account_audit_project_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
                $table->foreign('stock_party_id', 'stock_party_account_audit_party_fk')
                    ->references('id')->on('stock_parties')->restrictOnDelete();
                $table->foreign(['previous_account_id', 'project_id'], 'stock_party_account_audit_old_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
                $table->foreign(['new_account_id', 'project_id'], 'stock_party_account_audit_new_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
                $table->index(['project_id', 'stock_party_id', 'created_at'], 'stock_party_account_audit_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        // Preserve project-specific accounting assignments through rollback.
    }
};
