<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasIndex('project_head_subheads', 'project_head_subheads_id_project_unique')) {
            Schema::table('project_head_subheads', function (Blueprint $table): void {
                $table->unique(['id', 'project_id'], 'project_head_subheads_id_project_unique');
            });
        }

        if (!Schema::hasTable('contractors')) {
            Schema::create('contractors', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->string('code', 40);
                $table->string('name', 160);
                $table->string('phone', 50)->nullable();
                $table->text('address')->nullable();
                $table->unsignedBigInteger('project_head_subhead_id');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'code'], 'contractors_project_code_unique');
                $table->unique(['id', 'project_id'], 'contractors_id_project_unique');
                $table->foreign('project_id', 'contractors_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['project_head_subhead_id', 'project_id'], 'contractors_account_project_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('contractor_audits')) {
            Schema::create('contractor_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('contractor_id');
                $table->string('action', 30);
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['contractor_id', 'project_id'], 'contractor_audits_contractor_project_fk')
                    ->references(['id', 'project_id'])->on('contractors')->restrictOnDelete();
                $table->index(['project_id', 'contractor_id', 'created_at'], 'contractor_audits_lookup_idx');
            });
        }

        if (Schema::hasTable('journal_vouchers') && !Schema::hasColumn('journal_vouchers', 'contractor_id')) {
            Schema::table('journal_vouchers', function (Blueprint $table): void {
                $table->unsignedBigInteger('contractor_id')->nullable();
                $table->index(['contractor_id', 'project_id'], 'journal_vouchers_contractor_project_idx');
            });
            Schema::table('journal_vouchers', function (Blueprint $table): void {
                $table->foreign(['contractor_id', 'project_id'], 'journal_vouchers_contractor_project_fk')
                    ->references(['id', 'project_id'])->on('contractors')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Contractor and voucher history is financial history; preserve it on rollback.
    }
};
