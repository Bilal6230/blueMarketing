<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_cheque_intakes')) {
            return;
        }
        Schema::create('finance_cheque_intakes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('cheque_number', 100);
            $table->date('cheque_date');
            $table->date('received_date');
            $table->decimal('amount', 15, 2);
            $table->string('bank_name', 150);
            $table->string('branch', 150)->nullable();
            $table->string('received_from', 150);
            $table->string('purpose', 500);
            $table->text('remarks')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 32)->default('received');
            $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('leads')->restrictOnDelete();
            $table->unique(['project_id', 'bank_name', 'cheque_number'], 'finance_cheque_intake_unique');
            $table->index(['project_id', 'status', 'received_date'], 'finance_cheque_intake_scope');
        });
    }

    public function down(): void
    {
        // Do not destroy financial receiving evidence on rollback.
    }
};
