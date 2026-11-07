<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects') || !Schema::hasTable('project_head_subheads')) {
            throw new RuntimeException('Project bank accounts require projects and project account mappings.');
        }

        if (!Schema::hasIndex('project_head_subheads', 'project_head_subheads_id_project_unique')) {
            Schema::table('project_head_subheads', function (Blueprint $table): void {
                $table->unique(['id', 'project_id'], 'project_head_subheads_id_project_unique');
            });
        }

        if (!Schema::hasTable('project_bank_accounts')) {
            Schema::create('project_bank_accounts', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('account_mapping_id');
                $table->string('bank_name', 120);
                $table->string('account_title', 120);
                $table->string('branch', 120)->nullable();
                $table->text('account_number')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'project_bank_account_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['account_mapping_id', 'project_id'], 'project_bank_account_mapping_project_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
                $table->unique(['id', 'project_id'], 'project_bank_account_id_project_uq');
                $table->unique(['project_id', 'account_mapping_id'], 'project_bank_account_mapping_uq');
                $table->index(['project_id', 'is_active', 'bank_name'], 'project_bank_account_active_idx');
            });
        }

        // MySQL DDL may commit the table before a later FK fails. Complete a
        // partially-created isolated/deployed schema safely on a retry.
        $indexes = [
            'project_bank_account_id_project_uq' => [['id', 'project_id'], 'unique'],
            'project_bank_account_mapping_uq' => [['project_id', 'account_mapping_id'], 'unique'],
            'project_bank_account_active_idx' => [['project_id', 'is_active', 'bank_name'], 'index'],
        ];
        foreach ($indexes as $name => [$columns, $type]) {
            if (!Schema::hasIndex('project_bank_accounts', $name)) {
                Schema::table('project_bank_accounts', function (Blueprint $table) use ($name, $columns, $type): void {
                    $type === 'unique' ? $table->unique($columns, $name) : $table->index($columns, $name);
                });
            }
        }

        $foreignKeys = collect(Schema::getForeignKeys('project_bank_accounts'))->pluck('name')->all();
        if (!in_array('project_bank_account_project_fk', $foreignKeys, true)) {
            Schema::table('project_bank_accounts', function (Blueprint $table): void {
                $table->foreign('project_id', 'project_bank_account_project_fk')->references('id')->on('projects')->restrictOnDelete();
            });
        }
        if (!in_array('project_bank_account_mapping_project_fk', $foreignKeys, true)) {
            Schema::table('project_bank_accounts', function (Blueprint $table): void {
                $table->foreign(['account_mapping_id', 'project_id'], 'project_bank_account_mapping_project_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('project_bank_account_audits')) {
            Schema::create('project_bank_account_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('bank_account_id');
                $table->string('action', 24);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['bank_account_id', 'project_id'], 'project_bank_account_audit_scope_fk')
                    ->references(['id', 'project_id'])->on('project_bank_accounts')->restrictOnDelete();
                $table->index(['project_id', 'bank_account_id', 'created_at'], 'project_bank_account_audit_idx');
            });
        }
    }

    public function down(): void
    {
        // Retain bank configuration and audit history on rollback.
    }
};
