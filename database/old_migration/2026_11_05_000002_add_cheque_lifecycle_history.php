<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customer_ledger') && !Schema::hasColumn('customer_ledger', 'cheque_purpose')) {
            Schema::table('customer_ledger', function (Blueprint $table): void {
                $table->string('cheque_purpose', 500)->nullable();
            });
        }

        if (!Schema::hasTable('finance_cheque_lifecycle_events')) {
            Schema::create('finance_cheque_lifecycle_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('customer_ledger_id');
                $table->string('event_type', 32);
                $table->unsignedTinyInteger('from_status')->nullable();
                $table->unsignedTinyInteger('to_status')->nullable();
                $table->string('purpose', 500)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['project_id', 'customer_ledger_id', 'id'], 'cheque_events_project_source_idx');
                $table->index(['project_id', 'event_type', 'created_at'], 'cheque_events_project_type_idx');
            });
        }
    }

    public function down(): void
    {
        // Lifecycle events and purposes are financial audit history; preserve them on rollback.
    }
};
