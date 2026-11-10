<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_online_intakes')) {
            throw new \RuntimeException('Run the online intake migration before confirmation history.');
        }
        if (!Schema::hasTable('finance_online_confirmation_events')) {
            Schema::create('finance_online_confirmation_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('online_intake_id');
                $table->string('from_status', 20);
                $table->string('to_status', 20);
                $table->date('verification_date');
                $table->string('statement_reference', 150);
                $table->text('remarks')->nullable();
                $table->decimal('original_amount', 15, 2);
                $table->string('transaction_type', 10);
                $table->unsignedBigInteger('actor_id');
                $table->timestamp('confirmed_at');
                $table->timestamps();
                $table->foreign('online_intake_id','fin_online_confirmation_intake_fk')
                    ->references('id')->on('finance_online_intakes')->restrictOnDelete();
                $table->unique(['project_id','online_intake_id'],'fin_online_confirmation_once');
                $table->index(['project_id','confirmed_at'],'fin_online_confirmation_time');
            });
        }
    }

    public function down(): void
    {
        // Retain finance confirmation evidence; never silently drop the audit trail.
    }
};
