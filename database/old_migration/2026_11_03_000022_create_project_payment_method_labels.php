<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) throw new RuntimeException('Payment method settings require projects.');
        if (!Schema::hasTable('project_payment_method_labels')) {
            Schema::create('project_payment_method_labels', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedTinyInteger('method_code');
                $table->string('label', 40);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'project_payment_method_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->unique(['project_id', 'method_code'], 'project_payment_method_code_uq');
            });
        }
        if (!Schema::hasTable('project_payment_method_label_audits')) {
            Schema::create('project_payment_method_label_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedTinyInteger('method_code');
                $table->string('old_label', 40)->nullable();
                $table->string('new_label', 40);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('project_id', 'project_payment_method_audit_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->index(['project_id', 'method_code', 'created_at'], 'project_payment_method_audit_idx');
            });
        }
    }

    public function down(): void
    {
        // Keep payment labels and their audit history on rollback.
    }
};
