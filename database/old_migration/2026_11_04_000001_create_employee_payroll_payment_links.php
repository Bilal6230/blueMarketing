<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('employee_payroll_runs') || !Schema::hasTable('employee_payroll_run_items')
            || !Schema::hasTable('customer_ledger')) {
            throw new RuntimeException('Payroll payment links require payroll snapshots and customer_ledger.');
        }
        if (!Schema::hasTable('employee_payroll_run_audits')) {
            throw new RuntimeException('Payroll payment links require payroll audit history.');
        }
        if (!Schema::hasTable('employee_payroll_payment_links')) {
            Schema::create('employee_payroll_payment_links', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('payroll_run_id');
                $table->unsignedBigInteger('payroll_run_item_id');
                $table->unsignedBigInteger('customer_ledger_id');
                $table->decimal('amount', 14, 2);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['payroll_run_id', 'project_id'], 'payroll_payment_run_project_fk')
                    ->references(['id', 'project_id'])->on('employee_payroll_runs')->restrictOnDelete();
                $table->foreign('payroll_run_item_id', 'payroll_payment_item_fk')
                    ->references('id')->on('employee_payroll_run_items')->restrictOnDelete();
                $table->foreign('customer_ledger_id', 'payroll_payment_source_fk')
                    ->references('id')->on('customer_ledger')->restrictOnDelete();
                $table->unique(['payroll_run_item_id', 'customer_ledger_id'], 'payroll_payment_item_source_uq');
                $table->index(['project_id', 'customer_ledger_id'], 'payroll_payment_source_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        // Payment evidence is retained with the immutable payroll snapshot.
    }
};
