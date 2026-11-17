<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_online_lifecycle_events')) {
            Schema::create('finance_online_lifecycle_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('customer_ledger_id');
                $table->string('event_type', 32);
                $table->unsignedTinyInteger('from_status')->nullable();
                $table->unsignedTinyInteger('to_status')->nullable();
                $table->string('transaction_number', 100)->nullable();
                $table->unsignedBigInteger('bank_id')->nullable();
                $table->date('passing_date')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['project_id', 'customer_ledger_id', 'id'], 'online_events_project_source_idx');
                $table->index(['project_id', 'event_type', 'created_at'], 'online_events_project_type_idx');
            });
        }
    }

    public function down(): void
    {
        // Preserve payment lifecycle evidence on rollback.
    }
};
