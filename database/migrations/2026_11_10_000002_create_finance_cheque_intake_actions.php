<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_cheque_intakes')) {
            throw new \RuntimeException('Run cheque intake migration before cheque action history.');
        }
        if (!Schema::hasTable('finance_cheque_intake_actions')) {
            Schema::create('finance_cheque_intake_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('cheque_intake_id');
                $table->string('action', 32);
                $table->string('from_status', 32);
                $table->string('to_status', 32);
                $table->date('action_date');
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->string('bank_name', 150);
                $table->string('branch', 150)->nullable();
                $table->string('collector_name', 150)->nullable();
                $table->string('collector_cnic', 15)->nullable();
                $table->text('remarks')->nullable();
                $table->decimal('original_amount', 15, 2);
                $table->unsignedBigInteger('actor_id');
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['cheque_intake_id'], 'fin_chq_action_intake_fk')
                    ->references('id')->on('finance_cheque_intakes')->restrictOnDelete();
                $table->index(['project_id', 'cheque_intake_id', 'id'], 'fin_chq_action_history_idx');
                $table->index(['project_id', 'action', 'created_at'], 'fin_chq_action_type_idx');
            });
        }
    }

    public function down(): void
    {
        // Preserve financial receiving and banking evidence.
    }
};
